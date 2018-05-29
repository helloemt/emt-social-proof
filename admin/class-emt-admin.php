<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emt_Admin {

	protected static $instance               = null;
	public $is_plugin_settings_saved         = false;
	public $is_plugin_settings_saved_message = '';
	public $is_plugin_admin_page             = false;

	public function __construct() {
		/** Load all admin hooks **/
		$this->check_plugin_admin_page();
		$this->hooks();
	}

	/**
	 * Return an instance of this class.
	 * @since     1.0.0
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Check if the plugin admin page is currently opened
	 */
	public function check_plugin_admin_page() {
		if ( isset( $_GET['page'] ) && 'emt-plugin-settings' == $_GET['page'] ) {
			$this->is_plugin_admin_page = true;
		}
	}

	/**
	 * Fires all the admin hooks
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'emt_admin_enqueue_scripts' ), 11 );
		add_action( 'admin_menu', array( $this, 'emt_plugin_menu' ) );
		add_action( 'init', array( $this, 'save_tab_settings' ), 9 );
		add_action( 'admin_notices', array( $this, 'show_tab_messages' ) );
		/*
		 * Handles the ajax calls from the plugin settings
		 */
		add_action( 'wp_ajax_emt_soc_ajax_operations', array( $this, 'emt_soc_ajax_operations' ) );
	}

	/**
	 * Get Admin path
	 * @return string plugin admin path
	 */
	public function get_admin_url() {
		return plugin_dir_url( EMT_PLUGIN_FILE ) . 'admin';
	}

	/*
	 * function for adding admin css and admin js files
	 */

	public function emt_admin_enqueue_scripts() {
		if ( $this->is_plugin_admin_page ) {
			wp_enqueue_style( 'emt-select2-css', $this->get_admin_url() . '/assets/css/select2.min.css', false, EMT_VERSION );
			wp_enqueue_style( 'emt-admin-css', $this->get_admin_url() . '/assets/css/emt-admin.css', false, EMT_VERSION );
			wp_register_script( 'emt-select2-js', $this->get_admin_url() . '/assets/js/select2.full.js', array( 'jquery' ), EMT_VERSION, true );
			wp_enqueue_script( 'emt-select2-js' );
			wp_register_script( 'emt-admin-js', $this->get_admin_url() . '/assets/js/emt-admin.js', array( 'jquery' ), EMT_VERSION, true );
			wp_enqueue_script( 'emt-admin-js' );
		}
	}

	/*
	 * function for adding WordPress menu for the plugin
	 */

	public function emt_plugin_menu() {
		add_menu_page(
			__( 'EarnMoreTrust', 'emt-social-proof' ), __( 'EarnMoreTrust', 'emt-social-proof' ), 'manage_options', 'emt-plugin-settings', array(
				$this,
				'emt_settings_page',
			)
		);
	}

	/*
	 * callable function when the plugin menu page is clicked
	 */

	public function emt_settings_page() {
		$this->create_settings_tabs();
	}

	/**
	 * This function creates all the settings tabs in the plugin settings screen.
	 */
	public function create_settings_tabs() {
		$my_plugin_tabs = Emt_common::get_default_plugin_settings_tabs();
		echo '<div class="wrap">';
		echo '<h1>' . EMT_NAME . '</h1>';
		echo $this->create_tabs( $my_plugin_tabs );
		echo '</div>';
	}

	/**
	 * This function outputs the settings tabs in plugin settings screen.
	 */
	public function create_tabs( $tabs, $current = null ) {
		if ( is_null( $current ) ) {
			if ( isset( $_GET['tab'] ) ) {
				$current = $_GET['tab'];
			} else {
				$current = 'emt-license-activation';
			}
		}
		$content  = '';
		$content .= '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $location => $tabname ) {
			if ( $current == $location ) {
				$class           = ' nav-tab-active';
				$current_tabname = $tabname;
			} else {
				$class = '';
			}
			$content .= '<a class="nav-tab' . $class . '" href="?page=emt-plugin-settings&tab=' . $location . '">' . $tabname . '</a>';
		}
		$content .= '</h2>';

		$tab_options = Emt_common::get_tab_options( $current );
		if ( isset( $tab_options['emt_user_key'] ) && '' != $tab_options['emt_user_key'] ) {
			$submit_button_text        = __( 'Disconnect', 'emt-social-proof' );
			$sync_submit_button_text   = __( 'Sync', 'emt-social-proof' );
			$update_submit_button_text = __( 'Update', 'emt-social-proof' );
		} else {
			$submit_button_text        = __( 'Connect', 'emt-social-proof' );
			$sync_submit_button_text   = '';
			$update_submit_button_text = '';
		}

		ob_start();
		?>
		<div id="poststuff">
			<div class="metabox-holder columns-2" id="post-body">
				<div id="post-body-content">
					<div id="normal-sortables" class="meta-box-sortables ui-sortable wuev_content">
						<div id="dashboard_right_now_" class="postbox">
							<h2 class="hndle ui-sortable-handle">
								<span><?php echo Emt_Common::get_tab_headings( $current ); ?></span></h2>
							<div class="inside">
								<div class="main">
									<form method="post" class="emt-forms">
										<?php
										include_once 'settings-tabs/' . $current . '.php';
										?>
										<input type="hidden" name="emt_form_type" value="<?php echo $current; ?>">
										<?php
										if ( 'emt-license-activation' == $current ) {
											//                                          ( '' != $update_submit_button_text ) ? submit_button( $update_submit_button_text ) : '';
											//                                          ( '' != $sync_submit_button_text ) ? submit_button( $sync_submit_button_text ) : '';
											//                                                                                    submit_button( $submit_button_text );
										}
										?>
									</form>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="postbox-container wuev_sidebar" id="postbox-container-1">
					<?php do_action( 'xlwuev_options_page_right_content' ); ?>
				</div>
			</div>
		</div>

		<?php
		$content .= ob_get_clean();

		return $content;
	}

	/**
	 * This function saves the settings from plugin settings screen in options table.
	 */
	public function save_tab_settings() {
		if ( isset( $_POST['emt_form_type'] ) ) {
			switch ( $_POST['emt_form_type'] ) {
				case 'emt-integrations':
					break;
				case 'emt-events':
					break;
				default:
					break;
			}
		}
	}

	/**
	 * This function shows the response messages to user in admin on plugin settings screen
	 */
	public function show_tab_messages() {
		if ( $this->is_plugin_settings_saved ) {
			?>
			<div class="updated notice">
				<p><?php echo $this->is_plugin_settings_saved_message; ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Callback function for all the ajax operations from admin menu
	 */
	public function emt_soc_ajax_operations() {
		$type     = $_POST['type'];
		$response = array(
			'status'  => '0',
			'message' => 'Some Error Occurs at our server. Please try again later.',
		);
		switch ( $type ) {
			case 'site_connect':
				$response    = array(
					'status'  => '1',
					'message' => 'Settings Saved',
					'html'    => '<input type="button" name="sync" class="button button-primary emt-button emt-sync-site" value="Sync">
                                    <input type="button" name="disconnect" class="button button-primary emt-button emt-disconnect-site" value="Disconnect">
                                    <input type="button" name="remove" class="button emt-button emt-remove-site emt-minus" value="Remove Site">
                                    <span class="spinner"></span>',
				);
				$all_domains = get_option( 'emt_all_domains' );
				if ( is_array( $all_domains ) && count( $all_domains ) > 0 ) {
					if ( isset( $all_domains[ $_POST['api_key'] ] ) || ( isset( $all_domains[ $_POST['api_key'] ] ) && $all_domains[ $_POST['api_key'] ] == $_POST['api_secret_key'] ) ) {
						$response = array(
							'status'  => '0',
							'message' => 'These Keys are already used',
						);
					} else {
						$connection_result = Emt_Common::connect_site( $_POST );
						if ( isset( $connection_result['code'] ) ) {
							$response = array(
								'status'  => '0',
								'message' => 'Invalid API Keys',
							);
						} else {
							$all_domains[ $_POST['api_key'] ] = $_POST['api_secret_key'];
							update_option( 'emt_all_domains', $all_domains );
						}
					}
				} else {
					$all_domains       = array();
					$connection_result = Emt_Common::connect_site( $_POST );
					if ( isset( $connection_result['code'] ) ) {
						$response = array(
							'status'  => '0',
							'message' => 'Token Error',
						);
					} else {
						$all_domains[ $_POST['api_key'] ] = $_POST['api_secret_key'];
						update_option( 'emt_all_domains', $all_domains );
					}
				}
				break;
			case 'site_disconnect':
				$response    = array(
					'status'  => '1',
					'message' => 'Disconnected',
					'html'    => '<input type="button" name="connect" class="button button-primary emt-button emt-connect-site" value="Connect">
                                <input type="button" name="remove" class="button emt-button emt-remove-site emt-minus" value="Remove Site">
                                <span class="spinner"></span>',
				);
				$all_domains = get_option( 'emt_all_domains' );
				if ( isset( $all_domains[ $_POST['api_key'] ] ) ) {
					$connection_result = Emt_Common::disconnect_site( $_POST );
					unset( $all_domains[ $_POST['api_key'] ] );
					update_option( 'emt_all_domains', $all_domains );
					delete_option( 'emt_integrations_data_' . $_POST['api_key'] );
					if ( isset( $connection_result['code'] ) ) {
						$response = array(
							'status'  => '0',
							'message' => 'Token Error (Invalid API Keys)',
							'html'    => '<input type="button" name="connect" class="button button-primary emt-button emt-connect-site" value="Connect">
                                <input type="button" name="remove" class="button emt-button emt-remove-site emt-minus" value="Remove Site">
                                <span class="spinner"></span>',
						);
					}
				} else {
					$response = array(
						'status'  => '0',
						'message' => 'Invalid API Keys',
					);
				}
				break;
			case 'site_sync':
				Emt_Common::$user_api_key        = $_POST['api_key'];
				Emt_Common::$user_api_secret_key = $_POST['api_secret_key'];
				$token                           = Emt_Common::activate_license();
				if ( isset( $token['code'] ) ) {
					$response = array(
						'status'  => '0',
						'message' => 'Token Error',
					);
				} else {
					$result                     = Emt_Common::sync_with_emt();
					$all_supported_integrations = $GLOBALS['Emt_Social_Proof']->get_integration( '', true );
					$activated_integrations     = Emt_Common::get_active_integrations( $all_supported_integrations );
					if ( is_array( $activated_integrations ) && count( $activated_integrations ) > 0 ) {
						$all_supported_integrations_temp = array();
						foreach ( $activated_integrations as $integration_key => $integration_value ) {
							$all_supported_integrations_temp[ $integration_key ] = $all_supported_integrations[ $integration_key ];
						}
						$all_supported_integrations = $all_supported_integrations_temp;
					}
					$result['api_keys'] = array(
						'api_key'        => $_POST['api_key'],
						'api_secret_key' => $_POST['api_secret_key'],
					);

					if ( is_array( $result ) && count( $result ) > 0 ) {
						$result_sync = Emt_Common::emt_api_call( EMT_DOMAIN . EMT_SYNCWP_ENDPOINT, $result, $token );
						if ( isset( $result_sync['code'] ) && '1006' == $result_sync['code'] ) {
							$response = array(
								'status'  => '0',
								'message' => $result_sync['msg'],
							);
						} elseif ( isset( $result_sync['code'] ) && '1' == $result_sync['code'] ) {
							$integrations_data = json_decode( $result_sync['data'] );
							$integrations_data = Emt_Common::object_to_array( $integrations_data );
							if ( is_array( $integrations_data ) && count( $integrations_data ) > 0 ) {
								update_option( 'emt_integrations_data_' . $_POST['api_key'], $integrations_data );
								$message = array();
								foreach ( $integrations_data as $integration_key => $integration_details ) {
									if ( isset( $all_supported_integrations[ $integration_key ] ) ) {
										$message[] = $all_supported_integrations[ $integration_key ]->integration_name;
									}
								}
								$message  = 'Data synced for ' . implode( ', ', $message ) . '. You can now create events for these integrations.';
								$response = array(
									'status'  => '1',
									'message' => $message,
								);
							}
						}
					} else {
						$response = array(
							'status'  => '0',
							'message' => 'Your Website has no supported Integrations',
						);
					}
				}
				break;
			case 'integrations_settings':
				$excluded_products_option_key = 'emt_excluded_' . $_POST['emt_integration_slug'];
				$exluded_products             = ( isset( $_POST['products'] ) ) ? $_POST['products'] : array();
				update_option( $excluded_products_option_key, $exluded_products );
				$message  = __( 'Settings Saved', 'emt-social-proof' );
				$response = array(
					'status'  => '1',
					'message' => $message,
				);
				break;
			case 'emt_exclude_product_search':
				$search_term = $_POST['search_term']['term'];
				$post_type   = $_POST['emt_posttype'];
				$results     = Emt_Common::get_excluded_products( $search_term, $post_type );
				if ( is_array( $results ) && count( $results ) > 0 ) {
					$response = $results['search_results'];
				} else {
					$response = array();
				}
				break;
		}

		wp_send_json( $response );
	}

}

Emt_Admin::get_instance();
