<?php
/**
 * Represents a poco document with a value.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_PocoDocument extends Model_Mongo_Base {

   public function __toString() {
       $value = $this->getProperty('value');
       
       if (empty($value)) { return ''; }
       
       return $value;
   }

}