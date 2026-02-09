<?php

namespace MultilingualFormsFluentFormsWpml\Controllers;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Models\Form;
use FluentForm\App\Models\FormMeta;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SettingsController
{
    protected $app;

    private static $currentNotificationFeedId = null;

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
        add_filter('fluentform/recaptcha_lang', [$this, 'setCaptchaLanguage'], 10, 1);
        add_filter('fluentform/hcaptcha_lang', [$this, 'setCaptchaLanguage'], 10, 1);
        add_filter('fluentform/turnstile_lang', [$this, 'setCaptchaLanguage'], 10, 1);

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
        add_action('fluentform/integration_notify_notifications', [$this, 'setCurrentNotificationFeedId'], 5, 4);

        add_filter('fluentform/input_label_shortcode', [$this, 'translateLabelShortcode'], 10, 3);

        // Pro Module Translation Filters
        add_filter('fluentform/payment_confirmation_message', [$this, 'translatePaymentMessage'], 10, 3);
        add_filter('fluentform/quiz_result_title', [$this, 'translateQuizResultTitle'], 10, 3);
        add_filter('fluentform/quiz_result_message', [$this, 'translateQuizMessage'], 10, 3);
        add_filter('fluentform/modal_button_text', [$this, 'translateModalText'], 10, 2);
        add_filter('fluentform/survey_labels', [$this, 'translateSurveyLabels'], 10, 2);
        add_filter('fluentform/step_form_navigation_title', [$this, 'translateStepNavigation'], 10, 2);
        add_filter('fluentform/step_next_button_text', [$this, 'translateStepNextButtonText'], 10, 2);
        add_filter('fluentform/step_prev_button_text', [$this, 'translateStepPrevButtonText'], 10, 2);
        add_filter('fluentform/file_upload_messages', [$this, 'translateFileUploadMessages'], 10, 2);
        add_filter('fluentform/double_optin_messages', [$this, 'translateDoubleOptinMessages'], 10, 3);
        add_filter('fluentform/admin_approval_messages', [$this, 'translateAdminApprovalMessages'], 10, 3);

        // Shortcode Translation Filters
        add_filter('fluentform/popup_shortcode_defaults', [$this, 'translatePopupShortcodeDefaults'], 10, 2);
        add_filter('fluentform/survey_shortcode_defaults', [$this, 'translateSurveyShortcodeDefaults'], 10, 2);

        // Specific Pro Component Filters
        add_filter('fluentform/save_progress_button_text', [$this, 'translateSaveProgressButtonText'], 10, 2);
        add_filter('fluentform/survey_field_label', [$this, 'translateSurveyFieldLabel'], 10, 2);
        add_filter('fluentform/survey_votes_text', [$this, 'translateSurveyVotesText'], 10, 2);
        add_filter('fluentform/double_optin_confirmation_message', [$this, 'translateDoubleOptinConfirmationMessage'], 10, 3);
        add_filter('fluentform/file_upload_button_text', [$this, 'translateFileUploadButtonText'], 10, 2);

        // Payment-Specific Hooks (All Payment Methods)
        add_filter('fluentform/payment_success_title', [$this, 'translatePaymentSuccessTitle'], 10, 3);
        add_filter('fluentform/payment_failed_title', [$this, 'translatePaymentFailedTitle'], 10, 3);
        add_filter('fluentform/payment_error_message', [$this, 'translatePaymentErrorMessage'], 10, 3);

        // Pro-only payment hooks
        add_filter('fluentform/payment_pending_title', [$this, 'translatePaymentPendingTitle'], 10, 3);
        add_filter('fluentform/payment_pending_message', [$this, 'translatePaymentPendingMessage'], 10, 3);

        // Stripe specific
        add_filter('fluentform/stripe_payment_redirect_message', [$this, 'translateStripePaymentRedirectMessage'], 10, 3);
        add_filter('fluentform/stripe_payment_cancelled_message', [$this, 'translateStripePaymentCancelledMessage'], 10, 3);

        // Quiz translation filters
        add_filter('fluentform/quiz_score_value', [$this, 'translateQuizScoreValue'], 10, 4);
        add_filter('fluentform/quiz_no_grade_label', [$this, 'translateQuizNoGradeLabel'], 10, 2);

        // Square specific
        add_filter('fluentform/square_payment_redirect_message', [$this, 'translateSquarePaymentRedirectMessage'], 10, 3);

        // Paystack specific
        add_filter('fluentform/paystack_payment_modal_opening_message', [$this, 'translatePaystackPaymentModalOpeningMessage'], 10, 3);
        add_filter('fluentform/paystack_payment_confirming_message', [$this, 'translatePaystackPaymentConfirmingMessage'], 10, 3);
        add_filter('fluentform/paystack_payment_verification_error', [$this, 'translatePaystackPaymentVerificationError'], 10, 3);

        // PayPal specific
        add_filter('fluentform/paypal_payment_processing_message', [$this, 'translatePaypalPaymentProcessingMessage'], 10, 3);
        add_filter('fluentform/paypal_payment_sandbox_message', [$this, 'translatePaypalPaymentSandboxMessage'], 10, 3);
        add_filter('fluentform/paypal_payment_cancelled_title', [$this, 'translatePaypalPaymentCancelledTitle'], 10, 3);
        add_filter('fluentform/paypal_payment_cancelled_message', [$this, 'translatePaypalPaymentCancelledMessage'], 10, 3);

        // Form Validation Message Filters
        add_filter('fluentform/validations', [$this, 'translateValidationMessages'], 10, 3);
        add_filter('fluentform/validation_error_message', [$this, 'translateValidationErrorMessage'], 10, 3);
        add_filter('fluentform/token_based_validation_error_message', [$this, 'translateTokenBasedValidationErrorMessage'], 10, 2);

        // Conditional Logic
        add_filter('fluentform/conditional_content', [$this, 'translateConditionalContent'], 10, 3);

        // Advanced Field Message Filters
        add_filter('fluentform/calculation_field_messages', [$this, 'translateCalculationFieldMessages'], 10, 2);
        add_filter('fluentform/inventory_field_messages', [$this, 'translateInventoryFieldMessages'], 10, 2);

        // Email Template Filters
        add_filter('fluentform/email_header', [$this, 'translateEmailTemplateHeader'], 10, 3);
        add_filter('fluentform/email_footer', [$this, 'translateEmailTemplateFooter'], 10, 3);
        add_filter('fluentform/email_subject', [$this, 'translateEmailSubjectLine'], 10, 4);
        add_filter('fluentform/email_body', [$this, 'translateEmailBody'], 10, 4);

        // Subscription/Recurring Payment Filters
        add_filter('fluentform/subscription_confirmation_message', [$this, 'translateSubscriptionMessage'], 10, 3);
        add_filter('fluentform/recurring_payment_message', [$this, 'translateRecurringPaymentMessage'], 10, 3);

        // Submission Message Parse Filter
        add_filter('fluentform/submission_message_parse', [$this, 'translateSubmissionMessageParse'], 10, 4);

        // JavaScript Frontend Message Filters
        add_filter('fluentform/form_submission_messages', [$this, 'translateFormSubmissionMessages'], 10, 2);
        add_filter('fluentform/payment_handler_messages', [$this, 'translatePaymentHandlerMessages'], 10, 2);
        add_filter('fluentform/form_save_progress_messages', [$this, 'translateFormSaveProgressMessages'], 10, 2);
        add_filter('fluentform/address_autocomplete_messages', [$this, 'translateAddressAutocompleteMessages'], 10, 2);
        add_filter('fluentform/payment_gateway_messages', [$this, 'translatePaymentGatewayMessages'], 10, 2);

        add_filter('fluentform/entry_lists_labels', [$this, 'translateEntryListsLabels'], 10, 2);

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
            wp_send_json_success(__('Translation is disabled for this form', 'multilingual-forms-fluent-forms-wpml'));
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
        
        $formFields = FormFieldsParser::getFields($form, true);
        $package = $this->getFormPackage($form);

        // Extract and register strings from regular form fields
        $this->extractAndRegisterStrings($formFields, $formId, $package);

        // Extract and register strings from submit button
        if (isset($form->fields['submitButton'])) {
            $submitButton = json_decode(json_encode($form->fields['submitButton']), true);
            $this->extractAndRegisterStrings($submitButton, $formId, $package);
        }

        // Extract and register strings from step start elements
        if (isset($form->fields['stepsWrapper']['stepStart'])) {
            $stepStart = json_decode(json_encode($form->fields['stepsWrapper']['stepStart']), true);
            $this->extractAndRegisterStrings($stepStart, $formId, $package);
        }

        // Extract and register strings from step end elements
        if (isset($form->fields['stepsWrapper']['stepEnd'])) {
            $stepEnd = json_decode(json_encode($form->fields['stepsWrapper']['stepEnd']), true);
            $this->extractAndRegisterStrings($stepEnd, $formId, $package);
        }

        // Extract and register form settings strings
        if (isset($form->settings)) {
            $this->extractAndRegisterFormSettingsStrings($form->settings, $formId, $package);
        }

        Helper::setFormMeta($formId, 'ff_wpml', $isFFWpmlEnabled);
        wp_send_json_success(__('Translation is enabled for this form', 'multilingual-forms-fluent-forms-wpml'));
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

    public function removeWpmlSettings()
    {
        $request = $this->app->request->get();
        $formId = ArrayHelper::get($request, 'form_id');
        
        if (!$formId || !is_numeric($formId)) {
            wp_send_json_error(__('Invalid form ID.', 'multilingual-forms-fluent-forms-wpml'));
            return;
        }
        
        $this->removeWpmlStrings($formId);
        wp_send_json_success(__('Translations removed successfully.', 'multilingual-forms-fluent-forms-wpml'));
    }

    public function pushSettings($settingsMenus, $formId)
    {
        if ($this->isWpmlAndStringTranslationActive()) {
            $settingsMenus['ff_wpml'] = [
                'title' => __('WPML Translations', 'multilingual-forms-fluent-forms-wpml'),
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

    public static function setCaptchaLanguage($language)
    {
        $currentLanguage = apply_filters('wpml_current_language', null);

        if (!$currentLanguage) {
            return $language;
        }

        // Get the current filter being applied to determine CAPTCHA type
        $currentFilter = current_filter();

        if ($currentFilter === 'fluentform/recaptcha_lang') {
            $allowed = static::getRecaptchaLocales();
            if (isset($allowed[$currentLanguage])) {
                $language = $allowed[$currentLanguage];
            }
        } elseif ($currentFilter === 'fluentform/hcaptcha_lang') {
            $allowed = static::getHcaptchaLocales();
            if (isset($allowed[$currentLanguage])) {
                $language = $allowed[$currentLanguage];
            }
        } elseif ($currentFilter === 'fluentform/turnstile_lang') {
            $allowed = static::getTurnstileLocales();
            if (isset($allowed[$currentLanguage])) {
                $language = $allowed[$currentLanguage];
            }
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
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $form;
        }

        $formFields = FormFieldsParser::getFields($form, true);

        $extractedFields = [];
        foreach ($formFields as $field) {
            $this->extractFieldStrings($extractedFields, $field, $form->id);
        }

        // Extract strings from submit button
        if (isset($form->fields['submitButton'])) {
            $submitButton = json_decode(json_encode($form->fields['submitButton']), true);
            $submitId = isset($submitButton['uniqElKey']) ? $submitButton['uniqElKey'] : 'submit_button';
            
            if (isset($submitButton['settings']['btn_text']) && !empty($submitButton['settings']['btn_text'])) {
                $extractedFields["{$submitId}->btn_text"] = $submitButton['settings']['btn_text'];
            }
            
            if (isset($submitButton['settings']['button_ui']['text']) && !empty($submitButton['settings']['button_ui']['text'])) {
                $extractedFields["{$submitId}->button_ui->text"] = $submitButton['settings']['button_ui']['text'];
            }
            
            $this->extractFieldStrings($extractedFields, $submitButton, $form->id);
        }

        // Extract strings from step wrapper elements
        if (isset($form->fields['stepsWrapper']['stepStart'])) {
            $stepStart = json_decode(json_encode($form->fields['stepsWrapper']['stepStart']), true);
            $this->extractFieldStrings($extractedFields, $stepStart, $form->id);
        }

        if (isset($form->fields['stepsWrapper']['stepEnd'])) {
            $stepEnd = json_decode(json_encode($form->fields['stepsWrapper']['stepEnd']), true);
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
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $confirmation;
        }

        if (!is_array($confirmation)) {
            return $confirmation;
        }

        $package = $this->getFormPackage($form);

        $confirmationId = isset($confirmation['id']) && !empty($confirmation['id']) ? $confirmation['id'] : null;
        
        if ($confirmationId) {
            $messageKey = "form_{$form->id}_conditional_confirmation_{$confirmationId}_message";
            $customPageKey = "form_{$form->id}_conditional_confirmation_{$confirmationId}_custom_page";
            $pageTitleKey = "form_{$form->id}_conditional_confirmation_{$confirmationId}_page_title";
        } else {
            $messageKey = "form_{$form->id}_confirmation_message";
            $customPageKey = "form_{$form->id}_confirmation_custom_page";
            $pageTitleKey = "form_{$form->id}_confirmation_page_title";
        }

        if (!empty($confirmation['messageToShow'])) {
            $confirmation['messageToShow'] = apply_filters('wpml_translate_string', $confirmation['messageToShow'], $messageKey, $package);
        }

        if (!empty($confirmation['customPageHtml'])) {
            $confirmation['customPageHtml'] = apply_filters('wpml_translate_string', $confirmation['customPageHtml'], $customPageKey, $package);
        }

        if (!empty($confirmation['successPageTitle'])) {
            $confirmation['successPageTitle'] = apply_filters('wpml_translate_string', $confirmation['successPageTitle'], $pageTitleKey, $package);
        }

        return $confirmation;
    }
    
    public function translateLimitReachedMessage($message, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_limit_reached_message", $package);
    }

    public function translateFormPendingMessage($message, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_pending_message", $package);
    }
    
    public function translateFormExpiredMessage($message, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_expired_message", $package);
    }
    
    public function translateFormLoginMessage($message, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_require_login_message", $package);
    }
    
    public function translateEmptySubmissionMessage($message, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_empty_submission_message", $package);
    }
    
    public function translateIpRestrictionMessage($message, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_ip_restriction_message", $package);
    }

    public function translateCountryRestrictionMessage($message, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_country_restriction_message", $package);
    }

    public function translateKeywordRestrictionMessage($message, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_keyword_restriction_message", $package);
    }

    public function translatePaymentMessage($message, $formData, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_payment_message", $package);
    }

    public function translateQuizMessage($message, $formData, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_quiz_message", $package);
    }

    public function translateModalText($text, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $text;
        }

        $package = $this->getFormPackage($form);

        return apply_filters('wpml_translate_string', $text, "form_{$form->id}_modal_text", $package);
    }

    public function translateSurveyLabels($labels, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $labels;
        }

        $package = $this->getFormPackage($form);

        if (is_array($labels)) {
            foreach ($labels as $key => $label) {
                $labels[$key] = apply_filters('wpml_translate_string', $label, "form_{$form->id}_survey_label_{$key}", $package);
            }
        } else {
            $labels = apply_filters('wpml_translate_string', $labels, "form_{$form->id}_survey_labels", $package);
        }

        return $labels;
    }

    public function translateEntryListsLabels($formLabels, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id) || !is_array($formLabels)) {
            return $formLabels;
        }

        $package = $this->getFormPackage($form);
        foreach ($formLabels as $key => $label) {
            if (is_string($label)) {
                $formLabels[$key] = apply_filters('wpml_translate_string', $label, "form_{$form->id}_entry_list_label_{$key}", $package);
            }
        }

        return $formLabels;
    }

    public function translateStepNavigation($navigation, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $navigation;
        }

        $package = $this->getFormPackage($form);

        if (isset($navigation['next_btn_text'])) {
            $navigation['next_btn_text'] = apply_filters('wpml_translate_string', $navigation['next_btn_text'], "form_{$form->id}_step_next_btn", $package);
        }

        if (isset($navigation['prev_btn_text'])) {
            $navigation['prev_btn_text'] = apply_filters('wpml_translate_string', $navigation['prev_btn_text'], "form_{$form->id}_step_prev_btn", $package);
        }

        if (isset($navigation['step_title'])) {
            $navigation['step_title'] = apply_filters('wpml_translate_string', $navigation['step_title'], "form_{$form->id}_step_title", $package);
        }

        return $navigation;
    }

    public function translateFileUploadMessages($messages, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'drag_drop_text' => 'file_drag_drop_text',
            'upload_text' => 'file_upload_text',
            'max_file_error' => 'file_max_error',
            'file_type_error' => 'file_type_error',
            'file_size_error' => 'file_size_error',
            'upload_failed_text' => 'file_upload_failed_text',
            'upload_error_text' => 'file_upload_error_text'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translateDoubleOptinMessages($messages, $formData, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'confirmation_message' => 'optin_confirmation_message',
            'email_subject' => 'optin_email_subject',
            'email_body' => 'optin_email_body',
            'success_message' => 'optin_success_message',
            'error_message' => 'optin_error_message'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translateAdminApprovalMessages($messages, $formData, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'approval_message' => 'admin_approval_pending_message',
            'approved_message' => 'approval_approved_message',
            'rejected_message' => 'approval_rejected_message',
            'notification_subject' => 'admin_approval_email_subject',
            'notification_body' => 'admin_approval_email_body'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translatePopupShortcodeDefaults($defaults, $atts)
    {
        if (!isset($atts['form_id'])) {
            return $defaults;
        }

        $formId = intval($atts['form_id']);
        if (!$this->isWpmlEnabledOnForm($formId)) {
            return $defaults;
        }

        $form = (object)['id' => $formId];
        $package = $this->getFormPackage($form);

        // Translate default button text
        if (isset($defaults['btn_text'])) {
            $defaults['btn_text'] = apply_filters('wpml_translate_string', $defaults['btn_text'], "form_{$formId}_modal_button_text", $package);
        }

        return $defaults;
    }

    public function translateSurveyShortcodeDefaults($defaults, $atts)
    {
        if (!isset($atts['form_id'])) {
            return $defaults;
        }

        $formId = intval($atts['form_id']);
        if (!$this->isWpmlEnabledOnForm($formId)) {
            return $defaults;
        }

        $form = (object)['id' => $formId];
        $package = $this->getFormPackage($form);

        // Translate default labels
        $translatableKeys = ['label', 'counts'];
        foreach ($translatableKeys as $key) {
            if (isset($defaults[$key])) {
                $defaults[$key] = apply_filters('wpml_translate_string', $defaults[$key], "form_{$formId}_survey_{$key}_default", $package);
            }
        }

        return $defaults;
    }

    public function translateSaveProgressButtonText($text, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $text;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $text, "form_{$form->id}_save_progress_button_text", $package);
    }

    public function translateSurveyFieldLabel($label, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $label;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $label, "form_{$form->id}_survey_field_label", $package);
    }

    public function translateSurveyVotesText($text, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $text;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $text, "form_{$form->id}_survey_votes_text", $package);
    }

    public function translateDoubleOptinConfirmationMessage($message, $formData, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_double_optin_confirmation", $package);
    }

    public function translateFileUploadButtonText($text, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $text;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $text, "form_{$form->id}_file_upload_button_text", $package);
    }

    public function translatePaymentSuccessTitle($title, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $title;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $title, "form_{$form->id}_payment_success_title", $package);
    }

    public function translatePaymentFailedTitle($title, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $title;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $title, "form_{$form->id}_payment_failed_title", $package);
    }

    public function translatePaymentPendingTitle($title, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $title;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $title, "form_{$form->id}_payment_pending_title", $package);
    }

    public function translatePaymentPendingMessage($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_payment_pending_message", $package);
    }

    public function translatePaymentErrorMessage($message, $submission, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_payment_error_message", $package);
    }

    public function translateStripePaymentRedirectMessage($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_stripe_payment_redirect_message", $package);
    }

    public function translateSquarePaymentRedirectMessage($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_square_payment_redirect_message", $package);
    }

    public function translatePaystackPaymentModalOpeningMessage($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_paystack_payment_modal_opening_message", $package);
    }

    public function translatePaystackPaymentConfirmingMessage($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_paystack_payment_confirming_message", $package);
    }

    public function translatePaystackPaymentVerificationError($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_paystack_payment_verification_error", $package);
    }

    public function translateQuizResultTitle($title, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $title;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $title, "form_{$form->id}_quiz_result_title", $package);
    }

    public function translateStepNextButtonText($text, $data)
    {
        if (!isset($data['container']['form_instance'])) {
            return $text;
        }

        $form = $data['container']['form_instance'];
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $text;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $text, "form_{$form->id}_step_next_button_text", $package);
    }

    public function translateStepPrevButtonText($text, $data)
    {
        if (!isset($data['container']['form_instance'])) {
            return $text;
        }

        $form = $data['container']['form_instance'];
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $text;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $text, "form_{$form->id}_step_prev_button_text", $package);
    }

    public function translateStripePaymentCancelledMessage($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_stripe_payment_cancelled_message", $package);
    }

    public function translatePaypalPaymentProcessingMessage($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_paypal_payment_processing_message", $package);
    }

    public function translatePaypalPaymentSandboxMessage($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_paypal_payment_sandbox_message", $package);
    }

    public function translatePaypalPaymentCancelledTitle($title, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $title;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $title, "form_{$form->id}_paypal_payment_cancelled_title", $package);
    }

    public function translatePaypalPaymentCancelledMessage($message, $submission, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_paypal_payment_cancelled_message", $package);
    }

    public function translateValidationMessages($validations, $form, $formData)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $validations;
        }

        $package = $this->getFormPackage($form);
        list($rules, $messages) = $validations;

        foreach ($messages as $key => $message) {
            $translationKey = "form_{$form->id}_validation_" . str_replace('.', '_', (string) $key);
            $messages[$key] = apply_filters('wpml_translate_string', $message, $translationKey, $package);
        }

        return [$rules, $messages];
    }

    public function translateValidationErrorMessage($message, $field, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_validation_error_message", $package);
    }

    public function translateTokenBasedValidationErrorMessage($message, $formId)
    {
        $form = Form::find($formId);
        if (!$form || !$this->isWpmlEnabledOnForm($formId)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$formId}_token_based_validation_message", $package);
    }

    public function translateConditionalContent($content, $formData, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $content;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $content, "form_{$form->id}_conditional_content", $package);
    }

    public function translateStepValidationMessage($message, $formData, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_step_validation_message", $package);
    }

    public function translateRepeaterFieldMessages($messages, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'add_more_text' => 'repeater_add_more_text',
            'remove_text' => 'repeater_remove_text',
            'max_repeat_error' => 'repeater_max_repeat_error',
            'min_repeat_error' => 'repeater_min_repeat_error'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translateCalculationFieldMessages($messages, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'calculation_error' => 'calculation_error_message',
            'invalid_formula' => 'calculation_invalid_formula',
            'division_by_zero' => 'calculation_division_by_zero'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translateInventoryFieldMessages($messages, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'out_of_stock' => 'inventory_out_of_stock',
            'insufficient_stock' => 'inventory_insufficient_stock',
            'stock_limit_reached' => 'inventory_stock_limit_reached'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translateQuizScoreValue($result, $formId, $scoreType, $quizResults)
    {
        if (!$this->isWpmlEnabledOnForm($formId)) {
            return $result;
        }

        $package = $this->getFormPackage(Form::find($formId));

        // Translate quiz grade labels
        if ($scoreType === 'grade' && is_string($result)) {
            // Get quiz settings to find the grade index
            $quizSettings = Helper::getFormMeta($formId, 'quiz_settings', true);
            
            if ($quizSettings) {
                // Handle both array of quiz settings and single quiz settings object
                $quizSettingsArray = [];
                if (isset($quizSettings[0]) && is_array($quizSettings[0])) {
                    $quizSettingsArray = $quizSettings;
                } elseif (is_array($quizSettings)) {
                    $quizSettingsArray = [$quizSettings];
                }
                
                foreach ($quizSettingsArray as $quizIndex => $quizSetting) {
                    if (isset($quizSetting['grades']) && is_array($quizSetting['grades'])) {
                        foreach ($quizSetting['grades'] as $gradeIndex => $grade) {
                            if (isset($grade['label']) && $grade['label'] === $result) {
                                $translationKey = "form_{$formId}_quiz_grade_{$quizIndex}_{$gradeIndex}_label";
                                $translated = apply_filters('wpml_translate_string', $result, $translationKey, $package);
                                return $translated;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    public function translateQuizNoGradeLabel($label, $formId = null)
    {
        // If formId is not provided, try to get it from context or return label as-is
        if (!$formId) {
            // Try to get formId from global form context if available
            // This is a fallback for cases where the filter doesn't pass formId
            return $label;
        }

        if (!$this->isWpmlEnabledOnForm($formId)) {
            return $label;
        }

        $form = Form::find($formId);
        if (!$form) {
            return $label;
        }

        $package = $this->getFormPackage($form);
        $translationKey = "form_{$formId}_quiz_no_grade_label";
        
        return apply_filters('wpml_translate_string', $label, $translationKey, $package);
    }

    public function setCurrentNotificationFeedId($feed, $formData, $entry, $form)
    {
        self::$currentNotificationFeedId = ArrayHelper::get($feed, 'id');
    }

    public function translateFeedValuesBeforeParse($feed, $insertId, $formData, $form)
    {
        if (is_array($form)) {
            $formId = isset($form['id']) ? $form['id'] : null;
            if ($formId === null) {
                return $feed;
            }
            $package = [
                'kind'  => 'Fluent Forms',
                'name'  => $formId,
                'title' => isset($form['title']) ? $form['title'] : '',
            ];
        } elseif (is_object($form) && isset($form->id)) {
            $formId = $form->id;
            $package = $this->getFormPackage($form);
        } else {
            return $feed;
        }
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

        // Pro Integration Feeds (ActiveCampaign, Zapier, etc.)
        $proIntegrations = [
            'activecampaign', 'campaignmonitor', 'constantcontact', 'convertkit', 'getresponse',
            'hubspot', 'icontact', 'moosend', 'platformly', 'webhook', 'zapier', 'sendfox',
            'mailerlite', 'sms_notification', 'getgist', 'googlesheet', 'trello', 'drip',
            'sendinblue', 'user_registration', 'automizy', 'telegram', 'salesflare', 'discord',
            'cleverreach', 'clicksend', 'zohocrm', 'pipedrive', 'salesforce', 'amocrm',
            'onepagecrm', 'airtable', 'mailjet', 'insightly', 'mailster', 'notion'
        ];

        $metaKey = ArrayHelper::get($feed, 'meta_key');
        if (in_array($metaKey, $proIntegrations)) {
            // Common translatable integration settings
            $translatableKeys = [
                'success_message' => 'integration_success_message',
                'error_message' => 'integration_error_message',
                'confirmation_message' => 'integration_confirmation_message',
                'email_subject' => 'integration_email_subject',
                'email_body' => 'integration_email_body',
                'webhook_success_message' => 'webhook_success_message',
                'webhook_error_message' => 'webhook_error_message',
                'list_name' => 'integration_list_name',
                'tag_name' => 'integration_tag_name',
                'note' => 'integration_note',
                'description' => 'integration_description'
            ];

            foreach ($translatableKeys as $settingKey => $translationKey) {
                if (isset($feed['settings'][$settingKey])) {
                    $key = "form_{$formId}_feed_{$id}_{$translationKey}";
                    $feed['settings'][$settingKey] = apply_filters('wpml_translate_string', $feed['settings'][$settingKey], $key, $package);
                }
            }

            // Handle nested settings for complex integrations
            if (isset($feed['settings']['fields']) && is_array($feed['settings']['fields'])) {
                foreach ($feed['settings']['fields'] as $fieldKey => &$fieldValue) {
                    if (is_string($fieldValue) && !empty($fieldValue)) {
                        $key = "form_{$formId}_feed_{$id}_field_{$fieldKey}";
                        $fieldValue = apply_filters('wpml_translate_string', $fieldValue, $key, $package);
                    }
                }
            }
        }

        // Payment Integration Messages
        if (is_string($metaKey) && (strpos($metaKey, 'payment_') === 0 || in_array($metaKey, ['paypal', 'stripe', 'razorpay', 'mollie', 'paystack']))) {
            $paymentKeys = [
                'payment_success_message' => 'payment_success_message',
                'payment_error_message' => 'payment_error_message',
                'receipt_template' => 'payment_receipt_template',
                'confirmation_message' => 'payment_confirmation_message'
            ];

            foreach ($paymentKeys as $settingKey => $translationKey) {
                if (isset($feed['settings'][$settingKey])) {
                    $key = "form_{$formId}_payment_{$id}_{$translationKey}";
                    $feed['settings'][$settingKey] = apply_filters('wpml_translate_string', $feed['settings'][$settingKey], $key, $package);
                }
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
            $type = 'LINE';
            if (is_string($value) && (preg_match('/{([^}]+)}/', $value) || preg_match('/#([^#]+)#/', $value) || strpos($value, '<') !== false)) {
                $type = 'AREA';
            }

            do_action('wpml_register_string', $value, $key, $package, $formId, $type);
        }

        return $extractedFields;
    }

    // Extract all translatable strings form fields
    private function extractFieldStrings(&$fields, $field, $formId, $prefix = '')
    {
        if (is_array($field)) {
            $field = json_decode(json_encode($field));
        }

        $fieldIdentifier = isset($field->attributes->name) ? $field->attributes->name :
            (isset($field->uniqElKey) ? $field->uniqElKey : null);

        // Special handling for step elements which may not have attributes->name or uniqElKey
        if (!$fieldIdentifier && isset($field->element)) {
            if ($field->element === 'step_start') {
                $fieldIdentifier = 'step_start';
            } elseif ($field->element === 'step_end') {
                $fieldIdentifier = 'step_end';
            } elseif (($field->element === 'button' && isset($field->attributes->type) && $field->attributes->type === 'submit') || $field->element === 'custom_submit_button') {
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

        if (!empty($field->settings->prefix_label)) {
            $fields["{$fieldIdentifier}->prefix_label"] = $field->settings->prefix_label;
        }

        if (!empty($field->settings->suffix_label)) {
            $fields["{$fieldIdentifier}->suffix_label"] = $field->settings->suffix_label;
        }

        // Handle validation messages
        if (!empty($field->settings->validation_rules)) {
            foreach ($field->settings->validation_rules as $rule => $details) {
                if (!empty($details->message)) {
                    $fields["{$fieldIdentifier}->Validation Rules->{$rule}"] = $details->message;
                }
            }
        }

        // Handle unique validation message
        if (!empty($field->settings->unique_validation_message)) {
            $fields["{$fieldIdentifier}->unique_validation_message"] = $field->settings->unique_validation_message;
        }

        // Handle inventory/stock messages
        if (!empty($field->settings->inventory_stockout_message)) {
            $fields["{$fieldIdentifier}->inventory_stockout_message"] = $field->settings->inventory_stockout_message;
        }

        if (!empty($field->settings->stock_quantity_label)) {
            $fields["{$fieldIdentifier}->stock_quantity_label"] = $field->settings->stock_quantity_label;
        }

        // Handle conditional logic group titles
        if (!empty($field->settings->conditional_logics->condition_groups)) {
            foreach ($field->settings->conditional_logics->condition_groups as $groupIndex => $group) {
                if (!empty($group->title)) {
                    $fields["{$fieldIdentifier}->conditional_logics->condition_groups->{$groupIndex}->title"] = $group->title;
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
            case 'net_promoter':
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
            case 'custom_submit_button':
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

            case 'save_progress_button':
                // Extract save progress button messages and email templates
                if (!empty($field->settings->save_success_message)) {
                    $fields["{$fieldIdentifier}->save_success_message"] = $field->settings->save_success_message;
                }

                if (!empty($field->settings->email_subject)) {
                    $fields["{$fieldIdentifier}->email_subject"] = $field->settings->email_subject;
                }

                if (!empty($field->settings->email_body)) {
                    $fields["{$fieldIdentifier}->email_body"] = $field->settings->email_body;
                }

                if (!empty($field->settings->on_create_email_subject)) {
                    $fields["{$fieldIdentifier}->on_create_email_subject"] = $field->settings->on_create_email_subject;
                }

                if (!empty($field->settings->on_create_email_body)) {
                    $fields["{$fieldIdentifier}->on_create_email_body"] = $field->settings->on_create_email_body;
                }

                if (!empty($field->settings->on_update_email_subject)) {
                    $fields["{$fieldIdentifier}->on_update_email_subject"] = $field->settings->on_update_email_subject;
                }

                if (!empty($field->settings->on_update_email_body)) {
                    $fields["{$fieldIdentifier}->on_update_email_body"] = $field->settings->on_update_email_body;
                }
                break;

            case 'ratings':
                // Extract rating options labels
                if (!empty($field->options)) {
                    foreach ($field->options as $value => $label) {
                        if (!empty($label)) {
                            $fields["{$fieldIdentifier}->Rating Options->{$value}"] = $label;
                        }
                    }
                }
                break;

            case 'select_country':
                // Extract country options labels
                if (!empty($field->options)) {
                    foreach ($field->options as $value => $label) {
                        if (!empty($label)) {
                            $fields["{$fieldIdentifier}->Country Options->{$value}"] = $label;
                        }
                    }
                }
                break;

            case 'chained_select':
                // Extract chained select data source headers
                if (!empty($field->settings->data_source->headers)) {
                    foreach ($field->settings->data_source->headers as $index => $header) {
                        if (!empty($header)) {
                            $fields["{$fieldIdentifier}->data_source->headers->{$index}"] = $header;
                        }
                    }
                }
                break;

            case 'quiz_score':
                // Extract personality quiz options if they exist
                if (!empty($field->options) && is_array($field->options)) {
                    foreach ($field->options as $optionValue => $optionLabel) {
                        if (!empty($optionLabel)) {
                            $fields["{$fieldIdentifier}->Personality Options->{$optionValue}"] = $optionLabel;
                        }
                    }
                }
                break;

            case 'recaptcha':
            case 'hcaptcha':
            case 'turnstile':
                // These CAPTCHA fields only have labels that are handled by common field processing
                // No additional field-specific translatable content
                break;

            case 'accordion':
                // Extract accordion title and description
                if (!empty($field->settings->title)) {
                    $fields["{$fieldIdentifier}->title"] = $field->settings->title;
                }
                if (!empty($field->settings->description)) {
                    $fields["{$fieldIdentifier}->description"] = $field->settings->description;
                }
                break;

            case 'shortcode':
                // Extract shortcode text
                if (!empty($field->settings->shortcode)) {
                    $fields["{$fieldIdentifier}->shortcode"] = $field->settings->shortcode;
                }
                break;
        }
    }

    // Extract translatable strings from form settings
    private function extractAndRegisterFormSettingsStrings($settings, $formId, $package)
    {
        $extractedStrings = [];

        // Confirmation settings
        if (isset($settings['formSettings'][0]['confirmation'])) {
            $confirmation = $settings['formSettings'][0]['confirmation'];

            // Main confirmation message
            if (isset($confirmation['messageToShow'])) {
                $extractedStrings["form_{$formId}_confirmation_message"] = $confirmation['messageToShow'];
            }



            // Custom page HTML content
            if (isset($confirmation['customPageHtml'])) {
                $extractedStrings["form_{$formId}_confirmation_custom_page"] = $confirmation['customPageHtml'];
            }

            // Success page title
            if (isset($confirmation['successPageTitle'])) {
                $extractedStrings["form_{$formId}_confirmation_page_title"] = $confirmation['successPageTitle'];
            }
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

        // Payment Settings
        if (isset($settings['payment_settings'])) {
            foreach ($settings['payment_settings'] as $index => $paymentSettings) {
                if (isset($paymentSettings['confirmation_message'])) {
                    $extractedStrings["form_{$formId}_payment_confirmation_{$index}"] = $paymentSettings['confirmation_message'];
                }
                if (isset($paymentSettings['error_message'])) {
                    $extractedStrings["form_{$formId}_payment_error_{$index}"] = $paymentSettings['error_message'];
                }
                if (isset($paymentSettings['receipt_template'])) {
                    $extractedStrings["form_{$formId}_payment_receipt_{$index}"] = $paymentSettings['receipt_template'];
                }
            }
        }

        // Quiz Settings
        if (isset($settings['quiz_settings'])) {
            foreach ($settings['quiz_settings'] as $index => $quizSettings) {
                if (isset($quizSettings['result_message'])) {
                    $extractedStrings["form_{$formId}_quiz_result_{$index}"] = $quizSettings['result_message'];
                }
                if (isset($quizSettings['pass_message'])) {
                    $extractedStrings["form_{$formId}_quiz_pass_{$index}"] = $quizSettings['pass_message'];
                }
                if (isset($quizSettings['fail_message'])) {
                    $extractedStrings["form_{$formId}_quiz_fail_{$index}"] = $quizSettings['fail_message'];
                }
            }
        }

        // Modal Settings
        if (isset($settings['modal_settings'])) {
            if (isset($settings['modal_settings']['button_text'])) {
                $extractedStrings["form_{$formId}_modal_button_text"] = $settings['modal_settings']['button_text'];
            }
            if (isset($settings['modal_settings']['modal_title'])) {
                $extractedStrings["form_{$formId}_modal_title"] = $settings['modal_settings']['modal_title'];
            }
        }

        // Step Form Settings
        if (isset($settings['step_form_settings'])) {
            if (isset($settings['step_form_settings']['next_btn_text'])) {
                $extractedStrings["form_{$formId}_step_next_btn"] = $settings['step_form_settings']['next_btn_text'];
            }
            if (isset($settings['step_form_settings']['prev_btn_text'])) {
                $extractedStrings["form_{$formId}_step_prev_btn"] = $settings['step_form_settings']['prev_btn_text'];
            }
        }

        // File Upload Settings
        if (isset($settings['file_upload_settings'])) {
            $fileUploadKeys = [
                'drag_drop_text' => 'file_drag_drop_text',
                'upload_text' => 'file_upload_text',
                'max_file_error' => 'file_max_error',
                'file_type_error' => 'file_type_error',
                'file_size_error' => 'file_size_error'
            ];

            foreach ($fileUploadKeys as $key => $translationKey) {
                if (isset($settings['file_upload_settings'][$key])) {
                    $extractedStrings["form_{$formId}_{$translationKey}"] = $settings['file_upload_settings'][$key];
                }
            }
        }

        // Pro Integration Feeds
        $proIntegrations = [
            'activecampaign', 'campaignmonitor', 'constantcontact', 'convertkit', 'getresponse',
            'hubspot', 'icontact', 'moosend', 'platformly', 'webhook', 'zapier', 'sendfox',
            'mailerlite', 'sms_notification', 'getgist', 'googlesheet', 'trello', 'drip',
            'sendinblue', 'user_registration', 'automizy', 'telegram', 'salesflare', 'discord',
            'cleverreach', 'clicksend', 'zohocrm', 'pipedrive', 'salesforce', 'amocrm',
            'onepagecrm', 'airtable', 'mailjet', 'insightly', 'mailster', 'notion'
        ];

        foreach ($proIntegrations as $integration) {
            if (isset($settings[$integration])) {
                foreach ($settings[$integration] as $index => $feed) {
                    $translatableKeys = [
                        'success_message' => 'integration_success_message',
                        'error_message' => 'integration_error_message',
                        'confirmation_message' => 'integration_confirmation_message',
                        'email_subject' => 'integration_email_subject',
                        'email_body' => 'integration_email_body',
                        'list_name' => 'integration_list_name',
                        'tag_name' => 'integration_tag_name',
                        'note' => 'integration_note',
                        'description' => 'integration_description'
                    ];

                    foreach ($translatableKeys as $settingKey => $translationKey) {
                        if (isset($feed['settings'][$settingKey])) {
                            $extractedStrings["form_{$formId}_feed_{$index}_{$translationKey}"] = $feed['settings'][$settingKey];
                        }
                    }
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

        // Conditional Confirmations (Pro) - stored in FormMeta with meta_key 'confirmations'
        // Each confirmation is stored as a separate FormMeta row, so $settings['confirmations'] is an array
        // where keys are FormMeta IDs and values are the confirmation objects
        if (isset($settings['confirmations'])) {
            foreach ($settings['confirmations'] as $metaId => $confirmation) {
                // Handle both direct confirmation object and array of confirmations
                $confirmationsToProcess = [];
                if (isset($confirmation[0]) && is_array($confirmation[0])) {
                    // Array of confirmations (unlikely but handle it)
                    $confirmationsToProcess = $confirmation;
                } elseif (is_array($confirmation)) {
                    // Single confirmation object
                    $confirmationsToProcess = [$confirmation];
                }
                
                foreach ($confirmationsToProcess as $confirmation) {
                    if (is_array($confirmation) && (!isset($confirmation['active']) || $confirmation['active'])) {
                        // Use the confirmation's 'id' if available, otherwise use the FormMeta ID
                        $confirmationIndex = isset($confirmation['id']) ? $confirmation['id'] : $metaId;
                        
                        // Main confirmation message
                        if (isset($confirmation['messageToShow']) && !empty($confirmation['messageToShow'])) {
                            $extractedStrings["form_{$formId}_conditional_confirmation_{$confirmationIndex}_message"] = $confirmation['messageToShow'];
                        }
                        
                        // Custom page HTML content
                        if (isset($confirmation['customPageHtml']) && !empty($confirmation['customPageHtml'])) {
                            $extractedStrings["form_{$formId}_conditional_confirmation_{$confirmationIndex}_custom_page"] = $confirmation['customPageHtml'];
                        }
                        
                        // Success page title
                        if (isset($confirmation['successPageTitle']) && !empty($confirmation['successPageTitle'])) {
                            $extractedStrings["form_{$formId}_conditional_confirmation_{$confirmationIndex}_page_title"] = $confirmation['successPageTitle'];
                        }
                    }
                }
            }
        }

        // Admin Approval Settings (Pro) - stored in FormMeta with meta_key 'admin_approval_settings'
        if (isset($settings['admin_approval_settings'])) {
            $adminApprovalSettings = is_array($settings['admin_approval_settings']) && isset($settings['admin_approval_settings'][0]) 
                ? $settings['admin_approval_settings'][0] 
                : $settings['admin_approval_settings'];
            
            if (isset($adminApprovalSettings['approval_pending_message'])) {
                $extractedStrings["form_{$formId}_admin_approval_pending_message"] = $adminApprovalSettings['approval_pending_message'];
            }
            
            if (isset($adminApprovalSettings['email_subject'])) {
                $extractedStrings["form_{$formId}_admin_approval_email_subject"] = $adminApprovalSettings['email_subject'];
            }
            
            if (isset($adminApprovalSettings['email_body'])) {
                $extractedStrings["form_{$formId}_admin_approval_email_body"] = $adminApprovalSettings['email_body'];
            }
        }

        // Quiz Grade Labels (Pro) - extract from quiz_settings grades array
        if (isset($settings['quiz_settings'])) {
            foreach ($settings['quiz_settings'] as $index => $quizSettings) {
                // Handle both array of quiz settings and single quiz settings object
                $quizSettingsToProcess = [];
                if (isset($quizSettings[0]) && is_array($quizSettings[0])) {
                    $quizSettingsToProcess = $quizSettings;
                } elseif (is_array($quizSettings)) {
                    $quizSettingsToProcess = [$quizSettings];
                }
                
                foreach ($quizSettingsToProcess as $quizIndex => $quizSetting) {
                    if (isset($quizSetting['grades']) && is_array($quizSetting['grades'])) {
                        foreach ($quizSetting['grades'] as $gradeIndex => $grade) {
                            if (isset($grade['label']) && !empty($grade['label'])) {
                                $extractedStrings["form_{$formId}_quiz_grade_{$index}_{$gradeIndex}_label"] = $grade['label'];
                            }
                        }
                    }
                }
            }
        }

        // Quiz "Not Graded" label - register default string for translation
        // This is a default string that can be filtered, so we register the default for translation
        // The filter will handle translation at runtime
        $extractedStrings["form_{$formId}_quiz_no_grade_label"] = __('Not Graded', 'fluentformpro');

        foreach ($extractedStrings as $key => $value) {
            $type = 'LINE';
            if (is_string($value) && (preg_match('/{([^}]+)}/', $value) || preg_match('/#([^#]+)#/', $value) || strpos($value, '<') !== false)) {
                $type = 'AREA';
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

        if (isset($translations["{$fieldName}->prefix_label"])) {
            $field['settings']['prefix_label'] = $translations["{$fieldName}->prefix_label"];
        }

        if (isset($translations["{$fieldName}->suffix_label"])) {
            $field['settings']['suffix_label'] = $translations["{$fieldName}->suffix_label"];
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

        // Update unique validation message
        if (isset($translations["{$fieldName}->unique_validation_message"])) {
            $field['settings']['unique_validation_message'] = $translations["{$fieldName}->unique_validation_message"];
        }

        // Update inventory/stock messages
        if (isset($translations["{$fieldName}->inventory_stockout_message"])) {
            $field['settings']['inventory_stockout_message'] = $translations["{$fieldName}->inventory_stockout_message"];
        }

        if (isset($translations["{$fieldName}->stock_quantity_label"])) {
            $field['settings']['stock_quantity_label'] = $translations["{$fieldName}->stock_quantity_label"];
        }

        // Update conditional logic group titles
        if (isset($field['settings']['conditional_logics']['condition_groups'])) {
            foreach ($field['settings']['conditional_logics']['condition_groups'] as $groupIndex => &$group) {
                $key = "{$fieldName}->conditional_logics->condition_groups->{$groupIndex}->title";
                if (isset($translations[$key])) {
                    $group['title'] = $translations[$key];
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
            case 'net_promoter':
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
            case 'custom_submit_button':
                if (isset($field['settings']['button_ui']) && isset($field['settings']['button_ui']['text'])) {
                    $buttonTextKey = "{$fieldName}->button_ui->text";
                    if (isset($translations[$buttonTextKey])) {
                        $field['settings']['button_ui']['text'] = $translations[$buttonTextKey];
                    }
                }
                if (isset($field['settings']['btn_text'])) {
                    $btnTextKey = "{$fieldName}->btn_text";
                    if (isset($translations[$btnTextKey])) {
                        $field['settings']['btn_text'] = $translations[$btnTextKey];
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

            case 'save_progress_button':
                // Update save progress button messages and email templates
                $saveSuccessKey = "{$fieldName}->save_success_message";
                if (isset($translations[$saveSuccessKey])) {
                    $field['settings']['save_success_message'] = $translations[$saveSuccessKey];
                }

                $emailSubjectKey = "{$fieldName}->email_subject";
                if (isset($translations[$emailSubjectKey])) {
                    $field['settings']['email_subject'] = $translations[$emailSubjectKey];
                }

                $emailBodyKey = "{$fieldName}->email_body";
                if (isset($translations[$emailBodyKey])) {
                    $field['settings']['email_body'] = $translations[$emailBodyKey];
                }

                $onCreateSubjectKey = "{$fieldName}->on_create_email_subject";
                if (isset($translations[$onCreateSubjectKey])) {
                    $field['settings']['on_create_email_subject'] = $translations[$onCreateSubjectKey];
                }

                $onCreateBodyKey = "{$fieldName}->on_create_email_body";
                if (isset($translations[$onCreateBodyKey])) {
                    $field['settings']['on_create_email_body'] = $translations[$onCreateBodyKey];
                }

                $onUpdateSubjectKey = "{$fieldName}->on_update_email_subject";
                if (isset($translations[$onUpdateSubjectKey])) {
                    $field['settings']['on_update_email_subject'] = $translations[$onUpdateSubjectKey];
                }

                $onUpdateBodyKey = "{$fieldName}->on_update_email_body";
                if (isset($translations[$onUpdateBodyKey])) {
                    $field['settings']['on_update_email_body'] = $translations[$onUpdateBodyKey];
                }
                break;

            case 'ratings':
                // Update rating options labels
                if (isset($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $value => $label) {
                        $ratingKey = "{$fieldName}->Rating Options->{$value}";
                        if (isset($translations[$ratingKey])) {
                            $field['options'][$value] = $translations[$ratingKey];
                        }
                    }
                }
                break;

            case 'select_country':
                // Update country options labels
                if (isset($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $value => $label) {
                        $countryKey = "{$fieldName}->Country Options->{$value}";
                        if (isset($translations[$countryKey])) {
                            $field['options'][$value] = $translations[$countryKey];
                        }
                    }
                }
                break;

            case 'chained_select':
                // Update chained select data source headers
                if (isset($field['settings']['data_source']['headers']) && is_array($field['settings']['data_source']['headers'])) {
                    foreach ($field['settings']['data_source']['headers'] as $index => $header) {
                        $headerKey = "{$fieldName}->data_source->headers->{$index}";
                        if (isset($translations[$headerKey])) {
                            $field['settings']['data_source']['headers'][$index] = $translations[$headerKey];
                        }
                    }
                }
                break;

            case 'quiz_score':
                // Update personality quiz options
                if (isset($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $optionValue => $optionLabel) {
                        $personalityKey = "{$fieldName}->Personality Options->{$optionValue}";
                        if (isset($translations[$personalityKey])) {
                            $field['options'][$optionValue] = $translations[$personalityKey];
                        }
                    }
                }
                break;

            case 'recaptcha':
            case 'hcaptcha':
            case 'turnstile':
                // These CAPTCHA fields only have labels that are handled by common field processing
                // No additional field-specific translatable content to update
                break;

            case 'accordion':
                // Update accordion title and description
                $titleKey = "{$fieldName}->title";
                if (isset($translations[$titleKey])) {
                    $field['settings']['title'] = $translations[$titleKey];
                }
                $descriptionKey = "{$fieldName}->description";
                if (isset($translations[$descriptionKey])) {
                    $field['settings']['description'] = $translations[$descriptionKey];
                }
                break;

            case 'shortcode':
                // Update shortcode text
                $shortcodeKey = "{$fieldName}->shortcode";
                if (isset($translations[$shortcodeKey])) {
                    $field['settings']['shortcode'] = $translations[$shortcodeKey];
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
        if (!$formId || !is_numeric($formId) || $formId <= 0) {
            return;
        }
        
        if (!$this->isWpmlActive()) {
            return;
        }
        
        if (function_exists('do_action')) {
            do_action('wpml_delete_package', $formId, 'Fluent Forms');
        }
        
        if (class_exists('FluentForm\App\Helpers\Helper')) {
            Helper::setFormMeta($formId, 'ff_wpml', false);
        }
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


    /**
     * Get supported reCAPTCHA language codes
     * Based on: https://developers.google.com/recaptcha/docs/language
     */
    public static function getRecaptchaLocales()
    {
        return [
            'ar' => 'ar',        // Arabic
            'af' => 'af',        // Afrikaans
            'am' => 'am',        // Amharic
            'hy' => 'hy',        // Armenian
            'az' => 'az',        // Azerbaijani
            'eu' => 'eu',        // Basque
            'bn' => 'bn',        // Bengali
            'bg' => 'bg',        // Bulgarian
            'ca' => 'ca',        // Catalan
            'zh-HK' => 'zh-HK',  // Chinese (Hong Kong)
            'zh-CN' => 'zh-CN',  // Chinese (Simplified)
            'zh-TW' => 'zh-TW',  // Chinese (Traditional)
            'hr' => 'hr',        // Croatian
            'cs' => 'cs',        // Czech
            'da' => 'da',        // Danish
            'nl' => 'nl',        // Dutch
            'en' => 'en',        // English (US)
            'en-GB' => 'en-GB',  // English (UK)
            'et' => 'et',        // Estonian
            'fil' => 'fil',      // Filipino
            'fi' => 'fi',        // Finnish
            'fr' => 'fr',        // French
            'fr-CA' => 'fr-CA',  // French (Canadian)
            'gl' => 'gl',        // Galician
            'ka' => 'ka',        // Georgian
            'de' => 'de',        // German
            'de-AT' => 'de-AT',  // German (Austria)
            'de-CH' => 'de-CH',  // German (Switzerland)
            'el' => 'el',        // Greek
            'gu' => 'gu',        // Gujarati
            'iw' => 'he',        // Hebrew (WPML uses 'iw', reCAPTCHA uses 'he')
            'he' => 'he',        // Hebrew
            'hi' => 'hi',        // Hindi
            'hu' => 'hu',        // Hungarian
            'is' => 'is',        // Icelandic
            'id' => 'id',        // Indonesian
            'it' => 'it',        // Italian
            'ja' => 'ja',        // Japanese
            'kn' => 'kn',        // Kannada
            'ko' => 'ko',        // Korean
            'lo' => 'lo',        // Laothian
            'lv' => 'lv',        // Latvian
            'lt' => 'lt',        // Lithuanian
            'ms' => 'ms',        // Malay
            'ml' => 'ml',        // Malayalam
            'mr' => 'mr',        // Marathi
            'mn' => 'mn',        // Mongolian
            'no' => 'no',        // Norwegian
            'fa' => 'fa',        // Persian
            'pl' => 'pl',        // Polish
            'pt' => 'pt',        // Portuguese
            'pt-BR' => 'pt-BR',  // Portuguese (Brazil)
            'pt-PT' => 'pt-PT',  // Portuguese (Portugal)
            'ro' => 'ro',        // Romanian
            'ru' => 'ru',        // Russian
            'sr' => 'sr',        // Serbian
            'si' => 'si',        // Sinhalese
            'sk' => 'sk',        // Slovak
            'sl' => 'sl',        // Slovenian
            'es' => 'es',        // Spanish
            'es-419' => 'es-419', // Spanish (Latin America)
            'sw' => 'sw',        // Swahili
            'sv' => 'sv',        // Swedish
            'ta' => 'ta',        // Tamil
            'te' => 'te',        // Telugu
            'th' => 'th',        // Thai
            'tr' => 'tr',        // Turkish
            'uk' => 'uk',        // Ukrainian
            'ur' => 'ur',        // Urdu
            'vi' => 'vi',        // Vietnamese
            'zu' => 'zu',        // Zulu
        ];
    }

    /**
     * Get supported hCaptcha language codes
     * Based on: https://docs.hcaptcha.com/languages/
     */
    public static function getHcaptchaLocales()
    {
        return [
            'af' => 'af',        // Afrikaans
            'sq' => 'sq',        // Albanian
            'am' => 'am',        // Amharic
            'ar' => 'ar',        // Arabic
            'hy' => 'hy',        // Armenian
            'az' => 'az',        // Azerbaijani
            'eu' => 'eu',        // Basque
            'be' => 'be',        // Belarusian
            'bn' => 'bn',        // Bengali
            'bg' => 'bg',        // Bulgarian
            'ca' => 'ca',        // Catalan
            'zh-CN' => 'zh-CN',  // Chinese (Simplified)
            'zh-TW' => 'zh-TW',  // Chinese (Traditional)
            'hr' => 'hr',        // Croatian
            'cs' => 'cs',        // Czech
            'da' => 'da',        // Danish
            'nl' => 'nl',        // Dutch
            'en' => 'en',        // English
            'et' => 'et',        // Estonian
            'fil' => 'fil',      // Filipino
            'fi' => 'fi',        // Finnish
            'fr' => 'fr',        // French
            'gl' => 'gl',        // Galician
            'ka' => 'ka',        // Georgian
            'de' => 'de',        // German
            'el' => 'el',        // Greek
            'gu' => 'gu',        // Gujarati
            'iw' => 'he',        // Hebrew (WPML uses 'iw', hCaptcha uses 'he')
            'he' => 'he',        // Hebrew
            'hi' => 'hi',        // Hindi
            'hu' => 'hu',        // Hungarian
            'is' => 'is',        // Icelandic
            'id' => 'id',        // Indonesian
            'it' => 'it',        // Italian
            'ja' => 'ja',        // Japanese
            'kn' => 'kn',        // Kannada
            'kk' => 'kk',        // Kazakh
            'km' => 'km',        // Khmer
            'ko' => 'ko',        // Korean
            'ky' => 'ky',        // Kyrgyz
            'lo' => 'lo',        // Lao
            'lv' => 'lv',        // Latvian
            'lt' => 'lt',        // Lithuanian
            'mk' => 'mk',        // Macedonian
            'ms' => 'ms',        // Malay
            'ml' => 'ml',        // Malayalam
            'mt' => 'mt',        // Maltese
            'mn' => 'mn',        // Mongolian
            'my' => 'my',        // Myanmar (Burmese)
            'ne' => 'ne',        // Nepali
            'no' => 'no',        // Norwegian
            'fa' => 'fa',        // Persian
            'pl' => 'pl',        // Polish
            'pt' => 'pt',        // Portuguese
            'pt-BR' => 'pt-BR',  // Portuguese (Brazil)
            'ro' => 'ro',        // Romanian
            'ru' => 'ru',        // Russian
            'sr' => 'sr',        // Serbian
            'si' => 'si',        // Sinhala
            'sk' => 'sk',        // Slovak
            'sl' => 'sl',        // Slovenian
            'es' => 'es',        // Spanish
            'sw' => 'sw',        // Swahili
            'sv' => 'sv',        // Swedish
            'ta' => 'ta',        // Tamil
            'te' => 'te',        // Telugu
            'th' => 'th',        // Thai
            'tr' => 'tr',        // Turkish
            'uk' => 'uk',        // Ukrainian
            'ur' => 'ur',        // Urdu
            'uz' => 'uz',        // Uzbek
            'vi' => 'vi',        // Vietnamese
            'zu' => 'zu',        // Zulu
        ];
    }

    /**
     * Get supported Turnstile language codes
     * Based on: https://developers.cloudflare.com/turnstile/reference/supported-languages/
     */
    public static function getTurnstileLocales()
    {
        return [
            'ar-EG' => 'ar-EG',  // Arabic (Egypt)
            'de' => 'de',        // German
            'en' => 'en',        // English
            'es' => 'es',        // Spanish
            'fa' => 'fa',        // Persian
            'fr' => 'fr',        // French
            'id' => 'id',        // Indonesian
            'it' => 'it',        // Italian
            'ja' => 'ja',        // Japanese
            'ko' => 'ko',        // Korean
            'nl' => 'nl',        // Dutch
            'pl' => 'pl',        // Polish
            'pt-BR' => 'pt-BR',  // Portuguese (Brazil)
            'ru' => 'ru',        // Russian
            'tr' => 'tr',        // Turkish
            'zh-CN' => 'zh-CN',  // Chinese (Simplified)
            'zh-TW' => 'zh-TW',  // Chinese (Traditional)
            'ar' => 'ar-EG',     // Arabic -> Arabic (Egypt)
            'pt' => 'pt-BR',     // Portuguese -> Portuguese (Brazil)
            'zh' => 'zh-CN',     // Chinese -> Chinese (Simplified)
        ];
    }

    public function translateEmailTemplateHeader($header, $form, $notification)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $header;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $header, "form_{$form->id}_email_template_header", $package);
    }

    public function translateEmailTemplateFooter($footer, $form, $notification)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $footer;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $footer, "form_{$form->id}_email_template_footer", $package);
    }

    public function translateEmailSubjectLine($subject, $notification, $submittedData, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $subject;
        }

        $package = $this->getFormPackage($form);
        $feedId = self::$currentNotificationFeedId;
        $key = $feedId !== null
            ? "form_{$form->id}_notification_{$feedId}_subject"
            : "form_{$form->id}_email_subject_line";
        return apply_filters('wpml_translate_string', $subject, $key, $package);
    }

    public function translateEmailBody($emailBody, $notification, $submittedData, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $emailBody;
        }

        $package = $this->getFormPackage($form);
        $feedId = self::$currentNotificationFeedId;
        $key = $feedId !== null
            ? "form_{$form->id}_notification_{$feedId}_message"
            : "form_{$form->id}_email_body";
        $result = apply_filters('wpml_translate_string', $emailBody, $key, $package);
        if ($feedId !== null) {
            self::$currentNotificationFeedId = null;
        }
        return $result;
    }

    public function translateSubscriptionMessage($message, $formData, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_subscription_confirmation_message", $package);
    }

    public function translateRecurringPaymentMessage($message, $formData, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        return apply_filters('wpml_translate_string', $message, "form_{$form->id}_recurring_payment_message", $package);
    }

    public function translateSubmissionMessageParse($message, $insertId, $formData, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $message;
        }

        $package = $this->getFormPackage($form);
        $feedId = self::$currentNotificationFeedId;
        $key = $feedId !== null
            ? "form_{$form->id}_notification_{$feedId}_message"
            : "form_{$form->id}_submission_message_parse";
        return apply_filters('wpml_translate_string', $message, $key, $package);
    }

    public function translateFormSubmissionMessages($messages, $form)
    {
        if (!is_object($form) || !isset($form->id) || !$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'file_upload_in_progress' => 'file_upload_in_progress_message',
            'javascript_handler_failed' => 'javascript_handler_failed_message'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translatePaymentHandlerMessages($messages, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'stock_out_message' => 'payment_stock_out_message',
            'item_label' => 'payment_item_label',
            'price_label' => 'payment_price_label',
            'qty_label' => 'payment_qty_label',
            'line_total_label' => 'payment_line_total_label',
            'sub_total_label' => 'payment_sub_total_label',
            'discount_label' => 'payment_discount_label',
            'total_label' => 'payment_total_label',
            'signup_fee_label' => 'payment_signup_fee_label',
            'trial_label' => 'payment_trial_label',
            'processing_text' => 'payment_processing_text',
            'confirming_text' => 'payment_confirming_text'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translateFormSaveProgressMessages($messages, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'copy_button' => 'save_progress_copy_button',
            'email_button' => 'save_progress_email_button',
            'email_placeholder' => 'save_progress_email_placeholder',
            'copy_success' => 'save_progress_copy_success'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translateAddressAutocompleteMessages($messages, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'please_wait' => 'address_autocomplete_please_wait',
            'location_not_determined' => 'address_autocomplete_location_not_determined',
            'address_fetch_failed' => 'address_autocomplete_address_fetch_failed',
            'geolocation_failed' => 'address_autocomplete_geolocation_failed',
            'geolocation_not_supported' => 'address_autocomplete_geolocation_not_supported'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }

    public function translatePaymentGatewayMessages($messages, $form)
    {
        if (!$this->isWpmlEnabledOnForm($form->id)) {
            return $messages;
        }

        $package = $this->getFormPackage($form);

        $translatableKeys = [
            'request_failed' => 'payment_gateway_request_failed',
            'payment_failed' => 'payment_gateway_payment_failed',
            'no_method_found' => 'payment_gateway_no_method_found',
            'processing_text' => 'payment_gateway_processing_text'
        ];

        foreach ($translatableKeys as $key => $translationKey) {
            if (isset($messages[$key])) {
                $messages[$key] = apply_filters('wpml_translate_string', $messages[$key], "form_{$form->id}_{$translationKey}", $package);
            }
        }

        return $messages;
    }
}