<?php

namespace BiffBangPow\SSMonitor\Server\Helper;

use BiffBangPow\SSMonitor\Server\Model\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\Util;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;
use function GuzzleHttp\Promise\settle;
use function GuzzleHttp\Promise\unwrap;


class CommsHelper
{

    private $guzzleClient;

    private $requests = [];


    public function __construct()
    {
        $this->guzzleClient = new \GuzzleHttp\Client(['timeout' => 5]);
    }


    /**
     * Get all the data from the clients
     * @todo Set up batch sizes, etc.
     * @return array|string
     */
    public function process()
    {
        $clients = $this->getClientList();
        /**
         * @var Client $client
         */
        foreach ($clients as $client) {
            $helper = new ClientHelper($client);
            $this->requests[] = [
                'clientid' => $client->ID,
                'url' => $helper->getMonitorURL(),
                'apikey' => $helper->getAPIKey(),
                'uuid' => $client->UUID
            ];
        }

        if (count($this->requests) > 0) {
            return $this->doCommunications();
        }

        return _t(__CLASS__ . '.nothingtoprocess', 'Nothing to process');
    }


    /**
     * Connect to all the required clients
     * @return array
     */
    private function doCommunications()
    {
        $promises = [];
        foreach ($this->requests as $request) {
            $clientID = $request['uuid'];

            $promises[$clientID] = $this->guzzleClient->postAsync(
                $request['url'], [
                    'form_params' => [
                        'key' => $request['apikey']
                    ]
                ]
            );
        }

        $promiseResponses = Utils::settle($promises)->wait();
        $clientResponses = [];


        /**
         * @var PromiseInterface $promiseResponse
         */
        foreach ($promiseResponses as $clientID => $promiseResponse) {
            if ($promiseResponse['state'] === 'fulfilled') {
                $response = $promiseResponse['value'];
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                $clientResponses[] = [
                    'client' => $clientID,
                    'status' => $statusCode,
                    'body' => $body
                ];
            } else {
                $clientResponses[] = [
                    'client' => $clientID,
                    'status' => 'failed',
                    'body' => 'Request failed'
                ];
            }
        }

        return $clientResponses;
    }

    /**
     * Get the clients needed for this round
     * @todo Set up batch sizes, frequency, etc
     */
    private function getClientList()
    {
        return Client::get()
            ->filter(['active' => true])
            ->sort('LastFetch')
            ;
    }

}
