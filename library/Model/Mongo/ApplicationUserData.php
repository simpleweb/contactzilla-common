<?php
/**
 * Application installs can have user data logged against them. This takes care of this.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_ApplicationUserData extends Model_Mongo_Base {
    
    protected static $_collection = 'applicationUserData';
	
	protected static $_requirements = array(
        'applicationInstall' => array('Required'),
        'user' => array('Required'),
        'key' => array('Required'),
        'value' => array('Required')
    );
    
}