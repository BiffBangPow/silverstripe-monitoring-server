<?php

namespace BiffBangPow\SSMonitor\Server\Task;

use BiffBangPow\SSMonitor\Server\Helper\ClientHelper;
use BiffBangPow\SSMonitor\Server\Helper\CommsHelper;
use BiffBangPow\SSMonitor\Server\Helper\EncryptionHelper;
use BiffBangPow\SSMonitor\Server\Model\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;


class PollClientsTask extends AbstractQueuedJob
{
    use Configurable;
    use Extensible;

    /**
     * @config
     * @var int
     */
    private static $requeue_delay = 180;

    public function __construct()
    {

    }

    public function getTitle()
    {
        return "BBP Monitoring - Collect data";
    }

    public function process()
    {
        $helper = new CommsHelper();
        $res = $helper->process();

        if (is_array($res)) {
            $this->updateClients($res);
        } else {
            $this->addMessage($res);
        }

        $this->isComplete = true;
        $this->addMessage('Done');
    }

    /**
     * @param array $res
     * @return string
     */
    private function updateClients($res)
    {
        foreach ($res as $clientData) {
            $clientID = $clientData['client'];
            $httpStatus = $clientData['status'];
            $body = $clientData['body'];

            if ((is_int($httpStatus)) && ($httpStatus < 300)) {
                //We have a response
                $this->updateClientData($clientID, $body);
            } else if ((is_int($httpStatus)) && ($httpStatus < 500)) {
                //404 error - log it and fail it
                $this->failClient(
                    $clientID,
                    _t(__CLASS__ . '.40xerror', 'The client failed to respond.  Please ensure the client module is installed and the API keys are set up correctly')
                );
            } else {
                $this->failClient(
                    $clientID,
                    _t(__CLASS__ . '.50xerror', 'The client failed to respond. Please check it is online and configured properly.')
                );
            }
        }
    }


    private function updateClientData($clientID, $data)
    {
        $client = Client::getByUUID($clientID);
        $clientHelper = new ClientHelper($client);
        if ($client) {
            $clientSecret = $clientHelper->getEncryptionSecret();
            $clientSalt = $clientHelper->getEncryptionSalt();

            $encHelper = new EncryptionHelper($clientSecret, $clientSalt);
            $clientData = $encHelper->decrypt($data);

            if (!$clientData) {
                $this->failClient($clientID, "Error decrypting response data");
                return;
            }

            $clientDataArray = unserialize($clientData);

            if ((!isset($clientDataArray['clientid'])) || ($clientDataArray['clientid'] !== $clientID)) {
                $this->failClient($clientID, "UUID from client either not set, or incorrect");
                return;
            }

            $client->update([
                'LastFetch' => DBDatetime::now()->format('y-MM-dd HH:mm:ss'),
                'ErrorMessage' => $message,
                'FetchError' => false,
                'ClientData' => $data,
                'Notified' => false
            ]);
            $client->write();
            $this->extend('OnAfterClientUpdate', $client);
            return;

        } else {
            $this->failClient($clientID, 'Unknown UUID');
            return;
        }
        $this->failClient($clientID, 'An unknown error occurred');
    }


    /**
     * @param string $clientID
     * @return void
     * @throws \SilverStripe\ORM\ValidationException
     */
    private function failClient($clientID, $message)
    {
        $client = Client::getByUUID($clientID);
        if ($client) {
            $client->FetchError = true;
            $client->ErrorMessage = $message;
            $client->write();
            $clientMessage = 'Client: ' . $clientID . ' (' . $client->Title . ') ' . ' : ' . $message;
            $this->addMessage($clientMessage);
            $this->notifySlack($client, $clientMessage);
            $this->extend('OnAfterClientFail', $clientID);
        } else {
            $this->addMessage('Cannot find client with ID ' . $clientID);
        }
    }

    /**
     * @param Client $client
     * @param $message
     * @return void
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function notifySlack($client, $message = null)
    {
        $webhook = Environment::getEnv('SLACK_WEBHOOK');
        $channel = Environment::getEnv('SLACK_CHANNEL');
        if ($channel && $webhook && $client->Notified == 0) {
            Injector::inst()->get(LoggerInterface::class . '.SlackLogger')
                ->error($message);
            $client->update([
                'Notified' => true
            ]);
            $client->write();
        }
    }

    public function afterComplete()
    {
        $requeue_delay = $this->config()->get('requeue_delay');
        Injector::inst()->get(LoggerInterface::class)->info("Client job finished.  Requeuing in " . $requeue_delay . " seconds");

        $newJob = new PollClientsTask();
        if ($requeue_delay > 0) {
            singleton(QueuedJobService::class)
                ->queueJob(
                    $newJob,
                    date(
                        'Y-m-d H:i:s',
                        strtotime('+' . $requeue_delay . ' seconds')
                    )
                );
        } else {
            singleton(QueuedJobService::class)->queueJob($newJob);
        }

        parent::afterComplete();
    }

}
