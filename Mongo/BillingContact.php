<?php
/**
 * Represents a contact in the system. This is the CZ model, not to be confused with the POCO data
 * held within which is tried to be kept POCO compliant.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_BillingContact extends Model_Mongo_Base {


    protected static $_requirements = array();

    /**
    * Returns if this billing contact should pay VAT. 0 means no VAT.
    **/
    public function vatRate() {

        if (!$this->countryCode) {
            return 0;
        }
        
        //http://en.wikipedia.org/wiki/Taxation_of_Digital_Goods#Overview_of_Internet_Taxation_in_the_European_Union
        $country = Model_Mongo_Country::one(array('countryCode' => $this->countryCode));

        if (!$country) {
            throw new Exception("Couldn't find matching country for code " . $this->countryCode);
        }

        if (!$country->chargeVAT) {
            return 0;
        } else {

            //http://www.webmastersdiary.com/2011/12/php-vies-vat-number-validation-european.html
            if ($this->countryCode != 'GB' && $this->VATNo && !empty($this->VATNo)) {
                return 0;
            } else {
                return 20;
            }
        }

    }

    public function currencySymbol() {
        return '$';
    }

    public function currencyCode() {
        return 'USD';
    }

    public function __toString() {
        return $this->forename . ' ' . $this->surname;
    }
    
}
