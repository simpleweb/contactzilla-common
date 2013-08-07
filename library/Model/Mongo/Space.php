<?php
/**
 * Meat and Potatoes! Represents a space.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_Space extends Model_Mongo_Base 
  implements Model_Mongo_Interfaces_Space, Model_Mongo_Interfaces_Secured, Model_Mongo_Interfaces_ContactSearchable
{
    protected static $_listsCache;

    protected static $_collection = 'space';

    protected static $_requirements = array(
        'urlKey' => array('Required'),
        'spaceName' => array('Required', 'Filter:StringTrim'),
        'createdBy' => array('Document:Model_Mongo_User', 'AsReference', 'Required'),
        'secret' => array('Required')
    );

    protected function preInsert() {

        //Plan check.
        $existingSpaces = $this->createdBy->getOwnedSpaces()->count();
        $allowedSpaces = $this->createdBy->plan->addressBooks;

        //Make sure we've not exceeded the plan.
        if ($existingSpaces >= $allowedSpaces) {
            throw new Contactzilla_Exceptions_Plan(Contactzilla_Exceptions_Plan::ADDRESSBOOK_LIMIT);
        }

        $this->createdAt = new MongoDate();
        $this->secret = Contactzilla_Utility_Encrypt::GetSecretKey();

        //Important to check if this is already set in case it has been manually set.
        if (!isset($this->urlKey)) {
            $this->urlKey = Contactzilla_Utility_Functions::GetUrlKey($this->spaceName);

            if (empty($this->urlKey)) {
                $this->urlKey = 'invalid-url-key';
            }
        }

        $this->sort = array('spaceName' => mb_strtolower($this->spaceName,'UTF-8'));

        if (!$this->private) {
            Model_Mongo_Metric::LogUserEvent($this->createdBy, 'space-added', $this->spaceName);
        }

    }

    protected function preSave() {
        $this->sort = array('spaceName' => mb_strtolower($this->spaceName,'UTF-8'));
    }
    
    /**
     * Increments a state counter for the contact
     *
     * @return void
     * @author Tom Holder
     **/
    public function incrementState($stateVar, Model_Mongo_User $user = null, $storageField = 'state') {
        parent::incrementState($stateVar, $user);
    }

    /**
     * Sets a state variable for the contact
     *
     * @return void
     * @author Tom Holder
     **/
    public function setState($stateVar, $value, Model_Mongo_User $user = null, $storageField = 'state') {
        parent::setState($stateVar, $value, $user);
    }

    /**
    * Sets state on bulk
    **/
    public function setContactStateBulk($conditions, $stateVar, $value, Model_Mongo_User $user = null, $storageField = 'state') {
        $conditions = array_merge($conditions, array(
          'owner.$id' => $this->getId(),
          'parent' => array('$exists' => false),
          'deletedOwner' => array('$exists' => false)
        ));
        parent::setStateBulk(
            Model_Mongo_Contact::getMongoCollection(),
            $conditions,
            $stateVar,
            $value,
            $user, 
            $storageField
        );
    }

    /**
     * Returns state variable for space for the given user.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getState($stateVar, $default = '', Model_Mongo_User $user = null, $storageField = 'state') {
        return parent::getState($stateVar, $default, $user);
    }

    /**
     * Returns state variable for space and falls back to user if not available.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getStateWithFallback($stateVar, $default, Model_Mongo_User $user, $storageField = 'state') {
        $spaceState = parent::getState($stateVar, '', $user);

        if (empty($spaceState)) {
            $spaceState = $user->getState($stateVar, $default);
        }

        return $spaceState;
    }

    /**
    * Returns an application owned by the space.
    **/
    public function getApplication($id) {
        if(is_string($id)) {
            $id = new MongoId($id);
        }

        return Model_Mongo_Application::one(array('_id' => $id, 'owner.$id' => $this->getId()));
    }

    public function homePage() {
        return $this->url();
    }

    /**
     * undocumented function
     *
     * @return void
     * @author Dan Stringer
     **/
    public function teamMembers() {
       return Model_Mongo_User::all(array('spaces.space.$id' => $this->getId()));
    }

    /**
     * Return all users invited to space
     *
     * @return void
     * @author Sam Ramsay
     **/
    public function invitees() {
       // @TODO Exclude results already in the space
       return Model_Mongo_Invite::distinct('email', array('spaces.space.$id' => $this->getId()));
    }

    /**
    * Returns count of all team members and invitees
    **/
    public function memberCount() {
        $teamMembers = $this->teamMembers();
        $invitees = $this->invitees();

        $count = 0;

        if (isset($teamMembers) && $teamMembers instanceof Shanty_Mongo_Iterator_Cursor) {
            $count += $teamMembers->count();
        }

        if (isset($invitees) && is_array($invitees)) {
            $count += count($invitees);
        }

        return $count;
    }

    /**
    * Returns all installed applications for a space.
    **/
    public function getInstalledApplications() {
        return Model_Mongo_ApplicationInstall::GetInstalledApplications(array('space.$id' => $this->getId()));
    }

    public function hasInstalledApplications() {
        return sizeof($this->GetInstalledApplications()) > 0;
    }

    /**
     * Returns applications that have been given a specific hook.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getInstalledApplicationsWithHook($hook) {
        $apps = $this->getInstalledApplications();

        if(!$apps) {
            return array();
        }

        $spaces = array();
        //Loop over apps and keep ones with hook matching the passed hook.
        foreach($apps as $app) {

            if(isset($app->application->hook->$hook)) {
                $spaces[] = $app;
            }
        }

        return $spaces;
    }

    /**
    * Returns application install for user but only if the app exists.
    **/
    public function getApplicationInstall(Model_Mongo_Application $app) {

        $appInstall = Model_Mongo_ApplicationInstall::one(
            array(
                'space.$id' => $this->getId(),
                'application.$id' => $app->getId()
            )
        );

        if ($appInstall) {
            return $appInstall;
        } else {
            return false;
        }

    }

    /**
    * Determines if a user has access to this space.
    **/
    public function hasAccess(Model_Mongo_User $user) {
        return $user->HasAccess($this);
    }

    /**
    * Determines if a user is an admin of the space.
    **/
    public function isAdmin(Model_Mongo_User $user) {
        if(!isset($this->createdBy) || is_null($this->createdBy)) {
            return false;
        }
        return $this->createdBy->getId()->__toString() === $user->getId()->__toString();
    }

    /**
     * Returns the full web address to the space/
     *
     * @return void
     * @author Tom Holder
     **/
    public function url($path='') {
        $config = Zend_Registry::get('config');
        return 'https://'.$this->urlKey.'.'.$config->url.$path;
    }

    /**
     * Return the path/url for the first 
     * of the following that actually exists:
     *  - A custom space Icon
     *  - The default space icon
     *
     * @return void
     * @author Tom Holder
     **/
    public function getProfilePic($width = 45, $height = 45) {    
        if (trim($this->spaceIcon) != "") {
            return $this->spaceIcon;
        }elseif ($this->private){
            return $this->createdBy->contact->GetProfilePic($width, $height);
        }else {
            return Zend_Registry::get('config')->ssl_proxy.'/profileimg/' . $this->getId()->__toString();
        }
    }

    /**
     * Returns the contacts for a space.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getContacts($conditions = array(), $sort = array(), $fields = array(), $limit = 0, $skip = 0) {
        $conditions = array_merge($conditions, array(
          'owner.$id' => $this->getId(),
          'parent' => array('$exists' => false),
          'deletedOwner' => array('$exists' => false)
        ));

        return Model_Mongo_Contact::getContacts($conditions, $sort, $fields, $limit, $skip);
    }

    /**
     * Returns the deleted contacts for a space.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getDeletedContacts($conditions = array(), $sort = array(), $fields = array(), $limit = 0, $skip = 0) {
        $conditions = array_merge($conditions, array(
          'deletedOwner.$id' => $this->getId()
        ));

        return Model_Mongo_Contact::getContacts($conditions, $sort, $fields, $limit, $skip);
    }

    /**
    * Convenience method to add a contact (poco) to a space for a specified user.
    **/
    public function createContact(Model_Mongo_User $user, $poco, MongoId $id = null) {

        $batch = new Model_Mongo_Batch();
        $batch->createdBy = $user;
        $batch->owner = $this;
        $batch->save();
        if (is_null($id)) {
            $batch->addContact($poco);
        } else {
            $batch->addContact($poco, $id);
        }
        
        $batch->commit();
        
        return $batch;
    }

    /**
     * Get a single contact owned by this space.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getContact($conditions = array()) {

        //This shouldn't really be here, conditions should always be an array, but have re-implemented to fix card dav.
        if (!is_array($conditions)) {
            if (!($conditions instanceof MongoId)) {
                $conditions = new MongoId($conditions);
            }
        }

        //For simple ID search.
        if (($conditions instanceof MongoId)) {
            $conditions = array('_id' => $conditions);
        }

        //Merge in restrictive conditions.
        $conditions = array_merge($conditions, array(
          'owner.$id' => $this->getId(),
          'parent' => array('$exists' => false),
          'deletedOwner' => array('$exists' => false)
        ));

        return Model_Mongo_Contact::getContact($conditions);
    }
    
    /**
     * Returns the soft duplicate contacts for a space.
     *
     * @return void
     * @author Sam Ramsay
     **/
    public function getSoftDupes() {
      return $this->getContacts(array('softDupes.$id' => array('$exists' => true)), array(), array('_id','softDupes'));
    }

    /**
     * Convenience method for counting the number of
     * contacts associated with the space instance
     * that have soft duplicates
     * @return integer
     * @author Sam Ramsay
     */
     public function numSoftDupes() {
        $softDupesList = $this->getSoftDupeList();
        if ($softDupesList) {
            return $softDupesList->getContacts()->count();
        } else {
            return 0;
        }
     }

    /**
     * Convenience method for counting the number of
     * contacts associated with the space instance
     * @return integer
     * @author Dan Stringer
     */
    public function numContacts() {
        return $this->getContacts(array(), array(), array('_id'))->count();
    }

    /**
     * Convenience method for counting the number of team 
     * members (users) associated with the space instance
     * @return integer
     * @author Dan Stringer
     */
    public function numTeamMembers() {
        return $this->TeamMembers()->count();
    }

    /**
     * Returns the lists belonging to a space
     *
     * @return void
     * @author Tom Holder
     **/
    public function getLists() {
      
      if (!isset($this->_listsCache)) {
        $this->_listsCache = Model_Mongo_List::all(
              array(
                  'owner.$id' => $this->getId()
              )
          )->sort(array('sort.listName' => 1));
      }

      return $this->_listsCache;
      
    }

    /**
     * Create an array of list items keyed by 
     * list id.  Format returned should be
     * suitable for multiple select options
     * 
     * @return array
     * @author Dan Stringer
     **/
    public function getListsAsOptions() {
        $lists = array();
        foreach($this->getLists() as $list){
            $lists[$list->getId()->__toString()] = $list->listName;
        }
        return $lists;
    }

    /**
     * Returns an individual list belonging to a space.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getList($id) {

      if (!($id instanceof MongoId)) {
        $id = new MongoId($id);
      }

      return Model_Mongo_List::one(
          array(
              '_id' => $id,
              'owner.$id' => $this->getId()
          )
      );
    }

    /**
     * Returns an individual list by name belonging to a space.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getListByName($label) {

      return Model_Mongo_List::one(
          array(
              'listName' => $label,
              'owner.$id' => $this->getId()
          )
      );
    }

    /**
     * Returns soft dupe list
     *
     * @return Mondel_Mongo_List the soft dupe list.
     * @author Tom Holder
     **/
    public function getSoftDupeList() {

      return Model_Mongo_List::one(
          array(
              'listName' => Model_Mongo_List::POTENTIAL_DUPLICATE_LABEL,
              'owner.$id' => $this->getId()
          )
      );
    }

    /**
    * Deletes selected contacts.
    **/
    public function deleteContacts($conditions, Model_Mongo_User $user) {

        $conditions = array_merge($conditions, array(
          'owner.$id' => $this->getId(),
          'parent' => array('$exists' => false),
          'deletedOwner' => array('$exists' => false)
        ));

        $operation = array(
            '$rename' => array('owner' => 'deletedOwner')
        );

        Model_Mongo_Contact::getMongoCollection()->update(
          $conditions,
          $operation,
          array('multiple' => true)
        );

        return true;

    }

    /**
    * Counts contacts in this space matching specified criteria.
    **/
    public function countContacts($conditions = array()) {

        $conditions = array_merge($conditions, array(
          'owner.$id' => $this->getId(),
          'parent' => array('$exists' => false),
          'deletedOwner' => array('$exists' => false)
        ));

        return Model_Mongo_Contact::getMongoCollection()->count(
          $conditions
        );
    }

    /**
    * Returns an array representing selected criteria.
    **/
    public function getSelectedCriteria(Model_Mongo_User $user) {
        return array(
          'state.' . $user->getId() . '.selected' => true
        );
    }

    /**
    * Tags contacts with the specified tag.
    **/
    public function tagContacts($conditions, Model_Mongo_User $user, $tag) {
        $tag = mb_strtolower($tag, 'UTF-8');

        $conditions = array_merge($conditions, array(
          'owner.$id' => $this->getId(),
          'parent' => array('$exists' => false),
          'deletedOwner' => array('$exists' => false)
        ));

        $operation = array(
            '$addToSet' => array('poco.tags' => $tag)
        );

        Model_Mongo_Contact::getMongoCollection()->update(
          $conditions,
          $operation,
          array('multiple' => true)
        );

        return true;
    }

    /**
    * Untags all contacts in space.
    **/
    public function untagContacts($conditions, Model_Mongo_User $user, $tag) {

        $tag = mb_strtolower($tag, 'UTF-8');

        $conditions = array_merge($conditions, array(
          'owner.$id' => $this->getId(),
          'parent' => array('$exists' => false),
          'deletedOwner' => array('$exists' => false)
        ));

        $operation = array(
            '$pull' => array('poco.tags' => $tag)
        );

        Model_Mongo_Contact::getMongoCollection()->update(
          $conditions,
          $operation,
          array('multiple' => true)
        );

        return true;
    }

    /**
    * Tags contacts with the specified tag.
    **/
    public function getTagCount($conditions = array()) {

        $conditions = array_merge($conditions, array(
          'owner.$id' => $this->getId(),
          'parent' => array('$exists' => false),
          'deletedOwner' => array('$exists' => false),
        ));

        /*{
            $match: {
                "owner.$id" : ObjectId("51449071f9e5f63c3f000ff5"),
                "parent" : { "$exists": false },
                "deletedOwner" : { "$exists": false }
            }
        },
        {
            '$unwind' : '$poco.tags'
        },
        {
            $group : {
                '_id' : '$poco.tags',
                'number' : { '$sum' : true }
            }
        }*/

        $aggregate = array(
            array('$match' => $conditions),
            array('$unwind' => '$poco.tags'),
            array('$group' => array('_id' => '$poco.tags', 'number' => array( '$sum' => 1)))
        );

        return Model_Mongo_Contact::getMongoCollection()->aggregate($aggregate);
    }

    /**
    * Tags contacts with the specified tag.
    **/
    public function getSelectedTagCount($conditions, Model_Mongo_User $user) {
        $conditions = array_merge($conditions, array('state.' . $user->getId() . '.selected' => true));
        return $this->getTagCount(
            $conditions
        );
    }

    /**
    * Restores multiple contacts at once.
    **/
    public function restoreContacts($contacts, Model_Mongo_User $user) {

        $contacts = Model_Mongo_Contact::getIterableContacts($contacts, $this, 'getDeletedContacts');
        $restoredContactIds = array();
        foreach($contacts as $contact){
            $contact->undeleteOwner($user);
            $restoredContactIds[] = $contact->getId()->__toString();
        }

        $i = count($restoredContactIds);
        Model_Mongo_Metric::LogUserEvent($user, 'contact-restored', new MongoInt32($i));

        return $restoredContactIds;

    }

    /**
    * Take a documentset of contacts and merges them.
    * @return Model_Mongo_Contact returns the contact we merged in to.
    **/
    public function mergeContacts($contacts, Model_Mongo_User $user, &$removeIds) {

        if (!isset($contacts) || empty($contacts)) {
            return false;
        }

        $contacts = Model_Mongo_Contact::getIterableContacts($contacts, $this);

        $mergeTarget = false;
        $removeIds = array();  //Returns Ids we've removed.

        //Loop over contacts and export their essence.
        $forDestruction = array();
        foreach($contacts as $contact){
          
          if (!$mergeTarget) {
              $mergeTarget = $contact;

              //Check security of this user against merge target.
              if (!$user->hasAccess($mergeTarget->owner)) {
                throw new Contactzilla_Exceptions_Security(Contactzilla_Exceptions_Security::NO_ACCESS_TO_REQUIRED_SPACE);
              }

          } else {
            $mergeTarget->merge($contact);
            $forDestruction[] = $contact->getId();
          }

        }

        if (!$mergeTarget) {
            return false;
        }

        //Remove dupe tag.
        $tags = $mergeTarget->poco->tags instanceof Shanty_Mongo_Document ? $mergeTarget->poco->tags->export() : $mergeTarget->poco->tags;
        if (isset($tags) && is_array($tags)) {
            if(($key = array_search(Model_Mongo_List::POTENTIAL_DUPLICATE_LABEL, $tags)) !== false) {
                unset($tags[$key]);
            }
            $mergeTarget->poco->tags = $tags;
        }

        $mergeTarget->save();
        $mergeTarget->owner->deleteContacts(array('_id' => array('$in' => $forDestruction)), $user);
        $removeIds = $forDestruction;

        //Get latest version of merge target.
        $mergeTarget = Model_Mongo_Contact::find($mergeTarget->getId());

        Model_Mongo_Metric::LogUserEvent($user, 'contact-merged', new MongoInt32(count($removeIds)));

        return $mergeTarget;

    }

    /**
     * Merge all contacts within a space into one
     * @return integer
     * @author Tom Holder
     **/
    public function mergeSoftDuplicates(&$mergeMasters, &$removeDupeIds, $user) {

        $softDupeList = $this->getSoftDupeList();

        if (!isset($softDupeList)) {
            return false;
        }
        $softDupes = $softDupeList->getContacts();

        $nameList = array();  //Keeps track of names we've checked.
        $removeDupeIds = array();
        $mergeMasters = array();

        foreach($softDupes as $softDupe) {

          //Find soft dupes with same name as this contact.
          $dupeContacts = $softDupe->findSoftDupes($this, $nameList);

          //Only a soft dupe if 2 or more contacts are returned.
          if ($dupeContacts && $dupeContacts->count() > 1) {

            $removeIds = array();
            $mergeTarget = $this->mergeContacts($dupeContacts, $user, $removeIds);
            $mergeMasters[] = $mergeTarget;

            $removeDupeIds = array_merge($removeDupeIds, $removeIds);

          }


        }

        //Remove soft dupe list.
        $softDupeList->cleanAndDelete($user, $this);

        $mergeMasters = $this->explodeContacts($mergeMasters, $user);
        return count($mergeMasters);
      
    }

    /**
     * Takes contacts and turns them in to array with lists, selected state etc.
     * @static
     * @param $contacts
     * @return array
     */
    public function explodeContacts($contacts, Model_Mongo_User $user) {

        $lists = $this->getLists();
        if (is_object($lists) && get_class($lists) == 'Shanty_Mongo_Iterator_Cursor') {
            $lists = $lists->export();
        }

        $contacts = Model_Mongo_Contact::getIterableContacts($contacts, $this);

        $results = array();

        foreach($contacts as $contact) {
            $contactArray = $contact->export(true);
            
            $contactArray['id'] = $contact['_id']->__toString();
            
            unset($contactArray['_id']);
            unset($contactArray['_type']);

            $contactArray['poco']['fnLn'] = $contact->poco->dynamicDisplayName();
            $contactArray['poco']['lnFn'] = $contact->poco->dynamicDisplayName('{{#isOrg}}
            {{orgName}}
        {{/isOrg}}
        {{^isOrg}}
            {{name.familyName}} {{name.givenName}} 
        {{/isOrg}}');

            $tags = array();
            if (isset($contactArray['poco']['tags'])) {
                foreach ($contactArray['poco']['tags'] as $tag) {
                    
                    foreach ($lists as $list) {

                        if (strcasecmp($list['listName'], $tag) === 0) {
                            $tags[] = array('tag' => $tag, 'color' => $list['color'], 'id' => $list['_id']->__toString());
                        }
                    }

                }

                $contactArray['poco']['tags'] = $tags;

            }
            
            //Biscuit eater at simpleweb
            $contactArray['poco']['orgNameAndJobTitle'] = $contact->poco->orgNameAndJobTitle();

            $contactArray['updatedAtFriendly'] = Contactzilla_Utility_Functions::FriendlyDate($contact->updatedAt->sec);

            if (isset($contactArray['state']) && isset($contactArray['state'][$user->getId()->__toString()])) {
                $contactArray['state'] = $contactArray['state'][$user->getId()->__toString()];
            } else {
                $contactArray['state'] = array();
            }

            $results[] = $contactArray;
        }

        return $results;
    }

    /**
     * Delete method override.  Ensures that all references to this space
     * are cleaned up before deleting the actual space.
     * @param $safe: bool
     **/
    public function delete($safe = true) {
        // Remove this space from all Team Member spaces
        foreach( $this->TeamMembers() as $user ) {
            $user->addOperation('$pull', 'spaces', array('space.$id'=> $this->getId()));
            $user->save();
        }

        // Delete each contact in the space
        $contacts = Model_Mongo_Contact::all(array('owner.$id' => $this->getId()));
        foreach($contacts as $contact) {
            $contact->delete();
        }

        return parent::delete($safe);
    }

    /**
    * We've overridden save to catch urlKey clash.
    **/
    public function save($entierDocument = false, $safe = true) {
        try {
            parent::save($entierDocument, $safe);
        } catch(Exception $e) {

            $m = $e->getMessage();

            //We might have had a urlKey clash, in which case just use id.
            if (strpos($m, 'duplicate') && strpos($m, 'urlKey')) {
                $this->urlKey = $this->urlKey.'-'.time();
                parent::save($entierDocument, $safe);
            } else {
                throw $e;
            }

        }
    }

    protected function postInsert() {   
        if(!empty($this->createdBy)) {
            Model_Mongo_Application::InstallDefaultAppsInSpaceForUser($this, $this->createdBy);
        }
    }

    public function __toString() {
        return $this->spaceName;
    }

}
