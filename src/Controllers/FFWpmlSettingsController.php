<?php

namespace FluentFormWpml\Controllers;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Models\Form;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormWpml\Helpers\FFWpmlHelper;
use WPML_Language_Of_Domain;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class FFWpmlSettingsController
{
    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        add_action('plugins_loaded', [$this, 'loadLanguage'], 11);
        add_filter('wpml_get_translatable_types', [$this, 'getTranslatableTypes'], 10, 1);
        add_filter('fluentform/ajax_url', [$this, 'setAjaxLanguage'], 10, 1);
        add_filter('fluentform/rendering_form', [$this, 'setWpmlForm'], 10, 1);
        add_filter('fluentform/recaptcha_lang', [$this, 'setRecaptchaLanguage'], 10, 1);

        $this->handleAdmin();
    }

    public function handleAdmin()
    {
        add_filter('fluentform/form_settings_ajax', [$this, 'injectInFormSettings'], 10, 2);
        add_filter('fluentform/form_fields_update', [$this, 'handleFormFieldUpdate'], 10, 2);
        add_action('fluentform/after_save_form_settings', [$this, 'saveFormSettings'], 10, 2);
        add_action('fluentform/after_form_delete', [$this, 'clearWpmlSettings'], 10, 1);
    }

    public static function setRecaptchaLanguage($language)
    {
        $currentLanguage = FFWpmlHelper::getCurrentLanguage();
        $allowed = Helper::locales('captcha');

        if (isset($allowed[$currentLanguage])) {
            $language = $currentLanguage;
        }

        return $language;
    }

    public function injectInFormSettings($settings, $formId)
    {
        try {
            global $sitepress;
            $form = Form::find($formId);
            $formFields = FormFieldsParser::getFields($form);

            $extractedFields = [];
            foreach ($formFields as $field) {
                $this->extractFieldStrings($extractedFields, $field, $formId);
            }

            $languages = $sitepress->get_active_languages();
            $defaultLanguage = self::getStringLanguage();

            $translatableStrings = [
                'strings'             => [],
                'available_languages' => $languages,
                'default_language'    => $defaultLanguage,
                'enabled'             => 'no'
            ];

            $wpmlSettings = Helper::getFormMeta($formId, 'ff_wpml', []);

            foreach ($extractedFields as $key => $data) {
                $translatableStrings['strings'][] = [
                    'name'         => $key,
                    'value'        => $data['value'],
                    'identifier'   => $data['identifier'],
                    'translations' => [],
                    'status'       => []
                ];
            }

            if ($wpmlSettings) {
                $translatableStrings = wp_parse_args($wpmlSettings, $translatableStrings);
            }

            // Update existing strings and add new ones
            foreach ($extractedFields as $key => $data) {
                $existingIndex = $this->findExistingStringIndex($translatableStrings['strings'], $key);

                if ($existingIndex !== false) {
                    // Update existing string
                    $translatableStrings['strings'][$existingIndex]['value'] = $data['value'];
                    $translatableStrings['strings'][$existingIndex]['identifier'] = $data['identifier'];
                } else {
                    // Add new string
                    $translatableStrings['strings'][] = [
                        'name'         => $key,
                        'value'        => $data['value'],
                        'identifier'   => $data['identifier'],
                        'translations' => [],
                        'status'       => []
                    ];
                }
            }

            // Remove strings that no longer exist in extractedFields
            $translatableStrings['strings'] = array_filter($translatableStrings['strings'], function($string) use ($extractedFields) {
                return isset($extractedFields[$string['name']]);
            });

            $settings['ff_wpml'] = $translatableStrings;
        } catch (\Exception $ex) {
            wp_send_json_error('Cannot get WPML translations for Fluent Forms: ' . $ex->getMessage(), 422);
        }

        return $settings;
    }

    // Helper function to find existing string index
    private function findExistingStringIndex($strings, $name)
    {
        foreach ($strings as $index => $string) {
            if ($string['name'] === $name) {
                return $index;
            }
        }
        return false;
    }

    // Extract all translatable strings form fields
    private function extractFieldStrings(&$fields, $field, $formId)
    {
        $fieldName = $field->attributes->name;

        // Register label
        if (!empty($field->settings->label)) {
            $key = "{$formId}_**_{$fieldName}_**_label";
            $fields[$key] = [
                'identifier' => "{$fieldName}->Label",
                'value'      => $field->settings->label
            ];
        }

        // Register placeholder
        if (!empty($field->attributes->placeholder)) {
            $key = "{$formId}_**_{$fieldName}_**_placeholder";
            $fields[$key] = [
                'identifier' => "{$fieldName}->Placeholder",
                'value'      => $field->attributes->placeholder
            ];
        }

        // Register help message
        if (!empty($field->settings->help_message)) {
            $key = "{$formId}_**_{$fieldName}_**_help_message";
            $fields[$key] = [
                'identifier' => "{$fieldName}->Help Message",
                'value'      => $field->settings->help_message
            ];
        }

        // Register validation messages
        if (!empty($field->settings->validation_rules)) {
            foreach ($field->settings->validation_rules as $rule => $details) {
                if ($details->value && !empty($details->message)) {
                    $key = "{$formId}_**_{$fieldName}_**_{$rule}";
                    $fields[$key] = [
                        'identifier' => "{$fieldName}->Validation Rules->{$rule}",
                        'value'      => $details->message
                    ];
                } elseif ($details->global && !empty($details->global_message)) {
                    $key = "{$formId}_**_{$fieldName}_**_{$rule}";
                    $fields[$key] = [
                        'identifier' => "{$fieldName}->Validation Rules->{$rule}",
                        'value'      => $details->message
                    ];
                }
            }
        }

        // Register advanced options (for radio, checkbox, etc.)
        if (!empty($field->settings->advanced_options)) {
            foreach ($field->settings->advanced_options as $option) {
                if (!empty($option->label)) {
                    $key = "{$formId}_**_{$fieldName}_**_{$option->value}";
                    $fields[$key] = [
                        'identifier' => "{$field->attributes->name}->Options->{$option->value}",
                        'value'      => $option->label
                    ];
                }
            }
        }

        // Register inventory stockout message
        if (!empty($field->settings->inventory_stockout_message)) {
            $key = "{$formId}_**_{$fieldName}_**_stock_out_message";
            $fields[$key] = [
                'identifier' => "{$field->attributes->name}->Inventory Stock Out",
                'value'      => $field->settings->inventory_stockout_message
            ];
        }

        // Handle complex fields (like name, address)
        if (in_array($field->element, ['input_name', 'address']) && !empty($field->fields)) {
            foreach ($field->fields as $subField) {
                $this->extractFieldStrings($fields, $subField, $formId);
            }
        }
    }

    public function handleFormFieldUpdate($formFields, $formId)
    {
        $wpmlSettings = Helper::getFormMeta($formId, 'ff_wpml', []);

        if ($wpmlSettings) {
            $decodedFields = json_decode($formFields, true);
            $result = $this->updateWpmlSettingsString($wpmlSettings, $decodedFields);

            // Handle removed strings
            if (!empty($result['removed'])) {
                $this->removeStringsFromWPML($result['removed']);
            }

            // Handle updated strings
            if (!empty($result['updated'])) {
                $this->updateStringsInWPML($result['updated'], $decodedFields);
            }

            // Handle new strings
            $newStrings = $this->extractNewStrings($wpmlSettings, $decodedFields, $formId);
            if (!empty($newStrings)) {
                $wpmlSettings['strings'] = array_merge($wpmlSettings['strings'], $newStrings);
                $this->registerNewStringsInWPML($newStrings);
            }

            Helper::setFormMeta($formId, 'ff_wpml', $wpmlSettings);
        }

        return $formFields;
    }

    private function removeStringsFromWPML($stringNames)
    {
        global $wpdb;

        foreach ($stringNames as $stringName) {
            // First, get the string ID from icl_strings
            $stringId = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}icl_strings WHERE name = %s",
                $stringName
            ));

            if ($stringId) {
                // Delete from icl_string_translations
                $wpdb->delete(
                    $wpdb->prefix . 'icl_string_translations',
                    ['string_id' => $stringId],
                    ['%d']
                );

                // Delete from icl_strings
                $wpdb->delete(
                    $wpdb->prefix . 'icl_strings',
                    ['id' => $stringId],
                    ['%d']
                );
            }
        }
    }

    private function updateWpmlSettingsString(&$wpmlSettings, $formFields)
    {
        $removedStrings = [];
        $updatedStrings = [];

        foreach ($wpmlSettings['strings'] as $key => $string) {
            $name = ArrayHelper::get($string, 'name');
            $parts = explode('_**_', $name);
            if (count($parts) === 3) {
                $fieldName = $parts[1] ?? '';
                $attribute = $parts[2] ?? '';

                if ($fieldName) {
                    $field = $this->findFieldByName($formFields['fields'], $fieldName);

                    if ($field) {
                        $newValue = $this->getFieldValue($field, $attribute);

                        if ($newValue !== null && $newValue !== $string['value']) {
                            $wpmlSettings['strings'][$key]['value'] = $newValue;
                            $updatedStrings[$name] = $newValue;
                        }
                    } else {
                        // Field no longer exists in the form
                        $removedStrings[] = $name;
                        unset($wpmlSettings['strings'][$key]);
                    }
                }
            }
        }

        // Re-index the array after removing elements
        $wpmlSettings['strings'] = array_values($wpmlSettings['strings']);

        return [
            'removed' => $removedStrings,
            'updated' => $updatedStrings
        ];
    }

    private function findFieldByName($fields, $name)
    {
        foreach ($fields as $field) {
            if (ArrayHelper::get($field, 'attributes.name') == $name) {
                return $field;
            }
        }
        return null;
    }

    private function getFieldValue($field, $attribute)
    {
        if (isset($field['attributes'][$attribute])) {
            return $field['attributes'][$attribute];
        } elseif (isset($field['settings'][$attribute])) {
            return $field['settings'][$attribute];
        } elseif (isset($field['settings']['validation_rules'][$attribute]['message'])) {
            return $field['settings']['validation_rules'][$attribute]['message'];
        }
        return null;
    }

    private function extractNewStrings($wpmlSettings, $formFields, $formId)
    {
        $newStrings = [];
        $existingNames = array_column($wpmlSettings['strings'], 'name');

        foreach ($formFields['fields'] as $field) {
            $fieldName = ArrayHelper::get($field, 'attributes.name');
            if ($fieldName) {
                $this->extractFieldStrings($newStrings, $field, $formId);
            }
        }

        return array_filter($newStrings, function($key) use ($existingNames) {
            return !in_array($key, $existingNames);
        }, ARRAY_FILTER_USE_KEY);
    }

    private function updateStringsInWPML($updatedStrings, $formFields)
    {
        global $wpdb;

        foreach ($updatedStrings as $name => $value) {
            $wpdb->update(
                $wpdb->prefix . 'icl_strings',
                ['value' => $value],
                ['name' => $name],
                ['%s'],
                ['%s']
            );
        }
    }

    private function registerNewStringsInWPML($newStrings)
    {
        $this->updateOrInsertStrings($newStrings);
        $this->updateTranslations($newStrings);
    }

    public function saveFormSettings($formId, $settings)
    {
        $ffWpml = ArrayHelper::get($settings, 'ff_wpml', []);
        $wpmlSettings = is_string($ffWpml) ? json_decode($ffWpml, true) : $ffWpml;
        $isEnabled = ArrayHelper::get($wpmlSettings, 'enabled') === 'yes';

        if ($isEnabled) {
            $strings = ArrayHelper::get($wpmlSettings, 'strings', []);
            if ($strings) {
                $this->updateOrInsertStrings($strings);
                $this->updateTranslations($strings);
            }
            Helper::setFormMeta($formId, 'ff_wpml', $wpmlSettings);
        }
    }

    private function updateOrInsertStrings(&$strings)
    {
        global $wpdb;

        foreach ($strings as &$string) {
            $existingString = $wpdb->get_row($wpdb->prepare(
                "SELECT id, value FROM {$wpdb->prefix}icl_strings 
                WHERE context = 'fluentform' AND name = %s",
                $string['name']
            ));

            if ($existingString) {
                if ($existingString->value !== $string['value']) {
                    // Update existing string
                    $wpdb->update(
                        $wpdb->prefix . 'icl_strings',
                        [
                            'value' => $string['value'],
                        ],
                        [
                            'id' => $existingString->id
                        ]
                    );
                }
                $string['id'] = $existingString->id;
            } else {
                // Insert new string
                $wpdb->insert(
                    $wpdb->prefix . 'icl_strings',
                    [
                        'language'                => FFWpmlHelper::getDefaultLanguage(),
                        'context'                 => 'fluentform',
                        'name'                    => $string['name'],
                        'value'                   => $string['value'],
                        'domain_name_context_md5' => md5($string['name'] . 'fluentform'),
                        'status'                  => ICL_STRING_TRANSLATION_COMPLETE,
                    ]
                );
                $string['id'] = $wpdb->insert_id;
            }
        }
    }

    public static function loadLanguage()
    {
        $pluginFolder = basename(dirname(__FILE__, 2));
        load_plugin_textdomain('fluentformwpml', false, $pluginFolder . '/languages/');
    }

    public static function getTranslatableTypes($types)
    {
        $slug = 'fluentform';
        $name = 'Fluentform';

        if (isset($types[$slug])) {
            return $types;
        }

        $type = new \stdClass();
        $type->name = $slug;
        $type->label = $name;
        $type->prefix = 'package';
        $type->external_type = 1;

        $type->labels = new \stdClass();
        $type->labels->singular_name = $name;
        $type->labels->name = $name;

        $types[$slug] = $type;

        return $types;
    }

    public function setAjaxLanguage($url)
    {
        global $sitepress;
        if (is_object($sitepress)) {
            $url = add_query_arg(['lang' => $sitepress->get_current_language()], $url);
        }
        return $url;
    }

    public function setWpmlForm($form)
    {
        global $wpdb;
        $formId = $form->id;
        $formStrings = ArrayHelper::get(FFWpmlHelper::getStringsForForm($formId), 'strings');

        if (!$formStrings) {
            return $form;
        }

        $currentLanguage = FFWpmlHelper::getCurrentLanguage();

        foreach ($formStrings as $string) {
            $stringName = $string['name'];

            $translatedValue = $wpdb->get_var($wpdb->prepare(
                "SELECT t.value
                FROM {$wpdb->prefix}icl_string_translations t
                JOIN {$wpdb->prefix}icl_strings s ON s.id = t.string_id
                WHERE s.context = 'fluentform'
                AND s.name = %s
                AND t.language = %s",
                $stringName,
                $currentLanguage
            ));

            $this->updateFormString($form, $stringName, $translatedValue);
        }

        return $form;
    }

    public function updateFormString(&$form, $stringName, $value)
    {
        $parts = explode('_**_', $stringName);
        $fieldName = $parts[1]; // Assuming format is always "{formId}_**_{fieldName}_**_{type}"
        $type = end($parts);

        foreach ($form->fields as &$fieldGroup) {
            foreach ($fieldGroup as &$field) {
                if (isset($field['attributes']['name']) && $field['attributes']['name'] == $fieldName) {
                    $this->updateFieldValue($field, $type, $value);
                    return;
                } elseif (($field['element'] == 'input_name' || $field['element'] == 'address') && isset($field['fields'])) {
                    foreach ($field['fields'] as &$subField) {
                        if (isset($subField['attributes']['name']) && $subField['attributes']['name'] == $fieldName) {
                            $this->updateFieldValue($subField, $type, $value);
                            return;
                        }
                    }
                }
            }
        }
    }

    private function updateFieldValue(&$field, $type, $value)
    {
        switch ($type) {
            case 'placeholder':
                if (isset($field['attributes']['placeholder'])) {
                    $field['attributes']['placeholder'] = $value;
                }
                break;
            case 'label':
                if (isset($field['settings']['label'])) {
                    $field['settings']['label'] = $value;
                }
                break;
            case 'stock_out_message':
                if (isset($field['settings']['inventory_stockout_message'])) {
                    $field['settings']['inventory_stockout_message'] = $value;
                }
                break;
            case 'help_message':
                if (isset($field['settings']['help_message'])) {
                    $field['settings']['help_message'] = $value;
                }
                break;
            default:
                // Handle validation messages
                if (isset($field['settings']['validation_rules'])) {
                    foreach ($field['settings']['validation_rules'] as $ruleName => &$rule) {
                        if ($ruleName == $type && isset($rule['message'])) {
                            $rule['message'] = $value;
                        }
                    }
                }
                // Handle advanced options
                if (isset($field['settings']['advanced_options'])) {
                    foreach ($field['settings']['advanced_options'] as &$option) {
                        if (isset($option['value']) && $option['value'] == $type && isset($option['label'])) {
                            $option['label'] = $value;
                        }
                    }
                }
                break;
        }
    }

    public static function getStringLanguage()
    {
        global $sitepress;

        if (class_exists('WPML_Language_Of_Domain')) {
            $languageOfDomain = new WPML_Language_Of_Domain($sitepress);
            $defaultLanguage = $languageOfDomain->get_language('fluentform');
            if (!$defaultLanguage) {
                $defaultLanguage = FFWpmlHelper::getDefaultLanguage();
            }
        } else {
            global $sitepress_settings;
            $defaultLanguage = !empty($sitepress_settings['st']['strings_language']) ? $sitepress_settings['st']['strings_language'] : FFWpmlHelper::getDefaultLanguage();
        }

        return $defaultLanguage;
    }

    public static function updateTranslations($strings)
    {
        foreach ($strings as $string) {
            if ($stringId = ArrayHelper::get($string, 'id')) {
                $stringName = $string['name'];
                $translations = ArrayHelper::get($string, 'translations', []);

                if ($translations) {
                    foreach ($translations as $languageKey => $translation) {
                        if (!empty($translation)) {
                            try {
                                FFWpmlHelper::updateStringTranslation($stringId, $stringName, $languageKey,
                                    $translation);
                            } catch (\Exception $e) {
                                error_log("Error updating translation for string $stringName: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }

    public function clearWpmlSettings($formId)
    {
        global $wpdb;

        // Get all strings related to this form
        $strings = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}icl_strings 
            WHERE context = 'fluentform' AND name LIKE %s",
            $formId . '_**_%'
        ), ARRAY_A);

        if (empty($strings)) {
            return;
        }

        $stringIds = wp_list_pluck($strings, 'id');

        // Delete translations
        if (!empty($stringIds)) {
            $wpdb->query(
                "DELETE FROM {$wpdb->prefix}icl_string_translations 
                WHERE string_id IN (" . implode(',', $stringIds) . ")"
            );
        }

        // Delete strings
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}icl_strings 
            WHERE context = 'fluentform' AND name LIKE %s",
            $formId . '_**_%'
        ));
    }
}