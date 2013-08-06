<?php
/**
 * Represents functions for a collection of addresses.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_PocoAddressesDocumentSet extends Model_Mongo_PocoPluralDocumentSet
{

    /**
     * Return array of all records, if empty, puts in default photo
     *
     * @return void
     * @author Tom Holder
     **/
    public function GetAll($ensureNeverEmpty = false) {
        $results = parent::GetAll(false);
        if($ensureNeverEmpty && count($results) == 0) {
            $results[] = new Model_Mongo_PocoAddress();
        }
        return $results;
    }
    
}