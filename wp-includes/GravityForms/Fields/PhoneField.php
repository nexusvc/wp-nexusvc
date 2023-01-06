<?php

namespace App\GravityForms\Fields;

require_once( __DIR__ . '/../../../../gravityforms/includes/fields/class-gf-field-phone.php' );
require_once( __DIR__ . '/../../../../gravityforms/includes/fields/class-gf-fields.php' );

// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
    die();
}

use \Carbon\Carbon;
use \GuzzleHttp\Client;

// Normalize to E164 helper
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

class PhoneField extends \GF_Field_Phone {

    public $type = 'realvalidate_phone';

    protected $apiKey = 'realvalidate_api_token';
    protected $table  = 'gf_realvalidation';
    protected $url    = 'https://api.realvalidation.com/rpvWebService/RealPhoneValidationTurbo.php?';

    protected $invalid = [
        'disconnected',
        'disconnected-50',
        'disconnected-70',
        'disconnected-85',
        'ERROR',
        'Invalid Phone',
        'invalid-format',
        'invalid-phone',
        'restricted',
    ];

    public function get_form_editor_field_title() {
        return esc_attr__( 'RV Phone', 'gravityforms' );
    }

    protected function forceValid() {
        $this->failed_validation = false;
    }

    public function validate( $value, $form ) {
        if(!$this->isRequired && $value == '') return $this->forceValid();
        // Run Parent Validation
        parent::validate($value, $form);
        // Run Real Validation
        $this->failed_validation = $this->realValidate($value);
        // Set Error Message
        if ( ! empty( $this->errorMessage ) ) {
            $this->validation_message = $this->errorMessage;
        } else {
            $this->validation_message = $this->defaultErrorMessage();
        }
    }

    public function get_field_input( $form, $value = '', $entry = null ) {

        if ( is_array( $value ) ) {
            $value = '';
        }

        $is_entry_detail = $this->is_entry_detail();
        $is_form_editor  = $this->is_form_editor();

        $form_id  = $form['id'];
        $id       = intval( $this->id );
        $field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

        $size          = $this->size;
        $disabled_text = $is_form_editor ? "disabled='disabled'" : '';
        $class_suffix  = $is_entry_detail ? '_admin' : '';
        $class         = $size . $class_suffix;

        $instruction_div = '';
        if ( $this->failed_validation ) {
            $phone_format = $this->get_phone_format();
            if ( rgar( $phone_format, 'instruction' ) ) {
                $instruction_div = sprintf( "<div class='instruction validation_message'>%s %s</div>", esc_html__( 'Phone format:', 'gravityforms' ), $phone_format['instruction'] );
            }
        }

        $html_input_type       = \RGFormsModel::is_html5_enabled() ? 'tel' : 'text';
        $placeholder_attribute = $this->get_field_placeholder_attribute();
        $required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
        $invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
        $aria_describedby      = $this->get_aria_describedby();

        $tabindex = $this->get_tabindex();

        return sprintf( "<div class='ginput_container ginput_container_phone'><input name='input_%d' id='%s' type='{$html_input_type}' value='%s' maxlength='10' class='%s' {$tabindex} {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$aria_describedby} %s/>{$instruction_div}</div>", $id, $field_id, esc_attr( $value ), esc_attr( $class ), $disabled_text );

    }

    protected function hasValidatedBefore($value, $withinDays = 5) {
        // Get WPDB
        global $wpdb;

        // Hash value MD5
        $hash  = md5($value);

        // Timeframe since
        $since = Carbon::now()->startOfDay()
                              ->subDays($withinDays)
                              ->toDateTimeString();

        $table = $wpdb->base_prefix.$this->table;

        // Buld query
        $query = "SELECT
                        *
                    FROM
                        {$table}
                    WHERE
                        hash = %s
                    AND
                        created_at >= %s";

        return $wpdb->get_row($wpdb->prepare( $query, $hash, $since ));
    }

    protected function saveValidation($response) {
        // Save to Database
        // Reduces API Calls
        global $wpdb;

        if(array_key_exists('error_text', $response))
            unset($response['error_text']);

        // Clean response - remove arrays
        foreach($response as $key => $value) {
            if(is_array($response[$key])) unset($response[$key]);
        }

        // '%d', '%f', '%s' (integer, float, string)
        return $wpdb->insert( $wpdb->base_prefix.$this->table, $response);
    }

    protected function realvalidationUrl(array $params = []) {
        return $this->url . http_build_query($params);
    }


    protected function incrementValidationAttempts() {
        if(!session_id()) session_start();
        if(!array_key_exists('validation_attempts', $_SESSION)) $_SESSION['validation_attempts'] = 0;
        $_SESSION['validation_attempts']++;
    }

    protected function realValidate($value) {

        // Get WP Options
        $options = get_option('nexusvc_settings');

        // Force valid if no API Key
        if(!array_key_exists($this->apiKey, $options) ||
            $options[$this->apiKey] == '') return false;

        $value = normalize_phone_to_E164($value);

        // Deny Tollfree
        $tollfree = [
            '+1833',
            '+1844',
            '+1855',
            '+1866',
            '+1877',
            '+1888',
            '+1800',
        ];
        
        $areaCode = substr(normalize_phone_to_E164($value), 0, 5);
        
        if(in_array($areaCode, $tollfree)) {
            // Fails validation when true
            return true;
        }

        // Store validation attempts in session
        $this->incrementValidationAttempts();

        $hasValidatedBefore = $this->hasValidatedBefore($value);

        if(!$hasValidatedBefore) {

            $params = [
                'phone' => $value,
                'token' => $options[$this->apiKey]
            ];

            try {

                $client = new Client;

                try {
                    $request = $client->request('GET', $this->realvalidationUrl($params));
                    $response = \simplexml_load_string($request->getBody(true));
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    $response = \simplexml_load_string($e->getResponse()->getBody(true));
                }

                $response = json_decode(json_encode($response), true);

                if(array_key_exists('status', $response)) {

                    if(!in_array($response['status'], $this->invalid)) {
                        $response['is_valid'] = true;
                    }

                    $response['is_cell']     = $response['iscell'] == 'Y' ? true : false;
                    $response['caller_name'] = $response['cnam'];

                    unset($response['iscell']);
                    unset($response['cnam']);
                }

                $response['hash'] = md5($value);

                $this->saveValidation($response);

                if(!array_key_exists('is_valid', $response)) {
                    return true;
                }

                return !$response['is_valid'];
            } catch(\Exception $e) {
                // Force invalid;
                return true;
            }

        } else {
            return !$hasValidatedBefore->is_valid;
        }
    }

    protected function defaultErrorMessage() {
        return "We can not verify the phone number provided. Please use a different phone number.";
    }

}

// Register the phone field with the field framework.
\GF_Fields::register( new PhoneField() );
