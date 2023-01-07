<?php

if(!function_exists('nexusvcError')) {
    function nexusvcError($message, $subtitle = '', $title = '') {
        $title = $title ?: __('Nexusvc &rsaquo; Error', 'nexusvc');
        $footer = '<a href="https://nexusvc.org/wp/plugins/nexusvc/docs/">nexusvc.org/wp/plugins/nexusvc/docs/</a>';
        $message = "<h1>{$title}<br><small>{$subtitle}</small></h1><p>{$message}</p><p>{$footer}</p>";
        wp_die($message, $title);
    }
};


if (!file_exists($composer = __DIR__.'/../vendor/autoload.php')) {
    nexusvcError(
        __('You must run <code>composer install</code> from the <code>nexusvc</code> plugin directory.', 'nexusvc'),
        __('Autoloader not found.', 'nexusvc')
    );
}

require_once $composer;
require_once( __DIR__ . '/tables.php');

use Illuminate\Support\Str;

class Nexusvc_Settings {

    protected $textDomain = 'nxvc_text_domain';
    protected $settingsGroup;
    protected $settingsSection;

    public function __construct() {

        add_action( 'admin_menu', array( $this, 'adminMenu' ) );
        add_action( 'admin_init', array( $this, 'adminInit'  ) );

    }

    public function adminMenu() {

        add_menu_page(
            esc_html__( 'LDP API', $this->textDomain ),
            esc_html__( 'LDP API', $this->textDomain ),
            'manage_options',
            'nexusvc',
            array( $this, 'settingsPageLayout' ),
            'dashicons-store'
        );

        add_submenu_page( 
            'nexusvc', 
            esc_html__( 'System Status', $this->textDomain ),
            esc_html__( 'System Status', $this->textDomain ),
            'manage_options', 
            'nexusvc.system', 
            array( $this, 'systemStatusPageLayout' )
        );

    }

    public function adminInit() {

        register_setting(
            'nxvc_settings_group',
            'nexusvc_settings'
        );

        add_settings_section(
            'nexusvc_settings_section',
            '',
            false,
            'nexusvc_settings'
        );

        add_settings_field(
            'api_url',
            __( 'API Url', $this->textDomain ),
            array( $this, 'apiUrlField' ),
            'nexusvc_settings',
            'nexusvc_settings_section'
        );

        add_settings_field(
            'username',
            __( 'Username', $this->textDomain ),
            array( $this, 'usernameField' ),
            'nexusvc_settings',
            'nexusvc_settings_section'
        );

        add_settings_field(
            'password',
            __( 'Password', $this->textDomain ),
            array( $this, 'passwordField' ),
            'nexusvc_settings',
            'nexusvc_settings_section'
        );

        // add_settings_field(
        //     'private_key',
        //     __( 'Private Key', $this->textDomain ),
        //     array( $this, 'privateKeyField' ),
        //     'nexusvc_settings',
        //     'nexusvc_settings_section'
        // );

        add_settings_field(
            'domain',
            __( 'Domain', $this->textDomain ),
            array( $this, 'domainField' ),
            'nexusvc_settings',
            'nexusvc_settings_section'
        );

        add_settings_field(
            'forms',
            __( 'Forms', $this->textDomain ),
            array( $this, 'formsField' ),
            'nexusvc_settings',
            'nexusvc_settings_section'
        );

        add_settings_field(
            'realvalidate_api_token',
            __( 'RV API Token', $this->textDomain ),
            array( $this, 'realvalidationField' ),
            'nexusvc_settings',
            'nexusvc_settings_section'
        );

    }

    public function settingsPageLayout() {

        // Check required user capability
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', $this->textDomain ) );
        }

        // Admin Page Layout
        echo '<div class="wrap">' . "\n";
        echo '  <h1>' . get_admin_page_title() . '</h1>' . "\n";
        echo '  <form action="options.php" method="post">' . "\n";

        settings_fields( 'nxvc_settings_group' );
        do_settings_sections( 'nexusvc_settings' );
        submit_button();

        echo '  </form>' . "\n";
        echo '</div>' . "\n";

    }

    public function systemStatusPageLayout() {

        // Check required user capability
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', $this->textDomain ) );
        }

        echo '  <h1>' . get_admin_page_title() . '</h1>';

        $wp_list_table = new SystemStatusTable();
        $wp_list_table->prepare_items();
        
        $wp_list_table->display();
    }

    public function formsField() {

        // Retrieve data from the database.
        $options = get_option( 'nexusvc_settings' );
        // Choices array
        $choices = GFAPI::get_forms();
        // Set default value.
        $value = isset( $options['forms'] ) ? $options['forms'] : [];
        // Field output.
        echo '<select multiple name="nexusvc_settings[forms][]" class="forms_field">';
        foreach($choices as $choice) {

            $selected = in_array($choice['id'], $value) ? 'selected' : '';
            echo '  <option value="'.$choice['id'].'" ' . $selected . '> ' . __( $choice['title'], $this->textDomain ) . '</option>';    
        }
        echo '</select>';
        echo '<p class="description">' . __( 'Select the forms that will be allowed to connect to Nexusvc', $this->textDomain ) . '</p>';
    }

    public function domainField() {
        return $this->generateField(
            'text', 
            'domain', 
            'Domain assigned for marketing campaign'
        );
    }

    public function passwordField() {
        return $this->generateField(
            'text', 
            'password'
        );
    }

    public function apiUrlField() {
        return $this->generateField(
            'text',
            'api_url', 
            'Set the API Post URL for your CRM'
        );
    }

    public function privateKeyField() {
        return $this->generateField(
            'textarea',
            'private_key', 
            'Download your private key file and paste the contents in this field'
        );
    }

    public function realvalidationField() {
        return $this->generateField(
            'text',
            'realvalidate_api_token', 
            'Create an account at https://www.realphonevalidation.com'
        );
    }

    public function usernameField() {
        return $this->generateField(
            'text', 
            'username'
        );
    }

    protected function generateField($type, $name, $description = null, $label = null) {
        // Retrieve data from the database.
        $options = get_option( 'nexusvc_settings' );

        // Set default value.
        $value = esc_attr(isset( $options[$name] ) ? $options[$name] : '');
        $placeholder = esc_attr__( (is_null($label) ? snakeToTitle($name) : $label), $this->textDomain );
        
        // Field output.
        switch($type) {
            case 'password':
            case 'text':
                echo "<input 
                        type=\"{$type}\" 
                        name=\"nexusvc_settings[{$name}]\" 
                        class=\"regular-text {$name}_field\" 
                        placeholder=\"{$placeholder}\" 
                        value=\"{$value}\">";
                break;
            case 'textarea':
                echo "<textarea 
                        type=\"{$type}\" 
                        name=\"nexusvc_settings[{$name}]\" 
                        class=\"regular-text {$name}_field\" 
                        placeholder=\"{$placeholder}\">{$value}</textarea>";
                break;
        }

        if(!is_null($description)) echo '<p class="description">' . __( $description, $this->textDomain ) . '</p>';
    }

}

new Nexusvc_Settings;
