<?php

namespace BiffBangPow\SSMonitor\Server\Admin;

use BiffBangPow\SSMonitor\Server\Model\Client;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class \BiffBangPow\SSMonitor\Server\Admin\ClientAdmin
 *
 */
class ClientAdmin extends ModelAdmin
{
    private static $menu_icon_class = 'font-icon-flow-tree';

    private static $menu_title = 'Monitoring Client Admin';

    private static $url_segment = 'bbp-monitoring';

    private static $managed_models = [
      Client::class
    ];
}
