<?php
/**
 * Every application has to be installed against a space. This represents an app/space install.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_ApplicationInstall extends Model_Mongo_Base Implements Model_Mongo_Interfaces_Secured
{

    protected static $_collection = 'applicationInstall';

    protected static $_requirements = array(
        'application' => array('Document:Model_Mongo_Application', 'AsReference'),
        'space' => array('Document:Model_Mongo_Space', 'AsReference', 'Required'),
        'createdAt' => 'Required',
        'secret' => 'Required'
    );

    protected function preSave()
    {

        if ($this->isNewDocument()) {
            $this->createdAt = new MongoDate(time());
            $this->secret = Contactzilla_Utility_Encrypt::GetSecretKey();
        }

    }

    public function HomePage()
    {
        return '/';
    }

    /**
     * Determines if the user has access to this application installation.
     * Doesn't mean they are a developer and have access to the app.
     *
     * @return void
     * @author Tom Holder
     **/
    public function HasAccess(Model_Mongo_User $u) {
        //TODO: Erm, might wanna fix this.
        return true;
    }

    /**
     * Returns an application install.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function GetApplicationInstall($id) {

        if (is_string($id)) {
            $id = new MongoId($id);
        }

        return Model_Mongo_ApplicationInstall::find($id);
    }

    /**
     * Uinstalls an application.
     *
     * @return void
     * @author Tom Holder
     **/
    public function Uninstall()
    {
        $this->delete();
    }

    /**
     * Get installed applications.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function GetInstalledApplications($query)
    {
        $apps = Model_Mongo_ApplicationInstall::all($query);

        $output = array();
        foreach($apps as $appInstall) {
            if(is_object($appInstall->application)) {
                $output[] = $appInstall;
            }
        }

        return $output;
    }

    /**
     * Requests remote page from application.
     *
     * @return Zend_Http_Response
     * @author Tom Holder
     **/
    public function RequestPage(
        $hook,
        Model_Mongo_Token $accessToken,
        $pageName,
        $get = array(),
        $post = array(),
        $headers = array(),
        $timeout = 3600
        )
    {

        //Make sure a request token hasn't been passed in.
        if ($accessToken->type !== 'access') {
            throw new Contactzilla_Exceptions_Security(Contactzilla_Exceptions_Security::INVALID_OAUTH_TOKEN);
        }
        
        //Build the request parameters.

        $reqParams = $this->GetRequestParameters($hook, $accessToken, $pageName, $get, $post, $headers);

        $client = new Zend_Http_Client($reqParams['url']);

        $client->setConfig(array('timeout' => $timeout, 'maxredirects' => 0));
        $client->setCookieJar();

        $client->setHeaders($reqParams['headers']);
        $client->setParameterGet($reqParams['get']);
        
        //Deal with upload files to app.
        if (!empty($_FILES)) {
          foreach($_FILES as $key => $file) {
             $client->setFileUpload($file['name'], $key, file_get_contents($file['tmp_name']), $file['type']);
          }
        }
        
        if (!empty($post)) {
            $client->setParameterPost($reqParams['post']);
            $client->setMethod(Zend_Http_Client::POST);
        } else {
            $client->setMethod(Zend_Http_Client::GET);
        }

        //Request the remote page.
        return $client->request();

    }

    /**
     * Returns array of request parameters, url, get, post, headers
     *
     * @return void
     * @author Tom Holder
     **/
    public function GetRequestParameters(
        $hook,
        Model_Mongo_Token $accessToken,
        $pageName,
        $get = array(),
        $post = array(),
        $headers = array()
        )
    {
            $output = array();
            $output['url'] = $this->application->hook->$hook.$pageName;

            //Sort out headers.
            if (!array_key_exists('Cache-Control', $headers)) {
                $headers['Cache-Control'] = 'no-cache';
            }

            if (!array_key_exists('Connection', $headers)) {
                $headers['Connection'] = 'Keep-Alive';
            }

            //Add application context.
            $get['oauth_token'] = $accessToken->token;
            $get['oauth_token_secret'] = $accessToken->tokenSecret;
            $requestUri = $_SERVER['REQUEST_URI'];
            
            $trimPageName = $pageName;
            if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                
                $trimPageName = $pageName.'?'.$_SERVER['QUERY_STRING'];
            }
            
            //Trim off page name.
            $requestUri = Contactzilla_Utility_Functions::rightTrimString($requestUri, $trimPageName);
            
            $get['appContextUrl'] = rtrim('https://'.$_SERVER['HTTP_HOST'].$requestUri);

            $output['get'] = $get;
            $output['post'] = $post;
            $output['headers'] = $headers;
            
            return $output;
    }

    /**
     * Output of monthly cost.
     *
     * @return void
     * @author Tom Holder
     **/
    public function MonthlyCost($noCost = 0) {

        if(isset($this->monthlyPricePerInstall)) {
            return $this->monthlyPricePerInstall;
        } else {
            return $noCost;
        }
    }



}