<?php

/**
 * Plugin Name: Loginator
 * Description: Adds simple global methods for logging to files for developers. 
 * Version: 2.0.1
 * Author: Poly Plugins
 * Author URI: https://www.polyplugins.com
 * Plugin URI: https://wordpress.org/plugins/loginator/
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) exit;

use PolyPlugins\Settings;

register_activation_hook(__FILE__, array('Loginator', 'activation'));

/**
 * Todo
 * Need to handle settings class not being installed for those already using loginator 1.0 and 2.0
 * May need to modify to check for settings class in log function and return just in case
 * Need to update logo
 * Need to style
 */

class Loginator {
  /**
	 * Full path and filename of plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $plugin    Full path and filename of plugin.
	 */
  private $plugin;
  
  /**
	 * Namespace of plugin.
	 *
	 * @since    2.0.1
	 * @access   private
	 * @var      string    $namespace    Namespace of plugin.
	 */
  private $namespace;

  /**
	 * Plugin basename.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $plugin_basename    Get basename of plugin.
	 */
  private $plugin_basename;

  /**
	 * The ID of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $plugin_slug    The ID of this plugin.
	 */
  private $plugin_slug;

  /**
	 * The plugin name
	 *
	 * @since    2.0.1
	 * @access   private
	 * @var      string    $plugin_name    Name of the plugin
	 */
  private $plugin_name;

  /**
	 * The settings class configuration
	 *
	 * @since    2.0.1
	 * @access   private
	 * @var      array    $config    The settings class configuration
	 */
  private $config;

  /**
	 * The plugin's options fields
	 *
	 * @since    2.0.1
	 * @access   private
	 * @var      array    $fields    The plugin's options fields
	 */
  private $fields;

  /**
	 * The Settings class
	 *
	 * @since    2.0.1
	 * @access   private
	 * @var      object    $settings    The Settings class
	 */
  private $settings;

  /**
	 * Store admin notices
	 *
	 * @since    2.0.1
	 * @access   private
	 * @var      array    $admin_notice    Store admin notices
	 */
  private $admin_notice;
  
  /**
	 * The plugin's instance
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      object    $instance    The plugin's instance
	 */
  private static $instance = null;

  public function __construct()
  {
    $this->plugin           = __FILE__;
    $this->namespace        = 'polyplugins';
    $this->plugin_basename  = plugin_basename($this->plugin);
    $this->plugin_slug      = dirname(plugin_basename($this->plugin));
    $this->plugin_name      = __(mb_convert_case(str_replace('-', ' ', $this->plugin_slug), MB_CASE_TITLE), $this->plugin_slug);

    $this->config  = array(
      'page'       => 'options-general.php', // You can use non php pages such as woocommerce here to display a submenu under Woocommerce
      'position'   => 99, // Lower number moves the link position up in the submenu
      'capability' => 'manage_options', // What permission is required to see and edit settings
      'logo'       => '/img/logo.png', // Your custom logo
      'css'        => '/css/style.css', // Your custom colors and styles
      'support'    => 'https://wordpress.org/support/plugin/loginator/', // Your support link
    );

    $this->fields = array(
      'general' => array(
        array(
          'name'    => __('Enabled', $this->plugin_slug),
          'type'    => 'switch',
          'default' => true,
        ),
        array(
          'name'     => __('Emails', $this->plugin_slug),
          'type'     => 'email',
          'default'  => get_bloginfo('admin_email'),
          'required' => true,
          'help'     => __('Enter emails separated by commas to receive critical error alerts. If empty emails will be sent to ' . get_bloginfo('admin_email'), $this->plugin_slug),
        ),
        array(
          'name'    => __('Pipedream URL', $this->plugin_slug),
          'type'    => 'url',
          'help'    => __('Enter a pipedream url to send your log data as a payload to. https://your-id-here.m.pipedream.net', $this->plugin_slug),
        ),
      ),
    );

    add_action('plugins_loaded', array($this, 'dependency_check'));
  }
  
  /**
   * Check if an instance already exists before creating a new one.
   *
   * @since  2.0.0
   * @return object
   */
  public static function getInstance()
  {
    if (!isset(static::$instance)) {
      static::$instance = new static;
    }

    return static::$instance;
  }
  
  /**
   * Plugin should only be initialized on admin as everything else is static
   *
   * @since  2.0.0
   * @return void
   */
  public function init() {
    if (!is_admin()) return;

    $this->migration_check();

    add_action('plugin_action_links_' . $this->plugin_basename, array($this, 'plugin_action_links_loginator'));
  }

  /**
   * Check to see if Reusable Admin Panel is running
   *
   * @since  2.0.1
   * @return void
   */
  public function migration_check() {
    if (get_option('loginator_polyplugins_settings') === false) {
      $old_setting = get_option('loginator_enabled');

      if ($old_setting !== false) {
        $enabled = $old_setting ? 'on' : '';

        $migration = array('general' => 
          array(
            'enabled' => array(
              'value' => $enabled,
              'type' => 'switch',
            ),
            'emails' => array(
              'value' => '',
              'type' => 'email'
            ),
            'pipedream-url' => array(
                'value' => '',
                'type' => 'url'
            )
          )
        );

        update_option('loginator_polyplugins_settings', $migration);
      }
    }
  }

  /**
   * Check to see if Reusable Admin Panel is running
   *
   * @since  2.0.1
   * @return void
   */
  public function dependency_check() {
    if (class_exists('PolyPlugins\Settings')) {
      $this->settings = new Settings($this->plugin, $this->namespace, $this->config, $this->fields);
      $this->settings->init();
    } else {
      $this->add_notice('"' . $this->plugin_name . '"' . " requires <a href='/wp-admin/plugin-install.php?tab=plugin-information&amp;plugin=reusable-admin-panel&amp;TB_iframe=true&amp;width=772&amp;height=608' class='thickbox open-plugin-details-modal' aria-label='More information about Reusable Admin Panel' data-title='Reusable Admin Panel'>Reusable Admin Panel</a> to be installed.");
    }
  }

  /**
   * Plugin Page CTAs
   *
   * @since  2.0.0
   * @return void
   */
  public function plugin_action_links_loginator($links)
  {
    // Prevent uninstallation as once developers start using this plugin, it should never be uninstalled as it will result in errors anywhere you are logging.
    unset($links['deactivate']);
    // Add settings CTA
    $settings_cta = '<a href="' . admin_url('/options-general.php?page=loginator') . '" style="color: orange; font-weight: 700;">Settings</a>';
    array_unshift($links, $settings_cta);
    return $links;
  }
  
  /**
   * Display the notice on the admin backend
   *
   * @since  2.0.1
   * @return void
   */
  public function display_notice() {
    ?>
    <div class="notice notice-<?php echo $this->admin_notice['type']; ?>">
      <p><?php echo $this->admin_notice['message']; ?></p>
    </div>
    <?php
  }
  
  /**
   * Enqueue the admin notice
   *
   * @since  2.0.1
   * @param  string $message The message being displayed in admin
   * @param  string $type Optional. The type of message displayed. Default error.
   * @return void
   */
  private function add_notice($message, $type = 'error') {
    $this->admin_notice = array(
      'message' => $message,
      'type'   => $type
    );

    add_action('admin_notices', array($this, 'display_notice'));
  }

  /**
   * Emergency logging
   *
   * @since  2.0.0
   * @param  mixed $log  The data being logged
   * @param  array $args See log method for available args
   * @return void
   */
  public static function emergency($log, $args = array()) {
    $defaults = array(
      'flag' => 'em',
    );

    $args = wp_parse_args( $args, $defaults );

    self::getInstance()->log($log, $args);
  }

  /**
   * Alert logging
   *
   * @since  2.0.0
   * @param  mixed $log  The data being logged
   * @param  array $args See log method for available args
   * @return void
   */
  public static function alert($log, $args = array()) {
    $defaults = array(
      'flag' => 'a',
    );

    $args = wp_parse_args( $args, $defaults );

    self::getInstance()->log($log, $args);
  }

  /**
   * Critical logging
   *
   * @param  mixed $log  The data being logged
   * @param  array $args See log method for available args
   * @return void
   */
  public static function critical($log, $args = array()) {
    $defaults = array(
      'flag' => 'c',
    );
    
    $args = wp_parse_args( $args, $defaults );

    self::getInstance()->log($log, $args);
  }
  
  /**
   * Error logging
   *
   * @since  2.0.0
   * @param  mixed $log  The data being logged
   * @param  array $args See log method for available args
   * @return void
   */
  public static function error($log, $args = array()) {
    $defaults = array(
      'flag' => 'e',
    );
    
    $args = wp_parse_args( $args, $defaults );

    self::getInstance()->log($log, $args);
  }
  
  /**
   * Warning logging
   *
   * @since  2.0.0
   * @param  mixed $log  The data being logged
   * @param  array $args See log method for available args
   * @return void
   */
  public static function warning($log, $args = array()) {
    $defaults = array(
      'flag' => 'w',
    );
    
    $args = wp_parse_args( $args, $defaults );

    self::getInstance()->log($log, $args);
  }
  
  /**
   * Notice logging
   *
   * @since  2.0.0
   * @param  mixed $log  The data being logged
   * @param  array $args See log method for available args
   * @return void
   */
  public static function notice($log, $args = array()) {
    $defaults = array(
      'flag' => 'n',
    );
    
    $args = wp_parse_args( $args, $defaults );

    self::getInstance()->log($log, $args);
  }

  /**
   * Info logging
   *
   * @since  2.0.0
   * @param  mixed $log  The data being logged
   * @param  array $args See log method for available args
   * @return void
   */
  public static function info($log, $args = array()) {
    $defaults = array(
      'flag' => 'i',
    );
    
    $args = wp_parse_args( $args, $defaults );

    self::getInstance()->log($log, $args);
  }
  
  /**
   * Debug logging
   *
   * @since  2.0.0
   * @param  mixed $log  The data being logged
   * @param  array $args See log method for available args
   * @return void
   */
  public static function debug($log, $args = array()) {
    $defaults = array(
      'flag' => 'd',
      'pipedream' => true,
    );

    $args = wp_parse_args( $args, $defaults );

    self::getInstance()->log($log, $args);
  }

  /**
   * Success logging
   *
   * @since  2.0.0
   * @param  mixed $log  The data being logged
   * @param  array $args See log method for available args
   * @return void
   */
  public static function success($log, $args = array()) {
    $defaults = array(
      'flag' => 's',
    );
    
    $args = wp_parse_args( $args, $defaults );

    self::getInstance()->log($log, $args);
  }

  /**
   * Log data
   * @since  2.0.0
   * @param  mixed $log  The data being logged
   * @param  array $args See $default_args for a list of arguments
   */
  private function log($log, $args) {
    $default_args = array(
			'flag'      => 'd',
			'id'        => '',
			'file'      => '',
			'pipedream' => false,
		);

		$args      = array_map( 'trim', wp_parse_args( $args, $default_args ) );

    $flag      = ($args['flag']) ? $args['flag'] : '';
    $id        = ($args['id']) ? $args['id'] : '';
    $file      = ($args['file']) ? $args['file'] : '';
    $enabled   = self::getInstance()->settings->get_option('general', 'enabled');
    $email     = self::getInstance()->settings->get_option('general', 'email');
    $pipedream = ($args['pipedream']) ? self::getInstance()->settings->get_option('general', 'pipedream-url') : '';

    // Log if enabled
    if ($enabled) {
      // Sanitize
      $file = sanitize_file_name($file);
      $flag = sanitize_text_field($flag);
      $id   = ($id) ? '-' . sanitize_text_field($id) : '';

      // Error Email
      if ($flag === 'c' || $flag === 'em') {
        $to      = ($email) ? $email : get_bloginfo('admin_email');
        $subject = get_bloginfo('name') . ' ' . __('has encountered a critical error!', 'loginator');
        $body    = (is_object($log) || is_array($log)) ? print_r($log, true) : $log;
        
        wp_mail($to, $subject, $body);
      }

      // Pipe Dream
      if (filter_var($pipedream, FILTER_VALIDATE_URL) !== false) {
        $headers = array(
          'Content-Type'  => 'application/json',
        );
    
        $args = array(
          'headers' => $headers,
          'body'    => (!empty($log)) ? json_encode($log) : ''
        );
    
        wp_remote_post($pipedream, $args);
      }

      // Flag Handling
      switch ($flag) {
        case "em":
          $flag = "EMERGENCY";
          break;
        case "a":
          $flag = "ALERT";
          break;
        case "c":
          $flag = "CRITICAL";
          break;
        case "e":
          $flag = "ERROR";
          break;
        case "w":
          $flag = "Warning";
          break;
        case "n":
          $flag = "Notice";
          break;
        case "i":
          $flag = "Info";
          break;
        case "d":
          $flag = "Debug";
          break;
        case "s":
          $flag = "Success";
          break;
        default:
          $flag = "Debug";
          break;
      }

      // Use flag if file empty
      if (empty($file)) {
        $file = strtolower($flag);
      }

      // Save logs
      $dir = ABSPATH . '/wp-logs';
      if (is_object($log) || is_array($log)) {
        file_put_contents($dir . '/' . $file . $id . '.log', $flag . ' ' . date('m-d-y h:i:s') . ': ' . print_r($log, true) . PHP_EOL, FILE_APPEND);
      } else {
        file_put_contents($dir . '/' . $file . $id . '.log', $flag . ' ' . date('m-d-y h:i:s') . ': ' . $log . PHP_EOL, FILE_APPEND);
      }
    }
  }
  
  /**
   * Activation
   *
   * @since  2.0.0
   * @return void
   */
  public static function activation()
  {
    $dir_logs = ABSPATH . '/wp-logs';
    $index    = $dir_logs . '/index.php';
    $htaccess = $dir_logs . '/.htaccess';

    // Check if logs directory exists
    if (!file_exists($dir_logs)) {
      // Make the directory, allow writing so we can add a file
      mkdir($dir_logs, 0755, true);
      // Shhh we don't need script kiddies looking at our logs
      $contents = '<?php' . PHP_EOL . '// Silence is golden';
      file_put_contents($index, $contents);
      // Apache directory blocking
      $contents = 'Order Allow,Deny' . PHP_EOL . 'Deny from All';
      file_put_contents($htaccess, $contents);
    }
  }

}

/**
 * Backwards compatibility
 *
 * @since  2.0.1
 * @return void
 */
if (!function_exists('loginator')) {
  function loginator($log, $flag = 'd', $file = '', $id = '') {
    if (class_exists('PolyPlugins\Settings')) {
      Loginator::debug($log, array(
        'flag'      => $flag,
        'id'        => $id,
        'file'      => $file,
      ));
    }
  }
}

$loginator = Loginator::getInstance();
$loginator->init();