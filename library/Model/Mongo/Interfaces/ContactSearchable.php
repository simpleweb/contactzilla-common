<?php
/**
 * Interface that means an object can return contacts.
 * 
 * @package Models
 * @subpackage Interfaces
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
interface Model_Mongo_Interfaces_ContactSearchable {
    public function getContact($conditions);
    public function getContacts($conditions = array(), $sort = array(), $fields = array(), $limit = 0, $skip = 0);
}