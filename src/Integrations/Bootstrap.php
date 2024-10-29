<?php

namespace FluentFormWpml\Integrations;

use FluentForm\App\Helpers\IntegrationManagerHelper;
use FluentFormWpml\Controllers\FFWpmlSettingsController;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Bootstrap
{
    public $globalModule = 'ff_wpml';

    public function __construct($app) {
        $this->init();
    }

    public function init()
    {
        $enabled = $this->isEnabled();

        add_filter('fluentform/global_addons', [$this, 'addToIntegrationMenu'], 10, 1);

        if (!$enabled) {
            return;
        }

        new FFWpmlSettingsController();
    }

    public function addToIntegrationMenu($addons)
    {
        $addons[$this->globalModule] = [
            'title'       => 'Fluent Forms WPML',
            'category'    => 'wp_core',
            'description' => __('Fluent Forms with WPML Multilingual Support', 'fluentformpro'),
            'logo'        =>  fluentformMix('img/integrations/admin_approval.png'),
            'enabled'     => ($this->isEnabled()) ? 'yes' : 'no',
        ];
        return $addons;
    }

    public function isEnabled()
    {
        return IntegrationManagerHelper::isIntegrationEnabled($this->globalModule);
    }
}
