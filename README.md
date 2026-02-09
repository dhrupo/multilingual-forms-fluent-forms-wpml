# Multilingual Forms for Fluent Forms with WPML

A WordPress plugin that integrates [Fluent Forms](https://wordpress.org/plugins/fluentform/) with WPML to create multilingual forms for your WordPress website.

**Plugin Link:** [Multilingual Forms for Fluent Forms with WPML](https://wordpress.org/plugins/multilingual-forms-fluent-forms-wpml/)

## Requirements

This plugin requires the following plugins to be installed and activated:

- [Fluent Forms](https://wordpress.org/plugins/fluentform/) 6.0.2 or higher
- WPML Multilingual CMS 4.6 or higher  
- WPML String Translation 3.2 or higher

You must have all three plugins active for this integration to function properly.

## Installation

1. Install and activate WPML Multilingual CMS plugin and WPML String Translation plugin
2. Install and activate [Fluent Forms](https://wordpress.org/plugins/fluentform/) plugin
3. Install and activate this plugin from the [WordPress repository](https://wordpress.org/plugins/multilingual-forms-fluent-forms-wpml/)
4. Enable Fluent Forms WPML integration from Fluent Forms Integration menu
5. Go to form settings to translate form strings

## Usage

1. Go to specific Form Settings from Fluent Forms which needs to be translated
2. Navigate to WPML Translations from form settings menu sidebar, enable and save it
3. Go to WPML → Translations Management and add the form in translations queue
4. Navigate to WPML → Translations and translate the form against the selected language
5. View the form in the selected language

## Features

- Display form labels, placeholders, and options in the user's preferred language
- Validation messages appear in the user's preferred language
- Support for all Fluent Forms field types
- Compatible with Fluent Forms Pro features
- Easy setup and configuration

## Changelog

### 1.0.3
- Add multiple email notifications support
- Add PHP 7.4 to 8.3 compatibility
- Fix PHP notice "Trying to get property 'id' of non-object" when form is passed as array

### 1.0.1
- Fix object vs array issue (accordion/stdObject)

## About Fluent Forms

[Fluent Forms](https://wordpress.org/plugins/fluentform/) is a light-weight and fastest Form Builder plugin for WordPress. It helps you create hassle-free contact forms, subscription forms, or any kind of forms you need for your website in minutes.
