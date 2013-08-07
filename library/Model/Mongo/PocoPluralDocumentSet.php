<?php
/**
 * Represents functions for a collection of documents in a POCO plural group.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_PocoPluralDocumentSet extends Shanty_Mongo_DocumentSet
{

    private $_primary = false;

    /**
     * Returns primary item from a plural collection.
     *
     * @return void
     * @author Tom Holder
     **/
    public function GetPrimary() {

        if ($this->_primary) { return $this->_primary; }

        $backupPrimary = false;

        for($i= 0; $i < count($this->_cleanData); $i++) {

            if ($i == 0) {
                $backupPrimary = $this->getProperty($i);
            }

            if(array_key_exists('primary',$this->_cleanData[$i])) {
                $this->_primary = $this->getProperty($i);
            }
        }

        if (!$this->_primary && $backupPrimary) {
            $this->_primary = $backupPrimary;
        }
        
        if (!$this->_primary) {
            $this->_primary = new Model_Mongo_PocoPluralDocument(array('value' => ''));
        }

        return $this->_primary;

    }

    /**
     * Return array of all records, pops primary to top.
     * 
     * @param ensureNeverEmpty if set to true, an empty document will be added if none are returned. Useful for form output.
     * @return void
     * @author Tom Holder
     **/
    public function GetAll($ensureNeverEmpty = false) {
        $results = $this->GetByType();
        if($ensureNeverEmpty && count($results) == 0) {
            $results[] = new Model_Mongo_PocoPluralDocument();
        }
        return $results;
    }

    /**
     * Return array of records matching type(s), pops primary to top.
     *
     * @return void
     * @author Tom Holder
     **/
    public function GetByType($types=false, $ensureNeverEmpty = false) {

        if (is_string($types)) {
            $types = explode(',', $types);
        }

        $data = array();
        for($i= 0; $i < count($this->_cleanData); $i++) {

            if (!$types || 
                (
                    array_key_exists('type',$this->_cleanData[$i]) && 
                    count($this->needlesInHaystack($types, explode(';', $this->_cleanData[$i]['type']))) > 0
                )
            ) {

                //if primary put at beginning
                if (array_key_exists('primary',$this->_cleanData[$i])) {
                    array_unshift($data, $this->getProperty($i));
                } else {
                    array_push($data, $this->getProperty($i));
                }
            }
        }

        if($ensureNeverEmpty && count($data) == 0) {
            $data[] = new Model_Mongo_PocoPluralDocument();
        }

        return $data;

    }

    /**
     * Return array of records excluding specified type(s), pops primary to top.
     *
     * @return void
     * @author Tom Holder
     **/
    public function GetExcludingTypes($types, $ensureNeverEmpty = false) {

        if (is_string($types)) {
            $types = explode(',', $types);
        }

        $data = array();
        for($i= 0; $i < count($this->_cleanData); $i++) {
            //is there  type and is it one we're looking for?
            if (
                    !array_key_exists('type',$this->_cleanData[$i]) || 
                    count($this->needlesInHaystack($types, explode(';', $this->_cleanData[$i]['type']))) == 0
                ) {
                //if primary put at beginning
                if (array_key_exists('primary',$this->_cleanData[$i])) {
                    array_unshift($data, $this->getProperty($i));
                } else {
                    array_push($data, $this->getProperty($i));
                }
            }
        }

        if($ensureNeverEmpty && count($data) == 0) {
            $data[] = new Model_Mongo_PocoPluralDocument();
        }

        return $data;

    }

    private function needlesInHaystack($needles, $haystack, $insensitive = true) {

        if ($insensitive) {
            $needles = array_map('strtolower', $needles);
            $haystack = array_map('strtolower', $haystack);
        }

        $results = array();

        foreach ($needles as $needle) {
            if (in_array($needle, $haystack)) {
                $results[] = $needle;
            }
        }

        return $results;

    }

}