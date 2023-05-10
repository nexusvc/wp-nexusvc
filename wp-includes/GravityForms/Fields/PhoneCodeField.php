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
use App\WpPluginEncryption;

class PhoneCodeField extends \GF_Field_Text {

    public $type = 'phone_code';

    public function get_form_editor_field_title() {
        return esc_attr__( 'Phone Code Verification', 'gravityforms' );
    }

    public function get_form_editor_field_description() {
        return esc_attr__( 'Will validate 6 digit token against phone number. Should only be included after the Phone Opt-In Field has been validated. (Multi Step Form)', 'gravityforms' );
    }

    public function validate( $value, $form ) {
        $originalValue = json_encode($value);
        $value = is_array( $value ) ? rgar( $value, 0 ) : $value; // Form objects created in 1.8 will supply a string as the value.
        $is_blank = rgblank( $value ) || ( is_array( $value ) && rgempty( array_filter( $value ) ) );

        if($is_blank) {
            $this->failed_validation  = true;
            $this->validation_message = "Please enter the 6 digit code that was sent to the phone number you provided.";
        } else {
            $phone = false;

            foreach($form['fields'] as $field) {
                if($field['type'] == 'phone_opt_in') {
                    $input = "input_{$field['id']}";
                    $phone = $this->get_input_value_submission( $input );
                    $phone = is_array( $phone ) ? rgar( $phone, 0 ) : $phone; // Form objects created in 1.8 will supply a string as the value.
                }
            }

            if(WpPluginEncryption::alreadyValidated($value, $phone)) {
                $this->failed_validation = false;
                return;
            }

            $this->failed_validation = WpPluginEncryption::validate($value, $phone);

            if($this->failed_validation) {
                $this->validation_message = "Invalid token provided or the token is expired. Please check the token and try again.";
            }
        }

    }

}

\GF_Fields::register( new PhoneCodeField() );
