<?php
/**
 * Represents a user account. User logins exist once per actual account.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 **/
class Model_Mongo_User extends Model_Mongo_Base
{

    protected static $_collection = 'user';

    protected static $_requirements = array(
        'username' => array('Required'),
        'email' => array('Required'),           //Might at some point want to validate this is actually an email address?
        'secret' => array('Required'),
        'billingContact' => array('Document:Model_Mongo_BillingContact'),
        'contact' => array('Document:Model_Mongo_Contact', 'AsReference', 'Required'),
        'plan' => array('Document:Model_Mongo_Plan', 'AsReference', 'Required'),
        'spaces' => array('DocumentSet:Model_Mongo_SpaceDocumentSet'),
        'spaces.$' => array('Document:Model_Mongo_SpaceAccess'),
        'trials' => array('DocumentSet'),                                    //Holds apps the user has trialed.
        'trials.$' => array('Document:Model_Mongo_ApplicationTrial')
    );

    public function accountHomeLink()
    {
        return '/billing';
    }

    public function accountBillingDetailsLink()
    {
        return '/billing/billing-details';
    }

    public function accountPaymentMethodLink()
    {
        return '/billing/payment-method';
    }

    public function accountPricingPlansLink($error = false)
    { 
      return $error ? '/billing/pricing-plans/error/'.$error : '/billing/pricing-plans';
    }

    public function fullName()
    {
        return $this->forename.' '.$this->surname;
    }

    /**
     * Deletes a given space if the user is an admin for the space
     * and the space is not private
     * @param $space: Mongo_Model_Space instance
     * @return object or null
     **/
    public function deleteSpace(Model_Mongo_Space $space)
    {
        if($this->CanDeleteSpace($space)){
            return $space->delete();
        }
        return null;
    }

    /**
     * Increments a state counter for the contact
     *
     * @return void
     * @author Tom Holder
     **/
    public function incrementState($stateVar, Model_Mongo_User $user = null, $storageField = 'state') {
        parent::IncrementState($stateVar, $user, $storageField);
    }

    /**
     * Sets a state variable for the contact
     *
     * @return void
     * @author Tom Holder
     **/
    public function setState($stateVar, $value, Model_Mongo_User $user = null, $storageField = 'state') {
        parent::SetState($stateVar, $value, $user, $storageField);
    }

    /**
     * Returns state variable for user.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getState($stateVar, $default = '', Model_Mongo_User $user = null, $storageField = 'state') {
        return parent::GetState($stateVar, $default, $user, $storageField);
    }

    /**
    * Convenience method to create a space for a user.
    **/ 
    public function createSpace($spaceName) {
      $space = new Model_Mongo_Space();
      $space->spaceName = $spaceName;
      $space->createdBy = $this;
      $space->save();
      $this->grantAccessToSpace($space);
      
      return $space;
    }

    /**
     * Grants access to the specified space.
     *
     * Checks granting user has permission to grant access.
     *
     * If no granting user is specified, current user is assumed. Effectively, trying to give themselves access to a space.
     *
     * @return void
     * @author Tom Holder
     **/
    public function grantAccessToSpace(Model_Mongo_Space $space, Model_Mongo_User $grantingUser = null)
    {
        $grantingUser = is_null($grantingUser) ? $this : $grantingUser;

        // Don't use !== see http://php.net/manual/en/language.oop5.object-comparison.php
        if ($space->createdBy->getId() != $grantingUser->getId()) {
            throw new Exception('The granting user does not have permission to grant access to the specified space.');
        }

        $this->grantAccessToSpaceWithoutChecks($space);
        $this->save();
    }

    /**
     * This function exists and is private because of an issue when first setting up the user. We need
     * to be able to skip security/ownership check when granting access because of an issue with user object not correctly existing.
     *
     * Dan and I had issues with this, we think it might be an unresolved shanty issue.
     *
     * Note, this does not save $this due to an error found relating to usernames being set to null. Weird but true.
     * @return void
     * @author Tom Holder
     **/
    private function grantAccessToSpaceWithoutChecks(Model_Mongo_Space $space)
    {
        //Onl
        if ($this->spaces->getSpaceById($space->_id) ) { return; }

        //Save this space against the user and set is as primary.
        $uca = $this->spaces->new();
        $uca->space = $space;
        $uca->lastAccessed = new MongoDate(time());

        $this->spaces->addDocument($uca);
    }

    /**
    * Determines if a user has access to this space.
    **/
    public function hasAccess(Model_Mongo_Space $space)
    {
        foreach ($this->spaces as $spaceAccess) {
            if ($spaceAccess->space->getId()==$space->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Gets spaces created by user.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getOwnedSpaces()
    {
        return Model_Mongo_Space::all(
            array('createdBy.$id' => $this->getId())
        );
    }

    public function homePage() {
        return '/contacts';
    }

    /**
     * Returns user authenticated by usernme/password.
     *
     * @return user object or null if not authenticated
     * @author Tom Holder
     **/
    public static function getAuthedUser($username, $password)
    {
      $username = mb_strtolower($username,'UTF-8');
      $passwordHash = Contactzilla_Utility_Encrypt::GetHashedPassword($password);
      $u = Model_Mongo_User::one(
          array('$or' => array(
            array('username' => $username, 'password' => $passwordHash),
            array('email' => $username, 'password' => $passwordHash)
          ))
      );

      return $u;
    }

    /**
     * Returns user by usernme or email.
     *
     * @return user object or null if not authenticated
     * @author Tom Holder
     **/
    public static function getUserByUsernameOrEmail($identifier)
    {
      $identifier = mb_strtolower($identifier,'UTF-8');
      $u = Model_Mongo_User::one(
          array('$or' => array(
            array('username' => $identifier),
            array('email' => $identifier)
          ))
      );

      return $u;
    }

    /**
     * Returns user by reset token
     *
     * @return user object or null if not found
     * @author Tom Holder
     **/
    public static function getUserByPasswordResetToken($resetToken)
    {

      $u = Model_Mongo_User::one(array('passwordResetToken' => $resetToken));

      return $u;
    }

    /**
     * Sets a reset token against user object.
     *
     * @return reset token hash
     * @author Tom Holder
     **/
    public function getPasswordResetToken()
    {
      $token = Contactzilla_Utility_Encrypt::GetSecretKey();
      $this->passwordResetToken = $token;
      $this->save();
      return $token;
    }

    /**
    * Returns application install for user but only if the app exists.
    **/
    public function getApplicationInstall(Model_Mongo_Application $app)
    {

        $appInstall = Model_Mongo_ApplicationInstall::one(
            array(
                'user.$id' => $this->getId(),
                'application.$id' => $app->getId()
            )
        );

        if ($appInstall) {
            return $appInstall;
        } else {
            return false;
        }

    }

    /**
     * Returns all installed applications for a user.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getInstalledApplications()
    {
        return Model_Mongo_ApplicationInstall::GetInstalledApplications(array('user.$id' => $this->getId()));
    }

    /**
     * Processes pending invites for email
     */
    public function processPendingInvites() {
      $invite = Model_Mongo_Invite::one(array('email' => $this->email));

      //Make sure we got the invite.
      if ($invite) {
        $inviteDetails = new Zend_Session_Namespace('inviteDetails');
        $inviteDetails->invite = $invite;
//         $inviteDetails->space = $invite->getSpaceFromInvite($this->_getParam('space',''));
      }

    }

    /**
    * Returns url of landing page for user to be used when logging in or having created account.
    **/
    public function loginLandingLink($fromRegistration = false) {
        //This needs to be based on last accessed at some point.

        $invite = new Zend_Session_Namespace('inviteDetails');

        if ($invite->space instanceof Model_Mongo_SpaceInvite && $invite->space->space) {
            $url = $invite->space->space->Url();
            $invite->space->space->SetState("registration_onboarding", 'invited', $this);
        } else {
            $url = $this->url();

            if ($fromRegistration) {
                $this->SetState("registration_onboarding", 'registered');
            }
        }

        if ($invite) {
            $this->completeInvite();
            $invite->unsetAll();
        }
        return $url;

    }

    /**
     * Adds user to space(s) from invite.
     */
    private function completeInvite() {

        $invite = new Zend_Session_Namespace('inviteDetails');

        // Grant access to all spaces in the invite
        if($invite->invite && $invite->invite->spaces)
        {
            foreach($invite->invite->spaces as $spaceInvite)
            {
                try {
                    $this->GrantAccessToSpace($spaceInvite->space, $spaceInvite->addedByUser);
                } catch(Exception $e) {
                    Contactzilla_Utility_Log::LogNotice('Failed granting access to space.');
                    Contactzilla_Utility_Log::LogException($e);
                }

            }
            $this->save();

            $invite->invite->delete();
        }

    }

    /**
     * Returns the full web address to the user
     *
     * @return void
     * @author Tom Holder
     **/
    public function url()
    {
        $config = Zend_Registry::get('config');
        return 'https://'.$this->spaces->GetPrivate()->urlKey.'.'.$config->url;
    }

    /**
     * Takes app details and inserts an active billing document.
     * The active billing document will later be used to raise an invoice.
     */
    public function insertAppBilling(Model_Mongo_Application $app)
    {

        //No billing for free shit
        if (!$app->monthlyPricePerInstall || $app->monthlyPricePerInstall == 0) {
            return true;
        }

        $trialRemaining = $app->TrialRemaining($this);

        //Set period from date to 1month 1 day in the future and then zero the date on midnight.
        $periodFromDate = new Zend_Date();
        $periodFromDate->addDay($trialRemaining + 1);
        $periodFromDate->setHour(0);
        $periodFromDate->setMinute(0);
        $periodFromDate->setSecond(0);

        //If we couldn't find existing application billing row, create a new one.
        if (!$this->GetApplicationBilling($wb, $app)) {

            $ab = $wb->applications->new();
            $ab->application = $app;
            $ab->appName = $app->appName;
            $ab->monthlyPrice = $app->monthlyPricePerInstall;
            $ab->periodFrom = new MongoDate($periodFromDate->get());

            $periodToDate = new Zend_Date($periodFromDate->get());
            $periodToDate->addMonth(1);

            $ab->periodTo = new MongoDate($periodToDate->get());
            $wb->applications->addDocument($ab);
        }

        $this->save();
    }

    /**
    * Returns nicely formatted billing date.
    **/
    public function formattedBillingDate() {
      return $this->nextBillingDate ? date('jS F Y', $this->nextBillingDate->sec) : '';
    }

    /**
    * Gets primary credit card
    **/
    public function getPrimaryCreditCard() {
      $card = Model_Mongo_CreditCard::one(
        array(
          'user.$id' => $this->getId(),
          'isPrimary' => true
        )
      );

      return $card ?: false;
    }

    /**
    * Gets all credit cards for user, with primary card first.
    **/
    public function creditCards() {
      return Model_Mongo_CreditCard::all(
        array(
          'user.$id' => $this->getId()
        )
      )->sort(array('isPrimary' => -1));
    }

    /**
    * Attempts to pay off the outstanding balance of the account.
    **/
    public function payOutstandingBalance() {

      if ($this->balance <= 0) {
        return false;
      }

      $realex = new Realex_Eft();
      $payer = new Realex_Payer();
      $payer->ref = $this->realExPayerRef();

      // Loop over outstanding invoices.
      foreach ($this->getOutstandingInvoices() as $invoice) {

        //Loop through credit cards for this company and try to charge the invoice to each card in turn.
        $charged = false;
        foreach($this->creditCards() as $creditCard) {
          
          $card = new Realex_Card($payer);
          $card->ref = $creditCard->realExRef;
      
          $payment = new Realex_Payment($payer, $card);
          $payment->orderID = md5(microtime(true));
          
          $payment->amount = $invoice->balance*100;
          $payment->cvn = $creditCard->cvn;
          $response = $realex->RaisePayment($payment);
      
          //If payment was taken successfully.
          if($response->result == 0) {
            
            Model_Mongo_Metric::LogUserEvent($this,'payment-taken', (float) $invoice->balance);

            $this->balance = $this->balance - $invoice->balance;

            $invoice->realExResponse = $response->message;
            $invoice->balance = 0;
            $invoice->cardRef = $creditCard->ref;
            $invoice->cardRealExRef = $creditCard->realExRef;
            $invoice->paymentRef = $payment->orderID;
            $invoice->save();
          
            //Remove any error from card.
            $creditCard->lastErrorAt = null;
            $creditCard->lastErrorMessage = null;
            $creditCard->lastErrorAmount = null;
            $creditCard->save();

            $this->emailInvoice($invoice);
            
            $charged = true;
            break;
            
          } else {

            Model_Mongo_Metric::LogUserEvent($this,'payment-failed', (float) $invoice->balance, (string) $response->message);

            //Log error to card.
            $creditCard->lastErrorAt = time();
            $creditCard->lastErrorMessage = $response->message;
            $creditCard->lastErrorAmount = $invoice->balance;
            $creditCard->save();

          }
      
        }

        if (!$charged && (!$this->balanceAge || $this->balanceAge == 0)) {
          $this->emailInvoice($invoice, 'failed_to_charge.phtml', 'We failed to charge your card');
        }

      }

      if ($this->balance == 0) {
        $this->balanceAge = 0;
        $this->save();

        if ($this->suspended) {
          $this->unsuspendAccount();
        }

        return true;
      } else {

        $this->balanceAge = $this->balanceAge + 1;

        // Re-notify them
        if ($this->balanceAge == 3) {
          $this->notifyFailedToCharge();
        }

        // Tried to clear balance too many times, suspend account.
        if ($this->balanceAge == 5) {
          $this->suspendAccount('too_many_billing_attempts.phtml');
        }
        
        $this->save();
        
        return false;
      }

    }

    /**
    * Makes the specified card the primary
    **/
    public function makeCreditCardPrimary($realExRef) {

      foreach ($this->creditCards() as $card)  {
        if ($card->realExRef == $realExRef) {
          $card->isPrimary = true;
        } else {
          $card->isPrimary = false;
        }
        $card->save();
      }
    }

    /**
    * Deletes the specified card
    **/
    public function deleteCreditCard($realExRef) {
      $cc = Model_Mongo_CreditCard::one(array('realExRef' => $realExRef, 'user.$id' => $this->getId()));
      $cc->delete();
      Model_Mongo_Metric::LogUserEvent($this,'creditcard-delete');
    }

    /**
    * Adds a credit card to the space account. We do not store for security, only hold RealEx reference.
    **/
    public function addCreditCard($reference, $nameOnCard, $cardType, $cardNumber, $cvn, $expiryMonth, $expiryYear)
    {

        $expiry = strtotime($expiryYear.'-'.$expiryMonth.'-01');

        if ($expiry < time()) {
            throw new Contactzilla_Exceptions_CreditCard(Contactzilla_Exceptions_CreditCard::CARD_EXPIRED);
        }

        //Determine if we need to make this credit card primary and make sure reference doesn't already exist.
        $makePrimary = true;
        foreach ($this->creditCards() as $creditCard) {
            if ($creditCard->isPrimary) {
                $makePrimary = false;
            }

            if (strcasecmp($creditCard->ref, $reference) == 0) {
                throw new Contactzilla_Exceptions_CreditCard(Contactzilla_Exceptions_CreditCard::DUPLICATE_REF);
            }
        }

        $cc = new Model_Mongo_CreditCard();
        // We do this due to a shanty bug. Annoying.
        $cc->user = Model_Mongo_User::find($this->getId());
        $cc->ref = $reference;
        $cc->expiry = new MongoDate($expiry);
        $cc->isPrimary = $makePrimary;
        $cc->cvn = $cvn;

        //We need a unique card reference for realex, we'll use the ID of the space combined with microtime,
        $cc->realExRef = $this->getId()->__toString().microtime();
        //References must contain only numbers or digits.
        $cc->realExRef = preg_replace('/[^\\w\\d]/', '', $cc->realExRef);

        //Let RealEx do their bit, if it's gonna go wrong it will be now :)
        $realex = new Realex_Eft();
        $payer = new Realex_Payer();
        $payer->ref = $this->realExPayerRef();

        $rexCard = new Realex_Card($payer);

        //We use card id and created at for realex reference to guaranteee
        //uniqueness between testing and live environments.
        $rexCard->ref = $cc->realExRef;
        $rexCard->number = $cardNumber;
        $rexCard->holder = $nameOnCard;
        $rexCard->expiry = $expiryMonth.substr($expiryYear, 2, 2);
        $rexCard->type = $cardType;

        $response = $realex->NewCard($rexCard);

        if ($response->result == 0) {
            $cc->save();
            Model_Mongo_Metric::LogUserEvent($this,'creditcard-add');
            return true;
        } else {
          if (strcasecmp($response->message,"That Card Number does not correspond to the card type you selected") === 0) {
            throw new Contactzilla_Exceptions_CreditCard(Contactzilla_Exceptions_CreditCard::CARD_TYPE_MATCH);
          } else {
            throw new Contactzilla_Exceptions_CreditCard(Contactzilla_Exceptions_CreditCard::UNKNOWN_CARD_ERROR);
          }
        }

    }

    public function getInvitableSpaces()
    {
        $spaces = Model_Mongo_Space::all(array(
            'createdBy.$id' => $this->getId(),
            'private' => array('$ne' => true)
        ));
        return $spaces;
    }

    public function hasInvitableSpaces()
    {
        return sizeof($this->GetInvitableSpaces()->export()) > 0;
    }

    public function totalInstalledAppCost()
    {
        return 1;
    }

    public function totalInstalledPaidAppCount()
    {
        return 3;
    }

    /**
    * Gets the outstanding balance of all user invoices.
    **/
    public function getOutstandingBalance() {

        $conditions = array(
          'user.$id' => $this->getId()
        );

        $aggregate = array(
            array('$match' => $conditions),
            array('$group' => array('_id' => 'user.$id', 'balance' => array( '$sum' => '$balance')))
        );

        $answer = Model_Mongo_Invoice::getMongoCollection()->aggregate($aggregate);

        if (!isset($answer['result']) || !isset($answer['result'][0])) {
          return 0;
        } else {
          return number_format($answer['result'][0]['balance'],2);
        }
        
    }

    /**
    * Returns all invoices for user.
    **/
    public function getInvoices() {
      return Model_Mongo_Invoice::all(array('user.$id' => $this->getId()))->sort(array('createdAt' => -1));
    }

    /**
    * Get invoice
    **/
    public function getInvoice($id) {

      if (!$id instanceof MongoId) {
        $id = new MongoId($id);
      }

      return Model_Mongo_Invoice::one(
        array(
          '_id' => $id,
          'user.$id' => $this->getId()
        )
      );
    }

    /**
    * Returns all outstanding invoices for user.
    **/
    public function getOutstandingInvoices() {
      return Model_Mongo_Invoice::all(
        array(
          'user.$id' => $this->getId(),
          'balance' => array('$gt' => 0)
        )
      )->sort(array('createdAt' => -1));
    }

    /**
    * Pre warn users they will be billed.
    *
    * @return bool
    * @author Tom Holder
    **/
    public function preWarnOfBilling() {

      if ($this->plan->monthlyCost == 0) {
        return false;
      }

      $view = new Zend_View();
      $view->setScriptPath(APPLICATION_PATH."/app/views/emails");
      $view->displayName = (string) $this;
      $view->url = 'https://hq.'.Zend_Registry::get('config')->url;
      $view->currencySymbol = $this->billingContact->currencySymbol();
      $view->amount = number_format($this->plan->monthlyCost,2);
      $view->plan = (string) $this->plan;
      $mailer = new Contactzilla_Emailing_MailManager();
      $mailer->SendEmail($view, 'advance_billing_notice.phtml','Advance billing notice', Zend_Registry::get('config')->email->from, Zend_Registry::get('config')->email->from_email, $this->contact->poco->displayName, $this->email);

      return true;
    }

    /**
     * Issues an invoice against the user account for any apps due to be billed.
     *
     * @return bool
     * @author Tom Holder
     **/
    public function issueInvoice() {

      if ($this->plan->monthlyCost == 0) {
        return false;
      }

      if (!isset($this->billingContact)) {
        $this->suspendAccount('no_billing_contact.phtml');
        return false;
      }

      // Not time to invoice yet
      if (time() < $this->nextBillingDate->sec) {
        return false;
      }

      $invoice = new Model_Mongo_Invoice();

      //This is retarded but there is a bug in shanty, if we just assign $this then ref has annoying properties in it.
      $invoice->user = Model_Mongo_User::find($this->getId());

      $currentBillingDate = $this->nextBillingDate;

      // Advance billing date.
      $nextBillingDate = new Zend_Date($currentBillingDate->sec);
      $nextBillingDate->addMonth(1);
      $nextBillingDate->setHour(0);
      $nextBillingDate->setMinute(0);
      $nextBillingDate->setSecond(0);
      $nextBillingDate = new MongoDate($nextBillingDate->get());

      //Set invoice details.
      $invoice->taxRate = $this->billingContact->vatRate();
      $invoice->subTotal = $this->plan->monthlyCost;
      $invoice->taxTotal = (($invoice->subTotal / 100) * $invoice->taxRate);
      $invoice->total = $invoice->subTotal + $invoice->taxTotal;
      $invoice->balance = $invoice->total;
      $invoice->currencySymbol = $this->billingContact->currencySymbol();
      $invoice->currencyCode = $this->billingContact->currencyCode();
      $invoice->periodFrom = $currentBillingDate;
      $invoice->periodTo = $nextBillingDate;
      $invoice->billedTo = $this->billingContact;

      $ir = $invoice->rows->new();
      $ir->label = 'One month ' . $this->plan->label .' subscription to Contactzilla from ' . date("jS M Y", $invoice->periodFrom->sec) . ' to ' . date("jS M Y", $invoice->periodTo->sec);
      $ir->amount = $this->plan->monthlyCost;

      $invoice->rows->addDocument($ir);
      $invoice->save();

      if (!$this->balance) {
        $this->balance = 0;
      }

      // Balance age is increased after each time balance is tried to be cleared
      if (!$this->balanceAge) {
        $this->balanceAge = 0;
      }

      $this->balance = $this->balance + $invoice->balance;
      $this->nextBillingDate = $nextBillingDate;
      $this->save();
      
      Model_Mongo_Metric::LogUserEvent($this,'invoice-issue', $invoice->number);

      return $invoice;

    }

    /**
    * Suspends a user account and sends them an email to inform them.
    **/
    public function suspendAccount($template = false) {

      $this->suspended = true;
      $this->suspendedAt = new MongoDate();
      $this->save();

      if ($template) {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH."/app/views/emails");
        $view->displayName = (string) $this;
        $view->url = 'https://hq.'.Zend_Registry::get('config')->url;
        $view->currencySymbol = $this->billingContact->currencySymbol();
        $view->balance = number_format($this->balance,2);
        $mailer = new Contactzilla_Emailing_MailManager();
        $mailer->SendEmail($view, $template,'Your Contactzilla account has been temporarily suspended', Zend_Registry::get('config')->email->from, Zend_Registry::get('config')->email->from_email, $this->contact->poco->displayName, $this->email);
      }

    }


    /**
    * Notify of failure to charge card.
    **/
    public function notifyFailedToCharge($template = 'failed_to_charge_again.phtml', $subject = 'We failed to charge your card again') {

      $view = new Zend_View();
      $view->setScriptPath(APPLICATION_PATH."/app/views/emails");
      $view->displayName = $this->contact->poco->dynamicDisplayName();
      $view->url = 'https://hq.'.Zend_Registry::get('config')->url;
      $view->currencySymbol = $this->billingContact->currencySymbol();
      $view->balance = number_format($this->balance,2);
      $mailer = new Contactzilla_Emailing_MailManager();
      $mailer->SendEmail($view, $template, $subject, Zend_Registry::get('config')->email->from, Zend_Registry::get('config')->email->from_email, $this->contact->poco->displayName, $this->email);
    }

    /**
    * Emails invoice
    **/
    public function emailInvoice(Model_Mongo_Invoice $invoice, 
      $template = 'successfully_billed.phtml',
      $subject = 'Invoice Paid. You have been successfully billed.') {

      $pdf = Contactzilla_Utility_Pdf::getInvoicePdf($invoice);
      $pdfFileName = 'invoice-'.$invoice->number.'.pdf';
      $pdfPath = '/tmp/'.$pdfFileName;
      $pdf->saveAs($pdfPath);

      $view = new Zend_View();
      $view->setScriptPath(APPLICATION_PATH."/app/views/emails");
      $view->displayName = (string) $this->billingContact;
      $view->url = 'https://hq.'.Zend_Registry::get('config')->url;
      $view->total = number_format($invoice->total, 2);
      $view->currencySymbol = $invoice->currencySymbol;
      $mailer = new Contactzilla_Emailing_MailManager();
      $mailer->attachPdf($pdfFileName, $pdfPath);

      if ($this->email != $this->billingContact->email) {
        $mailer->addCc($this->email, $this->contact->poco->dynamicDisplayName());
      }
      $mailer->SendEmail($view, $template, $subject . ' - invoice/receipt ' . $invoice->number, Zend_Registry::get('config')->email->from, Zend_Registry::get('config')->email->from_email, (string) $this->billingContact, $this->billingContact->email);

      unlink($pdfPath);

    } 

    /**
    * Unsuspends an account.
    **/
    public function unsuspendAccount($force = false) {

      if ($this->suspended !==true) {
        return false;
      }

      if (isset($this->billingContact) && $this->creditCards()->count() > 0) {
        $force = true;
      }

      if ($force) {
        $this->suspended = null;
        $this->suspendedAt = null;
        $this->save();

        return true;
      }
      
      return false;

    } 

    /**
     * Takes an invoice and adds a row for each application installed against the specified space.
     * Updates invoice amount.
     *
     * @return void
     * @author Tom Holder
     **/
    private function addInvoiceRowsForSpace(Model_Mongo_Invoice &$invoice, Model_Mongo_Interfaces_Space $space) {

        $appInstalls = $space->GetInstalledApplications();

        foreach($appInstalls as $appInstall) {

            //IS there a price and was the period of this app billing row in the past? If so, bill for it.
            if (isset($appInstall->monthlyPricePerInstall) && $appInstall->periodTo->sec < time()) {

                //Set details of the invoice row.
                $ir = $invoice->rows->new();
                $ir->appLabel = $appInstall->label;
                $ir->appName = $appInstall->application->appName;
                $ir->periodFrom = $appInstall->periodFrom;
                $ir->periodTo = $appInstall->periodTo;
                $ir->amount = $appInstall->monthlyPricePerInstall;

                //Add the ivoice row to the invoice and increase the amount.
                $invoice->rows->addDocument($ir);
                $invoice->amount += $appInstall->monthlyPricePerInstall;

                //Advance the period forward.
                $periodFrom = new Zend_Date($appInstall->periodFrom->sec);
                $periodFrom->addMonth(1);

                $periodTo = new Zend_Date($appInstall->periodTo->sec);
                $periodTo->addMonth(1);

                $appInstall->periodFrom = new MongoDate($periodFrom->get());
                $appInstall->periodTo = new MongoDate($periodTo->get());
                $appInstall->save();
            }

        }

    }

    private function realExPayerRef() {
      return $this->getId()."_".APPLICATION_ENV;
    }

    /**
    * Saves payer to realex.
    **/
    public function saveRealExPayerRef()
    {

        if (!$this->billingContact) {
            return false;
        }

        $realex = new Realex_Eft();
        $payer = new Realex_Payer($this->realExPayerRef(), $this->billingContact);

        if (!$this->realExPayerRef) {

            $response = $realex->NewPayer($payer);

            if ($response->result==0) {
                $this->realExPayerRef = true;
                $this->save();
                return true;
            } else {

                if (strtolower($response->message)
                    == 'this payer ref ['.$this->realExPayerRef().'] has already been used - please use another one') {
                  $this->realExPayerRef = true;
                  $this->save();
                  return $this->SaveRealExPayerRef();
                }

                throw new Exception($response->message, $response->result);
            }

        } else {

            $response = $realex->UpdatePayer($payer);

            if ($response->result==0) {
                return true;
            } else {

                //This might occure if something got out of sync between us and realex.
                if (strtolower($response->message)
                    == 'this payer ref ['.$this->realExPayerRef().'] does not exist') {
                        unset($this->realExPayerRef);
                        $this->save();
                        return $this->SaveRealExPayerRef();
                }

                throw new Exception($response->message, $response->result);
            }
        }

    }

    /**
    * Pulls back existing trial app, or returns false if nothing found.
    **/
    public function getTrialApp(Model_Mongo_Application $app)
    {
        if($this->trials) {
            foreach ($this->trials as $t) {
                if (is_object($t->application) && $t->application->getId() == $app->getId()) {
                    return $t;
                }
            }
        }
        return false;
    }

    /**
    * Inserts a trial app, first checks to make sure none exists.
    * Returns ApplicationTrial document.
    **/
    public function insertTrialApp(Model_Mongo_Application $app)
    {

        //App might not have a free trial.
        if (!$app->freeTrial || $app->freeTrial ==0) {
            return false;
        }

        //Get existing free trial
        $t = $this->GetTrialApp($app);

        //Not found, insert a new free trial ending now + the number of trial days.
        if (!$t) {

            //Insert into active billing.
            $trialExpires = new Zend_Date();

            //We add one day to make sure they get the full trial period.
            //In reality this means they get the trial period plus half a day or so.
            $trialExpires->addDay($app->freeTrial + 1);

            //Zero out time to midnight stops issues with fractions and ceil pushing dates over
            $trialExpires->setHour(0);
            $trialExpires->setMinute(0);
            $trialExpires->setSecond(0);

            $t = $this->trials->new();
            $t->application = $app;
            $t->appName = $app->appName;
            $t->trialExpiresAt = new MongoDate($trialExpires->get());
            $t->createdAt = new MongoDate(time());
            $this->trials->addDocument($t);
            $this->save();
        }

        return $t;
    }

    /**
     * Gets the private space for a user.
     *
     * @return void
     * @author Tom Holder
     **/
    public function getPrivateSpace()
    {
        return $this->spaces->GetPrivate();
    }

    /**
     * Method for determining if the user is able to delete a given space
     *
     * @param $space: Model_Mongo_Space instance
     * @return bool
     **/
    public function canDeleteSpace(Model_Mongo_Space $space)
    {
        $editable = false;
        if ($space->IsAdmin($this))
        {
            // A user may not delete their private space
            if (is_null($space->private) || !$space->private)
            {
                $editable = true;
            }
        }
        return $editable;
    }

    protected function preInsert() {
      $this->balance = 0;
      $this->createdAt = new MongoDate();
      $this->secret = Contactzilla_Utility_Encrypt::GetSecretKey();

      if (!isset($this->poco) || !is_array($this->poco)) {
          throw new Exception('User requires poco to be set');
      }

      //Create private space for this user.
      $space = new Model_Mongo_Space();
      $space->createdBy = $this;
      $space->private = true;
      $space->spaceName = $this->username;
      $space->save();

      //Give user access to their fresh new space.
      $this->grantAccessToSpaceWithoutChecks($space);

      //Create user contact.
      $contact = new Model_Mongo_Contact();
      $poco = new Model_Mongo_Poco($this->poco);
      $contact->poco = $poco;
      $contact->owner = $space;
      $contact->createdBy = $this;
      $contact->save();
      $this->contact = $contact;

      unset($this->poco);
    }

    /**
    * Sets the next billing date
    **/
    public function consumeVoucherCode($code) {

      if (!$code) {
        return false;
      }

      $code = Model_Mongo_VoucherCode::one(array('code' => $code));

      if ($code && $code->remaining > 0) {
        $code->remaining = $code->remaining - 1;
        $code->save();

        $nextBillingDate = new Zend_Date();
        $nextBillingDate->addMonth($code->monthsFree);
        $nextBillingDate->setHour(0);
        $nextBillingDate->setMinute(0);
        $nextBillingDate->setSecond(0);
        $this->nextBillingDate = new MongoDate($nextBillingDate->get());

        return true;
      }

      return false;
      
    }

    protected function preSave() {
      $this->username = trim(mb_strtolower($this->username,'UTF-8'));
      $this->email = trim(mb_strtolower($this->email,'UTF-8'));

      // Set the next billing date.
      if ($this->plan->monthlyCost > 0) {
        if (!$this->nextBillingDate) {
          $nextBillingDate = new Zend_Date();
          $nextBillingDate->addMonth(1);
          $nextBillingDate->setHour(0);
          $nextBillingDate->setMinute(0);
          $nextBillingDate->setSecond(0);
          $this->nextBillingDate = new MongoDate($nextBillingDate->get());
        }
      } else {
        $this->nextBillingDate = null;
      }

    }

    public function save($entierDocument = false, $safe = true) {

      try {
          parent::save($entierDocument, $safe);
      } catch(Exception $e) {

          $m = $e->getMessage();

          //We might have had a urlKey clash, in which case just use id.
          if (strpos($m, 'duplicate') && strpos($m, 'username')) {
              throw new Contactzilla_Exceptions_User(Contactzilla_Exceptions_User::DUPLICATE_USERNAME);
          } elseif (strpos($m, 'duplicate') && strpos($m, 'email')){
            throw new Contactzilla_Exceptions_User(Contactzilla_Exceptions_User::DUPLICATE_EMAIL);
          } else {
              throw $e;
          }

      }

    }

    public function __toString() {
        return $this->contact->poco->dynamicDisplayName();
    }

    /**
     * Deliver an email to the user identifying the new space
     * that they have been added to
     * @param $space: Instance of Model_Mongo_Space.  The space that the user has been granted access to
     */
    public function deliverNewSpaceEmail(Model_Mongo_Space $space, Model_Mongo_User $inviter, $message = '')
    {
        $config = Zend_Registry::get('config');

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH."/app/views/emails");
        $view->user = $this;
        $view->inviter = $inviter;
        $view->space = $space;
        $view->message = nl2br($message);
        $view->email = $this->contact->poco->emails->GetPrimary()->value;

        $mailer = new Contactzilla_Emailing_MailManager();
        $mailer->SendEmail(
          $view,
          "user_added_to_space.phtml",
          'You have been granted access to a new address book on Contactzilla!',
          $config->email->from,
          $config->email->from_email,
          $this->FullName(),
          $this->contact->poco->emails->GetPrimary()->value,
          $inviter->email
        );

    }

    /**
     * Delete method override.  Ensures that all spaces and contacts are deleted for a user.
     * @param $safe: bool
     **/
    public function delete($safe = true)
    {

        //Get all spaces created by user. This might change in future when we can multiple space admins.
        $spaces = Model_Mongo_Space::all(array('createdBy.$id' => $this->getId()));

        // Delete each contact in the space
        foreach($spaces as $space) {
            if ($this->CanDeleteSpace($space) || $space->private) {
                $space->delete($safe);
            }
        }

        return parent::delete($safe);
    }

    public function unsetSpaceById($id)
    {
      if (is_string($id)) {
        $id = new MongoId($id);
      }

      $this->addOperation('$pull', 'spaces', array('space.$id'=> $id));
      $this->save();

      return true;
    }

}
