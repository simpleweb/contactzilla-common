<?php
/**
 * Interface that represents a space.
 * 
 * @package Models
 * @subpackage Interfaces
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
interface Model_Mongo_Interfaces_Space {
    public function GetInstalledApplications();
    public function GetInstalledApplicationsWithHook($hook);
    public function GetApplicationInstall(Model_Mongo_Application $app);
}