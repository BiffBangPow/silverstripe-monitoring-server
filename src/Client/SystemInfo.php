<?php

namespace BiffBangPow\SSMonitor\Server\Client;

use BiffBangPow\SSMonitor\Server\Helper\ReportingHelper;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;
use Psr\Log\LoggerInterface;
use SilverStripe\View\HTML;

class SystemInfo implements MonitoringClientInterface
{
    use Configurable;
    use ClientCommon;

    /**
     * @var string
     */
    private string $clientName = 'systeminfo';

    /**
     * @config
     * @var string
     */
    private static $client_title = 'System info';

    /**
     * @config
     * @var array
     */
    private static $data_config = [];

    /**
     * @var array
     */
    private $warnings = [];

    /**
     * @var array
     */
    private $clientData = [];

    /**
     * @var string
     */
    private $reportHTML = null;


    /**
     * Gathers up any warning states for this module
     * This should return a numeric array of warning messages
     * @param array $data
     * @return array|false
     */
    public function getWarnings($data)
    {
        $this->clientData = $data;
        $this->checkMemoryLimit();
        $this->checkPHPVersion();
        return (count($this->warnings) > 0) ? $this->warnings : false;
    }

    /**
     * Generate the markup for the reporting page
     * @param $data
     * @return string
     */
    public function getReport($data)
    {
        Injector::inst()->get(LoggerInterface::class)->info(print_r($data, true));
        $this->clientData = $data;
        return $this->generateReportsHTML();
    }


    private function generateReportsHTML() {
        $data = $this->clientData;
        $variables = ArrayList::create();
        $envVariables = false;

        $env = (isset($data['environment'])) ? $data['environment'] : false;
        if ($env) {
            unset($data['environment']);
            $envVariables = ArrayList::create();
            foreach ($env['value'] as $variableName => $value) {
                $envVariables->push(ArrayData::create([
                    'Variable' => $variableName,
                    'Value' => $value
                ]));
            }
        }

        foreach ($data as $id => $values) {
            $variables->push(ArrayData::create([
                'Variable' => $values['label'],
                'Value' => $values['value']
            ]));
        }

        $viewer = new SSViewer('BiffBangPow/SSMonitor/Server/Module/SystemInfo');
        return $viewer->process(ArrayData::create([
            'Title' => $this->getClientTitle(),
            'Variables' => $variables,
            'Environment' => $envVariables
        ]));
    }



    /**
     * Check the PHP version is OK
     * @return void
     */
    private function checkPHPVersion()
    {
        $config = $this->config()->get('data_config');
        if ((isset($config['warnings']['min_php_version'])) && (isset($this->clientData['php']))) {
            $clientVersion = $this->clientData['php']['value'];
            $thresholdVersion = $config['warnings']['min_php_version'];
            if (ReportingHelper::isVersionLess($clientVersion, $thresholdVersion)) {
                $this->warnings[] = _t(
                    __CLASS__ . '.phpwarning',
                    'PHP version is below the required value.  Required: {required}.  Actual: {actual}',
                    [
                        'required' => $thresholdVersion,
                        'actual' => $clientVersion
                    ]
                );
            }
        }
    }

    /**
     * Check that the configured memory is sufficient
     * @return void
     */
    private function checkMemoryLimit()
    {
        $config = $this->config()->get('data_config');
        if ((isset($config['warnings']['min_memory_limit'])) && (isset($this->clientData['memorylimit']))) {
            $threshold = ReportingHelper::convertMemoryStringToBytes($config['warnings']['min_memory_limit']);
            $actual = ReportingHelper::convertMemoryStringToBytes($this->clientData['memorylimit']['value']);

            if ($actual < $threshold) {
                $this->warnings[] = _t(
                    __CLASS__ . '.memorywarning',
                    'Memory limit is below the required value.  Required: {required}.  Actual: {actual}',
                    [
                        'required' => $config['warnings']['min_memory_limit'],
                        'actual' => $this->clientData['memorylimit']['value']
                    ]
                );
            }
        }
    }


}
