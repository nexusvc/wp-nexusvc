<?php
/**
 *
 * @package Nexusvc
 * @version 3.0.0
 */
/*
 * Plugin Name: WP-Nexusvc
 * Plugin URI: https://nexusvc.org/plugins/wp-nexusvc
 * Description: Nexusvc Marketing Plugin
 * Author: Nexusvc
 * Version: 3.0.0
 * Author URI: https://nexusvc.org
 */
namespace App;

require_once('vendor/autoload.php');

use App\NxvcCore;
use App\Jobs\PostLead;
use App\GravityForms\Fields\EmailOptInField;
use App\GravityForms\Fields\PhoneOptInField;
use App\GravityForms\Fields\EmailCodeField;
use App\GravityForms\Fields\PhoneCodeField;
use App\GravityForms\Fields\PhoneField;
use App\GravityForms\Fields\ZipcodeField;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use App\Models\GfEntryNote;
use LaravelZero\Framework\Application;

class GFSubmit {

    public $app;

    public static function persisted() {
        return [
            'clkid',
            'gclid',
            'msclkid',
            'sigid',
            'utm_content',
            'utm_source',
            'utm_medium',
            'utm_campaign'
        ];
    }

    public function __construct() {

        $this->app = new Application(
            dirname(__DIR__.'/bootstrap')
        );

        $this->app->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            \LaravelZero\Framework\Kernel::class
        );

        $this->app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \Illuminate\Foundation\Exceptions\Handler::class
        );

        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);

        $status = $kernel->handle(
            $input = new \Symfony\Component\Console\Input\ArgvInput,
            new \Symfony\Component\Console\Output\ConsoleOutput
        );

        // $kernel->terminate($input, $status);

        // dd($this->app->getInstance());
        // Enforce Session
        // if(!session_id()) session_start();

        // Import Settings
        require_once('wp-includes/settings.php');


        register_activation_hook( __FILE__, array( $this, 'checkForSupervisorConfig' ) );
        register_activation_hook( __FILE__, array( $this, 'composerUpdateAndMigrate' ) );

        add_action( 'init', [$this, 'persistSessionData']);
        // add_action( 'wp_logout', [$this, 'endSession']);
        // add_action( 'wp_login', [$this, 'endSession']);
        add_action( 'init', [$this, 'consoleSymlink'] );
        add_action( 'admin_init', [$this, 'consoleSymlink'] );
        add_action( 'admin_init', [$this, 'generateSystemFiles'] );
        add_filter( 'gform_entry_meta', [$this, 'entryMetaAttributes'], 10, 2);
        add_action( 'gform_post_submission', [$this, 'enqueueSubmission'], 10, 2 );
        add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'registerEntryMetabox' ), 10, 3 );

        add_action( 'rest_api_init', [$this, 'addRestEndpoints'] );

        add_option( 'nexusvc_db_version', '1.0.0' );

        add_action( 'plugins_loaded', [$this, 'installOptInTokensTable'] );
    }

    public function installOptInTokensTable() {
        global $wpdb;
        global $nexusvc_db_version;

        $nexusvc_db_version = "3.0.0";

        $installed_ver = get_option( "nexusvc_db_version" );

        if($installed_ver == '1.0.0') {
            $table_name = $wpdb->prefix . 'optin_tokens';
            
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                hash varchar(191) NOT NULL,
                token varchar(12) NOT NULL,
                used tinyint(1) DEFAULT 0 NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                expires_at datetime DEFAULT DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 2 MINUTE) NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            dbDelta( $sql );

            update_option( 'nexusvc_db_version', $nexusvc_db_version );
        }
    }

    public function addRestEndpoints() {
      register_rest_route( 'wp-nexusvc/api', '/entry/repost/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => [$this, 'apiEntryRepost'],
      ) );
    }

    public function apiEntryRepost( \WP_REST_Request $request ) {

      global $wpdb;
      global $blog_id;

      $entry = \GFAPI::get_entry( (int)$request['id'] );

      if($entry instanceof \WP_Error) return $entry;

      $trans = [];

      // Nexusvc Options
      $options = get_option( 'nexusvc_settings' );
      $forms   = array_key_exists('forms', $options) ? $options['forms'] : [];


      // Skip if not a registered form
      if(!in_array($entry['form_id'], $forms)) {
        // Create the response object
        $response = new \WP_REST_Response([
          'code' => 'form_config',
          'message' => 'Unable to repost entry. Check the WP-NexusVC configuration.',
          'data' => [
            'status' => 400
          ]
        ]);
        // Add a custom status code
        $response->set_status( 400 );
        return $response;
      }

      // Prepare the FileLoader
      $loader = new FileLoader(new Filesystem(), __DIR__.'/lang');

      // Register the API translator
      $map = new Translator($loader, "api");
      $entryJson = json_encode($entry);
      $formId = $entry['form_id'];
      
      foreach($entry as $key => $value) {
          if(is_numeric($key)) {

                if(($key == "4" && $formId == "3") || ($key == "10" && $formId == "11")) {
                    $key = "dob";
                } else {
                    $key = Str::snake(getFormLabel((int)$entry['form_id'], $key));
                }
              
          }

          if(!in_array($key, excludedKeys())) {
              // Attach to translated payload
              if($key != '' && !array_key_exists($map->get('default.'.$key), $trans)) {
                $trans[ $map->get('default.'.$key) ] = $value;
              } else {
                $trans[$key] = $value;
              }

              // if($value == 'Male') return $trans;
          }
      }
      // return $trans;
      $trans['repost'] = true;

      // foreach($trans as $key => $value) {
      //   if(strpos($key,'default.') !== false) {
      //     unset($trans[$key]);
      //   }
      // }

      if($trans['sigid'] == '') unset($trans['sigid']);
      if($trans['utm_campaign'] == '') unset($trans['utm_campaign']);
      if($trans['utm_medium'] == '') unset($trans['utm_medium']);
      if($trans['utm_source'] == '') unset($trans['utm_source']);
      if($trans['utm_content'] == '') unset($trans['utm_content']);

      if(array_key_exists('electronic_signature', $trans)) {
          if($trans['electronic_signature']) {
              // Should have this plugin
              try {
                  require_once( __DIR__ . '/../gravityformssignature/class-gf-signature.php' );
                  $signature_url = gf_signature()->get_signature_url( $trans['electronic_signature'] );
                  $trans['electronic_signature_url'] = $signature_url;
              } catch(\Exception $e) {
                  $trans['electronic_signature_url'] = 'GFSignature::FAILED';
                  \Log::error("Failed to get gf_signature()->get_signature_url({$trans['electronic_signature']})");
              }
          }
      }

      // return $entry;
      // Sets default medium for fallback
      // In case form does not have medium value
      if(!array_key_exists('domain', $trans)) {
          $trans['domain'] = $options['domain'];
      }

      $trans['blog_id'] = $blog_id;

      // \Log::debug(json_encode($trans));

      // Prepare the output array payload
      $output = ['options' => $options, 'lead' => $trans];

      // die(var_dump($output));

      $transmit = base64_encode(json_encode(serialize($output)));

      // Execute system console command to create job
      exec("php nxvc job:create {$transmit}", $op, $retval);
      $response = new \WP_REST_Response([
        'code' => ($retval == 0 ? 'success' : 'error'),
        'message' => $op[0],
        'data' => [
          'id' => $entry['id'],
          'form_id' => $entry['form_id']
        ]
      ]);
      $response->header( 'Location', $_SERVER['HTTP_REFERER'] );
      return $response;
    }

    public function endSession() {
      session_destroy();
    }

    public function persistSessionData() {
        try{
            if(!session_id()) session_start();
        } catch(\Exception $e) {

        }

      foreach(self::persisted() as $key) {
          if(array_key_exists($key, $_REQUEST) && $_REQUEST[$key] != '') $_SESSION[$key] = $_REQUEST[$key];
          if($key == 'msclkid' && array_key_exists($key, $_REQUEST) && $_REQUEST['msclkid'] != '') $_SESSION['clkid'] = $_REQUEST['msclkid'];
      }
    }

    public function composerUpdateAndMigrate() {
        // activate_blog: fires after a site is actived
        // deactivate_blog: fires after a site is deactivated
        // get_blog_id_from_url: Gets the current blog_id based on url
        // ms_cookie_constants: Defines Multisite cookie constants.


        // $is_network_activated = is_plugin_active_for_network( 'wp-nexusvc/wp-nexusvc.php' );
        // if ( is_multisite() ) { die(json_encode(get_sites())); }
        try {
            $php = shell_exec('which php');
            $bin = __DIR__ . '/nxvc';
            shell_exec("{$php} {$bin} migrate");
        } catch(\Exception $e) {
            return nexusvcError(
                __('Can not migrate tables. This may be due to permissions. You must manually run <code>php nxvc migrate</code> from the plugin root directory.', 'nexusvc'),
                __('Migration Failed', 'nexusvc')
            );
        }

        try {
            $bin = shell_exec('which composer');
            shell_exec("{$bin} update");
        } catch(\Exception $e) {
            return nexusvcError(
                __('Can not install/update the composer dependencies. You must manually run <code>composer update</code> from the plugin root directory.', 'nexusvc'),
                __('Composer Update Failed', 'nexusvc')
            );
        }

    }

    public function checkForSupervisorConfig() {

        if(getenv('SKIP_SUPERVISOR') == true) return;

        NxvcCore::hasSupervisor();

        try {
            if(!file_exists('/etc/supervisor/conf.d/nxvc.conf')) throw new \Exception('No file found at supervisor.conf path');
            $conf = fopen('/etc/supervisor/conf.d/nxvc.conf', 'w');
            if(!$conf) throw new \Exception('No file found at supervisor.conf path');

            $php  = shell_exec(sprintf("which %s", escapeshellarg('php')));
            $nxvc = __DIR__;
            $numprocs = 1;

            $contents = "[program:nxvc]\r\nprocess_name=%(program_name)s_%(process_num)02d\r\ncommand={$php} {$nxvc} queue:work --queue=default --daemon\r\nautostart=true\r\nautorestart=true\r\nnumprocs={$numprocs}\r\n";

            fwrite($conf, $contents);
            fclose($conf);
        } catch(\Exception $e) {
            return nexusvcError(
                __('No write permission on <code>/etc/supervisor/conf.d/</code> to generate <code>nxvc.conf</code> file. You must make the directory writeable by the webserver or manually install the config file.', 'nexusvc'),
                __('Missing configuration', 'nexusvc')
            );
        }
    }

    public function generateSystemFiles() {
        global $wpdb;

        if (!file_exists($composer = __DIR__.'/vendor/autoload.php')) {
            nexusvcError(
                __('You must run <code>composer install</code> from the <code>nexusvc</code> plugin directory.', 'nexusvc'),
                __('Autoloader not found.', 'nexusvc')
            );
        }

        require_once $composer;

        // Generate .env file for nxvc
        $env = fopen(__DIR__.'/.env', 'w') or nexusvcError(
                __('No write permission on <code>'.__DIR__.'</code> to generate .env file. You must make the directory writeable by the webserver.', 'nexusvc'),
                __('Permissions Error.', 'nexusvc')
            );

        $database = DB_NAME;
        $username = DB_USER;
        $password = DB_PASSWORD;
        $dbprefix = $wpdb->base_prefix;

        $contents = "DB_CONNECTION=mysql\r\nDB_DATABASE={$database}\nDB_USERNAME={$username}\nDB_PASSWORD={$password}\nDB_PREFIX={$dbprefix}\n";

        fwrite($env, $contents);
        fclose($env);
    }

    public function registerEntryMetabox( $meta_boxes, $entry, $form ) {
        // Nexusvc Options
        $options = get_option( 'nexusvc_settings' );
        $forms   = $options['forms'];

        // Skip if not a registered form
        if(!in_array($form['id'], $forms)) return;

        $meta_boxes[ 'additional_meta' ] = array(
            'title'    => 'Additional Meta',
            'callback' => array( $this, 'addAdditionalMetaMetabox' ),
            'context'  => 'normal',
            'priority' => 'core'
        );

        $meta_boxes[ 'api_actions' ] = array(
            'title'    => 'API Actions',
            'callback' => array( $this, 'addApiActionsMetabox' ),
            'context'  => 'side'
        );

        return $meta_boxes;
    }

    public function addApiActionsMetabox( $args ) {

        global $blog_id;

        $form  = $args['form'];
        $entry = $args['entry'];
        $action = 'api_actions';

        ?>
        <div class="text-center">
            <a href="/wp-json/wp-nexusvc/api/entry/repost/<?php echo $entry['id']; ?>?blog_id=<?php echo $blog_id; ?>" class="button-primary">Repost Data</a> &nbsp;
            <button disabled type='button' class="button-secondary">Remove Data</button>
        </div>
        <?php
    }

    public function addAdditionalMetaMetabox( $args ) {

        $form  = $args['form'];
        $entry = $args['entry'];

        $html   = '';
        $action = 'additional_meta';

        $fields = [
            'city',
            'state',
            'state_abbreviation',
            'api_response',
            'api_status',
            'lead_id',
            'clkid',
            'sigid',
            'gclid',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'validation_attempts'
        ];

        asort($fields);

        $html .= '<style>#additional_meta .inside { margin:0px!important; padding:0px!important; }</style>';
        $html .= '<table cellspacing="0" class="widefat fixed entry-detail-view" style="margin:0px!important; border-top:0px; border-left:0px; border-right:0px; border-bottom:0px;">';
        $html .= '    <tbody>';
        foreach($fields as $field) {
            $html .= '        <tr>';
            $html .= '            <td colspan="2" class="entry-view-field-name">'.snakeToTitle($field).'</td>';
            $html .= '        </tr>';
            $html .= '        <tr>';
            $html .= '            <td colspan="2" class="entry-view-field-value">'. (rgar( $entry, $field ) != '' ? rgar( $entry, $field ) : '-') .'</td>';
            $html .= '        </tr>';
        }
        $html .= '    </tbody>';
        $html .= '</table>';

        echo $html;
    }

    public function envSymlink() {
        if(!is_link( __DIR__ . '/.env-test')) {
            symlink( ABSPATH . '/.env', __DIR__ . '/.env-test' );
        }
    }

    public function consoleSymlink() {
        if(!is_link( ABSPATH . '/' . QUEUE_COMMAND )) {
            symlink( __DIR__ . '/nxvc', ABSPATH . '/' . QUEUE_COMMAND );
        }
    }

    public function entryMetaAttributes($entry_meta, $form_id){
        $entry_meta['city'] = array(
            'label' => 'City',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['state'] = array(
            'label' => 'State',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['state_abbreviation'] = array(
            'label' => 'State Abbreviation',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['api_response'] = array(
            'label' => 'API Response',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueAPIResponse'],
            'is_default_column' => true
        );
        $entry_meta['api_status'] = array(
            'label' => 'API Status',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueAPIResponse'],
            'is_default_column' => true
        );
        $entry_meta['lead_id'] = array(
            'label' => 'Lead ID',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['session_data'] = array(
            'label' => 'Session Data',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['clkid'] = array(
            'label' => 'CLKID',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['gclid'] = array(
            'label' => 'GCLID',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['sigid'] = array(
            'label' => 'SIGID',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['utm_campaign'] = array(
            'label' => 'UTM Campaign',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['utm_content'] = array(
            'label' => 'UTM Content',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['utm_source'] = array(
            'label' => 'UTM Source',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['utm_medium'] = array(
            'label' => 'UTM Medium',
            'is_numeric' => false,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueString'],
            'is_default_column' => true
        );
        $entry_meta['validation_attempts'] = array(
            'label' => 'Validation Attempts',
            'is_numeric' => true,
            'update_entry_meta_callback' => [$this, 'defaultMetaValueZero'],
            'is_default_column' => true
        );
        return $entry_meta;
    }

    public function defaultMetaValueZero($key, $lead, $form) {
        return 0;
    }

    public function defaultMetaValueString( $key, $lead, $form ){
        return '';
    }

    public function defaultMetaValueAPIResponse( $key, $lead, $form ){
        return "Pending";
    }

    public function enqueueSubmission( $lead, $form ) {
        global $wpdb;
        global $blog_id;

        $trans = [];

        // Nexusvc Options
        $options = get_option( 'nexusvc_settings' );
        $forms   = array_key_exists('forms', $options) ? $options['forms'] : [];

        $formId = $form['id'];

        // Skip if not a registered form
        if(!in_array($form['id'], $forms)) return;

        if(isset($_SESSION) && is_array($_SESSION)) {
            $_SESSION['session_id'] = session_id();

            // $lead['session_data'] = base64_encode(json_encode($_SESSION));

            // @todo save medium

            if(array_key_exists('sigid', $_SESSION)) {
                $lead['sigid'] = $_SESSION['sigid'];
                gform_update_meta( $lead['id'], 'sigid', $lead['sigid'] );
            }

            if(array_key_exists('city', $_SESSION)) {
                $lead['city'] = $_SESSION['city'];
                gform_update_meta( $lead['id'], 'city', $lead['city'] );
            }

            if(array_key_exists('state', $_SESSION)) {
                $lead['state'] = $_SESSION['state'];
                gform_update_meta( $lead['id'], 'state', $lead['state'] );
            }

            if(array_key_exists('state_abbreviation', $_SESSION)) {
                $lead['state_abbreviation'] = $_SESSION['state_abbreviation'];
                gform_update_meta( $lead['id'], 'state_abbreviation', $lead['state_abbreviation'] );
            }

            if(array_key_exists('clkid', $_SESSION)) {
                $lead['clkid'] = $_SESSION['clkid'];
                gform_update_meta( $lead['id'], 'clkid', $lead['clkid'] );
            }

            if(array_key_exists('utm_campaign', $_SESSION)) {
                $lead['utm_campaign'] = $_SESSION['utm_campaign'];
                gform_update_meta( $lead['id'], 'utm_campaign', $lead['utm_campaign'] );
            }

            if(array_key_exists('utm_medium', $_SESSION)) {
                $lead['utm_medium'] = $_SESSION['utm_medium'];
                gform_update_meta( $lead['id'], 'utm_medium', $lead['utm_medium'] );
            }

            if(array_key_exists('utm_source', $_SESSION)) {
                $lead['utm_source'] = $_SESSION['utm_source'];
                gform_update_meta( $lead['id'], 'utm_source', $lead['utm_source'] );
            }

            if(array_key_exists('utm_content', $_SESSION)) {
                $lead['utm_content'] = $_SESSION['utm_content'];
                gform_update_meta( $lead['id'], 'utm_content', $lead['utm_content'] );
            }

            if(array_key_exists('gclid', $_SESSION)) {
                $lead['gclid'] = $_SESSION['gclid'];
                $lead['clkid'] = $_SESSION['gclid'];
                gform_update_meta( $lead['id'], 'gclid', $lead['gclid'] );
                gform_update_meta( $lead['id'], 'clkid', $lead['gclid'] );
            }

            if(array_key_exists('msclkid', $_SESSION)) {
                $lead['msclkid'] = $_SESSION['msclkid'];
                gform_update_meta( $lead['id'], 'msclkid', $lead['msclkid'] );
                $lead['clkid'] = $_SESSION['msclkid'];
                gform_update_meta( $lead['id'], 'clkid', $lead['clkid'] );
                $lead['gclid'] = $_SESSION['msclkid'];
                gform_update_meta( $lead['id'], 'gclid', $lead['gclid'] );
            }

            if(array_key_exists('validation_attempts', $_SESSION)) {
                $lead['validation_attempts'] = "{$_SESSION['validation_attempts']}";
                gform_update_meta( $lead['id'], 'validation_attempts', $lead['validation_attempts'] );
            }

            // gform_update_meta( $lead['id'], 'session_data', $lead['session_data'] );
        }

        // Prepare the FileLoader
        $loader = new FileLoader(new Filesystem(), __DIR__.'/lang');

        // Register the API translator
        $map = new Translator($loader, "api");

        foreach($lead as $key => $value) {
             if(is_numeric($key)) {
                  if(($key == "4" && $formId == "3") || ($key == "10" && $formId == "11")) {
                      $key = "dob";
                  } else {
                      $key = Str::snake(getFormLabel((int)$lead['form_id'], $key));
                  }
                
            }

            if(!in_array($key, excludedKeys())) {
               // Attach to translated payload
               if($key != '' && !array_key_exists($map->get('default.'.$key), $trans)) {
                 $trans[ $map->get('default.'.$key) ] = $value;
               } else {
                 $trans[$key] = $value;
               }
               // if($value == 'Male') return $trans;
            }
        }

        if(array_key_exists('electronic_signature', $trans)) {
            if($trans['electronic_signature']) {
                // Should have this plugin
                try {
                    require_once( __DIR__ . '/../gravityformssignature/class-gf-signature.php' );
                    $signature_url = gf_signature()->get_signature_url( $trans['electronic_signature'] );
                    $trans['electronic_signature_url'] = $signature_url;
                } catch(\Exception $e) {
                    $trans['electronic_signature_url'] = 'GFSignature::FAILED';
                    \Log::error("Failed to get gf_signature()->get_signature_url({$trans['electronic_signature']})");
                }
            }
        }

        // Sets default medium for fallback
        // In case form does not have medium value
        if(!array_key_exists('domain', $trans)) {
            $trans['domain'] = $options['domain'];
        }

        // Set the default site ID
        $trans['blog_id'] = $blog_id;

        // Session Entry
        $_SESSION['entry'] = $trans;

        // Prepare the output array payload
        $output = ['options' => $options, 'lead' => $trans];

        // Encode & Serialize for Command
        // @todo: Encrypt Payload
        $transmit = base64_encode(json_encode(serialize($output)));

        // Execute system console command to create job
        try {
          $job = exec("php nxvc job:create {$transmit}", $op, $retval);
          if($retval === 0) {
            gform_update_meta( $trans['source_id'], 'api_status', $op[0]);
          } else {
            gform_update_meta( $trans['source_id'], 'api_status', 'PayloadError: Failed on job:create, entry was not queued to API.' );
          }
        } catch(\Exception $e) {
          // $result = \GFAPI::add_note( $lead['id'], 0, 'Admin Notification (ID: 5e68b0de6362d)', $e->getMessage() );
          gform_update_meta( $trans['source_id'], 'api_status', 'PayloadError: Failed on job:create, entry was not queued to API.' );
        }

        if(!session_id()) session_start();
        $_SESSION['validation_attempts'] = 0;
    }
}

// Php overwrite for session data
// ini_set('session.save_path','/var/www/html/wp-content/cache');
// ini_set('session.cookie_secure','Off');
// Force start session if not already started
if(!session_id()) session_start();
// Persist data
foreach(GFSubmit::persisted() as $key) {
    if(array_key_exists($key, $_REQUEST) && $_REQUEST[$key] != '') $_SESSION[$key] = $_REQUEST[$key];
    if($key == 'msclkid' && array_key_exists($key, $_REQUEST) && $_REQUEST['msclkid'] != '') $_SESSION['clkid'] = $_REQUEST['msclkid'];
    if($key == 'gclid' && array_key_exists($key, $_REQUEST) && $_REQUEST['gclid'] != '') $_SESSION['clkid'] = $_REQUEST['gclid'];
}

new GFSubmit;
new PhoneField;
new ZipcodeField;
new EmailOptInField;
new EmailCodeField;
new PhoneOptInField;
new PhoneCodeField;
