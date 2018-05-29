<?php

class Emt_Gf extends Emt_Integrations {

	private static $instance                = null;
	public static $current_integration_data = array();
	public static $is_active                = '0';
	public static $active_forms             = array();
	public $slug                            = 'gf';
	public $plugin_class_name               = 'GFForms';
	public $integration_name                = 'Gravity Forms';

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();
		add_action( 'gform_after_submission', array( $this, 'create_single_feed_after_submission' ), 11, 2 );
		add_action( 'wp', array( $this, 'process_all_forms_with_fields_call' ) );
	}

	/**
	 * @param $entry
	 *
	 * This function collects the submitted form data and send the feed to EMT.com
	 *
	 * @param $form
	 */
	public function create_single_feed_after_submission( $entry, $form ) {
		$form_id     = $form['id'];
		$all_domains = Emt_Common::emt_get_option( EMT_ALL_DOMAINS );
		if ( is_array( $all_domains ) && count( $all_domains ) > 0 ) {
			foreach ( $all_domains as $api_key => $api_secret_key ) {
				Emt_Common::$user_api_key        = $api_key;
				Emt_Common::$user_api_secret_key = $api_secret_key;
				$integration_data                = Emt_Common::emt_get_option( EMT_SHORT_SLUG . EMT_INTEGRATION_POSTFIX . '_' . $api_key );
				$integration_data                = $integration_data[ $this->slug ];
				if ( is_array( $integration_data ) && count( $integration_data ) > 0 ) {
					self::$current_integration_data = $integration_data;
					if ( isset( self::$current_integration_data['events'][ $form_id ]['gform_after_submission'] ) ) {
						$all_events = self::$current_integration_data['events'][ $form_id ]['gform_after_submission'];
						if ( is_array( $all_events ) && count( $all_events ) > 0 ) {
							foreach ( $all_events as $event_id => $value1 ) {
								$event_fields_value = null;
								$event_fields       = $value1['fields'];
								foreach ( $event_fields as $key1 => $field_value ) {
									if ( in_array( $field_value, $this->default_available_keys ) ) {
										unset( $event_fields[ $key1 ] );
									}
								}
								$temp_structure = array();
								foreach ( $event_fields as $key3 => $value3 ) {
									$temp_val                       = explode( ':', $value3 );
									$temp_structure[ $temp_val[0] ] = $temp_val[1];
								}
								$event_fields_value = $this->get_gf_form_fields_value( $form_id, $event_fields, 1 );
								if ( is_array( $event_fields_value ) && count( $event_fields_value ) > 0 ) {
									$data_to_send[ $event_id ] = $event_fields_value;
									$event_fields_value_temp   = array();
									foreach ( $event_fields_value[ $entry['id'] ]['fields'] as $key4 => $value4 ) {
										if ( 'ip' == $key4 ) {
											continue;
										}
										if ( 'email' == $key4 ) {
											$event_fields_value_temp['email'] = $value4;
											continue;
										}
										$array_key                             = $key4 . ':' . $temp_structure[ $key4 ];
										$event_fields_value_temp[ $array_key ] = $value4;
									}
									$event_fields_value_temp['ip'] = $entry['ip'];
									$data_to_send                  = array();
									$data_to_send['trigger_id']    = $event_id;
									$data_to_send['fields']        = $event_fields_value_temp;
									$this->send_feed( $data_to_send );
								}
							}
						}
					}
				}
			}
		}
	}

	/*
	 * Return all the gravity forms on the WordPress site along with their fields
	 */

	public function get_all_gf_forms_with_fields( $form_id = null ) {
		if ( ! is_null( $form_id ) ) {
			$forms   = array();
			$forms[] = GFAPI::get_form( $form_id );
		} else {
			$forms = GFAPI::get_forms();
		}
		$forms_to_return = array();
		if ( is_array( $forms ) && count( $forms ) > 0 ) {
			foreach ( $forms as $key1 => $value1 ) {
				if ( is_array( $value1['fields'] ) && count( $value1['fields'] ) > 0 ) {
					$form_fields = array();
					foreach ( $value1['fields'] as $key2 => $value2 ) {
						$form_fields[ $value2->id ] = array(
							'label' => $value2->label,
							'type'  => $value2->type,
						);
					}
				}

				$forms_to_return[ $value1['id'] ]['form_name'] = $value1['title'];
				if ( is_array( $form_fields ) && count( $form_fields ) > 0 ) {
					$forms_to_return[ $value1['id'] ]['form_fields'] = $form_fields;
				}
			}
		}
		return $forms_to_return;
	}

	/*
	 * Return all the gravity form entries with field values for a particular form
	 */

	public function get_gf_form_fields_value( $form_id, $event_fields, $entries_count = 20 ) {
		$entries_to_return = array();
		$current_form      = $this->get_all_gf_forms_with_fields( $form_id );
		if ( is_array( $current_form ) && count( $current_form ) > 0 ) {
			$current_form_fields = $current_form[ $form_id ]['form_fields'];
		}

		if ( is_array( $current_form_fields ) && count( $current_form_fields ) > 0 ) {
			$current_form_fields_temp = [];
			$form_field_types         = [];
			foreach ( $current_form_fields as $field_id => $field_details ) {
				$current_form_fields_temp[ $field_id ] = $field_details['label'];
				$form_field_types[ $field_id ]         = $field_details['type'];
			}
			$current_form_fields = $current_form_fields_temp;

			$search_criteria = array( 'status' => 'active' );
			$sorting         = array( 'direction' => 'DESC' );
			$paging          = array(
				'offset'    => 0,
				'page_size' => $entries_count,
			);
			$entries         = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );
			if ( is_array( $entries ) && count( $entries ) > 0 ) {
				foreach ( $entries as $key1 => $value1 ) {
					foreach ( $current_form_fields as $key2 => $value2 ) {
						if ( isset( $value1[ $key2 ] ) && in_array( $key2, $event_fields ) ) {
							$multiselect = json_decode( $value1[ $key2 ] );
							if ( is_array( $multiselect ) && count( $multiselect ) > 0 ) {
								$entries_to_return[ $value1['id'] ]['fields'][ $key2 ] = implode( ', ', $multiselect );
							} else {
								if ( filter_var( $value1[ $key2 ], FILTER_VALIDATE_EMAIL ) ) {
									$entries_to_return[ $value1['id'] ]['fields']['email'] = $value1[ $key2 ];
								}
								$entries_to_return[ $value1['id'] ]['fields'][ $key2 ] = $value1[ $key2 ];
							}
							$entries_to_return[ $value1['id'] ]['fields']['ip'] = $value1['ip'];
							$checkbox_or_address                                = array();
						}
						foreach ( $value1 as $key3 => $value3 ) {
							$key_exploded = explode( '.', $key3 );
							if ( is_array( $key_exploded ) && count( $key_exploded ) > 1 ) {
								if ( $key2 == $key_exploded[0] && '' != $value3 && in_array( $key2, $event_fields ) ) {
									$checkbox_or_address[ $key2 ][] = $value3;
								}
							}
						}

						if ( isset( $checkbox_or_address[ $key2 ] ) && is_array( $checkbox_or_address[ $key2 ] ) && count( $checkbox_or_address[ $key2 ] ) > 0 ) {
							if ( 'name' == $form_field_types[ $key2 ] ) {
								$entries_to_return[ $value1['id'] ]['fields'][ $key2 ] = implode( ' ', $checkbox_or_address[ $key2 ] );
							} else {
								$entries_to_return[ $value1['id'] ]['fields'][ $key2 ] = implode( ', ', $checkbox_or_address[ $key2 ] );
							}
							$entries_to_return[ $value1['id'] ]['fields']['ip'] = $value1['ip'];
						}

						$entries_to_return[ $value1['id'] ]['timestamp'] = strtotime( $value1['date_created'] );
					}
				}
			}
		}
		return $entries_to_return;
	}

	/**
	 * This function returns gravity form feeds to EMT.com
	 */
	public function getfeeds() {
		$form_id      = $this->post_vars['form_id'];
		$event_id     = $this->post_vars['event_id'];
		$event_fields = $this->post_vars['fields'];
		$feeds_count  = $this->post_vars['count'];

		if ( '' != $form_id && '' != $event_id && '' != $event_fields ) {
			$feeds          = $this->get_gf_form_fields_value( $form_id, $event_fields, $feeds_count );
			$this->response = $feeds;
		} else {
			$this->response = $this->response_codes[10005];
		}
	}

	public function process_all_forms_with_fields_call() {
		if ( isset( $_POST['action'] ) && 'fetch_all_forms_with_fields' == $_POST['action'] ) {
			$all_forms = $this->get_all_gf_forms_with_fields();
			if ( is_array( $all_forms ) && count( $all_forms ) > 0 ) {
				$all_forms_temp = [];
				foreach ( $all_forms as $key1 => $details ) {
					$all_forms_temp[ $key1 ]['form_name'] = $details['form_name'];
					foreach ( $details['form_fields'] as $field_id => $field_details ) {
						$all_forms_temp[ $key1 ]['form_fields'][ $field_id ] = $field_details['label'];
					}
				}
				$all_forms = $all_forms_temp;
				$response  = json_encode(
					array(
						'status'  => 1,
						'message' => 'Success',
						'data'    => $all_forms,
					)
				);
			} else {
				$response = json_encode(
					array(
						'status'  => 0,
						'message' => 'No Forms Available',
					)
				);
			}

			echo $response;
			exit;
		}
	}

}

return Emt_Gf::get_instance();

