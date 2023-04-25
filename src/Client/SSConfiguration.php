<?php

namespace BiffBangPow\SSMonitor\Server\Client;

use BiffBangPow\SSMonitor\Server\Helper\ReportingHelper;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

class SSConfiguration implements MonitoringClientInterface
{

    use ClientCommon;
    use Configurable;
    use Extensible;

    /**
     * @var string
     */
    private string $clientName = 'silverstripeconfig';

    /**
     * @config
     * @var string
     */
    private static $client_title = 'Silverstripe Configuration';

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

    public function getWarnings($data)
    {
        //Injector::inst()->get(LoggerInterface::class)->info(print_r($data, true));
        $this->clientData = $data;
        $this->checkEnvironment();
        $this->checkDefaultAdmin();
        $allWarnings = $this->warnings;
        $this->extend('updateWarnings', $allWarnings, $data);
        return (count($allWarnings) > 0) ? $allWarnings : false;
    }

    public function getReport($data)
    {
        $this->clientData = $data;
        return $this->generateReportsHTML();
    }

    private function checkDefaultAdmin()
    {
        $config = $this->config()->get('data_config');
        if ((isset($config['warnings']['required_default_admin'])) && (isset($this->clientData['defaultadmin']))) {
            $adminSet = $this->clientData['defaultadmin']['value'];
            $requiredAdmin = $config['warnings']['required_default_admin'];
            if ($adminSet !== $requiredAdmin) {
                $this->warnings[] = _t(
                    __CLASS__ . '.envwarning',
                    'Default admin credentials are present in the environment'
                );
            }
        }
    }

    private function checkEnvironment()
    {
        $config = $this->config()->get('data_config');
        if ((isset($config['warnings']['required_environment'])) && (isset($this->clientData['envtype']))) {
            $envType = $this->clientData['envtype']['value'];
            $requiredEnv = $config['warnings']['required_environment'];
            if ($envType !== $requiredEnv) {
                $this->warnings[] = _t(
                    __CLASS__ . '.envwarning',
                    'Environment type is not correct. Required: {required}.  Actual: {actual}',
                    [
                        'required' => $requiredEnv,
                        'actual' => $envType
                    ]
                );
            }
        }
    }

    private function generateReportsHTML()
    {
        $data = $this->clientData;
        $variables = ArrayList::create();

        foreach ($data as $id => $values) {
            $variables->push(ArrayData::create([
                'Variable' => $values['label'],
                'Value' => $values['value']
            ]));
        }

        $viewer = new SSViewer('BiffBangPow/SSMonitor/Server/Module/SSConfiguration');
        return $viewer->process(ArrayData::create([
            'Title' => $this->getClientTitle(),
            'Variables' => $variables
        ]));
    }


}
