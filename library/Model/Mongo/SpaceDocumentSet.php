<?php
/**
 * User has access to multiple spaces. This class is for that DocumentSet
 * to provide useful helper methods.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_SpaceDocumentSet extends Shanty_Mongo_DocumentSet
{

    /**
     * Gets a space by the url key
     *
     * @return void
     * @author Tom Holder
     **/
    public function getSpaceByUrlKey($urlKey = false)
    {

        for($i= 0; $i < count($this->_cleanData); $i++) {
            $space = $this->getProperty($i)->space;
            if (isset($space) && $space->urlKey == $urlKey) {
                return $space;
            }
        }

        return false;

    }

    /**
     * Gets a space by id
     *
     * @return void
     * @author Tom Holder
     **/
    public function getSpaceById($id)
    {

        if (is_string($id)) {
            $id = new MongoId($id);
        }

        for($i= 0; $i < count($this->_cleanData); $i++) {
            $space = $this->getProperty($i)->space;
            if ($space && $space->getId() == $id) {
                return $space;
            }
        }

        return false;

    }

    /**
     * Gets user's private space
     *
     * @return void
     * @author Tom Holder
     **/
    public function getPrivate()
    {
        for($i= 0; $i < count($this->_cleanData); $i++) {
            $space = $this->getProperty($i)->space;
            if ($space->private) {
                return $space;
            }
        }

        return false;
    }

    /**
     * Outputs ordered list of spaces user has access to. If selected space is provided,
     * this space will be popped to the top.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getAll($exclude = false)
    {

        if($exclude && get_class($exclude) != 'Model_Mongo_Space') {
            $exclude = false;
        }

        $output = array();
        for($i= 0; $i < count($this->_cleanData); $i++) {
            $space = $this->getProperty($i)->space;

            if ($space != $exclude && !is_null($space) && !is_null($space->getId())) {
                $output[$space->sort->spaceName] = $space;
            }
        }
        ksort($output);
        return $output;
    }

    /**
     * Outputs spaces created by the passed in user.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getCreatedBy(Model_Mongo_User $user, $negate = false, $excludeSpace = false)
    {

        $spaces = $this->getAll($excludeSpace);

        $output = array();
        foreach ($spaces as $space) {
            //Return everything but the ones created by user.
            if ($negate) {
                if (!is_null($space) && 
                    isset($space->createdBy) && 
                    $space->createdBy->getId()->__toString() != $user->getId()->__toString()) {
                    $output[$space->urlKey] = $space;
                }
            } else {
                if (!is_null($space) && 
                    isset($space->createdBy) && 
                    $space->createdBy->getId()->__toString() == $user->getId()->__toString()) {
                    $output[$space->urlKey] = $space;
                }
            }
        }

        return $output;
    }

    /**
     * Gets shared spaces or negates
     *
     * @return void
     * @author Tom Holder
     **/
    public function getSharedSpaces($negate = false, $excludeSpace = false)
    {

        $spaces = $this->getAll($excludeSpace);

        $output = array();
        foreach ($spaces as $space) {
            //Return everything but the ones created by user.
            if ($negate) {
                if (!is_null($space) && 
                    isset($space->createdBy) && 
                    $space->TeamMembers()->count() < 2) {
                    $output[$space->sort->spaceName] = $space;
                }
            } else {
                if (!is_null($space) && 
                    isset($space->createdBy) && 
                    $space->TeamMembers()->count() >= 2) {
                    $output[$space->sort->spaceName] = $space;
                }
            }
        }

        ksort($output);

        return $output;
    }

    public function getArrayOfIds() {

        $output = array();
        for($i= 0; $i < count($this->_cleanData); $i++) {
            $output[] = $this->getProperty($i)->space->getId();
        }

        return $output;

    }

}