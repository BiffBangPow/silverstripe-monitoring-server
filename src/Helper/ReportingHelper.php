<?php

namespace BiffBangPow\SSMonitor\Server\Helper;

class ReportingHelper
{

    /**
     * Compares Semver versions
     * Returns true is the version if lower than the threshold, false if the version is equal or greater
     * @param $version
     * @param $threshold
     * @return bool
     */
    public static function isVersionLess($version, $threshold)
    {
        $versionParts = explode('.', $version);
        $thresholdParts = explode('.', $threshold);

        // Compare major version numbers
        if ($versionParts[0] < $thresholdParts[0]) {
            return true;
        } elseif ($versionParts[0] > $thresholdParts[0]) {
            return false;
        }

        // Compare minor version numbers
        if ($versionParts[1] < $thresholdParts[1]) {
            return true;
        } elseif ($versionParts[1] > $thresholdParts[1]) {
            return false;
        }

        // Compare patch version numbers - default to zero if someone forgot to specify
        if (!isset($versionParts[2])) {
            $versionParts[2] = 0;
        }
        if (!isset($thresholdParts[2])) {
            $thresholdParts[2] = 0;
        }
        if ($versionParts[2] < $thresholdParts[2]) {
            return true;
        } elseif ($versionParts[2] > $thresholdParts[2]) {
            return false;
        }

        // Versions are equal or threshold is invalid
        return false;
    }


    /**
     * Convert a memory string to bytes
     * @param $memoryString
     * @return int
     */
    public static function convertMemoryStringToBytes($memoryString)
    {
        $unit = strtoupper(substr($memoryString, -1));
        $size = (int)substr($memoryString, 0, -1);

        switch ($unit) {
            case 'K':
                $size *= 1024;
                break;
            case 'M':
                $size *= 1048576;
                break;
            case 'G':
                $size *= 1073741824;
                break;
            default:
                // Assume bytes if no matching unit is provided
                break;
        }

        return $size;
    }

}
