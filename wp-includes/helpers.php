<?php

require_once( __DIR__ .'/../vendor/autoload.php');

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

if(!defined('QUEUE_COMMAND')) define('QUEUE_COMMAND', 'nxvc');
if(!defined('JOBS_TABLE')) define('JOBS_TABLE', 'jobs');

if(!function_exists('nexusvcError')) {
    function nexusvcError($message, $subtitle = '', $title = '') {
        $title = $title ?: __('Nexusvc &rsaquo; Error', 'nexusvc');
        $footer = '<a href="https://nexusvc.org/wp/plugins/nexusvc/docs/">nexusvc.org/wp/plugins/nexusvc/docs/</a>';
        $message = "<h1>{$title}<br><small>{$subtitle}</small></h1><p>{$message}</p><p>{$footer}</p>";
        wp_die($message, $title);
    }
};

if(!function_exists('snakeToTitle')) {
    function snakeToTitle($value) {
        return Str::title(str_replace('_', ' ', $value));
    }
}

if (! function_exists('normalize_phone_to_E164')) {
    function normalize_phone_to_E164($phone) {

        // get rid of any non (digit, + character)
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // validate intl 10
        if(preg_match('/^\+([2-9][0-9]{9})$/', $phone, $matches)){
            return "+{$matches[1]}";
        }

        // validate US DID
        if(preg_match('/^\+?1?([2-9][0-9]{9})$/', $phone, $matches)){
            return "+1{$matches[1]}";
        }

        // validate INTL DID
        if(preg_match('/^\+?([2-9][0-9]{8,14})$/', $phone, $matches)){
            return "+{$matches[1]}";
        }

        // premium US DID
        if(preg_match('/^\+?1?([2-9]11)$/', $phone, $matches)){
            return "+1{$matches[1]}";
        }

        return $phone;
    }
}

if(!function_exists('getInstallPath')) {
    function getInstallPath() {
        $base = dirname(__FILE__);
        $path = false;

        if (@file_exists(dirname(dirname($base))."/wp-config.php")) {
            $path = dirname(dirname($base))."/wp-config.php";
        } else if (@file_exists(dirname(dirname(dirname($base)))."/wp-config.php")) {
            $path = dirname(dirname(dirname($base)))."/wp-config.php";
        } else {
            $path = false;
        }

        if ($path != false) {
            $path = str_replace("\\", "/", $path);
        }

        return Str::replaceFirst('wp-config.php', '', $path);
    }
}

if(!function_exists('excludedKeys')) {
    function excludedKeys() {
        return [
            'created_by',
            'currency',
            'is_fulfilled',
            'is_read',
            'is_starred',
            'payment_amount',
            'payment_date',
            'payment_method',
            'payment_status',
            'post_id',
            'status',
            'transaction_id',
            'transaction_type',
        ];
    }
}

if(!function_exists('getFormLabel')) {
    function getFormLabel($formId, $key) {
        $formMeta = \GFFormsModel::get_form_meta($formId);
        $field = \GFFormsModel::get_field($formMeta, $key);
        if(is_null($field)) return;
        $label = $field['label'];
        if (is_array(rgar($field, "inputs"))) {
            $label = getSubLabels($key, $field);
        }
        return $label;
    }
}

if(!function_exists('getSubLabels')) {
    function getSubLabels($key, $field) {
        foreach($field["inputs"] as $input) {
            if ($input['id'] == $key) {
                if (!array_key_exists('adminLabel', $input)) {
                    return $input['label'];
                }
                return $input['adminLabel'];
            }
        }
    }
}
