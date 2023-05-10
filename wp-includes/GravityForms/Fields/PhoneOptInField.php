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
use App\WpPluginEncryption;

class PhoneOptInField extends \GF_Field_Phone {

    public $type = 'phone_opt_in';

    public function get_form_editor_field_title() {
        return esc_attr__( 'Phone Opt-In', 'gravityforms' );
    }

    public function get_form_editor_field_description() {
        return esc_attr__( 'Allows users to enter a valid phone number then generates a 6-digit code for verification.', 'gravityforms' );
    }

    public function validate( $value, $form ) {
        foreach($form['fields'] as $field) {
            if($field['type'] == 'phone_code') {
                $input = "input_{$field['id']}";
                $codeValue = $this->get_input_value_submission( $input );
                $codeValue = is_array( $codeValue ) ? rgar( $codeValue, 0 ) : $codeValue; // Form objects created in 1.8 will supply a string as the value.

                if($codeValue) {
                    if(WpPluginEncryption::alreadyValidated($codeValue, $value)) {
                        $this->failed_validation = false;
                        return;
                    }
                }
            }
        }

        $phone_format = $this->get_phone_format();

        if ( rgar( $phone_format, 'regex' ) && $value !== '' && $value !== 0 && ! preg_match( $phone_format['regex'], $value ) ) {
            $this->failed_validation = true;
            if ( ! empty( $this->errorMessage ) ) {
                $this->validation_message = $this->errorMessage;
            }
        }

        // Lets make sure field is already valid
        if(!$this->failed_validation) {
            // Sms must validate above actions first. If it fails then this will not run.
            WpPluginEncryption::sendSmsVerification($value);
        }

    }

}

\GF_Fields::register( new PhoneOptInField() );
