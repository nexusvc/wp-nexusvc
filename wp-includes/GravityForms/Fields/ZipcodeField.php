<?php

namespace App\GravityForms\Fields;

require_once( __DIR__ . '/../../../../gravityforms/includes/fields/class-gf-field-text.php' );
require_once( __DIR__ . '/../../../../gravityforms/includes/fields/class-gf-fields.php' );

// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
    die();
}

use \Carbon\Carbon;
use \GuzzleHttp\Client;

class ZipcodeField extends \GF_Field_Text {

    public $type = 'zipcode_lookup';

    protected $apiKey;
    protected $table;
    protected $url = 'https://api.zippopotam.us/us/';

    protected $invalid = [
    ];

    public function get_form_editor_field_title() {
        return esc_attr__( 'Zipcode Lookup', 'gravityforms' );
    }

    public function validate( $value, $form ) {
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

        $html_input_type       = 'number';
        $placeholder_attribute = $this->get_field_placeholder_attribute();
        $required_attribute    = $this->isRequired ? 'aria-required="true"' : '';
        $invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
        $aria_describedby      = $this->get_aria_describedby();

        $tabindex = $this->get_tabindex();

        return sprintf( "<div class='ginput_container ginput_container_zipcode_lookup'><input name='input_%d' id='%s' type='{$html_input_type}' value='%s' maxlength='5' pattern='%s' class='%s' {$tabindex} {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$aria_describedby} %s/>{$instruction_div}</div>", $id, $field_id, esc_attr( $value ), esc_attr( $class ), '(\d{5}([\-]\d{4})?)', $disabled_text );

    }


    protected function hasValidatedBefore($value, $withinDays = 5) {
    }

    protected function saveValidation($response) {
    }

    protected function realvalidationUrl(array $params = []) {
        return $this->url . http_build_query($params);
    }

    protected function realValidate($value) {
        $invalid = true;
        $client   = new \GuzzleHttp\Client;

        try {
            $response = $client->get("https://api.zippopotam.us/us/{$value}");
            $response = json_decode($response->getBody(true), true);
            $invalid  = false;
        } catch(\GuzzleHttp\Exception\ClientException $e) {
            $response = json_decode($e->getResponse()->getBody(true), true);
        }

        if($invalid) return $invalid;

        // Save Data to Session for Post
        if(!session_id()) session_start();
        $_SESSION['state_abbreviation'] = $response['places'][0]['state abbreviation'];
        $_SESSION['city'] = $response['places'][0]['place name'];
        $_SESSION['state'] = $response['places'][0]['state'];
    }

    protected function defaultErrorMessage() {
        return "We can not verify the postal code you provided at this time. Please try again or enter a valid 5 Digit US Postal Code.";
    }

}

// Register the phone field with the field framework.
\GF_Fields::register( new ZipcodeField() );
