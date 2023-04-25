<?php

namespace BiffBangPow\SSMonitor\Server\Client;

trait ClientCommon
{
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
}
