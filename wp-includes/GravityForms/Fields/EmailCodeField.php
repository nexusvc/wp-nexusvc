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

class EmailCodeField extends \GF_Field_Text {

    public $type = 'email_code';

    public function get_form_editor_field_title() {
        return esc_attr__( 'Email Code Verification', 'gravityforms' );
    }

    public function get_form_editor_field_description() {
        return esc_attr__( 'Will validate token against email adress. Should only be included after the Email Opt-In Field has been validated. (Multi Step Form)', 'gravityforms' );
    }

    public function validate( $value, $form ) {

        $originalValue = json_encode( $value );
        $value = is_array( $value ) ? rgar( $value, 0 ) : $value; // Form objects created in 1.8 will supply a string as the value.
        $is_blank = rgblank( $value ) || ( is_array( $value ) && rgempty( array_filter( $value ) ) );

        if($is_blank) {
        
            $this->failed_validation  = true;
            $this->validation_message = "Please enter the code that was sent to the email address you provided.";
        
        } else {

            $email = false;

            foreach($form['fields'] as $field) {
                if($field['type'] == 'email_opt_in') {
                    $input = "input_{$field['id']}";
                    $email = $this->get_input_value_submission( $input );
                    $email = is_array( $email ) ? rgar( $email, 0 ) : $email; // Form objects created in 1.8 will supply a string as the value.
                }
            }

            if(!$email) {
                $this->failed_validation  = true;
                $this->validation_message = "Unable to find the Email Opt-In.";
            }

            if(WpPluginEncryption::alreadyValidated($value, $email)) {
                $this->failed_validation = false;
                return;
            }

            $this->failed_validation = WpPluginEncryption::validate($value, $email);

            if($this->failed_validation) {
                $this->validation_message = "Invalid token provided or the token is expired. Please check the token and try again.";
            }
        }

    }

}

\GF_Fields::register( new EmailCodeField() );
