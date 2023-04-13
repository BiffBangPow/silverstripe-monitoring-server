<?php

namespace BiffBangPow\SSMonitor\Server\Model;

use BiffBangPow\SSMonitor\Server\Helper\EncryptionHelper;
use Ramsey\Uuid\Uuid;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\HTML;

/**
 * Class \BiffBangPow\SSMonitor\Server\Model\Client
 *
 * @property string $Title
 * @property string $BaseURL
 * @property string $UUID
 * @property string $EncSecret
 * @property string $EncSalt
 * @property string $APIKey
 * @property string $LastFetch
 * @property string $ClientData
 * @property bool $FetchError
 * @property bool $Active
 */
class Client extends DataObject
{
    private static $table_name = 'BBP_Monitoring_Client';
    private static $fetch_delay_warning = 600;
    private static $db = [
        'Title' => 'Varchar',
        'BaseURL' => 'Varchar',
        'UUID' => 'Varchar',
        'EncSecret' => 'Text',
        'EncSalt' => 'Text',
        'APIKey' => 'Text',
        'LastFetch' => 'Datetime',
        'ClientData' => 'Text',
        'FetchError' => 'Boolean',
        'Active' => 'Boolean'
    ];

    private static $summary_fields = [
        'Title' => 'Site',
        'BaseURL' => 'Base URL',
        'Active.Nice' => 'Active',
        'LastFetch.Nice' => 'Last Comms',
        'StatusHTML' => 'Status'
    ];

    private static $indexes = [
        'UUID' => true
    ];

    public function getStatusHTML()
    {
        $statusClass = ($this->FetchError) ? 'status-error' : 'status-ok';
        $lastFetch = ($this->LastFetch) ? $this->LastFetch : 0;
        $threshold = strtotime($lastFetch) + $this->config()->get('fetch_delay_warning');
        if ($threshold <= time() && !$this->FetchError) {
            $statusClass = 'status-warn';
        }

        return DBField::create_field('HTMLFragment', HTML::createTag('div', [
            'class' => 'bbp-monitoring_status-dot ' . $statusClass
        ], ' '));
    }

    /**
     * @param $uuid
     * @return DataObject|null
     */
    public static function getByUUID($uuid)
    {
        return self::get_one(self::class, ['UUID' => $uuid]);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName([
            'EncSecret',
            'EncSalt',
            'APIKey',
            'LastFetch',
            'ClientData',
            'FetchError',
            'UUID'
        ]);

        $session = Controller::curr()->getRequest()->getSession();
        $showSecurity = ($session->get('initial') === 'yes');

        if ($showSecurity) {
            $warning = _t(__CLASS__ . '.credentialswarning',
                'Warning!  These will not be displayed again!  Make sure you make a note of them, you will need them for the client machine.');
            $info = _t(__CLASS__ . '.credentialsinfo',
                'Copy and paste these environment variables into your client configuration:');
            $envTemplate = <<<EOT
MONITORING_ENC_SECRET=%s
MONITORING_ENC_SALT=%s
MONITORING_API_KEY=%s
MONITORING_UUID=%s
EOT;

            $storageSecret = Environment::getEnv('MONITORING_STORAGE_SECRET');
            $storageSalt = Environment::getEnv('MONITORING_STORAGE_SALT');

            $encHelper = new EncryptionHelper($storageSecret, $storageSalt);
            $encSecret = $encHelper->decrypt($this->EncSecret);
            $encSalt = $encHelper->decrypt($this->EncSalt);
            $apikey = $encHelper->decrypt($this->APIKey);

            $creds = sprintf($envTemplate, $encSecret, $encSalt, $apikey, $this->UUID);

            $credsContent = HTML::createTag('h2', [], _t(__CLASS__ . '.client-credentials', 'Client Credentials')) .
                HTML::createTag('p', [
                    'class' => 'bbp-monitoring_text-bold'
                ], $warning) .
                HTML::createTag('p', [], $info) .
                HTML::createTag('pre', [], $creds);

            $fields->addFieldsToTab('Root.Main', [
                LiteralField::create('monitoringclientdata', HTML::createTag('div', [
                    'class' => 'bbp-monitoring_alert-box'
                ], $credsContent))
            ]);

            $session->clear('initial');
        }

        return $fields;
    }

    /**
     * @throws \Exception
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->isInDB()) {
            $security = $this->generateSecurity();

            //Encrypt the data for storage
            $storageSecret = Environment::getEnv('MONITORING_STORAGE_SECRET');
            $storageSalt = Environment::getEnv('MONITORING_STORAGE_SALT');

            $encHelper = new EncryptionHelper($storageSecret, $storageSalt);
            $this->EncSecret = $encHelper->encrypt($security['secret']);
            $this->EncSalt = $encHelper->encrypt($security['salt']);
            $this->APIKey = $encHelper->encrypt($security['apikey']);
            $uuid = Uuid::uuid4();
            $this->UUID = $uuid->toString();

            $session = Controller::curr()->getRequest()->getSession();
            $session->set('initial', 'yes');
        }
    }

    /**
     * @return false|string[]
     * @todo - Handle the exceptions nicely
     */
    private function generateSecurity()
    {
        try {
            $encSecret = $this->generateRandomString(64);
            $encSalt = $this->generateRandomString(32);
            $apiKey = $this->generateRandomString(50);

            return [
                'secret' => $encSecret,
                'salt' => $encSalt,
                'apikey' => $apiKey
            ];
        } catch (\Exception $e) {

        }
        return false;
    }

    /**
     * @throws \Exception
     */
    function generateRandomString(int $length = 64): string
    {
        if ($length < 1) {
            throw new \RangeException("Length must be a positive integer");
        }
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces [] = $keyspace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }


}
