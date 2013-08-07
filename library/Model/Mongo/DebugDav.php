<?php
/**
 * This is for debugging card dav connections, logs vcard data.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_DebugDav extends Model_Mongo_Base
{

    protected static $_collection = 'debugDav';

    protected static $_requirements = array(
        'user' => array('Document:Model_Mongo_User', 'AsReference'),
        'card' => 'Required',
        'createdAt' => 'Required'
    );

    protected function preInsert() {
        $this->createdAt = new MongoDate();
    }

}