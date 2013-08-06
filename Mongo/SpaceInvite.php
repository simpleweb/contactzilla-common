<?php
/**
 * Represents an invite to a space.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_SpaceInvite extends Model_Mongo_Base {

    protected static $_requirements = array(
        'space' => array('Document:Model_Mongo_Space', 'AsReference'),
        'addedByUser' => array('Document:Model_Mongo_User', 'AsReference')
    );


    protected function preSave() {

        if($this->isNewDocument()) {
            $this->createdAt = new MongoDate(time());
        }

    }

}