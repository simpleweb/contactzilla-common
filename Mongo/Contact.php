<?php
/**
 * Represents a contact in the system. This is the CZ model, not to be confused with the POCO data
 * held within which is tried to be kept POCO compliant.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Contact extends Model_Mongo_Base
    Implements Model_Mongo_Interfaces_Secured {

    protected static $_collection = 'contact';

    protected static $_requirements = array(
        'user' => array('Document:Model_Mongo_User', 'AsReference'),  //Only set for contacts that match up with users.
        'createdBy' => array('Document:Model_Mongo_User', 'AsReference', 'Required'),
        'poco' => array('Document:Model_Mongo_Poco', 'Required'),
        'owner' => array('Document:Model_Mongo_Space', 'AsReference'),
        'deletedOwner' => array('Document:Model_Mongo_Space', 'AsReference'),
        'parent' => array('Document:Model_Mongo_Contact', 'AsReference'),
        'batch' => array('Document:Model_Mongo_Batch', 'AsReference'),
        'lists' => array('DocumentSet'),
        'lists.$' => array('Document:Model_Mongo_List', 'AsReference'),
        'references' => array('DocumentSet'),
        'references.$' => array('Document:Model_Mongo_Contact', 'AsReference')
    );

    protected function preInsert() {

        $this->createdAt = new MongoDate();
        $this->updatedAt = new MongoDate();

        //Lowercase displayname for sorting.
        $this->sort = $this->poco->getSort();

        //Replace POCO id with our new id. Keep source id.
        $alternativeIds = array();
        if (isset($this->poco->id)) {
            if (is_array($this->poco->id)) {
                $alternativeIds = array_merge($alternativeIds, $this->poco->id);
            } else {
                $alternativeIds[] = $this->poco->id;
            }
        }

        $this->poco->id = 'cz_'.$this->getId()->__toString();
        $alternativeIds[] = $this->poco->id;
        $this->alternativeIds = array_unique($alternativeIds);

    }

    protected function preUpdate() {
        $poco = $this->poco->export();
        $this->poco = new Model_Mongo_Poco($poco);
        $this->updatedAt = new MongoDate();
    }

    /**
     * Before every save.
     *
     * @return void
     * @author Tom Holder
     **/
    protected function preSave() {

        //Lowercase displayname for sorting.
        $this->sort = $this->poco->getSort();

        //This sets an array of identifiers used for dupe detection.
        $this->identifiers = $this->getIdentifiers();
    }

    /**
    * Merges the passed in contact in to this contact.
    **/
    public function merge(Model_Mongo_Contact $contact, $replace = false) {

        //Merge alternatice Ids.
        $alternativeIdsIncoming = $contact->alternativeIds instanceof Shanty_Mongo_Document ? $contact->alternativeIds->export() : $contact->alternativeIds;
        $alternativeIdsTarget = $this->alternativeIds instanceof Shanty_Mongo_Document ? $this->alternativeIds->export() : $this->alternativeIds;

        if (is_array($alternativeIdsIncoming) && is_array($alternativeIdsTarget)) {

            //Remove CZ id from incoming contact.
            if(($key = array_search('cz_'.$contact->getId(), $alternativeIdsIncoming)) !== false) {
                unset($alternativeIdsIncoming[$key]);
            }

            $this->alternativeIds = array_values(array_unique(array_merge($alternativeIdsTarget, $alternativeIdsIncoming)));
        }

        //Merge poco data.
        $this->poco->merge($contact->poco, $replace);
    }

    /**
     * Will snapshot off contact to historic version.
     *
     * @return contact
     * @author Tom Holder
     **/
    public function timeMachineSnapshot() {

        //Takes care of archiving off old document.
        $data = $this->export();
        $archivedDoc = new Model_Mongo_Contact($data);
        $archivedDoc->parent = $this;
        $archivedDoc->save();

        return $archivedDoc;
    }

    /**
     * Returns historical versions of this contact, most recent first.
     *
     * @return void
     * @author Tom Holder
     **/
    public function historicalContacts() {
        return Model_Mongo_Contact::all(array('parent.$id' => $this->getId()))->sort(array('createdAt' => 1));;
    }

    /**
     * Increments a state counter for the contact
     *
     * @return void
     * @author Tom Holder
     **/
    public function incrementState($stateVar, Model_Mongo_User $user = null, $storageField = 'state') {
        parent::IncrementState($stateVar, $user);
    }

    /**
     * Sets a state variable for the contact
     *
     * @return void
     * @author Tom Holder
     **/
    public function setState($stateVar, $value, Model_Mongo_User $user = null, $storageField = 'state') {
        parent::SetState($stateVar, $value, $user);
    }

    /**
     * Returns state variable for user.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getState($stateVar, $default = '', Model_Mongo_User $user = null, $storageField = 'state') {
        return parent::GetState($stateVar, $default, $user);
    }

    /**
     * Returns existing record based on alternative ids.
     * @return void
     * @author Tom Holder
     **/
    public function findByAlternativeIds(Model_Mongo_Space $owner) {

        if (!isset($this->alternativeIds)) {
            return false;
        }

        $query = array(
            '_id' => array('$ne' => $this->getId()),
            'owner.$id' => $owner->getId(),
            'parent' => array('$exists' => false),  //Only look at live contacts not historic.
            'deletedOwner' => array('$exists' => false), //Nothing deleted
            'alternativeIds' => array('$in' => $this->alternativeIds->export())
        );

        return Model_Mongo_Contact::one($query);

    }

    /**
     * Uses the normalized identifiers to find a potential duplicate.
     * This is based on a HARD match, display name and at least one identifier must match.
     * @return void
     * @author Tom Holder
     **/
    public function findDupe(Model_Mongo_Space $owner) {

        if(!isset($owner)) {
            throw new Exception('Contact must have an owner to check for dupes.');
        }

        //If this is for an intentional update.
        if (isset($this->targetId)) {

            $query = array(
                '_id' => $this->targetId,
                'owner.$id' => $owner->getId()
            );
            return Model_Mongo_Contact::one($query);
        }

        //Try and find a match based on alternative ids.
        $existing = $this->findByAlternativeIds($owner);
        if($existing) {
            return $existing;
        }

        //This wasn't an update, we're going to try and find a hard match, but we need some identifiers
        //to do that.
        if (!isset($this->identifiers) || is_null($this->identifiers) || empty($this->identifiers)) {
            return false;
        }

        $sort = $this->poco->getSort();

        //Find contacts with the same owner as this that don't have a parent (current records).
        //That have any matching identifiers.
        $query = array(
            '_id' => array('$ne' => $this->getId()),
            'owner.$id' => $owner->getId(),
            'sort.fnLn' => $sort['fnLn'], //Display names must match exactly.
            'parent' => array('$exists' => false),  //Only look at live contacts not historic.
            'deletedOwner' => array('$exists' => false), //Nothing deleted.
            'identifiers' => array('$in' => $this->GetIdentifiers())
        );

        return Model_Mongo_Contact::one($query);
    }

    /**
     * Finds duplicates based on name match only.
     * Name list is an array that keeps a list of names we've checked, to stop the function running constantly
     * on the same name.
     * Note, also returns current contact so this will always return at least 1 contact.
     * @return void
     * @author Sam Ramsay
     **/
    public function findSoftDupes(Model_Mongo_Space $owner, &$nameList) {

        if(!isset($owner)) {
            throw new Exception('Contact must have an owner to check for dupes.');
        }

        if (!isset($nameList) || !is_array($nameList)) {
            throw new Exception('Must pass an name array list.');
        }

        $sort = $this->poco->getSort();

        //Don't run on already checked names
        if (in_array($sort['fnLn'], $nameList)) {
            return false;
        }

        $nameList[] = $sort['fnLn'];

        //Find contacts with the same owner as this that don't have a parent (current records).
        $query = array(
            'sort.fnLn' => $sort['fnLn']
        );
        
        return $owner->getContacts($query);

    }

    /**
    * function takes unknown input and returns iterable contacts or throws relevant exception.
    **/
    public static function getIterableContacts($contacts, Model_Mongo_Interfaces_ContactSearchable $finder = null, $finderFunction = 'getContacts') {

        //If we're passed an object, we'll use it directly but needs to be iterable.
        if (is_object($contacts)) {

            switch (get_class($contacts)) {

                case 'Model_Mongo_Contact':
                    $contacts = array($contacts);
                    break;

                case 'Shanty_Mongo_Iterator_Cursor':        //No action required. Only here to be explicit.
                    break;

                default:
                    throw new Exception(get_class($contacts) . ' is not permitted. Require array of string ids, contact or Shanty_Mongo_Iterator_Cursor');
                    break;
            }

        } elseif($finder) {

            if (!is_array($contacts)) {
                $contacts = array($contacts);
            }

            $contacts = \Contactzilla_Utility_Functions::ArrayOfIdsToMongoIds($contacts);

            //Pull back contacts. Do this through the space for security.
            $contacts = $finder->$finderFunction(array('_id' => array('$in' => $contacts)), array('createdAt' => 1));


        }

        return $contacts;

    }

    /**
    * Returns array of what is considered solid identifiers for this contact.
    **/
    public function getIdentifiers()  {

        $contactIdentifiers = array_merge(
            $this->poco->getAllPhoneNumbers(true),
            $this->poco->getAllEmailAddresses()
        );

        return $contactIdentifiers;
    }

    /**
    * Creates lists for each poco tag.
    *
    * @return void
    * @author Tom Holder
    **/
    public function createListsFromTags($source = false) {

        if (isset($this->poco->tags)) {
            foreach ($this->poco->tags as $tag) {

                $color = false;
                if ($source && get_class($source) == 'Model_Mongo_Space') {
                    $list = $source->getListByName($tag);
                    $color = $list->color;
                }

                Model_Mongo_List::findOrCreate($tag, $this->owner, $color);
            }
        }
    }

    /**
     * Returns CZ home page for the contact.
     *
     * @return string
     * @author Tom Holder
     **/
    public function homePage() {
        return $this->owner->homePage().'/contacts/view/c/'.$this->getId();
    }

    /**
     * Determines if the user has access to this contact.
     *
     * @return void
     * @author Tom Holder
     **/
    public function hasAccess(Model_Mongo_User $user) {
        return true;
    }

    /**
     * Shares a contact in to a space. Exciting stuff!
     *
     * @return void
     * @author Tom Holder
     **/
    public function share(Model_Mongo_Space $space, Model_Mongo_User $user) {
        $batch = new Model_Mongo_Batch();
        $batch->createdBy = $user;
        $batch->owner = $space;
        $batch->save();

        $batch->addContact($this->poco->export());
        $results = $batch->commit(true, true, $this->owner);

        //If new document was committed or merged, add the reference.
        $result = false;
        if ($results['skipped'] == 0) {
            $this->save();
            Model_Mongo_Metric::LogUserEvent($user, 'contact-share');
            $result = true;
        } else {
            Model_Mongo_Metric::LogUserEvent($user, 'contact-share-skip');
        }

        //Will return false if contact was skipped (it already exists in space)
        return $result;
    }

    /**
    * Returns profile pic URL.
    *
    * @return void
    * @author Tom Holder
    **/
    public function getProfilePic($width = 45, $height = 45)
    {
        return Zend_Registry::get('config')->ssl_proxy.'/profileimg/' . $this->getId()->__toString();
    }

    /**
     * Poco compliant query
     * Want to refactor this out.
     * @return void
     * @author Tom Holder
     **/
    public static function query(
        Model_Mongo_Space $owner,
        $filterBy = false,
        $filterValue = false,
        $filterOp = 'equals',
        $sort = array('sort.fnLn' => 1),
        $updatedSince = false) {

        $query = array(
            'owner.$id' => $owner->getId(),
            'parent' => array('$exists' => false)
        );

        if(($filterBy && $filterValue) || ($filterBy && $filterOp == 'present')) {

            switch($filterOp) {

                case 'equals':
                    $query[$filterBy] = $filterValue;
                    break;
                case 'startswith':
                    $query[$filterBy] = array('$regex' => "^$filterValue");
                    break;
                case 'contains':
                    $query[$filterBy] = array('$regex' => "$filterValue");
                    break;
                case 'present':
                    $query[$filterBy] = array('$exists' => true);
                    break;
            }

        }

        // Contactzilla_Utility_Log::LogNotice(json_encode($query));
        return Model_Mongo_Contact::all($query)->sort($sort);
    }

    /**
     * Takes a filter by and translates it to the underlying data structure.
     * This is because .poco in contact is poco compliant. Due to case sensitivity of mongo
     * some of the data is lower cased in to sort field.
     * Function will return false if the passed in field can't essentially be filtered on.
     * @return void
     * @author Tom Holder
     **/
    public static function translateFilterBy($field)
    {

        switch(strtolower($field)) {
            case 'displayname':
                return 'sort.fnLn';
            case 'name.givenname':
                return 'sort.fnLn';
            case 'name.familyname':
                return 'sort.lnFn';
        }

        return 'poco.'.$field;

    }

    /**
    * This function takes an array of contacts that is probably one larger than we want. We do this for paging
    * purposes, if it is larger than the page size, we trim off the end item and return true, else, we leave
    * in tact and return false.
    **/
    public static function hasMoreContacts(&$contacts, $expectedSize) {

        if (!is_array($contacts) || empty($contacts)) {
            return false;
        }

        if (count($contacts) > $expectedSize) {
            $contacts = array_slice($contacts, 0, $expectedSize);
            return true;
        }

        return false;

    }

    /**
     * Returns contacts. Important function for anything implementing IContactSearchable
     *
     * @return void
     * @author Tom Holder
     **/
    public static function getContacts($conditions = array(), $sort = array(), $fields = array(), $limit = 0, $skip = 0) {

        if (empty($sort)) {
            $sort = array('sort.fnLn' => 1);
        }

        return Model_Mongo_Contact::all($conditions, $fields)->sort($sort)->skip($skip)->limit($limit);
    }


    /**
     * Returns single contact.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function getContact($conditions = array()) {
        return Model_Mongo_Contact::one($conditions);
    }

    /**
    * Determine if a contact already has a list/tag.
    **/
    public function hasTag(Model_Mongo_List $list) {
        
        if (!(isset($this->poco->tags))) {
            return false;
        }

        $hasList = false;
        foreach($this->poco->tags as $tag) {
            if ($tag == $list->listName) {
                $hasList = true;
                break;
            }
        }
        return $hasList;
    }

    /**
     * Deletes the document from a space but without actually removing it. Unsets the owner and sets deletedOwner
     * to owner so it can be restored.
     */
    public function deleteOwner(Model_Mongo_User $user, $safe = true) {
        if (!$user->hasAccess($this->owner)) {
            throw new Contactzilla_Exceptions_Security(Contactzilla_Exceptions_Security::NO_ACCESS_TO_REQUIRED_SPACE);
        }

        if ($user->contact->getId()->__toString() === $this->getId()->__toString()) {
            throw new Contactzilla_Exceptions_User(Contactzilla_Exceptions_User::SELF_DESTRUCTION);
        }

        $this->deletedOwner = $this->owner;
        unset($this->owner);
        $this->save();
    }

    /**
     * Reconnects a 'deleted' document back to the owner. Unsets the deletedOwner and sets owner back
     */
    public function undeleteOwner(Model_Mongo_User $u, $safe = true) {
        if (!$u->HasAccess($this->deletedOwner)) {
            throw new Contactzilla_Exceptions_Security(Contactzilla_Exceptions_Security::NO_ACCESS_TO_REQUIRED_SPACE);
        }

        $this->owner = $this->deletedOwner;
        unset($this->deletedOwner);
        $this->save();
    }

    public function updatedAtSeconds() {

        if (isset($this->updatedAt) && get_class($this->updatedAt) == 'MongoDate') {
            return $this->updatedAt->sec;
        } else {
            return 0;
        }
    }

    public function __toString() {
        return $this->poco->dynamicDisplayName();
    }

    public function toVcard() {
        $converter = new \Contactzilla_Converter_Vcard($this);
        return $converter->toVcard();
    }
}
