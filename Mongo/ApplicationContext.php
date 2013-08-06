<?php
/**
 * Applications can be assigned to one or more categories. This is for the 
 * purposes of the app store.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
abstract class Model_Mongo_ApplicationContext extends Model_Mongo_Base
{
    
    /**
    * Installs an app for this user.
    **/ 
    public function InstallApp(Model_Mongo_Application $app)
    {
        
        //If already installed, return false.
        if ($this->GetApplicationInstall($app)) {
            return false;
        }
        
        //Install the plugin
        $ai = new Model_Mongo_ApplicationInstall();
        $ai->application = $app;
        $ai->user = $this;
        $ai->save();
        
        //There is no billing for user apps.
        //Only insert paid apps in to active billing row.
        if ($app->monthlyPricePerInstall > 0) {
            $this->owner->InsertAppBilling($this, $app);
        }
        
        return true;
        
    }
    
    /**
    * Uninstalls an app from the user's account.
    **/
    public function UninstallApp(Model_Mongo_Application $app)
    {
        
        $appInstall = $this->GetApplicationInstall($app);
        
        if ($appInstall) {
            $appInstall->delete();
            return true;
        } else {
            return false;
        }
          
    }
    
}