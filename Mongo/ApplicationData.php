<?php
/**
 * Application installs can have data logged against them. This takes care of this.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_ApplicationData extends Model_Mongo_Base {

    protected static $_collection = 'applicationData';

    protected static $_requirements = array(
        'applicationInstall' => array('MongoId', 'Required'),
        'contact' => array('MongoId'),
        'user' => array('MongoId'),
        'createdAt' => array('Required')
    );

    protected function preInsert() {
        $this->createdAt = new MongoDate();
    }

    /**
     * Gets data in format we want for API
     *
     * @return void
     * @author Tom Holder
     **/
    public static function GetData(array $query = array(), array $fields = array())
    {
        $results = Model_Mongo_ApplicationData::all($query, $fields);
        $results = $results->export();

        //Going to remove some cruft. Probably want a better way to do this in future.
        $output = array();

        foreach($results as $result) {
            unset($result['applicationInstall']);
            unset($result['_type']);

            foreach($result as $k => $v) {
                if(is_object($v)) {

                    switch(get_class($v)) {

                        case 'MongoId':
                            $result[$k] = $v->__toString();
                            break;

                        case 'MongoDate':
                            $result[$k] = $v->sec;
                            break;
                    }

                }
            }

            $output[] = $result;
        }

        return $output;

    }

}