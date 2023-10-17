<?php

namespace BiffBangPow\SSMonitor\Server\Extension;

use BiffBangPow\SSMonitor\Server\Client\CorePackages;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\HTML;
use UncleCheese\DisplayLogic\Forms\Wrapper;

class CorePackagesClientExtension extends DataExtension
{
    private static $db = [
        'VersionCheckSource' => 'Enum("Packagist,YML,Custom")',
        'CustomConfig' => 'Text',
        'PackagistMajorConstraint' => 'Int'
    ];

    private $customPackageVersions = false;

    public function updateCMSFields(FieldList $fields)
    {
        $updateOptions = [
            'Packagist' => _t(__CLASS__ . '.packagist', "Packagist"),
            'YML' => _t(__CLASS__ . '.ymlconfig', 'Use YML config'),
            'Custom' => _t(__CLASS__ . '.customconfig', 'Enter versions manually')
        ];

        $fields->insertAfter('Active', LiteralField::create(
            'coremoduleconfigtitle',
            HTML::createTag('h2', [],
                _t(__CLASS__ . '.coremodulesconfig', 'Core modules configuration')
            )
        ));

        $fields->insertAfter('coremoduleconfigtitle', DropdownField::create(
            'VersionCheckSource',
            _t(__CLASS__ . '.versionsource', 'Source of package versions to check core modules'),
            $updateOptions
        ));

        $fields->insertAfter('VersionCheckSource',
            NumericField::create(
                'PackagistMajorConstraint',
                _t(__CLASS__ . 'limitmajor', '.Limit to packages in this major version')
            )
                ->setDescription(
                    _t(__CLASS__ . '.leaveblankforauto', 'Leave blank to use the latest version')
                )
                ->setMaxLength(3)
                ->setHTML5(true)
                ->setAttribute('max', 99)
                ->setAttribute('min', 0)
                ->setAttribute('step', 1)
                ->displayIf("VersionCheckSource")->isEqualTo("Packagist")->end()
        );

        $fields->insertAfter('VersionCheckSource',
            Wrapper::create(
                LiteralField::create('ConfigFromYML', $this->getYMLConfigForCMS())
            )->displayIf('VersionCheckSource')->isEqualTo("YML")->end()
        );

        $fields->insertAfter('VersionCheckSource',
            Wrapper::create(
                $this->getCustomValueCMSFields()
            )->displayIf('VersionCheckSource')->isEqualTo('Custom')->end()
        );

        parent::updateCMSFields($fields);
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite(); // TODO: Change the autogenerated stub
    }


    private function getCustomValueCMSFields()
    {
        $config = CorePackages::config()->get('data_config');
        if ((!isset($config['warnings']['min_versions'])) || (!is_array($config['warnings']['min_versions']))) {
            return HTML::createTag('p', [
                'class' => 'text-error'
            ],
                _t(__CLASS__ . '.noconfigpresent', 'No YML config can be found for the core modules!'));
        }

        $fields = FieldList::create();
        $storedVersions = $this->getStoredCustomVersions();
        foreach ($config['warnings']['min_versions'] as $packageName => $versionString) {
            $value = (isset($storedVersions[$packageName])) ? $storedVersions[$packageName] : '';
            $fields->push(TextField::create($packageName, $packageName)
                ->setMaxLength(10)
                ->setValue($value));
        }

        return $fields;

    }

    /**
     * @return array
     */
    private function getStoredCustomVersions()
    {
        if (is_array($this->customPackageVersions)) {
            return $this->customPackageVersions;
        }
        $storedVersionString = $this->getOwner()->CustomConfig;
        if ($storedVersionString == '') {
            return [];
        }
        $this->customPackageVersions = json_decode($storedVersionString);
        return $this->customPackageVersions;
    }

    private function getYMLConfigForCMS()
    {
        $config = CorePackages::config()->get('data_config');
        if ((!isset($config['warnings']['min_versions'])) || (!is_array($config['warnings']['min_versions']))) {
            return HTML::createTag('p', [
                'class' => 'text-error'
            ],
                _t(__CLASS__ . '.noconfigpresent', 'No YML config can be found for the core modules!'));
        }
        $versionBlock = '';
        foreach ($config['warnings']['min_versions'] as $packageName => $versionString) {
            $versionBlock .= $packageName . ': ' . $versionString . "<br>";
        }
        return HTML::createTag('<p>', [
            'class' => 'config-text'
        ], $versionBlock);
    }
}