<?php
/**
 * Represents a space/access combo.
 *
 * TODO: This should probably be where user/space settings go.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_SpaceAccess extends Model_Mongo_Base {

    protected static $_requirements = array(
        'space' => array('Document:Model_Mongo_Space', 'AsReference')
    );

}