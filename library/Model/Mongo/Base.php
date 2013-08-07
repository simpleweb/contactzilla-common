<?php
/**
 * Base class all models inherit from.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Base extends Shanty_Mongo_Document {

    protected static $_db = 'contactzilla';

    public static function setDatabase($db) {
        self::$_db = $db;
    }

    /**
     * Increments a state counter for the contact
     *
     * @return void
     * @author Tom Holder
     **/
    protected function incrementState($stateVar, Model_Mongo_User $user = null, $storageField = 'state') {

        if ($user) {
          $stateVar = $user->getId() . '.' . $stateVar;
        }

        $stateVar = $storageField.'.'.$stateVar;

        $this->inc($stateVar);
        $this->save(false, false);
    }

    /**
     * Sets a state variable for the contact
     *
     * @return void
     * @author Tom Holder
     **/
    protected function setState($stateVar, $value, Model_Mongo_User $user = null, $storageField = 'state') {

      if ($this->getState($stateVar, '', $user, $storageField) === $value) {
          return;
      }

      if ($user) {
          $stateVar = $user->getId() . '.' . $stateVar;
      }

      $stateVar = $storageField.'.'.$stateVar;

      //Convert string true and false to bool. False will be unset, no point storing it.
      if (is_string($value) && strcasecmp($value, 'false') == 0) {
        $value = false;
      }

      if (is_string($value) && strcasecmp($value, 'true') == 0) {
        $value = true;
      }

      if (!$value || empty($value)) {
          $this->addOperation('$unset', $stateVar, 1);
      } else {
          $this->addOperation('$set', $stateVar, $value);
      }

      $this->save(false, false);
    }

    /**
    * Sets state, but in bulk according to conditions.
    **/
    protected function setStateBulk(MongoCollection $collection, $conditions, $stateVar, $value, Model_Mongo_User $user = null, $storageField = 'state') {

      // Build the state var
      if ($user) {
          $stateVar = $user->getId() . '.' . $stateVar;
      }
      $stateVar = $storageField.'.'.$stateVar;

      //Convert string true and false to bool. False will be unset, no point storing it.
      if (is_string($value) && strcasecmp($value, 'false') == 0) {
        $value = false;
      }

      if (is_string($value) && strcasecmp($value, 'true') == 0) {
        $value = true;
      }

      if (!$value || empty($value)) {
          $operation = array('$unset' => array($stateVar => ""));
      } else {
          $operation = array('$set' => array($stateVar => $value));
      }
  
      $collection->update(
          $conditions,
          $operation,
          array('multiple' => true)
      );
    }

    /**
     * Returns state variable for user.
     *
     * @return void
     * @author Tom Holder
     **/
    protected function getState($stateVar, $default = '', Model_Mongo_User $user = null, $storageField = 'state')
    {

        if ($user) {
            $stateVar = $user->getId() . '.' . $stateVar;
        }

        $stateVar = $storageField.'.'.$stateVar;

        $userState = $this;
        $array = explode('.', $stateVar);

        for($i = 0; $i < count($array); $i++) {
            if(isset($userState->$array[$i])) {
                $userState = $userState->$array[$i];
            } else {
                $userState = false;
                break;
            }

        }

        if(!$userState) {
            $userState = $default;
        }

        if (is_object($userState) && get_class($userState) == 'Shanty_Mongo_Document') { 
            $userState = $userState->export(); 
        }

        return $userState;

    }

}