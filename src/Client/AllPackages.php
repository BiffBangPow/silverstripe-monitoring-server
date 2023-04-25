<?php

namespace BiffBangPow\SSMonitor\Server\Client;

use BiffBangPow\SSMonitor\Server\Helper\ReportingHelper;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\SSViewer;

class AllPackages implements MonitoringClientInterface
{

    use ClientCommon;
    use Configurable;
    use Extensible;

    /**
     * @var string
     */
    private string $clientName = 'allpackages';

    /**
     * @config
     * @var string
     */
    private static $client_title = 'All installed vendor packages';

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
        $this->checkMinVersions();
        $this->checkDevPackages();
        $allWarnings = $this->warnings;
        $this->extend('updateWarnings', $allWarnings, $data);
        return (count($allWarnings) > 0) ? $allWarnings : false;
    }

    public function getReport($data)
    {
        $versions = ArrayList::create();

        foreach ($data as $packageName => $version) {
            $versions->push([
                'PackageName' => $packageName,
                'PackageVersion' => $version
            ]);
        }

        $viewer = new SSViewer('BiffBangPow/SSMonitor/Server/Module/AllPackages');
        return $viewer->process(ArrayData::create([
            'Title' => $this->getClientTitle(),
            'Packages' => $versions
        ]));
    }

    /**
     * Check to see if any of the packages contain dev versions - this is generally a bad idea!
     * @return void
     */
    private function checkDevPackages()
    {
        $config = $this->config()->get('data_config');
        if ((isset($config['warnings']['dev_packages'])) && ($config['warnings']['dev_packages'])) {
            foreach ($this->clientData as $package => $version) {
                if (stristr($version, 'dev') !== false) {
                    $this->warnings[] = _t(
                        __CLASS__ . '.devpackages',
                        'Development packages may be present in the package list'
                    );
                    return;
                }
            }
        }
    }

    private function checkMinVersions()
    {
        $config = $this->config()->get('data_config');
        if (isset($config['warnings']['min_versions'])) {
            foreach ($this->clientData as $package => $version) {
                if (isset($config['warnings']['min_versions'][$package])) {
                    $threshold = $config['warnings']['min_versions'][$package];
                    if (ReportingHelper::isVersionLess($version, $threshold)) {
                        $this->warnings[] = _t(
                            __CLASS__ . '.corepackagewarning',
                            '{package} is below the required version. Required: {required}.  Actual: {actual}',
                            [
                                'package' => $package,
                                'required' => $threshold,
                                'actual' => $version
                            ]
                        );
                    }
                }
            }
        }
    }
}
