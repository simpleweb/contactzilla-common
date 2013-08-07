<?php
/**
 * Represents a single plural item
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2012
 **/
class Model_Mongo_PocoPluralDocument extends Shanty_Mongo_Document
{
    
    /**
     * Returns username for account, falling back to userid.
     *
     * @return void
     * @author Tom Holder
     **/
    public function customType() {
        
        if (isset($this->type) && !empty($this->type) && Contactzilla_Utility_Functions::StartsWith($this->type, 'x-ablabel:', true)) {       
            //Remove standard ios custom types.
            $type = $this->type;
            $type = str_ireplace('x-ablabel:_$!<anniversary>!$_', '', $type);
            $type = str_ireplace('x-ablabel:_$!<other>!$_', '', $type);
            $type = str_ireplace('x-ablabel:_$!<homepage>!$_', '', $type);

            return str_ireplace('x-ablabel:', '', $type);
        }
        
        return '';
    }

    /**
    * Returns an array of Key, Value, Params for vcard.
    * Normal vcard items will only have 1 item in array. More complex ones or ones with custom types will have multiple elements.
    **/
    public function getVcard($key, $value = false, $params = array()) {

        $value = $value ? $value : $this->value;

        $simpleTypes = array();
        $customTypes = array();
        $this->_splitTypes($simpleTypes, $customTypes);

        $items = array();
        $item = array();

        $item[] = array(
            'key' => $key,
            'value' => $value,
            'params' => array_merge($params, array('type' => $simpleTypes))
        );

        if (!empty($customTypes)) {

            foreach ($customTypes as $type) {
                $item[] = array(
                    'key' => 'X-ABLABEL',
                    'value' => $type
                );
            }
            
        }

        $items[] = $item;

        return $items;

    }

    /**
    * This function takes the type string, splits it and separates it out in to
    * simple (recognised) types and complex (custom) types.
    **/
    protected function _splitTypes(&$simpleTypes, &$customTypes, $type = false) {

        $type = $type ? $type : $this->type;
        $types = $this->_getTypes($type);

        foreach($types as $type) {
            if (Contactzilla_Utility_Functions::StartsWith($type, 'X-ABLabel:',true)) {
                $customTypes[] = str_ireplace('X-ABLabel:', '', $type);
            } else {
                //This replace is because of a problem that was causing ios to fail.
                $simpleTypes[] = str_ireplace('work:', '', $type);
            }
        }

    }

    protected function _getTypes($type) {
        $type = isset($type) ? $type : '';
        return explode(';', $type);
    }
}