<?php

namespace BiffBangPow\SSMonitor\Server\Model;

use BiffBangPow\SSMonitor\Server\Client\MonitoringClientInterface;
use BiffBangPow\SSMonitor\Server\Helper\ClientHelper;
use BiffBangPow\SSMonitor\Server\Helper\EncryptionHelper;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\View\HTML;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\View\SSViewer;

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
 * @property bool $HasWarnings
 * @property bool $Active
 * @property string $ErrorMessage
 * @property bool $Notified
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
        'HasWarnings' => 'Boolean',
        'Active' => 'Boolean',
        'ErrorMessage' => 'Text',
        'Notified' => 'Boolean'
    ];

    private static $summary_fields = [
        'Title' => 'Site',
        'BaseURL' => 'Base URL',
        'UUID' => 'Client ID',
        'Active.Nice' => 'Active',
        'LastFetch.Nice' => 'Last Comms',
        'StatusHTML' => 'Status'
    ];

    private static $indexes = [
        'UUID' => true
    ];

    /**
     * Get an HTML snippet to show the connection status of the client
     * @return DBField
     */
    public function getStatusHTML()
    {
        $statusClass = ($this->FetchError) ? 'status-error' : 'status-ok';
        $lastFetch = ($this->LastFetch) ? $this->LastFetch : 0;
        $threshold = strtotime($lastFetch) + $this->config()->get('fetch_delay_warning');
        if ($threshold <= time() && !$this->FetchError) {
            $statusClass = 'status-warn';
        }

        $warning = ($this->HasWarnings) ? "⚠" : "";

        return DBField::create_field(
            'HTMLFragment',
            HTML::createTag('div', ['class' => 'bbp-monitoring_status-dot ' . $statusClass], ' ') .
            HTML::createTag('div', ['class' => 'bbp-monitoring_status-warning ' . $warning], $warning)
        );
    }


    /**
     * @param $uuid
     * @return Client|null
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
            'UUID',
            'ErrorMessage',
            'HasWarnings',
            'Notified',
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
        } else {
            $clientData = $this->showClientData();
            if ($clientData) {
                $fields->addFieldsToTab('Root.Main', LiteralField::create('clientdata', $clientData));
            }
        }

        return $fields;
    }

    /**
     * Get the client data in HTML format
     * @return false|mixed
     * @throws \ReflectionException
     */
    private function showClientData()
    {
        $warnings = [];
        $reports = '';

        $res = $this->getConnectionReport();

        if ($this->ClientData) {
            $helper = new ClientHelper($this);
            $encHelper = new EncryptionHelper($helper->getEncryptionSecret(), $helper->getEncryptionSalt());
            $clientData = $encHelper->decrypt($this->ClientData);
            if ($clientData) {
                $clientDataArray = unserialize($clientData);

                //Find all the classes which implement our client interface and see if the data array contains something for them
                $clientClasses = ClassInfo::implementorsOf(MonitoringClientInterface::class);
                foreach ($clientClasses as $fqcn) {

                    $ref = new \ReflectionClass($fqcn);
                    $monitorClass = $ref->newInstance();
                    $monitorName = $monitorClass->getClientName();

                    if (isset($clientDataArray[$monitorName])) {
                        $reports .= $monitorClass->getReport($clientDataArray[$monitorName]);
                        $monitorWarnings = $monitorClass->getWarnings($clientDataArray[$monitorName]);
                        if ($monitorWarnings) {
                            $warnings = array_merge($warnings, $monitorWarnings);
                        }
                    }
                }
            }

            $res .= $this->getWarningsMarkup($warnings);
            $res .= $reports;
        }


        return $res;
    }


    /**
     * Generate some markup for the warnings
     * @param array $warnings
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    private function getWarningsMarkup(array $warnings) {
        $warningList = ArrayList::create();
        foreach ($warnings as $warning) {
            $warningList->push(ArrayData::create([
                'Message' => $warning
            ]));
        }
        $viewer = new SSViewer('BiffBangPow/SSMonitor/Server/Client/Warnings');
        return $viewer->process(ArrayData::create([
            'Warnings' => $warningList
        ]));
    }

    /**
     * Get the connection info for the client and return an HTML snippet for the client screen
     * @return string
     */
    private function getConnectionReport()
    {
        if ($this->FetchError) {
            //Can't connect to the client
            $status = 'error';
        } else {
            $lastFetch = ($this->LastFetch) ? $this->LastFetch : 0;
            $threshold = strtotime($lastFetch) + $this->config()->get('fetch_delay_warning');
            if ($threshold <= time() && !$this->FetchError) {
                //Last connection was a while ago
                $status = 'warning';
            } else {
                //Connection OK - just report
                $status = 'ok';
            }
        }

        $viewer = new SSViewer('BiffBangPow/SSMonitor/Server/Client/ConnectionStatus');
        return $viewer->process(ArrayData::create([
            'Status' => $status,
            'LastFetch' => $this->LastFetch,
            'LastFetchFormatted' => DBDatetime::create()->setValue($this->LastFetch)->FormatFromSettings()
        ]));
    }

    /**
     * Update the warning status for the client based on the latest data
     * Generally called from onAfterWrite() to remove the need to analyse the data for every gridfield view
     * @return void
     */
    private function updateWarningStatus()
    {
        if (!$this->ClientData) {
            return;
        }
        $helper = new ClientHelper($this);
        $encHelper = new EncryptionHelper($helper->getEncryptionSecret(), $helper->getEncryptionSalt());
        $clientData = $encHelper->decrypt($this->ClientData);
        if ($clientData) {
            $res = null;
            $clientDataArray = unserialize($clientData);
            $clientClasses = ClassInfo::implementorsOf(MonitoringClientInterface::class);

            foreach ($clientClasses as $fqcn) {

                $ref = new \ReflectionClass($fqcn);
                $monitorClass = $ref->newInstance();
                $monitorName = $monitorClass->getClientName();

                //Injector::inst()->get(LoggerInterface::class)->info("Checking " . $monitorName);

                if (isset($clientDataArray[$monitorName])) {
                    if ($monitorClass->getWarnings($clientDataArray[$monitorName]) !== false) {
                        $tableName = self::getSchema()->tableName(self::class);
                        SQLUpdate::create()
                            ->setTable($tableName)
                            ->setAssignments([
                                'HasWarnings' => 1
                            ])
                            ->setWhere([
                                'ID' => $this->ID
                            ])
                            ->execute();

                        return;
                    }
                }
            }
        }
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

        //Default the warnings to false, we will update the status in onAfterWrite()
        $this->HasWarnings = false;
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->updateWarningStatus();
    }


    /**
     * @return false|string[]
     * @todo - Handle the exceptions nicely
     */
    private function generateSecurity()
    {
        try {
            $encSecret = EncryptionHelper::generateRandomString(64);
            $encSalt = EncryptionHelper::generateRandomString(32);
            $apiKey = EncryptionHelper::generateRandomString(50);

            return [
                'secret' => $encSecret,
                'salt' => $encSalt,
                'apikey' => $apiKey
            ];
        } catch (\Exception $e) {

        }
        return false;
    }

}
