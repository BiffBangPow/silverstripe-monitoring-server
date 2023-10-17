<?php

namespace BiffBangPow\SSMonitor\Server\Task;

use BiffBangPow\SSMonitor\Server\Helper\ReportingHelper;
use SilverStripe\Dev\BuildTask;
use GuzzleHttp\Client;

class PackagistTest extends BuildTask
{
    private static $segment = 'packagisttest';
    protected $title = 'Packagist Test';

    public function run($request)
    {
        $client = new Client();
        $res = $client->request('GET', 'https://repo.packagist.org/p2/silverstripe/cms.json');

        $fullContent = $res->getBody();
        $content = json_decode($fullContent, true);
        $entries = $content['packages']['silverstripe/cms'];

        $majors = [];
        foreach ($entries as $key => $entry) {
            $version = $entry['version_normalized'];
            $major = ReportingHelper::getMajorVersion($version);
            if (!preg_match('/beta|rc|alpha/i', $version)) {
                $majors[$major][] = $version;
            }
        }

        echo "<pre>";
        foreach ($majors as $major => $subVersions) {
            echo "\n" . $major . " - " . array_shift($subVersions);
        }
        echo "</pre>";
    }
}
