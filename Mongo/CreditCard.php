<?php
/**
 * Represents a payment plan.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2013
 */
class Model_Mongo_CreditCard extends Model_Mongo_Base
{
    
    protected static $_collection = 'creditCard';
    
    protected static $_requirements = array(
    	'user' => array('Document:Model_Mongo_User', 'AsReference', 'Required'),
        'ref' => array('Required'),                
        'expiry' => array('Required'),
        'realExRef' => array('Required'),
        'cvn' => array('Required')
    );

}