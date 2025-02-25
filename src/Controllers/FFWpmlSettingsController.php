<?php

namespace FluentFormWpml\Controllers;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Models\Form;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class FFWpmlSettingsController
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
        $this->init();
    }

    public function init()
    {
        add_filter('fluentform/ajax_url', [$this, 'setAjaxLanguage'], 10, 1);
        add_filter('fluentform/rendering_form', [$this, 'setWpmlForm'], 10, 1);
        add_filter('fluentform/recaptcha_lang', [$this, 'setRecaptchaLanguage'], 10, 1);

        $this->handleAdmin();
    }

    public function handleAdmin()
    {
        $this->app->addAdminAjaxAction('fluentform_get_wpml_settings', [$this, 'getWpmlSettings']);
        $this->app->addAdminAjaxAction('fluentform_store_wpml_settings', [$this, 'storeWpmlSettings']);
        $this->app->addAdminAjaxAction('fluentform_delete_wpml_settings', [$this, 'removeWpmlSettings']);

        add_action('fluentform/form_settings_menu', [$this, 'pushSettings'], 10, 2);
        add_filter('fluentform/form_fields_update', [$this, 'handleFormFieldUpdate'], 10, 2);
        add_action('fluentform/after_form_delete', [$this, 'removeWpmlStrings'], 10, 1);
    }

    public function getWpmlSettings()
    {
        $request = $this->app->request->get();
        $formId = ArrayHelper::get($request, 'form_id');
        $isFFWpmlEnabled = $this->isWpmlEnabledOnForm($formId);
        wp_send_json_success($isFFWpmlEnabled);
    }

    public function storeWpmlSettings()
    {
        $request = $this->app->request->get();
        $isFFWpmlEnabled = ArrayHelper::get($request, 'is_ff_wpml_enabled', false) == 'true';
        $formId = ArrayHelper::get($request, 'form_id');

        if (!$isFFWpmlEnabled) {
            Helper::setFormMeta($formId, 'ff_wpml', false);
            wp_send_json_success(__('Translation is disabled for this form', 'fluentformwpml'));
        }

        $form = Form::find($formId);
        $formFields = FormFieldsParser::getFields($form);
        $package = $this->getFormPackage($form);
        
        $this->extractAndRegisterStrings($formFields, $formId, $package);

        Helper::setFormMeta($formId, 'ff_wpml', $isFFWpmlEnabled);
        wp_send_json_success(__('Translation is enabled for this form', 'fluentformwpml'));
    }

    public function handleFormFieldUpdate($formFields, $formId)
    {
        if (!$this->isWpmlEnabledOnForm($formId)) {
            return $formFields;
        }
        
        $form = Form::find($formId);
        $package = $this->getFormPackage($form);
        $decodedFields = json_decode($formFields);
        $fields = isset($decodedFields->fields) ? $decodedFields->fields : [];

        do_action('wpml_start_string_package_registration', $package);

        $this->extractAndRegisterStrings($fields, $formId, $package);

        do_action('wpml_delete_unused_package_strings', $package);

        return $formFields;
    }

    public function removeWpmlSettings($formId)
    {
        $request = $this->app->request->get();
        $formId = ArrayHelper::get($request, 'form_id');
        $this->removeWpmlStrings($formId);
        wp_send_json_success(__('Translations removed successfully.', 'fluentformwpml'));
    }

    public function pushSettings($settingsMenus, $formId)
    {
        if ($this->isWpmlAndStringTranslationActive()) {
            $settingsMenus['ff_wpml'] = [
                'title' => __('WPML Translations', 'fluentform'),
                'slug'  => 'ff_wpml',
                'hash'  => 'ff_wpml',
                'route' => '/ff-wpml',
            ];
        }

        return $settingsMenus;
    }

    public function isWpmlAndStringTranslationActive()
    {
        $wpmlActive = function_exists('icl_object_id');
        $wpmlStringActive = defined('WPML_ST_VERSION');

        return $wpmlActive && $wpmlStringActive;
    }

    public static function setRecaptchaLanguage($language)
    {
        $currentLanguage = apply_filters('wpml_current_language', null);
        $allowed = Helper::locales('captcha');

        if (isset($allowed[$currentLanguage])) {
            $language = $currentLanguage;
        }

        return $language;
    }

    // Extract all translatable strings form fields
    private function extractFieldStrings(&$fields, $field, $formId)
    {
        $fieldName = $field->attributes->name;

        // Register label
        if (!empty($field->settings->label)) {
            $fields["{$fieldName}->Label"] = $field->settings->label;
        }

        // Register placeholder
        if (!empty($field->attributes->placeholder)) {
            $fields["{$fieldName}->placeholder"] = $field->attributes->placeholder;
        }

        // Register help message
        if (!empty($field->settings->help_message)) {
            $fields["{$fieldName}->help_message"] = $field->settings->help_message;
        }

        // Register validation messages
        if (!empty($field->settings->validation_rules)) {
            foreach ($field->settings->validation_rules as $rule => $details) {
                if ($details->value && !empty($details->message)) {
                    $fields["{$fieldName}->Validation Rules->{$rule}"] = $details->message;
                } elseif ($details->global && !empty($details->global_message)) {
                    $fields["{$fieldName}->Validation Rules->{$rule}"] = $details->message;
                }
            }
        }

        // Register advanced options (for radio, checkbox, etc.)
        if (!empty($field->settings->advanced_options)) {
            foreach ($field->settings->advanced_options as $option) {
                if (!empty($option->label)) {
                    $fields["{$fieldName}->{$field->attributes->name}->Options->{$option->value}"] = $option->label;
                }
            }
        }

        // Register inventory stockout message
        if (!empty($field->settings->inventory_stockout_message)) {
            $fields["{$fieldName}->stock_out_message"] = $field->settings->inventory_stockout_message;
        }

        // Handle complex fields (like name, address)
        if (in_array($field->element, ['input_name', 'address']) && !empty($field->fields)) {
            foreach ($field->fields as $subField) {
                $this->extractFieldStrings($fields, $subField, $formId);
            }
        }
    }

    private function extractAndRegisterStrings($fields, $formId, $package)
    {
        $extractedFields = [];
        foreach ($fields as $field) {
            $this->extractFieldStrings($extractedFields, $field, $formId);
        }

        foreach ($extractedFields as $key => $value) {
            do_action('wpml_register_string', $value, $key, $package, $formId, 'LINE');
        }
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
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $form;
        }

        $formFields = FormFieldsParser::getFields($form);

        $extractedFields = [];
        foreach ($formFields as $field) {
            $this->extractFieldStrings($extractedFields, $field, $form->id);
        }

        foreach ($extractedFields as $key => $value) {
            $package = $this->getFormPackage($form);
            $extractedFields[$key] = apply_filters('wpml_translate_string', $value, $key, $package);
        }

        $updatedFields = $this->updateFormFieldsWithTranslations($form->fields['fields'], $extractedFields);

        $form->fields['fields'] = $updatedFields;

        return $form;
    }

    private function getFormPackage($form)
    {
        return [
            'kind'  => 'Fluent Forms',
            'name'  => $form->id,
            'title' => $form->title,
        ];
    }

    private function updateFormFieldsWithTranslations($fields, $translations)
    {
        foreach ($fields as &$field) {
            $fieldName = isset($field['attributes']['name']) ? $field['attributes']['name'] : null;

            if ($fieldName) {
                // Update label
                $labelKey = "{$fieldName}->Label";
                if (isset($translations[$labelKey])) {
                    $field['settings']['label'] = $translations[$labelKey];
                }

                // Update placeholder
                $placeholderKey = "{$fieldName}->placeholder";
                if (isset($translations[$placeholderKey]) && isset($field['attributes']['placeholder'])) {
                    $field['attributes']['placeholder'] = $translations[$placeholderKey];
                }

                // Update help message
                $helpMessageKey = "{$fieldName}->help_message";
                if (isset($translations[$helpMessageKey])) {
                    $field['settings']['help_message'] = $translations[$helpMessageKey];
                }

                // Update validation messages
                if (isset($field['settings']['validation_rules'])) {
                    foreach ($field['settings']['validation_rules'] as $ruleName => &$rule) {
                        $validationKey = "{$fieldName}->Validation Rules->{$ruleName}";
                        if (isset($translations[$validationKey])) {
                            $rule['message'] = $translations[$validationKey];
                        }
                    }
                }

                // Update options for radio, checkbox, etc.
                if (isset($field['settings']['advanced_options'])) {
                    foreach ($field['settings']['advanced_options'] as &$option) {
                        $optionKey = "{$fieldName}->{$field['attributes']['name']}->Options->{$option['value']}";
                        if (isset($translations[$optionKey])) {
                            $option['label'] = $translations[$optionKey];
                        }
                    }
                }

                // Update inventory stockout message
                $stockOutKey = "{$fieldName}->stock_out_message";
                if (isset($translations[$stockOutKey])) {
                    $field['settings']['inventory_stockout_message'] = $translations[$stockOutKey];
                }
            }

            // Handle nested fields (like for input_name)
            if (isset($field['fields']) && is_array($field['fields'])) {
                foreach ($field['fields'] as $subFieldName => &$subField) {
                    $this->updateSubField($subField, $subFieldName, $translations);
                }
            }
        }

        return $fields;
    }

    private function updateSubField(&$subField, $subFieldName, $translations)
    {
        // Update label
        $labelKey = "{$subFieldName}->Label";
        if (isset($translations[$labelKey])) {
            $subField['settings']['label'] = $translations[$labelKey];
        }

        // Update placeholder
        $placeholderKey = "{$subFieldName}->placeholder";
        if (isset($translations[$placeholderKey]) && isset($subField['attributes']['placeholder'])) {
            $subField['attributes']['placeholder'] = $translations[$placeholderKey];
        }

        // Update validation messages
        if (isset($subField['settings']['validation_rules'])) {
            foreach ($subField['settings']['validation_rules'] as $ruleName => &$rule) {
                $validationKey = "{$subFieldName}->Validation Rules->{$ruleName}";
                if (isset($translations[$validationKey])) {
                    $rule['message'] = $translations[$validationKey];
                }
            }
        }
    }

    private function isWpmlEnabledOnForm($formId)
    {
        return Helper::getFormMeta($formId, 'ff_wpml', false) == true;
    }
    
    private function removeWpmlStrings($formId)
    {
        do_action('wpml_delete_package', $formId, 'Fluent Forms');
        Helper::setFormMeta($formId, 'ff_wpml', false);
    }
}