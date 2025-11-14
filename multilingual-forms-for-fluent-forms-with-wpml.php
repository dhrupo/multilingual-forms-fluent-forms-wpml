<?php
/**
 * Plugin Name: Multilingual Forms for Fluent Forms with WPML
 * Description: Add multilingual form support for Fluent Forms using WPML.
 * Author: dhrupo, pyrobd
 * Plugin URI: https://github.com/dhrupo/fluent-forms-wpml
 * Author URI: https://github.com/dhrupo/
 * Version: 1.0.1
 * License: GPLv2 or later
 * Text Domain: multilingual-forms-fluent-forms-wpml
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: fluentform
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
 * Copyright 2025 WPManageNinja LLC. All rights reserved.
 */

defined('ABSPATH') || exit;
define('MFFFWPML_DIR', plugin_dir_path(__FILE__));
define('MFFFWPML_URL', plugins_url('', __FILE__));
defined('MFFFWPML_VERSION') or define('MFFFWPML_VERSION', '1.0.1');

class MultilingualFormsFluentFormsWpml
{
    private static $activePlugins;

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
        include_once MFFFWPML_DIR . 'src/Controllers/SettingsController.php';
    }

    protected function registerHooks($fluentForm)
    {
        if ($this->isWpmlActive()) {
            new MultilingualFormsFluentFormsWpml\Controllers\SettingsController($fluentForm);
        }
    }

    public static function isWpmlActive()
    {
        if (!isset(self::$activePlugins)) {
            self::setActivePlugins();
        }

        return (
            in_array(
                'sitepress-multilingual-cms/sitepress.php',
                self::$activePlugins,
                true
            ) ||
            array_key_exists(
                'sitepress-multilingual-cms/sitepress.php',
                self::$activePlugins
            )
        ) && (
            in_array(
                'wpml-string-translation/plugin.php',
                self::$activePlugins,
                true
            ) ||
            array_key_exists(
                'wpml-string-translation/plugin.php',
                self::$activePlugins
            )
        );
    }

    private static function setActivePlugins()
    {
        self::$activePlugins = (array)get_option('active_plugins', array());

        if (is_multisite()) {
            self::$activePlugins = array_merge(self::$activePlugins,
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

            printf(
                '<div class="%1$s"><p>%2$s</p></div>',
                esc_attr($class),
                wp_kses_post(sprintf(
                    'Fluent Forms WPML Add-On Requires Fluent Forms Plugin, <b><a href="%s">%s</a></b>',
                    esc_url($pluginInfo->url),
                    esc_html($install_url_text)
                ))
            );
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

add_action('fluentform/loaded', function() {
    if (!function_exists('icl_t')) {
        return;
    }

    (new MultilingualFormsFluentFormsWpml())->boot();
});
