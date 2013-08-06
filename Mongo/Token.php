<?php
/**
 * For oAuth. Generates request/access tokens.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_Token extends Model_Mongo_Base
{

    protected static $_collection = 'token';

    protected static $_requirements = array(
        //ID for the consumer.
        'consumerKey' => array('Required', 'Validator:MongoId'),
        //Indicates what type of consumer it is - should always be application, just forward thinking
        'consumerType' => array('Required'),
        'token' => array('Required'),
        'tokenSecret' => array('Required'),
        'user' => array('Document:Model_Mongo_User', 'AsReference'),
        'applicationInstall' => array('Document:Model_Mongo_ApplicationInstall', 'AsReference'),
        'createdAt' => array('Required')
    );

    protected function preSave()
    {

        if ($this->isNewDocument()) {
            $this->createdAt = new MongoDate(time());
            $this->token = Contactzilla_Utility_Encrypt::GetSecretKey();
            $this->tokenSecret = Contactzilla_Utility_Encrypt::GetSecretKey();
        }

    }

    /**
     * Generates a new request token.
     *
     * @return Model_Mongo_Token
     * @author Tom Holder
     **/
    public static function GenerateRequestToken(MongoId $consumerKey,
        $callback,
        $consumerType = 'application')
    {

        //Generate new request token.
        $data = array(
            'consumerKey' => $consumerKey,
            'consumerType' => $consumerType,
            'callback' => $callback,
            'type' => 'request'
        );
        $t = new Model_Mongo_Token($data);
        $t->save();
        return $t;
    }

    /**
     * Gets a request token (not to be confused with possible access token.)
     *
     * @return Model_Mongo_Token
     * @author Tom Holder
     **/
    public static function GetRequestToken($token)
    {
        return Model_Mongo_Token::one(
            array(
                'token' => $token,
                'type' => 'request'
            )
        );
    }

    /**
     * Sets verifier for request token.
     *
     * @return Model_Mongo_Token
     * @author Tom Holder
     **/
    public static function SetRequestTokenVerifier(Model_Mongo_Token $t, $verifier)
    {
        $t->verifier = $verifier;
        $t->save();
    }

    /**
     * Gets an access token for a specific user/consumer (not to be confused with possible request token.)
     *
     * @return Model_Mongo_Token
     * @author Tom Holder
     **/
    public static function GetAccessToken($token)
    {
        return Model_Mongo_Token::one(
            array(
                'token' => $token,
                'type' => 'access'
            )
        );
    }

    /**
     * Gets an access token for a specific user/consumer (not to be confused with possible request token.)
     *
     * @return Model_Mongo_Token
     * @author Tom Holder
     **/
    public static function GetAccessTokenForUser(
        Model_Mongo_User $user,
        MongoId $consumerKey,
        Model_Mongo_ApplicationInstall $appInstall = null)
    {
        $query = array(
            'user.$id' => $user->getId(),
            'consumerKey' => $consumerKey,
            'type' => 'access'
        );

        if($appInstall) {
            $query['applicationInstall.$id'] = $appInstall->getId();
        }

        return Model_Mongo_Token::one($query);
    }

    /**
     * Generates a new access token.
     *
     * @return Model_Mongo_Token
     * @author Tom Holder
     **/
    public static function GenerateAccessToken(Model_Mongo_User $user,
        MongoId $consumerKey,
        Model_Mongo_ApplicationInstall $appInstall = null,
        $consumerType = 'application')
    {

        Model_Mongo_Token::DeleteUserConsumerAccessTokens($user, $consumerKey, $appInstall);

        //Generate new access token.
        $t = new Model_Mongo_Token();
        $t->consumerKey = $consumerKey;
        $t->consumerType = $consumerType;
        $t->user = $user;
        $t->type = 'access';

        if ($appInstall) {
            $t->applicationInstall = $appInstall;
        }

        $t->save();

        return $t;
    }

    /**
     * Gets an access token or if it doesn't exist generates one.
     *
     * @return Model_Mongo_Token
     * @author Tom Holder
     **/
    public static function GetAccessTokenForUserOrGenerate(Model_Mongo_User $user, Model_Mongo_ApplicationInstall $appInstall)
    {

        $t = Model_Mongo_Token::GetAccessTokenForUser($user, $appInstall->application->getId(), $appInstall);

        if (!$t) {
            $t = Model_Mongo_Token::GenerateAccessToken($user, $appInstall->application->getId(), $appInstall);
        }

        return $t;

    }

    /**
     * Removes all access tokens for a user/consumer
     *
     * @return void
     * @author Tom Holder
     **/
    public static function DeleteUserConsumerAccessTokens(
        Model_Mongo_User $user,
        MongoId $consumerKey,
        Model_Mongo_ApplicationInstall $appInstall = null)
    {

        $query = array(
            'user.$id' => $user->getId(),
            'consumerKey' => $consumerKey
        );

        if ($appInstall) {
            $query['applicationInstall.$id'] = $appInstall->getId();
        }

        //Delete any existing token.
        Model_Mongo_Token::remove($query);

    }

    /**
     * undocumented function
     *
     * @return void
     * @author Tom Holder
     **/
    public static function GetConsumer(Model_Mongo_Token $token)
    {
        return Model_Mongo_Application::find($token->consumerKey);
    }
}