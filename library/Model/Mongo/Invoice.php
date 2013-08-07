<?php
/**
 * We like this model! Let's bill some people some wonga!
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Invoice extends Model_Mongo_Base {

    protected static $_collection = 'invoice';

    protected static $_requirements = array(
        'user' => array('Document:Model_Mongo_User', 'AsReference', 'Required'),
        'billedTo' => array('Document:Model_Mongo_BillingContact'),
        'subTotal' => array('Validator:Float', 'Required'),
        'taxRate' => array('Validator:Float', 'Required'),
        'total' => array('Validator:Float', 'Required'),
        'balance' => array('Validator:Float', 'Required'),
        'currencyCode' => array('Required'),
        'currencySymbol' => array('Required'),
        'rows' => array('DocumentSet'),
        'rows.$.label' => array('Required'),
        'rows.$.amount' => array('Validator:Float', 'Required')
    );

    public function paid() {
        return $this->balance == 0;
    }

    protected function preInsert() {
        //Fudge for auto id. Nasty.
        $total = Model_Mongo_Invoice::all()->count();
        $this->number = 1000 + $total;
    }

    protected function preSave() {

        if($this->isNewDocument()) {
            $this->createdAt = new MongoDate(time());
        }

    }

}