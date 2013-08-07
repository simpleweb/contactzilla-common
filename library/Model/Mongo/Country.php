<?php
/**
 * There are lots of countries! Some are big, some are small.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Country extends Model_Mongo_Base
{
    
    protected static $_collection = 'country';
    
    /**
     * Returns country list in a way that's convinient for select lists key/value.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function DataForSelect()
    {
        $data = array();
        $countries = Model_Mongo_Country::all()->sort(array('orderID' => -1, 'countryName' => 1));
        
        foreach ($countries as $country) {
            $data[$country->countryCode] = $country->countryNamePrintable;
        }
        
        return $data;
    }
    
    /**
     * Returns a single country by the ISO 3 character code.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function CountryFromIso($code) 
    {
        return Model_Mongo_Country::one(array("iso3" => strtoupper($code)));
    }
    
    /**
     * Returns a single country by the country code.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function CountryFromCode($code) 
    {
        return Model_Mongo_Country::one(array("countryCode" => strtoupper($code)));
    }
    
    /**
     * Returns a single country by the country name.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function CountryFromName($name) 
    {
        return Model_Mongo_Country::one(array("countryName" => strtoupper($name)));
    }
    
    /**
     * Works on an unknown identifier could be name, code, iso and tries to find country.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function CountryBestGuess($identifier) 
    {
        $identifierLen = strlen($identifier);
        
        if($identifierLen > 3) {
            return Model_Mongo_Country::CountryFromName($identifier);
        } elseif($identifierLen == 3) {
            return Model_Mongo_Country::CountryFromIso($identifier);
        } elseif($identifierLen == 2) {
            return Model_Mongo_Country::CountryFromCode($identifier);
        } else {
            return false;
        }
    }
}