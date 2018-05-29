<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emt_Common {

	protected static $instance = null;
	public static $http;
	public static $user_api_key         = null;
	public static $user_api_secret_key  = null;
	public static $javascript_file_path = null;
	public static $site_connect_url     = '';
	public static $site_disconnect      = '';

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'load_rules_classes' ) );
		add_action( 'wp', array( $this, 'verify_domain_with_app' ) );
		add_action( 'wp', array( $this, 'get_integrations_data_from_emt' ) );
		add_action( 'wp', array( $this, 'get_feeds_for_emt' ) );
		add_action( 'wp', array( $this, 'delete_domain_data' ) );
		add_action( 'wp_loaded', array( $this, 'check_app_sites' ) );
	}

	/**
	 * Return an instance of this class.
	 * @since 1.0.0
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Load utility and compatibility classes
	 */
	public static function load_rules_classes() {
		//Include the compatibility class
		include plugin_dir_path( EMT_PLUGIN_FILE ) . 'includes/class-emt-compatibility.php';
	}

	/**
	 * Activate the License On the Worpdress Site by connecting to EMT.com
	 */
	public static function activate_license() {
		return self::get_token();
	}

	/**
	 * Get a token from EMT.com
	 */

	public static function get_token() {
		if ( is_null( self::$user_api_key ) && is_null( self::$user_api_secret_key ) ) {
			$api_keys                  = self::get_api_keys();
			self::$user_api_key        = $api_keys['emt_api_key'];
			self::$user_api_secret_key = $api_keys['emt_secret_key'];
		}

		$final_output = null;
		$api_url      = EMT_DOMAIN . EMT_TOKEN_ENDPOINT;
		$data         = [
			'key'    => self::$user_api_key,
			'secret' => self::$user_api_secret_key,
		];
		if ( '' != self::$site_connect_url ) {
			$data = [
				'key'          => self::$user_api_key,
				'secret'       => self::$user_api_secret_key,
				'site_connect' => self::$site_connect_url,
			];
		}

		if ( '' != self::$site_disconnect ) {
			$data = [
				'key'             => self::$user_api_key,
				'secret'          => self::$user_api_secret_key,
				'site_disconnect' => '1',
				'site_url'        => site_url(),
			];
		}
		$httppostrequest = Emt_Common::http()->post(
			$api_url, array(
				'body'      => $data,
				'sslverify' => false,
				'timeout'   => 30,
			)
		);
		$body            = $httppostrequest['body'];

		if ( '' != $body ) {
			$response = json_decode( $body, true );
			if ( ! isset( $response['code'] ) && is_array( $response ) && count( $response ) > 0 ) {
				$final_output = $response['token'];
				$site_id      = $response['site_id'];
				update_option( 'emt-current-token', $final_output );
				update_option( 'emt-site-id', $site_id );
			} else {
				$final_output = $response;
			}
		}

		return $final_output;
	}

	/*
	 * Check if a token is valid or not
	 */

	public static function is_token_valid() {
		return true;
	}

	/*
	 * Returns data from options table
	 */

	public static function emt_get_option( $key ) {
		return get_option( $key );
	}

	/*
	 * Update data in options table
	 */

	public static function emt_update_option( $key, $value ) {
		update_option( $key, $value );
	}

	/**
	 * Default settings commonly for admin & public end
	 * This function returns all the default options of the plugin.
	 */
	public static function get_default_plugin_options() {
		$default_options = array(
			'emt-license-activation' => array(
				'emt_api_key'              => '',
				'emt_secret_key'           => '',
				'emt_user_key'             => '',
				'emt_javascript_file_path' => '',
			),
			'emt-integrations'       => array(
				'emt_woocommerce'            => '',
				'emt_easy_digital_downloads' => '',
				'emt_gravity_forms'          => '',
				'emt_contact_form7'          => '',
			),
			'emt-events'             => array(
				'emt_woocommerce_new_order'            => '',
				'emt_woocommerce_new_product_review'   => '',
				'emt_easy_digital_downloads_new_order' => '',
				'emt_gravity_forms_new_entry'          => '',
				'emt_contact_form7_new_entry'          => '',
			),
		);

		return apply_filters( 'modify_default_plugin_options', $default_options );
	}

	/*
	 * This function returns the values of all the fields of a single tab.
	 * It return default values if user has not saved the tab.
	 */

	public static function get_tab_options( $tab_slug ) {
		$tab_options            = array();
		$plugin_default_options = self::get_default_plugin_options();
		if ( isset( $plugin_default_options[ $tab_slug ] ) ) {
			$default_options    = $plugin_default_options[ $tab_slug ];
			$default_options_db = self::get_plugin_saved_settings( $tab_slug );
			if ( '' == $default_options_db ) {
				$tab_options = $default_options;
			} else {
				foreach ( $default_options as $key1 => $value1 ) {
					if ( isset( $default_options_db[ $key1 ] ) && '' != $default_options_db[ $key1 ] ) {
						$tab_options[ $key1 ] = $default_options_db[ $key1 ];
					} else {
						$tab_options[ $key1 ] = $value1;
					}
				}
			}
		}

		return $tab_options;
	}

	/*
	 * This function returns the setting of a single tab.
	 */

	public static function get_plugin_saved_settings( $option_key ) {
		return get_option( $option_key );
	}

	/*
	 * This function returns all the settings tabs of the plugin.
	 */

	public static function get_default_plugin_settings_tabs() {
		$my_plugin_tabs = array(
			'emt-license-activation' => __( 'Settings', 'emt-social-proof' ),
			'emt-integrations'       => __( 'Integrations', 'emt-social-proof' ),
		//          'emt-events'             => __( 'EMT Events', 'emt-social-proof' ),
		);

		return $my_plugin_tabs;
	}

	/**
	 * This function returns the tab heading of current active tab
	 *
	 * @param $tab
	 *
	 * @return mixed
	 */
	public static function get_tab_headings( $tab ) {
		$my_plugin_tabs = array(
			'emt-license-activation' => __( 'Enter Your Site Api Keys', 'emt-social-proof' ),
			'emt-integrations'       => __( 'Integrations Settings', 'emt-social-proof' ),
		);

		return $my_plugin_tabs[ $tab ];
	}

	public static function http() {
		if ( null == self::$http ) {
			self::$http = new WP_Http();
		}

		return self::$http;
	}

	/**
	 * This function runs when a connection request is made by the user from EMT.com
	 */
	public function verify_domain_with_app() {
		if ( isset( $_GET['action'] ) && 'connect_wp_site' == $_GET['action'] ) {
			$all_supported_integrations = $GLOBALS['Emt_Social_Proof']->get_integration( '', true );
			$emt_license_activation     = get_option( 'emt-license-activation' );
			if ( is_array( $emt_license_activation ) && count( $emt_license_activation ) > 0 ) {
				if ( $_GET['site_api_key'] == $emt_license_activation['emt_api_key'] && $_GET['site_api_secret'] == $emt_license_activation['emt_secret_key'] ) {
					update_option( 'emt-site-id', $_GET['site_id'] );
					$response               = array(
						'status'  => 1,
						'message' => 'Connected Successfully.',
					);
					$activated_integrations = Emt_Common::get_active_integrations( $all_supported_integrations );
					if ( is_array( $activated_integrations ) && count( $activated_integrations ) > 0 ) {
						$response['activated_integrations'] = json_encode( $activated_integrations );
						$gf_forms_data                      = $GLOBALS['Emt_Social_Proof']->get_integration( 'gf' )->get_all_gf_forms_with_fields();
						if ( is_array( $gf_forms_data ) && count( $gf_forms_data ) > 0 ) {
							$response['gf_data'] = json_encode( $gf_forms_data );
						}
					}
					$response = json_encode( $response );
				} else {
					$response = json_encode(
						array(
							'status'  => 0,
							'message' => 'Incorrect API Keys. Please Check API Keys On Your Wordpress Website.',
						)
					);
				}
			} else {
				$response = json_encode(
					array(
						'status'  => 0,
						'message' => 'Please Install And Configure The EMT Plugin On Your Wordpress Website.',
					)
				);
			}
			echo $response;
			exit;
		}
	}

	/**
	 * This function returns all the supported integrations
	 *
	 * @return array
	 */
	public static function sync_with_emt() {
		$response                   = array();
		$all_supported_integrations = $GLOBALS['Emt_Social_Proof']->get_integration( '', true );
		$emt_all_domains            = get_option( 'emt_all_domains' );
		if ( is_array( $emt_all_domains ) && count( $emt_all_domains ) > 0 ) {
			$response               = array();
			$activated_integrations = Emt_Common::get_active_integrations( $all_supported_integrations );
			if ( is_array( $activated_integrations ) && count( $activated_integrations ) > 0 ) {
				$response['activated_integrations'] = json_encode( $activated_integrations );
				if ( isset( $activated_integrations['gf'] ) ) {
					$gf_forms_data = $GLOBALS['Emt_Social_Proof']->get_integration( 'gf' )->get_all_gf_forms_with_fields();
					if ( is_array( $gf_forms_data ) && count( $gf_forms_data ) > 0 ) {
						$response['gf_data'] = json_encode( $gf_forms_data );
					}
				}
				if ( isset( $activated_integrations['cf7'] ) ) {
					$forms_data = $GLOBALS['Emt_Social_Proof']->get_integration( 'cf7' )->get_all_forms_with_fields();
					if ( is_array( $forms_data ) && count( $forms_data ) > 0 ) {
						$response['cf7_data'] = json_encode( $forms_data );
					}
				}
			}
			$response = $response;
		}

		return $response;
	}

	/**
	 * @param $api_url
	 * @param $data
	 * @param $token
	 *
	 * This function sends an api call to given url with specified data
	 *
	 * @return array
	 */
	public static function emt_api_call( $api_url, $data, $token ) {
		$data            = [
			'token' => $token,
			'data'  => $data,
		];
		$httppostrequest = Emt_Common::http()->post(
			$api_url, array(
				'body'      => $data,
				'sslverify' => false,
				'timeout'   => 30,
			)
		);
		if ( isset( $httppostrequest['body'] ) ) {
			$body = $httppostrequest['body'];
			$body = (array) json_decode( $body );
		} else {
			$body = array(
				'code' => 0,
				'msg'  => 'Empty Body',
			);
		}

		return $body;
	}

	/**
	 * @param $all_supported_integrations
	 *
	 * This function returns all the supported plugins information
	 *
	 * @return array
	 */
	public static function get_active_integrations( $all_supported_integrations ) {
		$result = array();
		foreach ( $all_supported_integrations as $key1 => $value1 ) {
			if ( isset( $value1->plugin_class_name ) && class_exists( $value1->plugin_class_name ) ) {
				$result[ $key1 ] = $key1;
			}
		}

		return $result;
	}

	public static function get_api_keys() {
		return self::emt_get_option( 'emt-license-activation' );
	}

	/**
	 * This function runs when user updates its event on EMT.com.
	 * This function fires a call to EMT.com and collects all the integration's and event's details
	 */
	public function get_integrations_data_from_emt() {
		if ( isset( $_GET['action'] ) && 'fetch_integrations_data' == $_GET['action'] ) {
			self::$user_api_key        = $_GET['site_api_key'];
			self::$user_api_secret_key = $_GET['site_api_secret'];
			Emt_Integrations::get_integrations_data( $_GET['site_api_key'], $_GET['user_id'], $_GET['site_id'] );
			echo json_encode( array( 'status' => '1' ) );
			exit;
		}
	}

	/**
	 * Get the site_api_key from EMT when a site is deleted
	 */
	public function delete_domain_data() {
		if ( isset( $_GET['action'] ) && 'delete_source_data' == $_GET['action'] ) {
			if ( isset( $_GET['site_api_key'] ) && '' != $_GET['site_api_key'] ) {
				$response = $this->delete_source_data( $_GET['site_api_key'] );
				wp_send_json( $response );
			}
		}
	}

	/**
	 * Run app sites checking to see which sites are present in EMT.com
	 */
	public function check_app_sites() {
		if ( isset( $_GET['check_app_status'] ) && '1' == $_GET['check_app_status'] ) {
			self::check_app_keys();
		}
	}

	/**
	 * @param $site_api_key
	 *
	 * Deletes the site data from current WordPress site.
	 *
	 * @return array
	 */
	public function delete_source_data( $site_api_key ) {
		$response    = array(
			'status'  => 1,
			'message' => 'No data to delete',
		);
		$all_domains = get_option( 'emt_all_domains' );
		if ( isset( $all_domains[ $site_api_key ] ) ) {
			unset( $all_domains[ $site_api_key ] );
			update_option( 'emt_all_domains', $all_domains );
			delete_option( 'emt_integrations_data_' . $site_api_key );
			$response = array(
				'status'  => 1,
				'message' => 'Data deleted from source',
			);
		}

		return $response;
	}

	/**
	 * This function runs when sync button is pressed on EMT.com for syncing the feeds of an event on EMT.com
	 */
	public function get_feeds_for_emt() {
		if ( isset( $_GET['action'] ) && 'fetch_integrations_feeds' == $_GET['action'] ) {
			if ( 'gf' == $_GET['slug'] ) {
				$fields        = html_entity_decode( stripslashes( $_GET['fields'] ) );
				$fields        = json_decode( $fields );
				$gf_feeds_data = $GLOBALS['Emt_Social_Proof']->get_integration( $_GET['slug'] )->get_gf_form_fields_value( $_GET['form_id'], $fields, $_GET['count'] );
				if ( is_array( $gf_feeds_data ) && count( $gf_feeds_data ) > 0 ) {
					$event_fields   = $fields;
					$temp_structure = array();
					foreach ( $event_fields as $key3 => $value3 ) {
						$temp_val                       = explode( ':', $value3 );
						$temp_structure[ $temp_val[0] ] = $temp_val[1];
					}

					$data_to_send[ $event_id ]     = $event_fields_value;
					$event_fields_value_temp       = array();
					$final_event_fields_value_temp = array();
					$count                         = 1;
					foreach ( $gf_feeds_data as $key1 => $value1 ) {
						foreach ( $value1['fields'] as $key4 => $value4 ) {
							$array_key = $key4 . ':' . $temp_structure[ $key4 ];
							if ( 'ip' == $key4 ) {
								$array_key = $key4;
							}
							if ( 'email' == $key4 ) {
								$event_fields_value_temp['email'] = $value4;
								continue;
							}
							$event_fields_value_temp[ $array_key ] = $value4;
						}
						$single_array                    = array(
							'timestamp' => ( $value1['timestamp'] + $count ),
							'fields'    => $event_fields_value_temp,
						);
						$final_event_fields_value_temp[] = $single_array;
						$count ++;
					}
					$data['data'] = $final_event_fields_value_temp;
					$result       = array(
						'status' => '1',
						'data'   => $data,
					);
				} else {
					$result = array(
						'status'  => '0',
						'message' => 'No Entries',
					);
				}
			} else {
				$feeds_data = $GLOBALS['Emt_Social_Proof']->get_integration( $_GET['slug'] )->get_data_for_syncing( $_GET['slug'], $_GET['event_type'], $_GET['count'], $_GET['event_id'], $_GET['api_key'], $_GET['api_secret_key'] );
				if ( is_array( $feeds_data ) && count( $feeds_data ) > 0 ) {
					$result = array(
						'status' => '1',
						'data'   => $feeds_data,
					);
				} else {
					$result = array(
						'status'  => '0',
						'message' => 'No Entries',
					);
				}
			}

			echo json_encode( $result );
			exit;
		}
	}

	public static function object_to_array( $obj ) {
		if ( is_object( $obj ) ) {
			$obj = (array) $obj;
		}
		if ( is_array( $obj ) ) {
			$new = array();
			foreach ( $obj as $key => $val ) {
				$new[ $key ] = self::object_to_array( $val );
			}
		} else {
			$new = $obj;
		}

		return $new;
	}

	/**
	 * @param $post
	 *
	 * This function send api details to EMT.com when connect button is pressed from plugin settings screen
	 *
	 * @return array|mixed|null|object
	 */
	public static function connect_site( $post ) {
		self::$user_api_key        = $post['api_key'];
		self::$user_api_secret_key = $post['api_secret_key'];
		self::$site_connect_url    = site_url();
		$response                  = self::activate_license();

		return $response;
	}

	/**
	 * @param $post
	 *
	 * This function send api details to EMT.com when disconnect button is pressed from plugin settings screen.
	 * Site is disconnected and no data is sent to EMT.com
	 *
	 * @return array|mixed|null|object
	 */
	public static function disconnect_site( $post ) {
		self::$user_api_key        = $post['api_key'];
		self::$user_api_secret_key = $post['api_secret_key'];
		self::$site_disconnect     = site_url();
		$response                  = self::activate_license();

		return $response;
	}

	/**
	 * This function check if the keys of saved sites are still available on EMT.com
	 */
	public static function check_app_keys() {
		$all_domains = get_option( 'emt_all_domains' );
		if ( is_array( $all_domains ) && count( $all_domains ) > 0 ) {
			foreach ( $all_domains as $api_key => $api_secret_key ) {
				Emt_Common::$user_api_key        = $api_key;
				Emt_Common::$user_api_secret_key = $api_secret_key;
				$token                           = Emt_Common::activate_license();
				if ( isset( $token['code'] ) ) {
					unset( $all_domains[ $api_key ] );
				}
			}
			self::emt_update_option( 'emt_all_domains', $all_domains );
		}
	}

	/**
	 * This function returns the product_ids which are excluded from the plugin settings screen.
	 *
	 * @param $integration_slug
	 *
	 * @return mixed|void
	 */
	public static function get_excluded_product_ids( $integration_slug ) {
		return get_option( 'emt_excluded_' . $integration_slug );
	}

	public static function get_excluded_products( $search_term = null, $post_type, $excluded_products = null ) {
		$final_result  = array();
		$search_result = array();
		$result        = array();
		$args          = array(
			'numberposts' => -1,
			'post_type'   => $post_type,
			'post_status' => 'publish',
		);

		if(!is_null( $search_term)){
			$args['s'] = $search_term;
		}

		if(is_array( $excluded_products) && count($excluded_products) > 0){
			$args['post__in'] = $excluded_products;
		}

		$products = get_posts( $args );

		if ( is_array( $products ) && count( $products ) > 0 ) {
			foreach ( $products as $key1 => $value1 ) {
				$result[ $value1->ID ] = $value1->post_title;
				$search_result[]       = array(
					'id'   => $value1->ID,
					'text' => $value1->post_title,
				);
			}
			$final_result['results']           = $search_result;
			$final_result['search_results']    = $final_result;
			$final_result['excluded_products'] = $result;
		}

		return $final_result;
	}

}

Emt_Common::get_instance();
