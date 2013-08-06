<?php
/**
 * For oAuth. Stores nonces. Pedo Bear. http://farm2.static.flickr.com/1024/5184977943_d2ab948133.jpg
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Nonce extends Model_Mongo_Base
{
    
    protected static $_collection = 'nonce';
    
    protected static $_requirements = array(
        //ID for the consumer.
        'consumerKey' => array('Required', 'Validator:MongoId'),
        //Indicates what type of consumer it is - should always be application, just forward thinking
        'nonce' => array('Required'),                
        'timestamp' => array('Required')
    );

}