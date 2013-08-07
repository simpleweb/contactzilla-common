<?php
/**
 * Interface that a class can inherit meaning it has security check.
 * 
 * @package Models
 * @subpackage Interfaces
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
interface Model_Mongo_Interfaces_Secured {
    public function HasAccess(Model_Mongo_User $user);
}