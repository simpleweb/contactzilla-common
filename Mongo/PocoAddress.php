<?php
/**
 * Represents a single poco address
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_PocoAddress extends Model_Mongo_PocoPluralDocument
{

    public function formatted($delimiter = '<br />', $includePostalCode = true, $includeCountry = true)
    {
        $address = array();

        if(array_key_exists('streetAddress',$this->_cleanData)) {
            $address = explode("\n",$this->_cleanData['streetAddress']);
        }

        if(array_key_exists('locality',$this->_cleanData)) {
            $address[] = $this->_cleanData['locality'];
        }

        if(array_key_exists('region',$this->_cleanData)) {
            $address[] = $this->_cleanData['region'];
        }

        if($includePostalCode && array_key_exists('postalCode',$this->_cleanData)) {
            $address[] = $this->_cleanData['postalCode'];
        }

        if($includeCountry && array_key_exists('country',$this->_cleanData)) {
            $address[] = $this->_cleanData['country'];
        }

        return implode($delimiter, $address);

    }

    /**
    * Returns an array of Key, Value, Params for vcard.
    * Normal vcard items will only have 1 item in array. More complex ones or ones with custom types will have multiple elements.
    **/
    public function getVcard($key, $value = false, $params = array()) {

        $parts = array('', ''); //Vcard has two empty containers on addresses at beginning.
        $parts[] = Contactzilla_Utility_Functions::removeVcardEscaping($this->streetAddress);
        $parts[] = Contactzilla_Utility_Functions::removeVcardEscaping($this->locality);
        $parts[] = Contactzilla_Utility_Functions::removeVcardEscaping($this->region);
        $parts[] = Contactzilla_Utility_Functions::removeVcardEscaping($this->postalCode);
        $parts[] = Contactzilla_Utility_Functions::removeVcardEscaping($this->country);

        $addressValue = str_ireplace("\r", "", implode($parts, ';'));

        return parent::getVcard($key, $addressValue, $params);

    }

}
