<?php
/**
 * DOCUMENT
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_PocoAccountsDocumentSet extends Model_Mongo_PocoPluralDocumentSet
{

    /**
     * returns accounts matching the given domain.
     *
     * @return void
     * @author Tom Holder
     **/
    public function GetAccountsByDomain($domain)
    {

        $accounts = array();
        for($i=0; $i < count($this->_cleanData); $i++) {
            $account = $this->_cleanData[$i];
            if($account['domain'] == $domain) {
               $accounts[] = $this->getProperty($i);
            }
        }

        return $accounts;
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
            $results[] = new Model_Mongo_PocoAccount();
        }
        return $results;
    }

}