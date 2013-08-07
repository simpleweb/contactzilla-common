<?php
/**
 * This is the crux of the contact data. This represents a model that follows Portable Contacts standard.
 * Obviously, given the nature of Mongo, this could be messed up a bit, but this class takes care of
 * marshalling data in to the closest POCO representation as possible.
 *
 * Data is run through a serious of lightweight validators to perform sensible actions against different
 * POCO fields.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Poco extends Model_Mongo_Base
{

    protected static $_requirements = array(
        'name' => array('Document'),
        'isOrg' => array('Filter:StringTrim'),
        'note' => array('Filter:StringTrim'),
        'birthday' => array('Filter:StringTrim'),
        'emails' => array('DocumentSet:Model_Mongo_PocoPluralDocumentSet'),
        'emails.$' => array('Document:Model_Mongo_PocoPluralDocument'),
        'phoneNumbers' => array('DocumentSet:Model_Mongo_PocoPluralDocumentSet'),
        'phoneNumbers.$' => array('Document:Model_Mongo_PocoPluralDocument'),
        'addresses' => array('DocumentSet:Model_Mongo_PocoAddressesDocumentSet'),
        'addresses.$' => array('Document:Model_Mongo_PocoAddress'),
        'photos' => array('DocumentSet:Model_Mongo_PocoPhotosDocumentSet'),
        'photos.$' => array('Document:Model_Mongo_PocoPluralDocument'),
        'dates' => array('DocumentSet:Model_Mongo_PocoPluralDocumentSet'),
        'dates.$' => array('Document:Model_Mongo_PocoPluralDocument'),
        'organizations' => array('DocumentSet:Model_Mongo_PocoPluralDocumentSet'),
        'organizations.$' => array('Document:Model_Mongo_PocoPluralDocument'),
        'schools' => array('DocumentSet:Model_Mongo_PocoPluralDocumentSet'),
        'schools.$' => array('Document:Model_Mongo_PocoPluralDocument'),
        'accounts' => array('DocumentSet:Model_Mongo_PocoAccountsDocumentSet'),
        'accounts.$' => array('Document:Model_Mongo_PocoAccount'),
        'urls' => array('DocumentSet:Model_Mongo_PocoPluralDocumentSet'),
        'urls.$' => array('Document:Model_Mongo_PocoPluralDocument'),
        'ims' => array('DocumentSet:Model_Mongo_PocoPluralDocumentSet'),
        'ims.$' => array('Document:Model_Mongo_PocoIm')
    );

    //Comparator functions return a single value for a given key that can be used for comparison purposes.
    //Each function takes an array that matches a poco element and should return a string of all values that are
    //important for comparison, ignoring sorting.
    protected static $_comparators = array(
        'name' => 'keyedArrayComparator',
        'tags' => 'sortedImplode'
    );

    public function __construct($data = array(), $config = array('new' => true)) {
        //POCOify everthing, make sure the data follows the rules.
        if ($config['new']) {
            $cleaner = new Contactzilla_Poco_Cleaner();
            $data = $cleaner->getCleanPoco($data);
        }

        parent::__construct($data, $config);
    }

    /**
     * Display name isn't saved in to db, it can change depending on user preference. Eg. Tom Holder or Holder Tom
     * Fixed format will force to givenName familyName format. This is for sorting/deduping purposes.
     * @param bool $fixedFormat
     */
    public function dynamicDisplayName($template = "
        {{#isOrg}}
            {{&orgName}}
        {{/isOrg}}
        {{^isOrg}}
            {{&name.givenName}} {{&name.familyName}} 
        {{/isOrg}}") {

        $m = new Mustache_Engine();
        $name = trim($m->render($template, $this));

        if (empty($name)) {
            return 'No Name';
        } else {
            return $name;
        }

    }

    /**
    * Returns job title or empty string
    **/
    public function jobTitle() {

        if (!isset($this->organizations)) {
            return '';
        }

        // Shanty, damn you.
        if (is_array($this->organizations)) {
            $this->organizations = new Model_Mongo_PocoPluralDocumentSet($this->organizations);
        }

        $org = $this->organizations->getPrimary();
        $jobTitle = '';
        if (isset($org->title)) {
            $jobTitle = $org->title;
        }

        return $jobTitle;

    }

    /**
    * Returns company name or empty string
    **/
    public function orgName() {

        if (!isset($this->organizations)) {
            return '';
        }
            
        // Shanty, damn you.
        if (is_array($this->organizations)) {
            $this->organizations = new Model_Mongo_PocoPluralDocumentSet($this->organizations);
        }

        $org = $this->organizations->getPrimary();
        $orgName = '';
        if (isset($org->name)) {
            $orgName = $org->name;
        }

        return $orgName;

    }

    /**
    * Returns company name and title formatted by tash template
    **/
    public function orgNameAndJobTitle($template = "{{jobTitle}}, {{orgName}}") {
        $m = new Mustache_Engine();
        return trim($m->render($template, $this), " ,");
    }

    /**
     * Does this poco have a name?
     * @return bool
     */
    public function hasName() {
        return isset($this->name) && (
            (isset($this->name->givenName) && !empty($this->name->givenName)) || 
            (isset($this->name->familyName) && !empty($this->name->familyName))
        );
    }

    /**
    * Returns an array with key:value assigned to value for comparison.
    */
    private function keyedArrayComparator(Array $array, $forReplace = true) {

        ksort($array);

        $flatValues = array();

        foreach($array as $k => $v) {
            $flatValues[] = $k.':'.$v;
        }

        return implode(',',$flatValues);
    }

    /**
    * Returns an array of simple values for a plural array block
    */
    protected function standardPluralComparator(Array $plural, $forReplace = true) {

        $flatValues = array();

        //Loop over every item in the array and simplify it's value.
        foreach ($plural as $k => $v) {

            if (is_array($v)) {
                ksort($v);
                $v = $this->standardPluralComparator($v, true);
            }

            if (is_numeric($k)) {
                $flatValues[] = $v;
            } else {
                $flatValues[] = $k . '::' . $v;
            }

        }

        //When doing a replace, we want to return a single value
        if ($forReplace) {    
            return implode(',',$flatValues);    
        } else {    
            return $flatValues;  
        }

    }

    /**
    * Returns single value for comparison, sorts array and implodes.
    */
    private function sortedImplode(Array $array) {
        sort($array);
        return implode(',',$array);
    }

    /**
    * Determines if there's a difference beetwen this poco doc and another.
    *
    * @return bool
    * @author Tom Holder
    **/
    public function isDifferent(Model_Mongo_Poco $comparison, $forReplace = true) {

        $p1 = $this->export();
        $p2 = $comparison->export();
        $this->removeKeysForComparison($p1, $p2);

        $output1 = array();
        $output2 = array();

        $this->simplifyPocoValues($p1, $output1, $forReplace);
        $this->simplifyPocoValues($p2, $output2, $forReplace);

        //Remove IDs, not relevant for comparison.
        if (isset($output1['id'])) {
            unset($output1['id']);
        }

        if (isset($output2['id'])) {
            unset($output2['id']);
        }

        //var_dump($output1);
        //var_dump($output2);
        //die();

        //Check values.
        $result = $this->hasMissingValue($output1, $output2);

        //If we are doing a replace, we need to check the reverse.
        if (!$result && $forReplace) {
            $result = $this->hasMissingValue($output2, $output1);
        }

        return $result;

    }

    private function removeKeysForComparison($p1, $p2)
    {
        //Exlude dates from diff comparison.
        if (array_key_exists('id', $p1)) {
            unset($p1['id']);
        }
        if (array_key_exists('id', $p2)) {
            unset($p2['id']);
        }
        if (array_key_exists('updated', $p1)) {
            unset($p1['updated']);
        }
        if (array_key_exists('updated', $p2)) {
            unset($p2['updated']);
        }
        if (array_key_exists('published', $p1)) {
            unset($p1['published']);
        }
        if (array_key_exists('published', $p2)) {
            unset($p2['published']);
        }
    }

    /**
    * Loops over array to check and sees if all the values are already in source.
    * If it finds an empty array, it checks it against source and if source is not empty it will also return true.
    * @param array $source
    * @param array $check
    * @return bool
     */
    private function hasMissingValue(Array $source, Array $check) {

        foreach($check as $k => $v) {

            if (is_array($v)) {
                if (!isset($source[$k])) { return true; }
                if (empty($v) && !empty($source[$k])) { return true; }
                if ($this->hasMissingValue($source[$k], $v)) { return true; }
            } else {

                if (isset($source[$k])) {

                    if (is_array($source[$k]) && !in_array($v, $source[$k])) {
                        return true;
                    } elseif ($source[$k] !== $v) {
                        return true;
                    }

                } else {
                    return true;
                }
            }

        }

        return false;

    }

    /**
    * Loops over array and simplifies the values according to poco comparator functions
    * so that two poco blocks can be compared.
    * @param array $input
    * @param array $output
    * @return mixed
    */
    public function simplifyPocoValues(Array $input, Array &$output, $forReplace = true) {

        foreach($input as $k => $v) {

            if(is_array($v)) {
                if (array_key_exists($k, self::$_comparators)) {
                    $fnc = self::$_comparators[$k];
                    $output[$k] = self::$fnc($v, $forReplace);
                } else {
                    $output[$k] = self::standardPluralComparator($v, $forReplace);
                }
            } elseif (is_string($v)) {
                $output[$k] = $k.':'.$v;
            } elseif (is_bool($v)) {
                $output[$k] = $k.':'.(string) $v;
            }


        }

        return;
    }

    /**
    * Merges passed in poco document in to this document.
    *
    * @return void
    * @author Tom Holder
    **/
    public function merge(Model_Mongo_Poco $poco, $replace = false) {

        $sourceData = $poco->export();
        $targetData = $this->export();

        foreach ($sourceData as $k => $v) {

            //If the value is an array but not an associative array.
            if (!$replace && is_array($v) && !Contactzilla_Utility_Functions::isAssoc($v)) {

                if (array_key_exists($k, $targetData) && is_array($targetData[$k])) {
                    $targetData[$k] = array_merge($v, $targetData[$k]);
                } else {
                    $targetData[$k] = $v;
                }

            } else {

                //Value is either not an array or is associative array or we want to force a replace.
                if ($replace || !array_key_exists($k, $targetData)) {
                    $targetData[$k] = $v;
                }

                if ((is_array($v) && Contactzilla_Utility_Functions::isAssoc($v)) || is_string($v)) {
                    $targetData[$k] = $v;
                }

            }
        }

        //If this is a replacement.
        if ($replace) {

            //We need to remove any elements in target that don't exist in source.
            foreach ($targetData as $k => $v) {

                if (array_key_exists($k, $targetData) && !array_key_exists($k, $sourceData)) {
                    unset($targetData[$k]);
                }
            }
        }

        //Re-canonicalize the data
        $cleaner = new Contactzilla_Poco_Cleaner();
        $this->_data = $this->_cleanData = $cleaner->getCleanPoco($targetData);

    }

    /**
    * Returns array of unique phone numbers
    *
    * @return void
    * @author Tom Holder
    **/
    public function getAllPhoneNumbers($stripFormatting = false) {

        if(!$this->hasProperty('phoneNumbers')) {
            return array();
        }

        $numbers = array();
        for($i = 0; $i < count($this->phoneNumbers); $i++) {

            $number = $this->phoneNumbers[$i];

            if(array_key_exists('value', $number)) {
                $value = $number['value'];
            } elseif(isset($number->value)) {
                $value = $number->value;
            }
            
            if (isset($value)) {
                if ($stripFormatting) {
                    $numbers[] = preg_replace ('/[^\\d]/', '', $value);
                } else {
                    $numbers[] = $value;
                }
            }

        }

        return array_unique($numbers);

    }

    /**
    * Returns array of unique email addresses.
    *
    * @return void
    * @author Tom Holder
    **/
    public function getAllEmailAddresses() {

        if(!$this->hasProperty('emails')) {
            return array();
        }

        $emails = array();

        for($i = 0; $i < count($this->emails); $i++) {

            $email = $this->emails[$i];

            if(array_key_exists('value', $email)) {
                $emails[] = $email['value'];
            } elseif(isset($email->value)) {
                $emails[] = $email->value;
            }

        }

        return array_unique($emails);

    }

    /**
    * Calls down to plural documentset to get all values.
    * 
    * @param ensureNeverEmpty if set to true, an empty document will be added if none are returned. Useful for form output.
    * @return void
    * @author Tom Holder
    **/
    public function getAll($field, $ensureNeverEmpty = false) {

        $results = $this->getProperty($field);

        if (!isset($results) || is_null($results) || empty($results) || !is_object($results)) {
            if ($ensureNeverEmpty) {
                $results[] = new Model_Mongo_PocoPluralDocument();
                return $results;
            } else {
                return array();
            }
        }

        return $results->GetAll($ensureNeverEmpty);

    }

    /**
    * Calls down to plural documentset to get values of a specific type.
    *
    * @return void
    * @author Tom Holder
    **/
    public function getByType($field, $types=false, $ensureNeverEmpty = false) {

        $results = $this->getProperty($field);

        if (!isset($results) || is_null($results) || empty($results) || !is_object($results)) {
            if ($ensureNeverEmpty) {
                $results[] = new Model_Mongo_PocoPluralDocument();
                return $results;
            } else {
                return array();
            }
        }

        return $results->GetByType($types, $ensureNeverEmpty);

    }

    /**
    * Calls down to plural documentset to get all values not of a specific type.
    *
    * @return void
    * @author Tom Holder
    **/
    public function getExcludingTypes($field, $types, $ensureNeverEmpty = false) {

        $results = $this->getProperty($field);

        if (!isset($results) || is_null($results) || empty($results) || !is_object($results)) {
            if ($ensureNeverEmpty) {
                $results[] = new Model_Mongo_PocoPluralDocument();
                return $results;
            } else {
                return array();
            }
        }

        return $results->GetExcludingTypes($types, $ensureNeverEmpty);

    }

    /**
    * This is a bit of a hacky method to return data we can use for sort/querying due to mongo being case sensitive.
    *
    * @return void
    * @author Tom Holder
    **/
    public function getSort() {
        $sort = array();

        $fnLnTemplate = "
        {{#isOrg}}
            {{&orgName}}
        {{/isOrg}}
        {{^isOrg}}
            {{&name.givenName}} {{&name.familyName}} 
        {{/isOrg}}";

        $lnFnTemplate = "
        {{#isOrg}}
            {{&orgName}}
        {{/isOrg}}
        {{^isOrg}}
            {{&name.familyName}} {{&name.givenName}}
        {{/isOrg}}";

        $sort['fnLn'] = mb_strtolower($this->dynamicDisplayName($fnLnTemplate), 'UTF-8');
        $sort['lnFn'] = mb_strtolower($this->dynamicDisplayName($lnFnTemplate), 'UTF-8');

        // Sort no name contacts as per apple address book.
        $sort['fnLn'] = $sort['fnLn'] == 'no name' ? '#' : $sort['fnLn'];
        $sort['lnFn'] = $sort['lnFn'] == 'no name' ? '#' : $sort['lnFn'];

        //Nasty shanty hack because sometimes we get an array
        if (isset($this->name) && is_array($this->name)) {
            $this->name = new Shanty_Mongo_Document($this->name);
        }

        if ($this->hasName()) {

            if ($this->name->honorificPrefix) {
                $sort['name']['honorificPrefix'] = mb_strtolower($this->name->honorificPrefix,'UTF-8');
            }
            
            if ($this->name->givenName) {
                $sort['name']['givenName'] = mb_strtolower($this->name->givenName,'UTF-8');
            }

            if ($this->name->middleName) {
                $sort['name']['middleName'] = mb_strtolower($this->name->middleName,'UTF-8');
            }

            if ($this->name->familyName) {
                $sort['name']['familyName'] = mb_strtolower($this->name->familyName,'UTF-8');
            }

            if ($this->name->honorificSuffix) {
                $sort['name']['honorificSuffix'] = mb_strtolower($this->name->honorificSuffix,'UTF-8');
            }

        }

        $orgName = mb_strtolower($this->orgName(),'UTF-8');
        if (!empty($orgName)) {
            $sort['orgName'] = $orgName;
        }

        $jobTitle = mb_strtolower($this->jobTitle(),'UTF-8');
        if (!empty($jobTitle)) {
            $sort['jobTitle'] = $jobTitle;
        }

        return $sort;
    }

    /**
    * Takes array of data (as if submitted by a form) and builds poco from it.
    *
    * @return array
    * @author Tom Holder
    **/
    public static function buildPocoFromFormData(Array $formData) {
        
        //Map the form out to an array of keys that may or may not
        //contain an array of further keys. This is to make it easy to set singular/plural
        //fields using form field names such as pluralfield_field. And, forms fields are arrays a lot of the time.
        $mappedForm = array();
        
        $requirements = Model_Mongo_Poco::$_requirements;
        $requirements['tags'] = '';
        foreach($formData as $k => $v) {
            
            //We limit explode to 2 because POCO doesn't go deeper than this. But if it did, 
            //we might have to make this mapping recursive.
            $fieldNames = explode('_',$k, 2);
            $topLevelFieldName = $fieldNames[0];
            
            //Make sure this form item is a valid poco field.
            if (!array_key_exists($topLevelFieldName, $requirements)) {
                continue;
            }
            
            if (!array_key_exists($topLevelFieldName, $mappedForm)) {
                $mappedForm[$topLevelFieldName] = array();
            }
            
            //If this is a nested field, might be an array nested field or single value nested field.
            if (count($fieldNames) == 2) {
                
                //If this is a form item that's an array - (been specified with [])
                if (is_array($v)) { 
                    for($i = 0; $i < count($v); $i++) {
                        if (!empty($v[$i])) {  
                            $mappedForm[$topLevelFieldName][$i][$fieldNames[1]] = $v[$i];
                        }
                    }
                } else {
                    $mappedForm[$topLevelFieldName][$fieldNames[1]] = $v;
                }
                
            } else {
                //Plain old single value.
                $mappedForm[$topLevelFieldName] = $v;

            }

            
        }

        return $mappedForm;
    }

}