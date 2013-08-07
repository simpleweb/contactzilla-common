<?php
/**
 * Represents a payment plan.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2013
 */
class Model_Mongo_Plan extends Model_Mongo_Base
{
    
    protected static $_collection = 'plan';
    
    protected static $_requirements = array(
        'label' => array('Required'),                
        'monthlyCost' => array('Required'),
        'addressBooks' => array('Required'),
        'syncing' => array('Required'),
        'users' => array('Required')
    );

    /**
    * Determine if the user is allowed this plan.
    **/
    public function isUserEligible(Model_Mongo_User $user) {

    	$spaces = $user->getOwnedSpaces();

    	if ($spaces->count() > $this->addressBooks) {
            return false;
        }

        foreach ($spaces as $space) {
        	if ($space->memberCount() > $this->users) {
        		return false;
        	}
        }

        return true;

    }

    /**
    * Outputs a formatted cost and apends + VAT if they are vatable.
    **/
    public function formattedCost(Model_Mongo_User $user, $zeroCost = 'FREE') {

        if ($this->monthlyCost == 0) {
            return $zeroCost;
        }
        
        $currency = $user->billingContact->currencySymbol();

        if ($user->billingContact && $user->billingContact->vatRate() > 0) {
            return $currency.number_format($this->monthlyCost,2). ' + VAT';
        } else {
            return $currency.number_format($this->monthlyCost,2);
        }
    }

    public function __toString() {
        return $this->label ? $this->label : '';
    }
}