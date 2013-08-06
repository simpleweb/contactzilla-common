<?php
/**
 * Represents a user account. User logins exist once per actual account.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_OAuthProfile extends Model_Mongo_Base
{
	protected static $_collection = 'oauthProfile';

	protected static $_requirements = array(
        'user' => array('Document:Model_Mongo_User', 'AsReference')
    );

	protected function preSave() {
      $this->providerId = trim(mb_strtolower($this->providerId,'UTF-8'));
    }

    /**
    * Gets an unregistered profile
    **/
    public static function getUnregisteredProfile($providerId, $identifier) {
    	$providerId = trim(mb_strtolower($providerId,'UTF-8'));

    	return Model_Mongo_OAuthProfile::one(
    		array(
    			'providerId' => $providerId,
    			'identifier' => $identifier,
    			'user' => array('$exists' => false)
    		)
    	);
    }

    /**
    * Gets an registered profile
    **/
    public static function getRegisteredProfile($providerId, $identifier) {
    	$providerId = trim(mb_strtolower($providerId,'UTF-8'));

    	return Model_Mongo_OAuthProfile::one(
    		array(
    			'providerId' => $providerId,
    			'identifier' => $identifier,
    			'user' => array('$exists' => true)
    		)
    	);
    }

    /**
    * attaches user to profile
    **/
    public static function associateProfile($user, $providerId, $identifier) {
    	$profile = Model_Mongo_OAuthProfile::getUnregisteredProfile($providerId, $identifier);

    	if ($profile) {
    		$profile->user = $user;
    		$profile->save();
    	}
    }
}