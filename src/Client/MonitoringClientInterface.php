<?php

namespace BiffBangPow\SSMonitor\Server\Client;

interface MonitoringClientInterface
{
    public function getClientTitle();

    public function getWarnings($data);

    public function getClientName();

    public function getReport($data);
}
