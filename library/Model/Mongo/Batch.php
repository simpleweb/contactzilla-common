<?php
/**
 * Contacts are added/imported using a batch. These takes care of adding contacts, deduping them, rolling back imports etc.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Batch extends Model_Mongo_Base
  implements Model_Mongo_Interfaces_ContactSearchable
{

    protected static $_collection = 'batch';

    protected static $_requirements = array(
        'owner' => array('Document:Model_Mongo_Space', 'AsReference', 'Required'),
        'createdAt' => array('Required'),
        'createdBy' => array('Document:Model_Mongo_User', 'AsReference', 'Required')
    );

    protected function preInsert() {
        $this->createdAt = new MongoDate(time());
    }

    /**
     * The crux of the contacts system! Adds a new contact. Expects data in poco format but doesn't validate at present.
     * If existing contact Id is passed, this will be used to do a replace.
     *
     * @return Model_Mongo_Contact
     * @author Tom Holder
     **/
    public function addContact($poco, MongoId $existingContactId = null) {

        if(is_array($poco)) {
            $poco = new Model_Mongo_Poco($poco);
        } elseif (!is_object($poco)) {
            return false;
        } elseif (get_class($poco) != 'Model_Mongo_Poco') {
            return false;
        }

        try {

            //Add contact
            $contact = new Model_Mongo_Contact();
            $contact->batch = $this;
            $contact->poco = $poco;
            $contact->createdBy = $this->createdBy;

            //If we have passed in an existing contact id, flag it. This is
            //used when we commit to do an update.
            if (isset($existingContactId)) {
              $contact->targetId = $existingContactId;
            }

            $contact->save();

            return $contact;

        } catch(Shanty_Mongo_Exception $e) {
            return false;
        }
    }

    /**
     * Deals with committing the documents in a batch.
     *
     * @return void
     * @author Tom Holder
     **/
    public function commit($snapshot = true, $flagSoftDuplicates = true, $source = false) {

        /* Can't commit an already committed batch */
        if ($this->committed) {
            return false;
        }

        //Ensure they can commit to this space
        if (!$this->owner->hasAccess($this->createdBy)) {
            throw new Contactzilla_Exceptions_Security(Contactzilla_Exceptions_Security::NO_ACCESS_TO_REQUIRED_SPACE);
        }

        $results = array('committed' => 0, 'merged' => 0, 'updated' => 0, 'skipped' => 0);

        //Loop over all the contacts from our batch.
        $contacts = Model_Mongo_Contact::all(
            array(
                'batch.$id' => $this->getId(),
                'owner' => array('$exists' => false)        //This is important because results are retrieved as they are iterated over.
            )
        )->sort(array('createdAt' => 1));

        //Loop over the contacts that were added to the batch.
        foreach($contacts as $contact) {

            //For the incoming contact, find if there is a strong duplicate match.
            $target = $contact->findDupe($this->owner);
            if($target) {

                $forReplace = isset($contact->targetId);

                //If the poco is different.
                if ($target->poco->isDifferent($contact->poco, $forReplace)) {

                    //Save old version.
                    if ($snapshot) {
                      $target->timeMachineSnapshot();
                    }

                    //Merge the poco
                    $target->merge($contact, $forReplace);

                    //Generate a new list for any missing tags.
                    $target->createListsFromTags($source);
                    
                    //Add the batch
                    $target->batch = $contact->batch;

                    //Save target
                    $target->updatedAt = new MongoDate();
                    $target->save();

                    //Delete the document we've disgarded.
                    $contact->delete();

                    if ($forReplace) {
                      $results['updated']++;
                    } else {
                      $results['merged']++;
                    }

                } else {

                    //Delete the document we've disgarded.
                    $contact->delete();
                    $results['skipped']++;
                }

            } else {

                //Generate a new list for any missing tags.
                $contact->owner = $this->owner;
                $contact->createListsFromTags($source);
                $contact->updatedAt = new MongoDate();
                $contact->save(true, false);
                $results['committed']++;

            }
        }

        $this->committed = true;
        $this->results = $results;
        $this->save();

        //Tag any soft duplicates.
        if ($flagSoftDuplicates) {
          $this->flagSoftDuplicates();
        }

        return $results;
    }

    /**
    * Finds soft duplicates and tags them as potential dupes.
    **/
    public function flagSoftDuplicates() {

      if (!isset($this->owner)) {
        return false;
      }

      //Will hold a reference to our dupe list.
      $dupeList = false;

      $nameList = array();  //Keeps track of names we've checked.

      //Loop over all the contacts in the batch.
      $batchContacts = $this->getContacts();
      foreach ($batchContacts as $contact) {
        
          //Find soft dupes with same name as this contact.
          $dupeContacts = $contact->findSoftDupes($this->owner, $nameList);

          //Only a soft dupe if 2 or more contacts are returned.
          if ($dupeContacts && $dupeContacts->count() > 1) {

            //Get the potential dupe list if we don't already have it.
            if (!$dupeList) {
              $dupeList = Model_Mongo_List::findOrCreate(Model_Mongo_List::POTENTIAL_DUPLICATE_LABEL, $this->owner);
            }

            //Add contacts to the dupe list.
            $results = $dupeList->addContactsToList($dupeContacts, $this->createdBy);

          }

      }

    }

    /**
     * Gets the contacts that belong to the batch.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getContacts($conditions = array(), $sort = array(), $fields = array(), $limit = 0, $skip = 0) {
        $conditions = array_merge($conditions, array('batch.$id' => $this->getId()));
        return Model_Mongo_Contact::getContacts($conditions, $sort, $fields, $limit, $skip);
    }

    /**
     * Get a single contact owned by this batch.
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
        $conditions = array_merge($conditions, array('batch.$id' => $this->getId()));
        return Model_Mongo_Contact::getContact($conditions);
    }

    /**
     * Convenience method to build all contacts belonging to this owner
     *
     * @return void
     * @autohor Sam Ramsay
     **/
    public function buildBatch(){
      $contacts = Model_Mongo_Contact::getContacts(array('owner.$id' => $this->owner->getId()));
      
      foreach ($contacts as $contact){
        $this->AddExistingContact($contact);
      }

    }
}