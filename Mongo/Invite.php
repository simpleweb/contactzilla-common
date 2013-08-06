<?php
/**
 * The invite collection contains one document for each invited email address.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Invite extends Model_Mongo_Base
{

    protected static $_collection = 'invite';

    protected static $_requirements = array(
        'secret' => 'Required',
        'email' => 'Required',
        'spaces' => array('DocumentSet'),
        'spaces.$' => array('Document:Model_Mongo_SpaceInvite')
    );

    public static function getInvite($secret)
    {
        if (!$secret) return false;
        return Model_Mongo_Invite::one(array('secret' => $secret));
    }

    public static function getInviteByEmail($email)
    {
        if (!$email) return false;
        return Model_Mongo_Invite::one(array('email' => $email));
    }

    public static function getInviteBySpaceEmail(Model_Mongo_Space $space, $email)
    {
        if (!$email) return false;
        return Model_Mongo_Invite::one(array('spaces.space.$id' => $space->getId(), 'email' => $email));
    }

    public function removeSpaceInvite($email, Model_Mongo_Space $space)
    {
      $this->addOperation('$pull', 'spaces', array('space.$id'=> $space->getId()));
      $this->save();

      return true;
    }

    /**
     * This will either create a new invite if the email address doesn't have an existing invite, or it will return
     * the correct invite.
     * @static
     * @param $email
     * @return Model_Mongo_Invite|Shanty_Mongo_Document
     */
    public static function getOrCreate($email, $name = ''){
        $invitation = self::one(array("email" => $email));
        if(is_null($invitation)){
            $invitation = new self(array("email" => $email, "name" => $name));
            $invitation->spaces = new Model_Mongo_SpaceDocumentSet();
            $invitation->save();
        }
        return $invitation;
    }

    /**
     * This function will add the given space to the invite if it doesn't already exist. Records a flag of who
     * added the space to the invite.
     * @param Model_Mongo_Space $space
     * @param Model_Mongo_User $invitedBy
     */
    public function inviteToSpace(Model_Mongo_Space $space, Model_Mongo_User $invitedBy)
    {

        if (is_null($space)) {
            throw new Exception('Space must be set correctly.');
        }

        if (is_null($invitedBy)) {
            throw new Exception('Invited by user must be set correctly.');
        }

        $alreadyExists = false;

        if (isset($this->spaces)) {
            foreach($this->spaces as $k => $v){
                if($v->space->urlKey == $space->urlKey){
                    return $v;
                }
            }
        } else {
            $this->spaces = new Model_Mongo_SpaceDocumentSet();
        }

        $space_invite = new Model_Mongo_SpaceInvite(array('space' => $space, 'addedByUser' => $invitedBy));
        $this->spaces->addDocument($space_invite);
        $this->save();

        return $space_invite;
    }

    public function getSpaceFromInvite($spaceId) {

        if (!($spaceId instanceof MongoId)) {
            $spaceId = new MongoId($spaceId);
        }

        foreach($this->spaces as $k => $v){
            if($v->space && $v->space->getId()->__toString() ===  $spaceId->__toString()) {
                return $v;
            }
        }

        return false;

    }

    protected function preSave()
    {

        if ($this->isNewDocument()) {
            $this->createdAt = new MongoDate(time());
            $this->secret = Contactzilla_Utility_Encrypt::GetSecretKey();
        }

    }

    /**
     * Deliver this invitation to the email address
     * stored against the instance
     */
    public function deliverInvitation(Model_Mongo_SpaceInvite $spaceInvite, Model_Mongo_User $user, $message = '')
    {
        $config = Zend_Registry::get('config');
        $errors = array();
        $email = $this->email;

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH."/app/views/emails");
        $view->base_url = 'hq.' . $config->url;
        $view->invite = $this;
        $view->spaceInvite = $spaceInvite;
        $view->message = nl2br($message);

        $mailer = new Contactzilla_Emailing_MailManager();
        $mailer->SendEmail(
            $view,
            "user_invited_to_space.phtml",
            'You have been invited to join "'.$spaceInvite->space->spaceName.'" on Contactzilla!',
            $config->email->from,
            $config->email->from_email,
            $email,
            $email,
            $user->email
        );

    }

}
