<?php
/**
 * Applications can be assigned to one or more categories. This is for the 
 * purposes of the app store.
 * 
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_ApplicationCategory extends Model_Mongo_Base {

    protected static $_collection = 'applicationCategory';

    protected static $_requirements = array(
        'categoryName' => array('Required'),
        'parentCategory' => array('Document:Model_Mongo_ApplicationCategory', 'AsReference')
    );

    /**
     * Returns a simple key/value array for select lists.
     *
     * @return void
     * @author Tom Holder
     **/
    public static function GetKeyValueArray() {
        $cats = Model_Mongo_ApplicationCategory::all()->sort(array('categoryName' => 1));
        $output = array();
        foreach($cats as $cat) {
            $output[$cat->getId()->__toString()] = $cat ->categoryName;
        }
        return $output;
    }

    /**
     * Returns applications for category. Deals with psedo category for featured apps.
     *
     * @return void
     * @author Tom Holder
     **/
    public function Applications() {
        //Returns applications
        if(strcasecmp('Featured', $this->categoryName) == 0) {
            return Model_Mongo_Application::all(array('featured' => true));
        } else {
            return Model_Mongo_Application::all(array('category.$id' => $this->getId()));
        }

    }
}