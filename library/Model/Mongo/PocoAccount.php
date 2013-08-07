<?php
/**
 * Represents a single poco account
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2012
 **/
class Model_Mongo_PocoAccount extends Model_Mongo_PocoPluralDocument
{

    /**
     * Returns icon for account
     *
     * @return void
     * @author Tom Holder
     **/
    public function Icon($tag = false)
    {

        $missing = 'missing.png';
        
        $icon = '/images/accounts/missing.png';
        $label = 'Unknown social account';

        $socialUtil = new Contactzilla_Utility_Social();
        $type = $socialUtil->gettype($this->type);
        if ($type) {
            $icon = '/images/accounts/'.$this->type.'.png';
            $label = $type['name'];
        }
        
        if ($tag) {
            return '<img src="'.$icon.'" alt="'.$label.'">';
        } else {
            return $icon;
        }
    }
    
    /**
     * Returns username for account, falling back to userid.
     *
     * @return void
     * @author Tom Holder
     **/
    public function Username() {
        
        $username = '';

        if (isset($this->username) && !empty($this->username)) {
            $username = $this->username;
        } elseif (isset($this->url) && !empty($this->url)) {
            $username = $this->url;
        }
        
        return $username;
    }
    
    /**
     * Returns profile link for account.
     *
     * @return void
     * @author Tom Holder
     **/
    public function ProfileLink($includeIcon = false, $content = '')
    {
        
        //Should thumbnail be included.
        if ($includeIcon) {
            $content = $this->Icon(true) . $content;
        }

        $networkName = $this->type;
        $socialUtil = new Contactzilla_Utility_Social();
        $type = $socialUtil->gettype($this->type);

        if ($type) {
            $networkName = $type['name'];
        }
        
        if (isset($this->url) && !empty($this->url)) {
            return '<a href="'.$this->url.'" class="accountProfileLink" title="Link to ' . $networkName . ' profile of '. $this->Username(). '" target="_blank">'.$content.'</a>';
        } else {
            return $content;
        }
        
    }

    /**
    * Returns an array of Key, Value, Params for vcard.
    * Normal vcard items will only have 1 item in array. More complex ones or ones with custom types will have multiple elements.
    **/
    public function getVcard($key, $value = false, $params = array()) {

        $items = array();
        $type = isset($this->type) ? $this->type : '';
        $value = isset($this->url) && !empty($this->url) ? $this->url : 'x-apple:'.$this->username;
        $type = str_ireplace('X-ABLabel:', '', $type);

        $items[] = array(
            array(
                'key' => $key,
                'value' => $value,
                'params' => array_merge($params, array('type' => explode(';', $type)))
            )
        );

        return $items;

    }

    /**
     * Outputs username when toString is called.
     *
     * @return void
     * @author Tom Holder
     **/
    public function __toString()
    {
        return $this->Username();
    }

}