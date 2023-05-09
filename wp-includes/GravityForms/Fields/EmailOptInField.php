<?php

namespace App\GravityForms\Fields;

require_once( __DIR__ . '/../../../../gravityforms/includes/fields/class-gf-field-email.php' );
require_once( __DIR__ . '/../../../../gravityforms/includes/fields/class-gf-fields.php' );

// If Gravity Forms isn't loaded, bail.
if ( ! class_exists( 'GFForms' ) ) {
    die();
}

use \Carbon\Carbon;
use \GuzzleHttp\Client;
use App\Traits\WpPluginEncryption;

class EmailOptInField extends \GF_Field_Email {

    public $type = 'email_opt_in';

    public function get_form_editor_field_title() {
        return esc_attr__( 'Email Opt-In', 'gravityforms' );
    }

    public function get_form_editor_field_description() {
        return esc_attr__( 'Allows users to enter a valid email address then generates a 6-digit code for verification.', 'gravityforms' );
    }

    public function validate( $value, $form ) {
        $email = is_array( $value ) ? rgar( $value, 0 ) : $value; // Form objects created in 1.8 will supply a string as the value.
        $is_blank = rgblank( $value ) || ( is_array( $value ) && rgempty( array_filter( $value ) ) );

        if ( ! $is_blank && ! \GFCommon::is_valid_email( $email ) ) {
            $this->failed_validation  = true;
            $this->validation_message = empty( $this->errorMessage ) ? esc_html__( 'The email address entered is invalid, please check the formatting (e.g. email@domain.com).', 'gravityforms' ) : $this->errorMessage;
        } elseif ( $this->emailConfirmEnabled && ! empty( $email ) ) {
            $confirm = is_array( $value ) ? rgar( $value, 1 ) : $this->get_input_value_submission( 'input_' . $this->id . '_2' );
            if ( $confirm != $email ) {
                $this->failed_validation  = true;
                $this->validation_message = esc_html__( 'Your emails do not match.', 'gravityforms' );
            }
        }

        // Lets make sure field is already valid
        if(!$this->failed_validation) {
            // Email must validate above actions first. If it fails then this will not run.
            WpPluginEncryption::sendEmailVerification($email);

        }

    }

}

\GF_Fields::register( new EmailOptInField() );
