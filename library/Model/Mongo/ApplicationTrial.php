<?php
/**
 * Apps can be on trial. This determines when the trial was created and when it will expire.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_ApplicationTrial extends Model_Mongo_Base {
    
	protected static $_requirements = array(
        'application' => array('Document:Model_Mongo_Application', 'AsReference', 'Required'),
        'appName' => array('Required'),
        'trialExpiresAt' => array('Required'),
        'createdAt' => array('Required')
    );

}