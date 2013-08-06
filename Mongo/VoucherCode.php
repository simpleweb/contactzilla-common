<?php
/**
 * Represents a voucher code for an account.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2013
 */
class Model_Mongo_VoucherCode extends Model_Mongo_Base
{
    
    protected static $_collection = 'voucherCode';
    
    protected static $_requirements = array(
        'code' => array('Required'),                
        'quantity' => array('Required'),
        'remaining' => array('Required'),
        'monthsFree' => array('Required')
    );

    protected function preInsert()
    {   
        $this->code = mb_strtolower($this->code, 'UTF-8');
        $this->remaining = $this->quantity;
        $this->createdAt = new MongoDate();
    }

    /**
    * Returns an array of code details, if it's valid, months free and the date first payment will be.
    **/
    public static function getCodeDetails($code) {

        $result = array();
        $result['voucher'] = mb_strtolower($code,'UTF-8');
        $code = Model_Mongo_VoucherCode::one(array('code' => $result['voucher']));

        if ($code) {

            $firstBill = new Zend_Date();
            $firstBill->addMonth($code->monthsFree);
            $firstBill->setHour(0);
            $firstBill->setMinute(0);
            $firstBill->setSecond(0);

            $result['voucherExpired'] = $code->remaining <= 0;
            $result['voucherValid'] = true;
            $result['voucherMonthsFree'] = $code->monthsFree;
            $result['date'] = $firstBill->toString('EEEE, d MMMM yyyy');

        } else {
            $result['voucherValid'] = false;
            $result['voucherMonthsFree'] = 1;
            $firstBill = new Zend_Date();
            $firstBill->addMonth(1);
            $firstBill->setHour(0);
            $firstBill->setMinute(0);
            $firstBill->setSecond(0);
            $result['date'] = $firstBill->toString('EEEE, d MMMM yyyy');
        }

        return $result;
    }
}