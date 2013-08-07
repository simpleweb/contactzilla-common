<?php
/**
 * Represents functions for a collection of photos in a POCO plural group.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_PocoPhotosDocumentSet extends Model_Mongo_PocoPluralDocumentSet
{

    /**
     * Return array of all records, if empty, puts in default photo
     *
     * @return void
     * @author Tom Holder
     **/
    public function GetAll($ensureNeverEmpty = false) {
        $photos = parent::GetByType();

        if($ensureNeverEmpty && count($results) == 0) {
            $photos[] = $this->getDefaultPhoto();
        }

        return $photos;
    }

    /**
     * Returns primary item from photos, if nothing returned, adds default.
     *
     * @return void
     * @author Tom Holder
     **/
    public function GetPrimary() {

        $primary = parent::GetPrimary();

        if (!$primary) {
            $primary = $this->getDefaultPhoto();
        }

        return $primary;

    }

    private function getDefaultPhoto() {
        $defaultImage = array('value' => '/images/default_profile.png', 'type' => 'home', 'primary' => true);
        return new Shanty_Mongo_Document($defaultImage);
    }
}