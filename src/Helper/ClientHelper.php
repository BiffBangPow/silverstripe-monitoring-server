<?php

namespace BiffBangPow\SSMonitor\Server\Helper;

use BiffBangPow\SSMonitor\Server\Model\Client;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;

class ClientHelper
{
    const CLIENT_ENDPOINT = 'montoro';

    private Client $client;

    private $encHelper;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $storageSecret = Environment::getEnv('MONITORING_STORAGE_SECRET');
        $storageSalt = Environment::getEnv('MONITORING_STORAGE_SALT');
        $this->encHelper = new EncryptionHelper($storageSecret, $storageSalt);
    }

    /**
     * Builds the full monitoring URL for the client
     * @return string
     */
    public function getMonitorURL()
    {
        return Controller::join_links([
            $this->client->BaseURL,
            self::CLIENT_ENDPOINT
        ]);
    }

    /**
     * Get the API key for the client
     * @return false|string
     */
    public function getAPIKey()
    {
        return $this->encHelper->decrypt($this->client->APIKey);
    }

    /**
     * Get the encryption secret for the client
     * @return false|string
     */
    public function getEncryptionSecret()
    {
        return $this->encHelper->decrypt($this->client->EncSecret);
    }

    /**
     * Get the encryption salt for the client
     * @return false|string
     */
    public function getEncryptionSalt()
    {
        return $this->encHelper->decrypt($this->client->EncSalt);
    }

}
