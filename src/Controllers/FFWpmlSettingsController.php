<?php

namespace FluentFormWpml\Controllers;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Models\Form;
use FluentForm\App\Models\FormMeta;
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
        add_action('init', [$this, 'setupLanguageForAjax'], 5);
        
        add_filter('fluentform/ajax_url', [$this, 'setAjaxLanguage'], 10, 1);
        add_filter('fluentform/rendering_form', [$this, 'setWpmlForm'], 10, 1);
        add_filter('fluentform/recaptcha_lang', [$this, 'setRecaptchaLanguage'], 10, 1);

        add_filter('fluentform/form_submission_confirmation', [$this, 'translateConfirmationMessage'], 10, 3);
        add_filter('fluentform/entry_limit_reached_message', [$this, 'translateLimitReachedMessage'], 10, 2);
        add_filter('fluentform/schedule_form_pending_message', [$this, 'translateFormPendingMessage'], 10, 2);
        add_filter('fluentform/schedule_form_expired_message', [$this, 'translateFormExpiredMessage'], 10, 2);
        add_filter('fluentform/form_requires_login_message', [$this, 'translateFormLoginMessage'], 10, 2);
        add_filter('fluentform/deny_empty_submission_message', [$this, 'translateEmptySubmissionMessage'], 10, 2);
        add_filter('fluentform/ip_restriction_message', [$this, 'translateIpRestrictionMessage'], 10, 2);
        add_filter('fluentform/country_restriction_message', [$this, 'translateCountryRestrictionMessage'], 10, 2);
        add_filter('fluentform/keyword_restriction_message', [$this, 'translateKeywordRestrictionMessage'], 10, 2);

        add_filter('fluentform/integration_feed_before_parse', [$this, 'translateFeedValuesBeforeParse'], 10, 4);

        add_filter('fluentform/input_label_shortcode', [$this, 'translateLabelShortcode'], 10, 3);

        add_filter('fluentform/all_data_shortcode_html', [$this, 'translateAllDataShortcode'],10, 4);

        add_filter('fluentform_pdf/check_wpml_active', [$this, 'isWpmlActive'], 10, 1);
        add_filter('fluentform_pdf/get_current_language', [$this, 'getCurrentWpmlLanguage'], 10, 1);
        add_filter('fluentform_pdf/add_language_to_url', [$this, 'addLanguageToUrl'], 10, 1);
        add_filter('fluentform_pdf/handle_language_for_pdf', [$this, 'handleLanguageForPdf'], 10, 1);
        
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
            wp_send_json_success(__('Translation is disabled for this form', 'fluent-forms-wpml'));
        }

        $form = Form::find($formId);
        $formSettings = FormMeta
            ::where('form_id', $formId)
            ->whereNot('meta_key', [
                'step_data_persistency_status',
                'form_save_state_status',
                '_primary_email_field',
                'ffs_default',
                '_ff_form_styles',
                'ff_wpml',
                '_total_views',
                'revision',
                '_landing_page_settings',
                'template_name'
            ])
            ->get()
            ->reduce(function ($result, $item) {
                $value = $item['value'];
                $decodedValue = json_decode($value, true);
                $metaValue = (json_last_error() === JSON_ERROR_NONE) ? $decodedValue : $value;

                if (!isset($result[$item['meta_key']])) {
                    $result[$item['meta_key']] = [];
                }

                $result[$item['meta_key']][$item['id']] = $metaValue;

                return $result;
            }, []);

        $form->settings = $formSettings;
        
        $formFields = FormFieldsParser::getFields($form);
        $package = $this->getFormPackage($form);

        // Extract and register strings from regular form fields
        $this->extractAndRegisterStrings($formFields, $formId, $package);

        // Extract and register strings from submit button
        if (isset($form->fields['submitButton'])) {
            $submitButton = json_decode(json_encode($form->fields['submitButton']));
            $this->extractAndRegisterStrings($submitButton, $formId, $package);
        }

        // Extract and register strings from step start elements
        if (isset($form->fields['stepsWrapper']['stepStart'])) {
            $stepStart = json_decode(json_encode($form->fields['stepsWrapper']['stepStart']));
            $this->extractAndRegisterStrings($stepStart, $formId, $package);
        }

        // Extract and register strings from step end elements
        if (isset($form->fields['stepsWrapper']['stepEnd'])) {
            $stepEnd = json_decode(json_encode($form->fields['stepsWrapper']['stepEnd']));
            $this->extractAndRegisterStrings($stepEnd, $formId, $package);
        }

        // Extract and register form settings strings
        if (isset($form->settings)) {
            $this->extractAndRegisterFormSettingsStrings($form->settings, $formId, $package);
        }

        Helper::setFormMeta($formId, 'ff_wpml', $isFFWpmlEnabled);
        wp_send_json_success(__('Translation is enabled for this form', 'fluent-forms-wpml'));
    }

    public function handleFormFieldUpdate($formFields, $formId)
    {
        if (!$this->isWpmlEnabledOnForm($formId)) {
            return $formFields;
        }

        $form = Form::find($formId);
        $formSettings = FormMeta
            ::where('form_id', $formId)
            ->whereNot('meta_key', [
                'step_data_persistency_status',
                'form_save_state_status',
                '_primary_email_field',
                'ffs_default',
                '_ff_form_styles',
                'ff_wpml',
                '_total_views',
                'revision',
                '_landing_page_settings',
                'template_name'
            ])
            ->get()
            ->reduce(function ($result, $item) {
                $value = $item['value'];
                $decodedValue = json_decode($value, true);
                $metaValue = (json_last_error() === JSON_ERROR_NONE) ? $decodedValue : $value;

                if (!isset($result[$item['meta_key']])) {
                    $result[$item['meta_key']] = [];
                }

                $result[$item['meta_key']][$item['id']] = $metaValue;

                return $result;
            }, []);
        
        $form->settings = $formSettings;
        
        $package = $this->getFormPackage($form);
        $decodedFields = json_decode($formFields);

        // Start the registration process
        do_action('wpml_start_string_package_registration', $package);

        // Extract and register regular form fields
        $fields = isset($decodedFields->fields) ? $decodedFields->fields : [];
        $this->extractAndRegisterStrings($fields, $formId, $package);

        // Extract and register submit button
        if (isset($decodedFields->submitButton)) {
            $submitButton = $decodedFields->submitButton;
            $this->extractAndRegisterStrings($submitButton, $formId, $package);
        }

        // Extract and register step elements
        if (isset($decodedFields->stepsWrapper)) {
            if (isset($decodedFields->stepsWrapper->stepStart)) {
                $stepStart = $decodedFields->stepsWrapper->stepStart;
                $this->extractAndRegisterStrings($stepStart, $formId, $package);
            }

            if (isset($decodedFields->stepsWrapper->stepEnd)) {
                $stepEnd = $decodedFields->stepsWrapper->stepEnd;
                $this->extractAndRegisterStrings($stepEnd, $formId, $package);
            }
        }

        if (isset($form->settings)) {
            $this->extractAndRegisterFormSettingsStrings($form->settings, $formId, $package);
        }

        // Finish the registration process
        do_action('wpml_delete_unused_package_strings', $package);

        return $formFields;
    }

    public function removeWpmlSettings($formId)
    {
        $this->removeWpmlStrings($formId);
        wp_send_json_success(__('Translations removed successfully.', 'fluent-forms-wpml'));
    }

    public function pushSettings($settingsMenus, $formId)
    {
        if ($this->isWpmlAndStringTranslationActive()) {
            $settingsMenus['ff_wpml'] = [
                'title' => __('WPML Translations', 'fluent-forms-wpml'),
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
        $allowed = static::getLocales('captcha');

        if (isset($allowed[$currentLanguage])) {
            $language = $currentLanguage;
        }

        return $language;
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

        // Extract strings from submit button
        if (isset($form->fields['submitButton'])) {
            $submitButton = json_decode(json_encode($form->fields['submitButton']));
            $this->extractFieldStrings($extractedFields, $submitButton, $form->id);
        }

        // Extract strings from step wrapper elements
        if (isset($form->fields['stepsWrapper']['stepStart'])) {
            $stepStart = json_decode(json_encode($form->fields['stepsWrapper']['stepStart']));
            $this->extractFieldStrings($extractedFields, $stepStart, $form->id);
        }

        if (isset($form->fields['stepsWrapper']['stepEnd'])) {
            $stepEnd = json_decode(json_encode($form->fields['stepsWrapper']['stepEnd']));
            $this->extractFieldStrings($extractedFields, $stepEnd, $form->id);
        }

        foreach ($extractedFields as $key => $value) {
            $package = $this->getFormPackage($form);
            $extractedFields[$key] = apply_filters('wpml_translate_string', $value, $key, $package);
        }

        $updatedFields = $this->updateFormFieldsWithTranslations($form->fields['fields'], $extractedFields);
        $form->fields['fields'] = $updatedFields;

        // Update submit button
        if (isset($form->fields['submitButton'])) {
            $submitButton = $form->fields['submitButton'];
            $submitId = isset($submitButton['uniqElKey']) ? $submitButton['uniqElKey'] : 'submit_button';
            $this->updateFieldTranslations($submitButton, $submitId, $extractedFields);
            $form->fields['submitButton'] = $submitButton;
        }

        // Update step wrapper elements
        if (isset($form->fields['stepsWrapper']['stepStart'])) {
            $stepStart = $form->fields['stepsWrapper']['stepStart'];
            $this->updateFieldTranslations($stepStart, 'step_start', $extractedFields);
            $form->fields['stepsWrapper']['stepStart'] = $stepStart;
        }

        if (isset($form->fields['stepsWrapper']['stepEnd'])) {
            $stepEnd = $form->fields['stepsWrapper']['stepEnd'];
            $this->updateFieldTranslations($stepEnd, 'step_end', $extractedFields);
            $form->fields['stepsWrapper']['stepEnd'] = $stepEnd;
        }
        
        return $form;
    }
    
    public function translateConfirmationMessage($confirmation, $formData, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $confirmation;
        }

        $package = $this->getFormPackage($form);

        $confirmation['messageToShow'] = apply_filters('wpml_translate_string', $confirmation['messageToShow'], "form_{$form->id}_confirmation_message", $package);
        
        return $confirmation;
    }
    
    public function translateLimitReachedMessage($message, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_limit_reached_message", $package);
    }

    public function translateFormPendingMessage($message, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_pending_message", $package);
    }
    
    public function translateFormExpiredMessage($message, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_expired_message", $package);
    }
    
    public function translateFormLoginMessage($message, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_require_login_message", $package);
    }
    
    public function translateEmptySubmissionMessage($message, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_empty_submission_message", $package);
    }
    
    public function translateIpRestrictionMessage($message, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_ip_restriction_message", $package);
    }

    public function translateCountryRestrictionMessage($message, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_country_restriction_message", $package);
    }

    public function translateKeywordRestrictionMessage($message, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_keyword_restriction_message", $package);
    }
    
    public function translateFeedValuesBeforeParse(&$feed, $insertId, $formData, $form)
    {
        $formId = $form->id;
        $package = $this->getFormPackage($form);
        $id = ArrayHelper::get($feed, 'id');
        
        // email notification
        if (ArrayHelper::get($feed, 'meta_key') === 'notifications') {
            if (isset($feed['settings']['subject'])) {
                $key = "form_{$formId}_notification_{$id}_subject";
                $feed['settings']['subject'] = apply_filters('wpml_translate_string',
                    $feed['settings']['subject'], $key, $package);
            }
            if (isset($feed['settings']['message'])) {
                $key = "form_{$formId}_notification_{$id}_message";
                $feed['settings']['message'] = apply_filters('wpml_translate_string',
                    $feed['settings']['message'], $key, $package);
            }
        }

        // pdf
        if (ArrayHelper::get($feed, 'meta_key') === '_pdf_feeds') {
            if (isset($feed['settings']['header'])) {
                $key = "form_{$formId}_pdf_{$id}_header";
                $feed['settings']['header'] = apply_filters('wpml_translate_string', $feed['settings']['header'], $key, $package);
            }
            if (isset($feed['settings']['body'])) {
                $key = "form_{$formId}_pdf_{$id}_body";
                $feed['settings']['body'] = apply_filters('wpml_translate_string', $feed['settings']['body'], $key, $package);
            }
            if (isset($feed['settings']['footer'])) {
                $key = "form_{$formId}_pdf_{$id}_footer";
                $feed['settings']['footer'] = apply_filters('wpml_translate_string', $feed['settings']['footer'], $key, $package);
            }
        }
        
        return $feed;
    }

    private function getFormPackage($form)
    {
        return [
            'kind'  => 'Fluent Forms',
            'name'  => $form->id,
            'title' => $form->title,
        ];
    }

    private function extractAndRegisterStrings($fields, $formId, $package)
    {
        $extractedFields = [];

        // Handle both array of fields and single field objects
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $this->extractFieldStrings($extractedFields, $field, $formId);
            }
        } else {
            // If a single object was passed (for submit button or step elements)
            $this->extractFieldStrings($extractedFields, $fields, $formId);
        }

        foreach ($extractedFields as $key => $value) {
            // Check if the string contains shortcodes or HTML
            $type = 'LINE';
            if (preg_match('/{([^}]+)}/', $value) || preg_match('/#([^#]+)#/', $value) || strpos($value, '<') !== false) {
                $type = 'AREA'; // Use AREA for HTML/shortcodes
            }

            do_action('wpml_register_string', $value, $key, $package, $formId, $type);
        }

        return $extractedFields;
    }

    // Extract all translatable strings form fields
    private function extractFieldStrings(&$fields, $field, $formId, $prefix = '')
    {
        $fieldIdentifier = isset($field->attributes->name) ? $field->attributes->name :
            (isset($field->uniqElKey) ? $field->uniqElKey : null);

        // Special handling for step elements which may not have attributes->name or uniqElKey
        if (!$fieldIdentifier && isset($field->element)) {
            if ($field->element === 'step_start') {
                $fieldIdentifier = 'step_start';
            } elseif ($field->element === 'step_end') {
                $fieldIdentifier = 'step_end';
            } elseif ($field->element === 'button' && isset($field->attributes->type) && $field->attributes->type === 'submit') {
                $fieldIdentifier = 'submit_button';
            }
        }

        if (!$fieldIdentifier) {
            return;
        }

        $fieldIdentifier = $prefix . $fieldIdentifier;

        // Extract common fields
        if (!empty($field->settings->label)) {
            $fields["{$fieldIdentifier}->Label"] = $field->settings->label;
        }

        if (!empty($field->settings->admin_label)) {
            $fields["{$fieldIdentifier}->admin_label"] = $field->settings->admin_field_label;
        }

        if (!empty($field->attributes->placeholder)) {
            $fields["{$fieldIdentifier}->placeholder"] = $field->attributes->placeholder;
        } elseif (!empty($field->settings->placeholder)) {
            $fields["{$fieldIdentifier}->placeholder"] = $field->settings->placeholder;
        }

        if (!empty($field->settings->help_message)) {
            $fields["{$fieldIdentifier}->help_message"] = $field->settings->help_message;
        }

        if (!empty($field->settings->btn_text)) {
            $fields["{$fieldIdentifier}->btn_text"] = $field->settings->btn_text;
        }

        // Handle validation messages
        if (!empty($field->settings->validation_rules)) {
            foreach ($field->settings->validation_rules as $rule => $details) {
                if (!empty($details->message)) {
                    $fields["{$fieldIdentifier}->Validation Rules->{$rule}"] = $details->message;
                }
            }
        }

        // Handle advanced options
        if (!empty($field->settings->advanced_options)) {
            foreach ($field->settings->advanced_options as $option) {
                if (!empty($option->label)) {
                    $fields["{$fieldIdentifier}->Options->{$option->value}"] = $option->label;
                }
            }
        }

        // Handle specific field types
        switch ($field->element) {
            case 'input_name':
            case 'address':
                if (!empty($field->fields)) {
                    foreach ($field->fields as $subFieldName => $subField) {
                        $this->extractFieldStrings($fields, $subField, $formId, $fieldIdentifier . '_');
                    }
                }
                break;

            case 'terms_and_condition':
            case 'gdpr_agreement':
                if (!empty($field->settings->tnc_html)) {
                    $fields["{$fieldIdentifier}->tnc_html"] = $field->settings->tnc_html;
                }
                break;

            case 'custom_html':
                if (!empty($field->settings->html_codes)) {
                    $fields["{$fieldIdentifier}->html_codes"] = $field->settings->html_codes;
                }
                break;

            case 'section_break':
                if (!empty($field->settings->description)) {
                    $fields["{$fieldIdentifier}->description"] = $field->settings->description;
                }
                break;

            case 'tabular_grid':
                if (!empty($field->settings->grid_columns)) {
                    foreach ($field->settings->grid_columns as $key => $value) {
                        $fields["{$fieldIdentifier}->Grid Columns->{$key}"] = $value;
                    }
                }
                if (!empty($field->settings->grid_rows)) {
                    foreach ($field->settings->grid_rows as $key => $value) {
                        $fields["{$fieldIdentifier}->Grid Rows->{$key}"] = $value;
                    }
                }
                break;

            case 'form_step':
                if (isset($field->settings->prev_btn) && isset($field->settings->prev_btn->text)) {
                    $fields["{$fieldIdentifier}->prev_btn_text"] = $field->settings->prev_btn->text;
                }
                if (isset($field->settings->next_btn) && isset($field->settings->next_btn->text)) {
                    $fields["{$fieldIdentifier}->next_btn_text"] = $field->settings->next_btn->text;
                }
                break;

            case 'net_promoter_score':
                if (!empty($field->settings->start_text)) {
                    $fields["{$fieldIdentifier}->start_text"] = $field->settings->start_text;
                }
                if (!empty($field->settings->end_text)) {
                    $fields["{$fieldIdentifier}->end_text"] = $field->settings->end_text;
                }

                // Extract the options values (0-10)
                if (!empty($field->options)) {
                    foreach ($field->options as $optionIndex => $optionValue) {
                        $fields["{$fieldIdentifier}->NPS-Option-{$optionIndex}"] = $optionValue;
                    }
                }
                break;

            case 'multi_payment_component':
                // Extract price_label
                if (!empty($field->settings->price_label)) {
                    $fields["{$fieldIdentifier}->price_label"] = $field->settings->price_label;
                }

                // Extract pricing_options labels
                if (!empty($field->settings->pricing_options)) {
                    foreach ($field->settings->pricing_options as $index => $option) {
                        if (!empty($option->label)) {
                            $fields["{$fieldIdentifier}->pricing_options->{$index}"] = $option->label;
                        }
                    }
                }
                break;

            case 'subscription_payment_component':
                // Extract common fields like label and help_message (already handled by common code)

                // Extract price_label
                if (!empty($field->settings->price_label)) {
                    $fields["{$fieldIdentifier}->price_label"] = $field->settings->price_label;
                }

                // Extract subscription_options elements
                if (!empty($field->settings->subscription_options)) {
                    foreach ($field->settings->subscription_options as $index => $option) {
                        // Extract plan name
                        if (!empty($option->name)) {
                            $fields["{$fieldIdentifier}->subscription_options->{$index}->name"] = $option->name;
                        }

                        // Extract billing interval (if it's text and not a code)
                        if (!empty($option->billing_interval)) {
                            $fields["{$fieldIdentifier}->subscription_options->{$index}->billing_interval"] = $option->billing_interval;
                        }

                        // Extract plan features (if they exist)
                        if (!empty($option->plan_features) && is_array($option->plan_features)) {
                            foreach ($option->plan_features as $featureIndex => $feature) {
                                if (is_string($feature)) {
                                    $fields["{$fieldIdentifier}->subscription_options->{$index}->plan_features->{$featureIndex}"] = $feature;
                                }
                            }
                        }
                    }
                }

                break;

            case 'container':
            case 'repeater_container':
                $containerPrefix = $fieldIdentifier . '_container_';
                if (!empty($field->columns)) {
                    foreach ($field->columns as $columnIndex => $column) {
                        if (!empty($column->fields)) {
                            foreach ($column->fields as $columnFieldIndex => $columnField) {
                                if (isset($columnField->attributes->name) || isset($columnField->uniqElKey)) {
                                    $this->extractFieldStrings($fields, $columnField, $formId, $containerPrefix);
                                }
                            }
                        }
                    }
                }
                break;

            case 'repeater_field':
                $repeaterPrefix = $fieldIdentifier . '_repeater_';
                if (!empty($field->fields)) {
                    foreach ($field->fields as $index => $repeaterField) {
                        // Get field name or use index if not available
                        $repeaterFieldName = isset($repeaterField->attributes->name) ?
                            $repeaterField->attributes->name :
                            (isset($repeaterField->uniqElKey) ?
                                $repeaterField->uniqElKey :
                                'field_' . $index);

                        $fullRepeaterFieldName = $repeaterPrefix . $repeaterFieldName;

                        // Extract label
                        if (!empty($repeaterField->settings->label)) {
                            $fields["{$fullRepeaterFieldName}->Label"] = $repeaterField->settings->label;
                        }

                        // Extract help_message
                        if (!empty($repeaterField->settings->help_message)) {
                            $fields["{$fullRepeaterFieldName}->help_message"] = $repeaterField->settings->help_message;
                        }

                        // Extract advanced_options labels for select fields
                        if ($repeaterField->element === 'select' &&
                            !empty($repeaterField->settings->advanced_options)) {
                            foreach ($repeaterField->settings->advanced_options as $option) {
                                if (!empty($option->label)) {
                                    $fields["{$fullRepeaterFieldName}->Options->{$option->value}"] = $option->label;
                                }
                            }
                        }

                        // Continue with recursive extraction
                        $this->extractFieldStrings($fields, $repeaterField, $formId, $repeaterPrefix);
                    }
                }
                break;

            case 'payment_method':
                // Extract payment methods and their settings
                if (!empty($field->settings->payment_methods)) {
                    foreach ($field->settings->payment_methods as $methodKey => $method) {
                        // Extract method title
                        if (!empty($method->title)) {
                            $fields["{$fieldIdentifier}->payment_methods->{$methodKey}->title"] = $method->title;
                        }

                        // Extract method settings
                        if (!empty($method->settings)) {
                            foreach ($method->settings as $settingKey => $setting) {
                                // Extract option_label value
                                if ($settingKey === 'option_label' && !empty($setting->value)) {
                                    $fields["{$fieldIdentifier}->payment_methods->{$methodKey}->option_label->value"] = $setting->value;
                                }

                                // Extract setting label
                                if (!empty($setting->label)) {
                                    $fields["{$fieldIdentifier}->payment_methods->{$methodKey}->{$settingKey}->label"] = $setting->label;
                                }

                                // If the setting has nested properties (sometimes the case with complex settings)
                                if (is_object($setting) && !empty(get_object_vars($setting))) {
                                    foreach ($setting as $propKey => $propValue) {
                                        if ($propKey === 'label' && is_string($propValue)) {
                                            $fields["{$fieldIdentifier}->payment_methods->{$methodKey}->{$settingKey}->{$propKey}"] = $propValue;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                break;

            case 'payment_coupon':
                // Extract the suffix_label
                if (!empty($field->settings->suffix_label)) {
                    $fields["{$fieldIdentifier}->suffix_label"] = $field->settings->suffix_label;
                }
                break;

            case 'button':
                // Extract button text
                if (!empty($field->settings->button_ui) && !empty($field->settings->button_ui->text)) {
                    $fields["{$fieldIdentifier}->button_ui->text"] = $field->settings->button_ui->text;
                }

                break;

            case 'step_start':
                // Extract step titles if they exist
                if (!empty($field->settings->step_titles)) {
                    foreach ($field->settings->step_titles as $index => $title) {
                        if (!empty($title)) {
                            $fields["{$fieldIdentifier}->step_titles->{$index}"] = $title;
                        }
                    }
                }
                break;

            case 'step_end':
                // Extract previous button text
                if (!empty($field->settings->prev_btn) && !empty($field->settings->prev_btn->text)) {
                    $fields["{$fieldIdentifier}->prev_btn->text"] = $field->settings->prev_btn->text;
                }
                break;
        }
    }

    // Extract translatable strings from form settings
    private function extractAndRegisterFormSettingsStrings($settings, $formId, $package)
    {
        $extractedStrings = [];

        // Confirmation settings
        if (isset($settings['formSettings'][0]['confirmation']['messageToShow'])) {
            $extractedStrings["form_{$formId}_confirmation_message"] = $settings['formSettings'][0]['confirmation']['messageToShow'];
        }

        // Restriction messages
        if (isset($settings['formSettings'][0]['restrictions'])) {
            $restrictions = $settings['formSettings'][0]['restrictions'];

            // Entry limit message
            if (isset($restrictions['limitNumberOfEntries']['limitReachedMsg'])) {
                $extractedStrings["form_{$formId}_limit_reached_message"] = $restrictions['limitNumberOfEntries']['limitReachedMsg'];
            }

            // Schedule messages
            if (isset($restrictions['scheduleForm'])) {
                if (isset($restrictions['scheduleForm']['pendingMsg'])) {
                    $extractedStrings["form_{$formId}_pending_message"] = $restrictions['scheduleForm']['pendingMsg'];
                }
                if (isset($restrictions['scheduleForm']['expiredMsg'])) {
                    $extractedStrings["form_{$formId}_expired_message"] = $restrictions['scheduleForm']['expiredMsg'];
                }
            }

            // Login requirement message
            if (isset($restrictions['requireLogin']['requireLoginMsg'])) {
                $extractedStrings["form_{$formId}_require_login_message"] = $restrictions['requireLogin']['requireLoginMsg'];
            }

            // Empty submission message
            if (isset($restrictions['denyEmptySubmission']['message'])) {
                $extractedStrings["form_{$formId}_empty_submission_message"] = $restrictions['denyEmptySubmission']['message'];
            }

            // Form restriction messages
            if (isset($restrictions['restrictForm']['fields'])) {
                $restrictFields = $restrictions['restrictForm']['fields'];

                if (isset($restrictFields['ip']['message'])) {
                    $extractedStrings["form_{$formId}_ip_restriction_message"] = $restrictFields['ip']['message'];
                }

                if (isset($restrictFields['country']['message'])) {
                    $extractedStrings["form_{$formId}_country_restriction_message"] = $restrictFields['country']['message'];
                }

                if (isset($restrictFields['keywords']['message'])) {
                    $extractedStrings["form_{$formId}_keyword_restriction_message"] = $restrictFields['keywords']['message'];
                }
            }
        }

        // Notifications - now definitely an array of arrays
        if (isset($settings['notifications'])) {
            foreach ($settings['notifications'] as $index => $notification) {
                if (isset($notification['subject'])) {
                    $extractedStrings["form_{$formId}_notification_{$index}_subject"] = $notification['subject'];
                }
                if (isset($notification['message'])) {
                    $extractedStrings["form_{$formId}_notification_{$index}_message"] = $notification['message'];
                }
            }
        }

        // Double Opt-in Settings
        if (isset($settings['double_optin_settings'])) {
            foreach ($settings['double_optin_settings'] as $optinSettings) {
                if (isset($optinSettings['confirmation_message'])) {
                    $extractedStrings["form_{$formId}_optin_confirmation_message"] = $optinSettings['confirmation_message'];
                }

                if (isset($optinSettings['email_subject'])) {
                    $extractedStrings["form_{$formId}_optin_email_subject"] = $optinSettings['email_subject'];
                }

                if (isset($optinSettings['email_body'])) {
                    $extractedStrings["form_{$formId}_optin_email_body"] = $optinSettings['email_body'];
                }

                break;
            }
        }

        // Advanced Validation Settings
        if (isset($settings['advancedValidationSettings'])) {
            foreach ($settings['advancedValidationSettings'] as $validationSettings) {
                if (isset($validationSettings['error_message'])) {
                    $extractedStrings["form_{$formId}_advanced_validation_error"] = $validationSettings['error_message'];
                    break; // Only process the first one
                }
            }
        }

        // PDF Feeds
        if (isset($settings['_pdf_feeds'])) {
            foreach ($settings['_pdf_feeds'] as $index => $feed) {
                if (isset($feed['settings']['header'])) {
                    $extractedStrings["form_{$formId}_pdf_{$index}_header"] = $feed['settings']['header'];
                }
                if (isset($feed['settings']['footer'])) {
                    $extractedStrings["form_{$formId}_pdf_{$index}_footer"] = $feed['settings']['footer'];
                }
                if (isset($feed['settings']['body'])) {
                    $extractedStrings["form_{$formId}_pdf_{$index}_body"] = $feed['settings']['body'];
                }
            }
        }

        foreach ($extractedStrings as $key => $value) {
            // Check if the string contains shortcodes or HTML
            $type = 'LINE';
            if (preg_match('/{([^}]+)}/', $value) || preg_match('/#([^#]+)#/', $value) || strpos($value, '<') !== false) {
                $type = 'AREA'; // Use AREA for HTML/shortcodes
            }

            do_action('wpml_register_string', $value, $key, $package, $formId, $type);
        }
    }

    private function updateFormFieldsWithTranslations($fields, $translations, $prefix = '')
    {
        foreach ($fields as &$field) {
            $fieldName = isset($field['attributes']['name']) ? $field['attributes']['name'] :
                (isset($field['uniqElKey']) ? $field['uniqElKey'] : null);

            if (!$fieldName) {
                continue;
            }

            // Apply prefix if we're in a nested structure
            $fullFieldName = $prefix ? $prefix . $fieldName : $fieldName;

            // Update this field with translations
            $this->updateFieldTranslations($field, $fullFieldName, $translations);

            // Handle special field types with nested structures
            switch ($field['element']) {
                // Handle address and name fields
                case 'input_name':
                case 'address':
                    if (!empty($field['fields'])) {
                        foreach ($field['fields'] as $subFieldName => &$subField) {
                            $subFieldKey = $fullFieldName . '_' . $subFieldName;
                            $this->updateFieldTranslations($subField, $subFieldKey, $translations);
                        }
                    }
                    break;

                // Handle container and repeater_container
                case 'container':
                case 'repeater_container':
                    $containerPrefix = $fullFieldName . '_container_';
                    if (!empty($field['columns'])) {
                        foreach ($field['columns'] as &$column) {
                            if (!empty($column['fields'])) {
                                foreach ($column['fields'] as &$columnField) {
                                    $columnFieldName = isset($columnField['attributes']['name']) ?
                                        $columnField['attributes']['name'] :
                                        (isset($columnField['uniqElKey']) ? $columnField['uniqElKey'] : null);

                                    if ($columnFieldName) {
                                        $fullColumnFieldName = $containerPrefix . $columnFieldName;
                                        $this->updateFieldTranslations($columnField, $fullColumnFieldName,
                                            $translations);

                                        // Recursively handle nested fields within containers
                                        if (in_array($columnField['element'], [
                                            'input_name',
                                            'address',
                                            'container',
                                            'repeater_container',
                                            'repeater_field'
                                        ])) {
                                            $tempFields = [$columnField];
                                            $tempFields = $this->updateFormFieldsWithTranslations($tempFields,
                                                $translations, $containerPrefix);
                                            $columnField = $tempFields[0];
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;

                case 'repeater_field':
                    $repeaterPrefix = $fullFieldName . '_repeater_';

                    // Process each field within the repeater
                    if (!empty($field['fields'])) {
                        foreach ($field['fields'] as $index => &$repeaterField) {
                            // Get field name or use index if not available
                            $repeaterFieldName = isset($repeaterField['attributes']['name']) ?
                                $repeaterField['attributes']['name'] :
                                (isset($repeaterField['uniqElKey']) ?
                                    $repeaterField['uniqElKey'] :
                                    'field_' . $index);

                            $fullRepeaterFieldName = $repeaterPrefix . $repeaterFieldName;

                            // Directly translate label
                            if (isset($translations["{$fullRepeaterFieldName}->Label"]) &&
                                isset($repeaterField['settings']['label'])) {
                                $repeaterField['settings']['label'] = $translations["{$fullRepeaterFieldName}->Label"];
                            }

                            // Directly translate help_message
                            if (isset($translations["{$fullRepeaterFieldName}->help_message"]) &&
                                isset($repeaterField['settings']['help_message'])) {
                                $repeaterField['settings']['help_message'] = $translations["{$fullRepeaterFieldName}->help_message"];
                            }

                            // Translate advanced_options labels for select fields
                            if ($repeaterField['element'] === 'select' &&
                                isset($repeaterField['settings']['advanced_options'])) {
                                foreach ($repeaterField['settings']['advanced_options'] as &$option) {
                                    $optionKey = "{$fullRepeaterFieldName}->Options->{$option['value']}";
                                    if (isset($translations[$optionKey])) {
                                        $option['label'] = $translations[$optionKey];
                                    }
                                }
                            }

                            // Then process all other translations
                            $this->updateFieldTranslations($repeaterField, $fullRepeaterFieldName, $translations);

                            // Recursively handle nested fields if needed
                            if (in_array($repeaterField['element'],
                                ['input_name', 'address', 'container', 'repeater_container', 'repeater_field'])) {
                                $tempFields = [$repeaterField];
                                $tempFields = $this->updateFormFieldsWithTranslations($tempFields, $translations,
                                    $repeaterPrefix);
                                $repeaterField = $tempFields[0];
                            }
                        }
                    }
                    break;
            }
        }

        return $fields;
    }

    private function updateFieldTranslations(&$field, $fieldName, $translations)
    {
        // Update common fields
        if (isset($translations["{$fieldName}->Label"])) {
            $field['settings']['label'] = $translations["{$fieldName}->Label"];
        }

        if (!empty($field->settings->admin_label)) {
            $field['settings']['admin_label'] = $translations["{$fieldName}->admin_field_label"];
        }

        if (isset($translations["{$fieldName}->placeholder"])) {
            if (isset($field['attributes']['placeholder'])) {
                $field['attributes']['placeholder'] = $translations["{$fieldName}->placeholder"];
            }
            if (isset($field['settings']['placeholder'])) {
                $field['settings']['placeholder'] = $translations["{$fieldName}->placeholder"];
            }
        }

        if (isset($translations["{$fieldName}->help_message"])) {
            $field['settings']['help_message'] = $translations["{$fieldName}->help_message"];
        }

        if (isset($translations["{$fieldName}->btn_text"])) {
            $field['settings']['btn_text'] = $translations["{$fieldName}->btn_text"];
        }

        // Update validation messages
        if (isset($field['settings']['validation_rules'])) {
            foreach ($field['settings']['validation_rules'] as $rule => &$details) {
                $key = "{$fieldName}->Validation Rules->{$rule}";
                if (isset($translations[$key])) {
                    $details['message'] = $translations[$key];
                }
            }
        }

        // Update advanced options
        if (isset($field['settings']['advanced_options'])) {
            foreach ($field['settings']['advanced_options'] as &$option) {
                $key = "{$fieldName}->Options->{$option['value']}";
                if (isset($translations[$key])) {
                    $option['label'] = $translations[$key];
                }
            }
        }

        // Handle specific field types
        switch ($field['element']) {
            case 'terms_and_condition':
            case 'gdpr_agreement':
                $key = "{$fieldName}->tnc_html";
                if (isset($translations[$key])) {
                    $field['settings']['tnc_html'] = $translations[$key];
                }
                break;

            case 'custom_html':
                $key = "{$fieldName}->html_codes";
                if (isset($translations[$key])) {
                    $field['settings']['html_codes'] = $translations[$key];
                }
                break;

            case 'section_break':
                $key = "{$fieldName}->description";
                if (isset($translations[$key])) {
                    $field['settings']['description'] = $translations[$key];
                }
                break;

            case 'net_promoter_score':
                $startTextKey = "{$fieldName}->start_text";
                $endTextKey = "{$fieldName}->end_text";
                if (isset($translations[$startTextKey])) {
                    $field['settings']['start_text'] = $translations[$startTextKey];
                }
                if (isset($translations[$endTextKey])) {
                    $field['settings']['end_text'] = $translations[$endTextKey];
                }

                // Update the options values (0-10)
                if (isset($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $optionIndex => $optionValue) {
                        $optionKey = "{$fieldName}->NPS-Option-{$optionIndex}";
                        if (isset($translations[$optionKey])) {
                            $field['options'][$optionIndex] = $translations[$optionKey];
                        }
                    }
                }
                break;

            case 'tabular_grid':
                if (isset($field['settings']['grid_columns'])) {
                    foreach ($field['settings']['grid_columns'] as $key => &$value) {
                        $columnKey = "{$fieldName}->Grid Columns->{$key}";
                        if (isset($translations[$columnKey])) {
                            $value = $translations[$columnKey];
                        }
                    }
                }
                if (isset($field['settings']['grid_rows'])) {
                    foreach ($field['settings']['grid_rows'] as $key => &$value) {
                        $rowKey = "{$fieldName}->Grid Rows->{$key}";
                        if (isset($translations[$rowKey])) {
                            $value = $translations[$rowKey];
                        }
                    }
                }
                break;

            case 'form_step':
                $prevBtnKey = "{$fieldName}->prev_btn_text";
                $nextBtnKey = "{$fieldName}->next_btn_text";
                if (isset($translations[$prevBtnKey]) && isset($field['settings']['prev_btn'])) {
                    $field['settings']['prev_btn']['text'] = $translations[$prevBtnKey];
                }
                if (isset($translations[$nextBtnKey]) && isset($field['settings']['next_btn'])) {
                    $field['settings']['next_btn']['text'] = $translations[$nextBtnKey];
                }
                break;

            case 'multi_payment_component':
                $priceLabelKey = "{$fieldName}->price_label";
                if (isset($translations[$priceLabelKey])) {
                    $field['settings']['price_label'] = $translations[$priceLabelKey];
                }

                // Update pricing_options labels
                if (isset($field['settings']['pricing_options']) && is_array($field['settings']['pricing_options'])) {
                    foreach ($field['settings']['pricing_options'] as $index => &$option) {
                        $optionKey = "{$fieldName}->pricing_options->{$index}";
                        if (isset($translations[$optionKey])) {
                            $option['label'] = $translations[$optionKey];
                        }
                    }
                }

                break;

            case 'subscription_payment_component':
                // Update price_label
                $priceLabelKey = "{$fieldName}->price_label";
                if (isset($translations[$priceLabelKey])) {
                    $field['settings']['price_label'] = $translations[$priceLabelKey];
                }

                // Update subscription_options elements
                if (isset($field['settings']['subscription_options']) && is_array($field['settings']['subscription_options'])) {
                    foreach ($field['settings']['subscription_options'] as $index => &$option) {
                        // Update plan name
                        $nameKey = "{$fieldName}->subscription_options->{$index}->name";
                        if (isset($translations[$nameKey])) {
                            $option['name'] = $translations[$nameKey];
                        }

                        // Update billing interval (if it's text and not a code)
                        $intervalKey = "{$fieldName}->subscription_options->{$index}->billing_interval";
                        if (isset($translations[$intervalKey])) {
                            $option['billing_interval'] = $translations[$intervalKey];
                        }

                        // Update plan features (if they exist)
                        if (isset($option['plan_features']) && is_array($option['plan_features'])) {
                            foreach ($option['plan_features'] as $featureIndex => &$feature) {
                                $featureKey = "{$fieldName}->subscription_options->{$index}->plan_features->{$featureIndex}";
                                if (isset($translations[$featureKey])) {
                                    $feature = $translations[$featureKey];
                                }
                            }
                        }
                    }
                }

                break;

            case 'payment_coupon':
                $suffixLabelKey = "{$fieldName}->suffix_label";
                if (isset($translations[$suffixLabelKey])) {
                    $field['settings']['suffix_label'] = $translations[$suffixLabelKey];
                }
                break;

            case 'payment_method':
                // Update payment methods and their settings
                if (isset($field['settings']['payment_methods']) && is_array($field['settings']['payment_methods'])) {
                    foreach ($field['settings']['payment_methods'] as $methodKey => &$method) {
                        // Update method title
                        $titleKey = "{$fieldName}->payment_methods->{$methodKey}->title";
                        if (isset($translations[$titleKey])) {
                            $method['title'] = $translations[$titleKey];
                        }

                        // Update method settings
                        if (isset($method['settings']) && is_array($method['settings'])) {
                            foreach ($method['settings'] as $settingKey => &$setting) {
                                // Update option_label value
                                if ($settingKey === 'option_label' && isset($setting['value'])) {
                                    $optionLabelKey = "{$fieldName}->payment_methods->{$methodKey}->option_label->value";
                                    if (isset($translations[$optionLabelKey])) {
                                        $setting['value'] = $translations[$optionLabelKey];
                                    }
                                }

                                // Update setting label
                                $labelKey = "{$fieldName}->payment_methods->{$methodKey}->{$settingKey}->label";
                                if (isset($translations[$labelKey]) && isset($setting['label'])) {
                                    $setting['label'] = $translations[$labelKey];
                                }

                                // If the setting has nested properties
                                foreach ($setting as $propKey => &$propValue) {
                                    if ($propKey === 'label' && is_string($propValue)) {
                                        $propLabelKey = "{$fieldName}->payment_methods->{$methodKey}->{$settingKey}->{$propKey}";
                                        if (isset($translations[$propLabelKey])) {
                                            $propValue = $translations[$propLabelKey];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                break;

            // For the submit button
            case 'button':
                // Update button text
                if (isset($field['settings']['button_ui']) && isset($field['settings']['button_ui']['text'])) {
                    $buttonTextKey = "{$fieldName}->button_ui->text";
                    if (isset($translations[$buttonTextKey])) {
                        $field['settings']['button_ui']['text'] = $translations[$buttonTextKey];
                    }
                }
                break;

            // For step_start element  
            case 'step_start':
                // Update step titles if they exist
                if (isset($field['settings']['step_titles']) && is_array($field['settings']['step_titles'])) {
                    foreach ($field['settings']['step_titles'] as $index => &$title) {
                        $titleKey = "{$fieldName}->step_titles->{$index}";
                        if (isset($translations[$titleKey])) {
                            $title = $translations[$titleKey];
                        }
                    }
                }
                break;

            // For step_end element
            case 'step_end':
                // Update previous button text
                if (isset($field['settings']['prev_btn']) && isset($field['settings']['prev_btn']['text'])) {
                    $prevBtnTextKey = "{$fieldName}->prev_btn->text";
                    if (isset($translations[$prevBtnTextKey])) {
                        $field['settings']['prev_btn']['text'] = $translations[$prevBtnTextKey];
                    }
                }
                break;
        }
    }

    private function isWpmlEnabledOnForm($formId)
    {
        return $this->isWpmlActive() && Helper::getFormMeta($formId, 'ff_wpml', false) == true;
    }
    
    private function removeWpmlStrings($formId)
    {
        do_action('wpml_delete_package', $formId, 'Fluent Forms');
        Helper::setFormMeta($formId, 'ff_wpml', false);
    }
    
    public function setupLanguageForAjax()
    {
        // Check if this is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            return;
        }

        // Check if WPML is active
        if (!$this->isWpmlActive()) {
            return;
        }

        // These are the PDF and form-related AJAX actions we want to handle
        $ajaxActions = [
            'fluentform_pdf_download',
            'fluentform_pdf_download_public',
            'fluentform_pdf_admin_ajax_actions',
            'fluentform_submit',
        ];

        $request = $this->app->request->all();

        $action = isset($request['action']) ? sanitize_text_field($request['action']) : '';

        if (!in_array($action, $ajaxActions)) {
            return;
        }

        // Try to get language from various sources
        $language = null;

        // Check request parameter first
        if (isset($request['lang'])) {
            $language = sanitize_text_field($request['lang']);
        }
        // If no language in request, try WPML cookie
        elseif (isset($_COOKIE['_icl_current_language'])) {
            $language = sanitize_text_field(wp_unslash($_COOKIE['_icl_current_language']));
        }
        // If still no language, try referrer URL for clues
        elseif (isset($_SERVER['HTTP_REFERER'])) {
            $referer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));

            // Check for directory-based language in referer URL
            if (preg_match('~^https?://[^/]+/([a-z]{2})(/|$)~i', $referer, $matches)) {
                $possibleLang = $matches[1];

                // Verify this is a valid WPML language
                $activeLanguages = apply_filters('wpml_active_languages', []);
                if (!empty($activeLanguages) && isset($activeLanguages[$possibleLang])) {
                    $language = $possibleLang;
                }
            }

            // Check for query param lang in referer URL
            if (!$language && preg_match('/[?&]lang=([a-z]{2})/i', $referer, $matches)) {
                $possibleLang = $matches[1];

                // Verify this is a valid WPML language
                $activeLanguages = apply_filters('wpml_active_languages', []);
                if (!empty($activeLanguages) && isset($activeLanguages[$possibleLang])) {
                    $language = $possibleLang;
                }
            }
        }

        // If we found a language, set it
        if ($language) {
            do_action('wpml_switch_language', $language);
        }
    }

    public function translateLabelShortcode($inputLabel, $key, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $inputLabel;
        }

        $package = $this->getFormPackage($form);

        // Try different translation keys in order of priority
        $translationKeys = [
            "{$key}->admin_label",
            "{$key}->Label"
        ];

        // Try each translation key and use the first one that returns a different value
        foreach ($translationKeys as $translationKey) {
            $translated = apply_filters('wpml_translate_string', $inputLabel, $translationKey, $package);

            // If we got a different value back, it means there's a translation available
            if ($translated !== $inputLabel) {
                return $translated;
            }
        }

        return $inputLabel;
    }
    
    public function translateAllDataShortcode($html, $formFields, $inputLabels, $response)
    {
        $formId = $response->form_id;

        // Check if WPML is enabled for this form
        if (!$this->isWpmlEnabledOnForm($formId)) {
            return $html;
        }

        $form = Form::find($formId);
        if (!$form) {
            return $html;
        }

        $package = $this->getFormPackage($form);

        // Translate field labels and values
        $translatedLabels = [];
        $translatedValues = [];

        foreach ($inputLabels as $inputKey => $label) {
            // Try different translation keys in order of priority
            $translationKeys = [
                "{$inputKey}->admin_label",
                "{$inputKey}->Label"
            ];

            $translatedLabel = $label; // Default to original

            // Try each translation key and use the first one that returns a different value
            foreach ($translationKeys as $translationKey) {
                $translated = apply_filters('wpml_translate_string', $label, $translationKey, $package);

                // If we got a different value back, it means there's a translation available
                if ($translated !== $label) {
                    $translatedLabel = $translated;
                    break; // Use the first available translation
                }
            }

            $translatedLabels[$inputKey] = $translatedLabel;

            // Translate value if applicable
            if (array_key_exists($inputKey, $response->user_inputs)) {
                $value = $response->user_inputs[$inputKey];

                // Only translate select/radio/checkbox option values
                if (isset($formFields[$inputKey]) &&
                    in_array($formFields[$inputKey]['element'], ['select', 'radio', 'checkbox']) &&
                    is_string($value)) {

                    $optionKey = "{$inputKey}->Options->{$value}";
                    $translatedValue = apply_filters('wpml_translate_string', $value, $optionKey, $package);

                    // Only save if actually different (translated)
                    if ($translatedValue !== $value) {
                        $translatedValues[$inputKey] = $translatedValue;
                    }
                }
            }
        }

        // Rebuild the HTML with translated labels and values
        $newHtml = '<table class="ff_all_data" width="600" cellpadding="0" cellspacing="0"><tbody>';

        foreach ($inputLabels as $inputKey => $label) {
            if (array_key_exists($inputKey, $response->user_inputs) && '' !== ArrayHelper::get($response->user_inputs, $inputKey)) {
                $data = ArrayHelper::get($response->user_inputs, $inputKey);

                // Skip arrays and objects
                if (is_array($data) || is_object($data)) {
                    continue;
                }

                // Use translated value if available
                if (isset($translatedValues[$inputKey])) {
                    $data = $translatedValues[$inputKey];
                }

                // Use translated label
                $translatedLabel = isset($translatedLabels[$inputKey]) ? $translatedLabels[$inputKey] : $label;

                $newHtml .= '<tr class="field-label"><th style="padding: 6px 12px; background-color: #f8f8f8; text-align: left;"><strong>' . $translatedLabel . '</strong></th></tr><tr class="field-value"><td style="padding: 6px 12px 12px 12px;">' . $data . '</td></tr>';
            }
        }

        $newHtml .= '</tbody></table>';

        return $newHtml;
    }
    
    protected function isWpmlActive()
    {
        return defined('ICL_SITEPRESS_VERSION') && defined('WPML_ST_VERSION');
    }

    public function getCurrentWpmlLanguage()
    {
        if (!$this->isWpmlActive()) {
            return null;
        }

        return apply_filters('wpml_current_language', null);
    }

    public function addLanguageToUrl($url)
    {
        if (!$this->isWpmlActive()) {
            return $url;
        }

        $currentLang = $this->getCurrentWpmlLanguage();
        if (!$currentLang) {
            return $url;
        }

        // Add language parameter
        return add_query_arg(['lang' => $currentLang], $url);
    }

    public function handleLanguageForPdf($requestData)
    {
        if (!$this->isWpmlActive()) {
            return;
        }

        // If language is specified in the request, switch to it
        if (isset($requestData['lang'])) {
            $lang = sanitize_text_field($requestData['lang']);
            do_action('wpml_switch_language', $lang);
        }
    }

    public static function getLocales($type = 'date')
    {
        $locales = [
            'en'     => __('English', 'fluent-forms-wpml'),
            'af'     => __('Afrikaans', 'fluent-forms-wpml'),
            'sq'     => __('Albanian', 'fluent-forms-wpml'),
            'ar-DZ'  => __('Algerian Arabic', 'fluent-forms-wpml'),
            'am'     => __('Amharic', 'fluent-forms-wpml'),
            'ar'     => __('Arabic', 'fluent-forms-wpml'),
            'hy'     => __('Armenian', 'fluent-forms-wpml'),
            'az'     => __('Azerbaijani', 'fluent-forms-wpml'),
            'eu'     => __('Basque', 'fluent-forms-wpml'),
            'be'     => __('Belarusian', 'fluent-forms-wpml'),
            'bn'     => __('Bengali', 'fluent-forms-wpml'),
            'bs'     => __('Bosnian', 'fluent-forms-wpml'),
            'bg'     => __('Bulgarian', 'fluent-forms-wpml'),
            'ca'     => __('Catalan', 'fluent-forms-wpml'),
            'zh-HK'  => __('Chinese Hong Kong', 'fluent-forms-wpml'),
            'zh-CN'  => __('Chinese Simplified', 'fluent-forms-wpml'),
            'zh-TW'  => __('Chinese Traditional', 'fluent-forms-wpml'),
            'hr'     => __('Croatian', 'fluent-forms-wpml'),
            'cs'     => __('Czech', 'fluent-forms-wpml'),
            'da'     => __('Danish', 'fluent-forms-wpml'),
            'nl'     => __('Dutch', 'fluent-forms-wpml'),
            'en-GB'  => __('English/UK', 'fluent-forms-wpml'),
            'eo'     => __('Esperanto', 'fluent-forms-wpml'),
            'et'     => __('Estonian', 'fluent-forms-wpml'),
            'fo'     => __('Faroese', 'fluent-forms-wpml'),
            'fa'     => __('Farsi/Persian', 'fluent-forms-wpml'),
            'fil'    => __('Filipino', 'fluent-forms-wpml'),
            'fi'     => __('Finnish', 'fluent-forms-wpml'),
            'fr'     => __('French', 'fluent-forms-wpml'),
            'fr-CA'  => __('French/Canadian', 'fluent-forms-wpml'),
            'fr-CH'  => __('French/Swiss', 'fluent-forms-wpml'),
            'gl'     => __('Galician', 'fluent-forms-wpml'),
            'ka'     => __('Georgian', 'fluent-forms-wpml'),
            'de'     => __('German', 'fluent-forms-wpml'),
            'de-AT'  => __('German/Austria', 'fluent-forms-wpml'),
            'de-CH'  => __('German/Switzerland', 'fluent-forms-wpml'),
            'el'     => __('Greek', 'fluent-forms-wpml'),
            'gu'     => __('Gujarati', 'fluent-forms-wpml'),
            'he'     => __('Hebrew', 'fluent-forms-wpml'),
            'iw'     => __('Hebrew', 'fluent-forms-wpml'),
            'hi'     => __('Hindi', 'fluent-forms-wpml'),
            'hu'     => __('Hungarian', 'fluent-forms-wpml'),
            'is'     => __('Icelandic', 'fluent-forms-wpml'),
            'id'     => __('Indonesian', 'fluent-forms-wpml'),
            'it'     => __('Italian', 'fluent-forms-wpml'),
            'ja'     => __('Japanese', 'fluent-forms-wpml'),
            'kn'     => __('Kannada', 'fluent-forms-wpml'),
            'kk'     => __('Kazakh', 'fluent-forms-wpml'),
            'km'     => __('Khmer', 'fluent-forms-wpml'),
            'ko'     => __('Korean', 'fluent-forms-wpml'),
            'ky'     => __('Kyrgyz', 'fluent-forms-wpml'),
            'lo'     => __('Laothian', 'fluent-forms-wpml'),
            'lv'     => __('Latvian', 'fluent-forms-wpml'),
            'lt'     => __('Lithuanian', 'fluent-forms-wpml'),
            'lb'     => __('Luxembourgish', 'fluent-forms-wpml'),
            'mk'     => __('Macedonian', 'fluent-forms-wpml'),
            'ml'     => __('Malayalam', 'fluent-forms-wpml'),
            'ms'     => __('Malaysian', 'fluent-forms-wpml'),
            'mr'     => __('Marathi', 'fluent-forms-wpml'),
            'no'     => __('Norwegian', 'fluent-forms-wpml'),
            'nb'     => __('Norwegian Bokmål', 'fluent-forms-wpml'),
            'nn'     => __('Norwegian Nynorsk', 'fluent-forms-wpml'),
            'pl'     => __('Polish', 'fluent-forms-wpml'),
            'pt'     => __('Portuguese', 'fluent-forms-wpml'),
            'pt-BR'  => __('Portuguese/Brazilian', 'fluent-forms-wpml'),
            'pt-PT'  => __('Portuguese/Portugal', 'fluent-forms-wpml'),
            'rm'     => __('Romansh', 'fluent-forms-wpml'),
            'ro'     => __('Romanian', 'fluent-forms-wpml'),
            'ru'     => __('Russian', 'fluent-forms-wpml'),
            'sr'     => __('Serbian', 'fluent-forms-wpml'),
            'sr-SR'  => __('Serbian', 'fluent-forms-wpml'),
            'si'     => __('Sinhalese', 'fluent-forms-wpml'),
            'sk'     => __('Slovak', 'fluent-forms-wpml'),
            'sl'     => __('Slovenian', 'fluent-forms-wpml'),
            'es'     => __('Spanish', 'fluent-forms-wpml'),
            'es-419' => __('Spanish/Latin America', 'fluent-forms-wpml'),
            'sw'     => __('Swahili', 'fluent-forms-wpml'),
            'sv'     => __('Swedish', 'fluent-forms-wpml'),
            'ta'     => __('Tamil', 'fluent-forms-wpml'),
            'te'     => __('Telugu', 'fluent-forms-wpml'),
            'th'     => __('Thai', 'fluent-forms-wpml'),
            'tj'     => __('Tajiki', 'fluent-forms-wpml'),
            'tr'     => __('Turkish', 'fluent-forms-wpml'),
            'uk'     => __('Ukrainian', 'fluent-forms-wpml'),
            'ur'     => __('Urdu', 'fluent-forms-wpml'),
            'vi'     => __('Vietnamese', 'fluent-forms-wpml'),
            'cy-GB'  => __('Welsh', 'fluent-forms-wpml'),
            'zu'     => __('Zulu', 'fluent-forms-wpml'),
        ];

        $unset = [];

        if ($type === 'captcha') {
            $unset = [
                'sq',
                'bs',
                'eo',
                'fo',
                'fr-CH',
                'sr-SR',
                'ar-DZ',
                'be',
                'cy-GB',
                'kk',
                'km',
                'ky',
                'lb',
                'mk',
                'nb',
                'nn',
                'rm',
                'tj'
            ];
        } elseif ($type === 'date') {
            $unset = [
                'fil',
                'fr-CA',
                'de-AT',
                'de-CH',
                'iw',
                'hi',
                'pt',
                'pt-PT',
                'es-419',
                'mr',
                'lo',
                'kn',
                'si',
                'gu',
                'bn',
                'zu',
                'ur',
                'te',
                'sw',
                'am'
            ];
        }

        return array_diff_key($locales, array_flip($unset));
    }
}