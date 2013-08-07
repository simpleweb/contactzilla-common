<?php
/**
 * Represents an application. Contains list of developers which determines who is working on the app.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Application extends Model_Mongo_Base Implements Model_Mongo_Interfaces_Secured
{

    protected static $_collection = 'application';

    protected static $_requirements = array(
        'appName' => array('Required'),
        'teaser' => array('Required'),
        'description' => array('Required'),
        'createdBy' => array('Document:Model_Mongo_User', 'AsReference', 'Required'),
        'developers' => array('DocumentSet', 'Required'),
        'developers.$' => array('Document:Model_Mongo_User', 'AsReference'),
        'monthlyPricePerInstall' => array('Validator:Float'),
        'category' => array('DocumentSet', 'Required'),
        'category.$' => array('Document:Model_Mongo_ApplicationCategory', 'AsReference'),
        'type' => array('Required'),
        'default' => array('Filter:Boolean')
    );

    protected function preInsert()
    {
        $this->createdAt = new MongoDate();
        $this->secret = Contactzilla_Utility_Encrypt::GetSecretKey();
    }

    protected function preSave()
    {
        $this->sortAppName = strtolower($this->appName);
    }

    public function HomePage()
    {
        return '';
    }

    public function AssetUrl()
    {
        $config = Zend_Registry::get('config');
        return $config->ssl_proxy."/assetproxy/".$this->getId()->__toString();
    }

    /**
     * Determines if the user is a developer and has access to this application.
     * This is not the same as determining if a user has access to run an application which
     * is done via an app installation instead.
     *
     * @return void
     * @author Tom Holder
     **/
    public function HasAccess(Model_Mongo_User $u) {
        foreach($this->developers as $d) {
            if($d->getId() == $u->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determines if the app can be run in the space. This is down to the app type and the space type.
     *
     * @return void
     * @author Tom Holder
     **/
    public function CanRunInSpace(Model_Mongo_Space $space)
    {
        $typeLookout = 'teamspace';
        if($space->private) {
            $typeLookout = 'private';
        }
        return array_search($typeLookout, $this->type->export()) === false ? false : true;
    }

    /**
    * Returns the number of actual trial days available to the specified space.
    * When an app is first trialed it is inserted against the space->trials so that only one trial can be used.
    * If no trial exists yet they are entitled to max app trial days.
    *
    * @param $forceTrialInsert - if true, and an app is found not to have an active trial, it will be inserted.
    * This is to get around potential issue of data being removed from DB, effectively allowing a trial reset.
    * Only called from manage apps.
    **/
    public function TrialRemaining(Model_Mongo_User $user, $forceTrialInsert = false)
    {

        //Will hold zend date representing the date in the future the trial will end.
        $trialExpires = false;

        //Get existing trial.
        $trial = $user->GetTrialApp($this);

        if ($trial) {
            $trialExpires = new Zend_date($trial->trialExpiresAt->sec);
        } else {

            if (!$this->freeTrial || $this->freeTrial ==0) {
                return 0;
            }

            if ($forceTrialInsert) {
                $user->InsertTrialApp($this);
            }

            $trialExpires = new Zend_Date();
            $trialExpires->setOptions(array('fix_dst' => false)); //This might break after 25th march 2012?
            $trialExpires->addDay($this->freeTrial);
        }

        $today = new Zend_Date();
        $diff = $trialExpires->sub($today)->toValue();
        
        $days = floor($diff/60/60/24);

        return $days;
    }

    /**
     * Installs an application for the given user in to the specified space.
     *
     * @return void
     * @author Tom Holder
     **/
    public function install($label, Model_Mongo_User $user, Model_Mongo_Space $space) {

        $appInstall = new Model_Mongo_ApplicationInstall();
        $appInstall->label = $label;
        $appInstall->application = $this;

        //Only the admin can install against a space
        if($space && !$space->IsAdmin($user)) {
            throw new Contactzilla_Exceptions_Security(Contactzilla_Exceptions_Security::INVALID_SECURITY_LEVEL);
        }

        //Insert price for app install if the app has a price.
        if($this->monthlyPricePerInstall) {
            //Set price
            $appInstall->monthlyPricePerInstall = $this->monthlyPricePerInstall;

            //Insert billing period.
            $trialRemaining = $this->TrialRemaining($user);

            //Set period from date to 1month 1 day in the future and then zero the date on midnight.
            $periodFromDate = new Zend_Date();
            $periodFromDate->addDay($trialRemaining + 1);
            $periodFromDate->setHour(0);
            $periodFromDate->setMinute(0);
            $periodFromDate->setSecond(0);
            $appInstall->periodFrom = new MongoDate($periodFromDate->get());

            $periodToDate = new Zend_Date($periodFromDate->get());
            $periodToDate->addMonth(1);
            $appInstall->periodTo = new MongoDate($periodToDate->get());
        }

        $appInstall->space = $space;

        $appInstall->save();

        $user->insertTrialApp($this);

        return $appInstall;
    }

    public function IsType($type) {
        $appType = $this->type->export();
        return in_array($type, $appType);
    }

    /**
     * Gets all applications developed by a user.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function GetDeveloperApplications(Model_Mongo_User $developer)
    {
        return Model_Mongo_Application::all(
            array(
                'developers.$id' => array(
                    '$in' => array($developer->getId())
                    )
                )
            )->sort(
                array('sortAppName' => 1)
            );
    }
    
    /**
     * Gets all applications flagged as being 'default' applications.
     *
     * @param $sort: Optional sort criteria for the application instances
     * @return Shanty_Mongo_Iterator_Cursor instance
     * @author Dan Stringer
     */
    public static function GetDefaultApplications($sort = array('sortAppName' => 1))
    {
        return Model_Mongo_Application::all(array('default' => true))->sort($sort);
    }

    /**
     * Installs all default apps into the given space for the given user
     *
     * @param $space: Instance of Model_Mongo_Space
     * @param $user: Instance of Model_Mongo_User
     * @return void
     * @author Dan Stringer
     */
    public static function InstallDefaultAppsInSpaceForUser(Model_Mongo_Space $space, Model_Mongo_User $user)
    {
        $apps = self::GetDefaultApplications();
        foreach($apps as $app) {
            $app->Install($app->appName, $user, $space);
        }
    }

}