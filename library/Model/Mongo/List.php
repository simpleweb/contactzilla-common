<?php
/**
 * Represents a list of contacts. Each contact can be assigned to one or more lists.
 * Lists belong to a space.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2012
 */
class Model_Mongo_List extends Model_Mongo_Base
    implements Model_Mongo_Interfaces_ContactSearchable
{

    protected static $_collection = 'list';

    const POTENTIAL_DUPLICATE_LABEL = "potential duplicate";

    protected static $_requirements = array(
        'listName' => array('Required'),
        'owner' => array('Document:Model_Mongo_Space', 'AsReference')
    );
    
    public function __toString() {
        return $this->listName;
    }
    
    public function preSave() {
        $this->listName = mb_strtolower($this->listName, 'UTF-8');

        //Make dupe label always red.
        $this->color = ($this->listName === self::POTENTIAL_DUPLICATE_LABEL ? '#f00' : $this->color);
    }

    /**
    * This function tries to find a list by label and if doesn't exist it will create it.
    **/
    public static function findOrCreate($label, Model_Mongo_Space $owner, $color = false) {

        if (!isset($owner) || empty($label)) {
            return false;
        }

        $label = mb_strtolower($label, 'UTF-8');

        if (!$color) {

            switch ($label) {

                case 'linkedin':
                    $color = '#4875B4';
                    break;

                case 'twitter':
                    $color = '#33CCFF';
                    break;

                case 'google':
                    $color = '#C63D2D';
                    break;

                case 'facebook':
                    $color = '#3B5998';
                    break;

                case 'freeagent':
                    $color = '#5c9c19';
                    break;

                default:
                    $color = Contactzilla_Utility_Functions::randomHexColor();
            }
            
        }

        
        $listItem = Model_Mongo_List::one(array('listName' => $label, 'owner.$id' => $owner->getId()));

        if (!$listItem) {
            $list = new Model_Mongo_List(array(
                'listName' => $label,
                'owner'    => $owner,
                'color'    => $color
              ));
            $list->save();

            $listItem = $list;
        }

        return $listItem;
    }

    /**
    * Takes an array of contact ids and adds them to a list.
    **/
    public function addContactsToList($contacts, Model_Mongo_User $user) {

        if (!isset($contacts)) {
            return false;
        }
        
        //Ensure they can access this space.
        if (!$this->owner->hasAccess($user)) {
            throw new Contactzilla_Exceptions_Security(Contactzilla_Exceptions_Security::NO_ACCESS_TO_REQUIRED_SPACE);
        }

        $contacts = Model_Mongo_Contact::getIterableContacts($contacts, $this->owner);

        //Create a new batch.
        $batch = new Model_Mongo_Batch();
        $batch->createdBy = $user;
        $batch->owner = $this->owner;
        $batch->save();
        //Loop over each contact..
        foreach ($contacts as $contact) {
        
            $poco = $contact->poco->export();

            if (isset($poco['tags']) && is_array($poco['tags'])) {
                $poco['tags'] = array_merge($poco['tags'], array($this->listName));
            } else {
                $poco['tags'] = array($this->listName);
            }

            $batch->addContact($poco, $contact->getId());

        }

        return $batch->commit(!$this->isSoftDupeList());
    }

    /**
     * Returns the contacts for a list.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getContacts($conditions = array(), $sort = array(), $fields = array(), $limit = 0, $skip = 0) {
        $conditions = array_merge($conditions, array(
            'poco.tags' => $this->listName,
            'owner.$id' => $this->owner->getId(),
            'parent' => array('$exists' => false)
            )
        );
        return Model_Mongo_Contact::getContacts($conditions, $sort, $fields, $limit, $skip);
    }

    /**
     * Get a single contact for a list.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getContact($conditions = array()) {

        //For simple ID search.
        if (($conditions instanceof MongoId)) {
            $conditions = array('_id' => $conditions);
        }

        //Merge in restrictive conditions.
        $conditions = array_merge($conditions, array(
            'poco.tags' => $this->listName,
            'owner.$id' => $this->owner->getId(),
            'parent' => array('$exists' => false)
            )
        );
        return Model_Mongo_Contact::getContact($conditions);
    }

    public function isSoftDupeList() {
        return $this->listName == self::POTENTIAL_DUPLICATE_LABEL;
    }
    /**
    * Stub function due to interface compatability but we don't want to call this.
    **/
    public function delete($safe = true) {
        throw new Exception('Can only delete via cleanAndDelete function.');
    }

    /**
    * Deletes a list.
    **/
    public function cleanAndDelete(Model_Mongo_User $user, Model_Mongo_Space $space) {

        //Ensure user can delete this list.
        if (!$this->owner->hasAccess($user)) {
            throw new Contactzilla_Exceptions_Security(Contactzilla_Exceptions_Security::NO_ACCESS_TO_REQUIRED_SPACE);
        }

        $space->untagContacts(array(), $user, $this->listName);

        //Delete the actual list.
        parent::delete();
    }

}