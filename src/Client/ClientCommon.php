<?php

namespace BiffBangPow\SSMonitor\Server\Client;

use SilverStripe\Core\Extensible;

trait ClientCommon
{
    use Extensible;

    /**
     * Get the client name/identifier for this client module
     * @return string
     * @throws \Exception
     */
    public function getClientName(): string
    {
        if ($this->clientName === '') {
            throw new \Exception("No client name defined for monitoring client");
        }
        return $this->clientName;
    }

    /**
     * Get the friendly name for this client module
     * @return string
     * @throws \Exception
     */
    public function getClientTitle(): string
    {
        if ($this->config()->get('client_title')) {
            return $this->config()->get('client_title');
        }
        return $this->getClientName();
    }

    /**
     * Get an array of warning messages
     * @param $data
     * @return array|false
     */
    public function getWarnings($data)
    {
        $allWarnings = [];
        $this->extend('updateWarnings', $allWarnings, $data);
        return (count($allWarnings) > 0) ? $allWarnings : false;
    }
}
