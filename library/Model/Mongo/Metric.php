<?php
/**
 * This model is just used for logging stats/metrics.
 *
 * It saves in to metrics collecion, this is then moved over to MySQL by a cron job calling /Metrics so that
 * KissMetrics can pick up on it.
 *
 * @package Models
 * @author Tom Holder
 * @copyright Simpleweb 2011
 */
class Model_Mongo_Metric extends Model_Mongo_Base
{

    protected static $_collection = 'metric';

    protected static $_requirements = array(
        'identity' => 'Required',
        'event' => 'Required',
        'createdAt' => 'Required'
    );

    public static function LogUserEvent(Model_Mongo_User $u, $event, $custom1 = false, $custom2 = false, $custom3 = false, $custom4 = false, $custom5 = false) {
        self::LogEvent($u->username, $event, $custom1, $custom2, $custom3, $custom4, $custom5);
    }

    public static function LogAnonymousEvent($event, $custom1 = false, $custom2 = false, $custom3 = false, $custom4 = false, $custom5 = false) {
        self::LogEvent('anonymous', $event, $custom1, $custom2, $custom3, $custom4, $custom5);
    }

    public static function LogEvent($identity, $event, $custom1 = false, $custom2 = false, $custom3 = false, $custom4 = false, $custom5 = false) {

        $metric = new self();
        $metric->identity = $identity;
        $metric->event = $event;
        $metric->custom1 = $custom1;
        $metric->custom2 = $custom2;
        $metric->custom3 = $custom3;
        $metric->custom4 = $custom4;
        $metric->custom5 = $custom5;
        $metric->save();

    }

    protected function preInsert() {
        $this->createdAt = new MongoDate();
    }

    public function save($entierDocument = false, $safe = false) {

        try {
            parent::save($entierDocument, $safe);
        } catch(Exception $e) {
            Contactzilla_Utility_Log::LogException($e);
        }

    }

}