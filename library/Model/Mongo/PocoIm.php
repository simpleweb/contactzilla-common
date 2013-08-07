<?php
/**
 * Represents a single poco account
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2012
 **/
class Model_Mongo_PocoIm extends Model_Mongo_PocoPluralDocument
{

	/**
	* VCARD for IMPP uses a strange prefix for the value. Example:
	* 
	* IMPP;X-SERVICE-TYPE=AIM;type=HOME;type=pref:aim:amyaim
	*
	* The aim bit after type=pref: is what we need, but we don't store it so we map it off
	* the stored service.
	**/
	private function getValuePrefix() {

		switch(strtolower($this->service)) {

			case 'aim':
				return 'aim:';

			case 'gadugadu':
				return 'x-apple:';

			case 'googletalk':
				return 'xmpp:';

			case 'icq':
				return 'aim:';

			case 'jabber':
				return 'xmpp:';

			case 'facebook':
				return 'xmpp:';

			case 'msn':
				return 'msnim:';

			case 'yahoo':
				return 'ymsgr:';

			case 'qq':
				return 'x-apple:';

			case 'skype':
				return 'skype:';

		}

	}

	/**
	* Get service X-TYPE, a service can have an X-TYPE representation.
	**/
	private function getXtypeKey() {

		switch(strtolower($this->service)) {

			case 'aim':
				return 'X-AIM';

			case 'icq':
				return 'X-ICQ';

			case 'jabber':
				return 'X-JABBER';

			case 'msn':
				return 'X-MSN';

			case 'yahoo':
				return 'X-YAHOO';
		}

		return false;

	}

	/**
    * Returns an array of Key, Value, Params for vcard.
    * Normal vcard items will only have 1 item in array. More complex ones or ones with custom types will have multiple elements.
    **/
    public function getVcard($key, $value = false, $params = array()) {

    	$items = array();

    	$this->addImppVcard($key, $items);
    	$this->addXtypeVcard($items);

        return $items;

    }

    private function addImppVcard($key, &$items) {

        $simpleTypes = array();
        $customTypes = array();
        $this->_splitTypes($simpleTypes, $customTypes);
        
        $item = array();

        $item[] = array(
            'key' => $key,
            'value' => $this->getValuePrefix().$this->value,
            'params' => array('X-SERVICE-TYPE' => $this->service, 'type' => $simpleTypes)
        );

        if (!empty($customTypes)) {

            foreach ($customTypes as $type) {
                $item[] = array(
                    'key' => 'X-ABLABEL',
                    'value' => $type
                );
            }
            
        }

        $items[] = $item;

    }

    private function addXtypeVcard(&$items) {

    	$key = $this->getXtypeKey();

    	if (!$key) { return false; }

        $simpleTypes = array();
        $customTypes = array();
        $this->_splitTypes($simpleTypes, $customTypes);
        
        $item = array();

        $item[] = array(
            'key' => $key,
            'value' => $this->value,
            'params' => array('type' => $simpleTypes)
        );

        if (!empty($customTypes)) {

            foreach ($customTypes as $type) {
                $item[] = array(
                    'key' => 'X-ABLABEL',
                    'value' => $type
                );
            }
            
        }

        $items[] = $item;
    }

}
