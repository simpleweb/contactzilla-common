<?php
/**
 * This model is just used for logging deleted accounts.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_DeletedAccount extends Model_Mongo_Base
{

    protected static $_collection = 'deletedAccount';

    protected static $_requirements = array(
        'username' => 'Required',
        'email' => 'Required',
        'reason' => 'Required',
        'createdAt' => 'Required'
    );

    public static function LogDeletedAccount($username, $email, $reason) {

        $metric = new self();
        $metric->username = $username;
        $metric->email = $email;
        $metric->reason = $reason;
        $metric->save();

    }

    protected function preInsert() {
        $this->createdAt = new MongoDate();
    }

}