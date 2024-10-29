<?php
/**
 * Plugin Name: Fluent Forms WPML
 * Plugin URI:  https://github.com/fluentform/fluent-forms-connector-for-mailpoet
 * Description: Add multilingual support for Fluent Forms using WPML.
 * Author: WPManageNinja LLC
 * Author URI:  https://wpmanageninja.com/wp-fluent-form/
 * Version: 1.0.0
 * Text Domain: fluentformwpml
 */

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright 2019 WPManageNinja LLC. All rights reserved.
 */

defined('ABSPATH') || exit;
define('FFWPML_DIR', plugin_dir_path(__FILE__));
define('FFWPML_URL', plugins_url('', __FILE__));

class FluentFormWpml
{
    private static $active_plugins;

    public function boot()
    {
        if (!defined('FLUENTFORM')) {
            return $this->injectDependency();
        }

        $this->includeFiles();

        if (function_exists('wpFluentForm')) {
            return $this->registerHooks(wpFluentForm());
        }
    }

    protected function includeFiles()
    {
        include_once FFWPML_DIR . 'src/Integrations/Bootstrap.php';
        include_once FFWPML_DIR . 'src/Controllers/FFWpmlSettingsController.php';
        include_once FFWPML_DIR . 'src/Helpers/FFWpmlHelper.php';
    }

    protected function registerHooks($fluentForm)
    {
        if ($this->isWpmlActive()) {
            new FluentFormWpml\Integrations\Bootstrap($fluentForm);
        }
    }

    public static function isWpmlActive()
    {
        if (!isset(self::$active_plugins)) {
            self::setActivePlugins();
        }

        return (
            in_array(
                'sitepress-multilingual-cms/sitepress.php',
                self::$active_plugins,
                true
            ) ||
            array_key_exists(
                'sitepress-multilingual-cms/sitepress.php',
                self::$active_plugins
            )
        ) && (
            in_array(
                'wpml-string-translation/plugin.php',
                self::$active_plugins,
                true
            ) ||
            array_key_exists(
                'wpml-string-translation/plugin.php',
                self::$active_plugins
            )
        );
    }

    private static function setActivePlugins()
    {
        self::$active_plugins = (array)get_option('active_plugins', array());

        if (is_multisite()) {
            self::$active_plugins = array_merge(self::$active_plugins,
                get_site_option('active_sitewide_plugins', array()));
        }
    }

    /**
     * Notify the user about the FluentForm dependency and instructs to install it.
     */
    protected function injectDependency()
    {
        add_action('admin_notices', function() {
            $pluginInfo = $this->getFluentFormInstallationDetails();

            $class = 'notice notice-error';

            $install_url_text = 'Click Here to Install the Plugin';

            if ($pluginInfo->action == 'activate') {
                $install_url_text = 'Click Here to Activate the Plugin';
            }

            $message = 'FluentForm WPML Add-On Requires Fluent Forms Add On Plugin, <b><a href="' . $pluginInfo->url
                       . '">' . $install_url_text . '</a></b>';

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        });
    }

    protected function getFluentFormInstallationDetails()
    {
        $activation = (object)[
            'action' => 'install',
            'url'    => ''
        ];

        $allPlugins = get_plugins();

        if (isset($allPlugins['fluentform/fluentform.php'])) {
            $url = wp_nonce_url(
                self_admin_url('plugins.php?action=activate&plugin=fluentform/fluentform.php'),
                'activate-plugin_fluentform/fluentform.php'
            );

            $activation->action = 'activate';
        } else {
            $api = (object)[
                'slug' => 'fluentform'
            ];

            $url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=' . $api->slug),
                'install-plugin_' . $api->slug
            );
        }

        $activation->url = $url;

        return $activation;
    }
}

register_activation_hook(__FILE__, function() {
    $globalModules = get_option('fluentform_global_modules_status');
    if (!$globalModules || !is_array($globalModules)) {
        $globalModules = [];
    }

    $globalModules['ff_wpml'] = 'yes';
    update_option('fluentform_global_modules_status', $globalModules);
});

add_action('fluentform/loaded', function() {
    if (!function_exists('icl_t')) {
        return;
    }

    (new FluentFormWpml())->boot();
});
