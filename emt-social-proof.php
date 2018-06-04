<?php

/**
 * Plugin Name: EMT Social Proof - XLPlugins
 * Plugin URI: https://xlplugins.com/
 * Description: Sends feeds from your WordPress website
 * Version: 2.0
 * Author: XLPlugins
 * Author URI: https://www.xlplugins.com
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: emt-social-proof
 *
 * @package EMT Social Proof
 * @Category Core
 * @author XLPlugins
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'Emt_Social_Proof' ) ) :

	class Emt_Social_Proof {

		/**
		 * @var Emt_social_proof
		 */
		public static $_instance = null;
		protected static $service_dir;
		public static $integrations;
		public static $active_integrations = array(
			'gf'  => 'gf',
			'wc'  => 'wc',
			'cf7' => 'cf7',
			'edd' => 'edd',
		);

		public function __construct() {

			/**
			 * Load important variables and constants
			 */
			$this->define_plugin_properties();

			/**
			 * Loads activation hooks
			 */
			$this->maybe_load_activation();
			/**
			 * Loads deactivation hooks
			 */
			$this->maybe_load_deactivation();
			/**
			 * Loads all the hooks
			 */
			$this->load_hooks();
		}

		/**
		 * Define Plugin Constants
		 */
		public function define_plugin_properties() {
			/*             * ****** DEFINING CONSTANTS ********* */
			define( 'EMTCORE_ENDPOINT', 'emtclient' );
			define( 'EMT_VERSION', '2.0' );
			define( 'EMT_NAME', 'EarnMoreTrust' );
			define( 'EMT_PLUGIN_FILE', __FILE__ );
			define( 'EMT_SOURCE_PL_DIR', __DIR__ );
			define( 'EMT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			define( 'EMT_FULL_SLUG', 'emt-social-proof' );
			define( 'EMT_SHORT_SLUG', 'emt_' );
			define( 'EMT_DOMAIN', 'https://app.earnmoretrust.com/' );
			define( 'EMT_INTEGRATION_POSTFIX', 'integrations_data' );
			define( 'EMT_GET_ALL_INFO_ENDPOINT', 'api/v1.0/event/getInfo' );
			define( 'EMT_PUSH_SINGLE_FEED_ENDPOINT', 'api/v1.0/feeds/add' );
			define( 'EMT_TOKEN_ENDPOINT', 'api/v1.0/token/get/' );
			define( 'EMT_SYNCWP_ENDPOINT', 'api/v1.0/sync/sync_wp/' );
			define( 'EMT_ADD_BULK_FEED_ENDPOINT', 'api/v1.0/feeds/bulk_add/' );
			define( 'EMT_ALL_DOMAINS', 'emt_all_domains' );
		}

		/**
		 * Loading all the required plugin files
		 */
		public function load_hooks() {
			/** Initializing Functionality */
			add_action( 'plugins_loaded', array( $this, 'emt_init' ), 0 );

			add_action( 'init', array( $this, 'add_endpoint' ) );

			add_action( 'template_redirect', array( $this, 'handle_endpoint' ) );

			/** Initialize Localization */
			add_action( 'init', array( $this, 'emt_init_localization' ) );

			/** Redirecting Plugin to the settings page after activation */
			add_action( 'activated_plugin', array( $this, 'xlutm_settings_redirect' ) );
		}

		/**
		 * @return null|Emt_social_proof
		 */
		public static function get_instance() {
			if ( null == self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		/**
		 * Handle the request of an add_endpoint
		 */
		public function add_endpoint() {
			$api_controller = EMTCORE_ENDPOINT . '_controller';
			$api_callback   = EMTCORE_ENDPOINT . '_callback';

			add_rewrite_tag( "%$api_controller%", '([^&]+)' );
			add_rewrite_tag( "%$api_callback%", '([^&]+)' );
			add_rewrite_rule( '^' . EMTCORE_ENDPOINT . '/([^/]*)/([^/]*)/?', 'index.php?' . $api_controller . '=$matches[1]&' . $api_callback . '=$matches[2]', 'top' );
		}

		public function valid_method( $methodarray, $apicallback ) {
			if ( in_array( $apicallback, $methodarray ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Handle the request of an endpoint
		 */
		function handle_endpoint() {
			global $wp_query;

			$api_controller = sanitize_text_field( $wp_query->get( EMTCORE_ENDPOINT . '_controller' ) );
			$api_callback   = sanitize_text_field( $wp_query->get( EMTCORE_ENDPOINT . '_callback' ) );
			if ( '' != $api_controller && '' != $api_callback ) {
				$integration_object = $this->get_integration( trim( $api_controller ) );
				if ( ! is_null( $integration_object ) && method_exists( $integration_object, $api_callback ) ) {
					$is_valid = Emt_Common::is_token_valid();
					if ( true === $is_valid ) {
						$methodcheckerstatus = $this->valid_method( $integration_object->public_methods, $api_callback );
						if ( true === $methodcheckerstatus ) {
							$integration_object->$api_callback();
						}
						wp_send_json( $integration_object->response );
					} else {
						wp_send_json(
							array(
								'code'  => 4001,
								'error' => 'Invalid Token',
							)
						);
					}
				} else {
					wp_send_json(
						array(
							'code'  => 4000,
							'error' => 'Invalid Method for Url',
						)
					);
				}
				exit;
			}
		}


		/**
		 * Load all integrations
		 * @since 1.0.0
		 * @return type
		 */
		public function load_integrations() {
			$integrations      = array();
			$services_path     = EMT_SOURCE_PL_DIR . '/integrations';
			self::$service_dir = $services_path;
			$handle            = opendir( $services_path );
			if ( $handle ) {
				// load all the integrations folders and their files automatically
				while ( false !== ( $entry = readdir( $handle ) ) ) {
					if ( ! is_file( $entry ) && '.' != $entry && '..' != $entry ) {
						$needed_file = self::$service_dir . '/' . $entry . '/class-instance.php';
						if ( file_exists( $needed_file ) ) {
							$temp = include_once $needed_file;
							if ( ! class_exists( $temp->plugin_class_name ) ) {
								continue;
							}

							if ( in_array( $temp->slug, self::$active_integrations ) ) {
								$temp_slug                  = $temp->slug;
								$integrations[ $temp_slug ] = $temp;
							}
						}
					}
				}
				closedir( $handle );
			}

			self::$integrations = $integrations;
			do_action( 'emt_integrations_loaded' );

			return self::$integrations;
		}

		public function get_integration( $slug = '', $all = false ) {
			if ( $all ) {
				return self::$integrations;
			} else {
				return ( isset( self::$integrations[ $slug ] ) ) ? self::$integrations[ $slug ] : null;
			}

		}

		/**
		 * Loading the main plugin files
		 */
		public function emt_init() {
			// common code can come here
			require 'includes/class-emt-integrations.php';
			require 'includes/class-emt-common.php';

			if ( is_admin() ) {
				// admin
				require'includes/updater.php';
				require 'admin/class-emt-admin.php';
			} else {
				// public
				require 'public/class-emt-public.php';
			}

			$is_site_connected = Emt_Common::emt_get_option( 'emt_all_domains' );
			if ( is_array( $is_site_connected ) && count( $is_site_connected ) > 0 ) {
				$this->load_integrations();
			}
		}

		/**
		 * Added redirection on plugin activation
		 *
		 * @param $plugin
		 */
		public function xlutm_settings_redirect( $plugin ) {
			if ( plugin_basename( __FILE__ ) == $plugin ) {
				// redirect to plugin settings page if available
				wp_redirect(
					add_query_arg(
						array(
							'page'             => 'emt-plugin-settings',
							'check_app_status' => '1',
						), admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}

		public function maybe_load_activation() {
			/** Hooking action to the activation */
			register_activation_hook( __FILE__, array( $this, 'emt_activation' ) );
		}

		public function maybe_load_deactivation() {
			register_deactivation_hook( __FILE__, array( $this, 'emt_deactivation' ) );
		}

		/** Triggering activation initialization */
		public function emt_activation() {
		}

		/** Triggering deactivation initialization */
		public function emt_deactivation() {

		}

		public function emt_init_localization() {
		}

	}

endif;


if ( ! function_exists( 'Emt_Social_Proof' ) ) {

	/**
	 * Global Common function to load all the classes
	 *
	 * @param bool $debug
	 *
	 * @return Emt_Social_Proof
	 */
	function emt_social_proof() {
		return Emt_Social_Proof::get_instance();
	}
}

/** Triggering plugin functionality */
$GLOBALS['Emt_Social_Proof'] = emt_social_proof();
