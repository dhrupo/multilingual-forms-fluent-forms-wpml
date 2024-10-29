<?php

namespace FluentFormWpml\Helpers;

use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class FFWpmlHelper
{
    public static function getDefaultLanguage()
    {
        return apply_filters('wpml_default_language', null);
    }

    public static function getCurrentLanguage()
    {
        return apply_filters('wpml_current_language', null);
    }

    public static function getStringsForForm($formId)
    {
        global $wpdb;

        $queryArgs = ['fluentform'];
        $like = 'name LIKE %s';
        $queryArgs[] = "$formId\_%";

        $query = $wpdb->prepare(
            "SELECT id, name, value, language 
            FROM {$wpdb->prefix}icl_strings 
            WHERE context=%s AND $like
            ORDER BY name DESC",
            $queryArgs
        );

        return ['strings' => $wpdb->get_results($query, ARRAY_A)];
    }

    public static function updateStringTranslation($stringId, $stringName, $language, $value)
    {
        global $wpdb;

        $status = empty($value) ? ICL_STRING_TRANSLATION_NOT_TRANSLATED : ICL_TM_COMPLETE;

        // Check if the string exists in icl_strings
        $string = $wpdb->get_row($wpdb->prepare(
            "SELECT id, value FROM {$wpdb->prefix}icl_strings 
            WHERE id = %d AND name = %s",
            $stringId,
            $stringName
        ));

        if (!$string) {
            // If the string doesn't exist in icl_strings, we can't add a translation
            error_log("String not found in icl_strings: ID = $stringId, Name = $stringName");
            return;
        }

        // Check if a translation already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, value FROM {$wpdb->prefix}icl_string_translations 
            WHERE string_id = %d AND language = %s",
            $stringId,
            $language
        ));

        if ($existing) {
            // Update existing translation
            if ($existing->value !== $value) {
                $wpdb->update(
                    $wpdb->prefix . 'icl_string_translations',
                    [
                        'value' => $value,
                        'status' => $status,
                        'translation_date' => current_time('mysql')
                    ],
                    [
                        'id' => $existing->id
                    ]
                );
            }
        } else {
            // Insert new translation
            $wpdb->insert(
                $wpdb->prefix . 'icl_string_translations',
                [
                    'string_id' => $stringId,
                    'language' => $language,
                    'value' => $value,
                    'status' => $status,
                    'translation_date' => current_time('mysql')
                ]
            );
        }

        // If this is an update to the original language, update all other translations to 'needs update'
        if ($language === self::getDefaultLanguage()) {
            $wpdb->update(
                $wpdb->prefix . 'icl_string_translations',
                ['status' => ICL_STRING_TRANSLATION_NEEDS_UPDATE],
                ['string_id' => $stringId, 'language' => $language, 'status' => ICL_TM_COMPLETE]
            );
        }
    }

    public static function unregisterString($name)
    {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'icl_strings',
            ['context' => 'fluentform', 'name' => $name]
        );

        $stringId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = 'fluentform' AND name = %s",
            $name
        ));

        if ($stringId) {
            $wpdb->delete(
                $wpdb->prefix . 'icl_string_translations',
                ['string_id' => $stringId]
            );
        }
    }
}