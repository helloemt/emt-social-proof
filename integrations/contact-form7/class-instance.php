<?php

class Emt_Cf7 extends Emt_Integrations {
	private static $instance                = null;
	public static $current_integration_data = array();
	public $slug                            = 'cf7';
	public static $is_active                = '0';
	public static $active_forms             = array();
	public $plugin_class_name               = 'WPCF7';
	public $integration_name                = 'Contact Forms';

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();
		add_action( 'wpcf7_before_send_mail', array( $this, 'create_single_feed_after_submission' ), 11, 2 );
	}

	/**
	 * @param $entry
	 *
	 * This function collects the submitted form data and send the feed to EMT.com
	 *
	 * @param $form
	 */
	public function create_single_feed_after_submission( $contact_form ) {
		$submission  = WPCF7_Submission::get_instance();
		$posted_data = $submission->get_posted_data();

		$form_id                = $posted_data['_wpcf7'];
		$form_fields_with_types = $this->get_all_forms_with_fields( $form_id );
		if ( is_array( $form_fields_with_types ) && count( $form_fields_with_types ) > 0 ) {
			$form_fields_with_types_temp = array();
			$form_fields_with_types      = $form_fields_with_types[ $form_id ]['form_fields'];
			foreach ( $form_fields_with_types as $key1 => $single_field ) {
				$form_fields_with_types_temp[ $single_field['label'] ] = $single_field['type'];
			}
			$form_fields_with_types = $form_fields_with_types_temp;
		}

		$all_domains = Emt_Common::emt_get_option( EMT_ALL_DOMAINS );
		if ( is_array( $all_domains ) && count( $all_domains ) > 0 && is_array( $form_fields_with_types ) && count( $form_fields_with_types ) > 0 ) {
			foreach ( $all_domains as $api_key => $api_secret_key ) {
				Emt_Common::$user_api_key        = $api_key;
				Emt_Common::$user_api_secret_key = $api_secret_key;
				$integration_data                = Emt_Common::emt_get_option( EMT_SHORT_SLUG . EMT_INTEGRATION_POSTFIX . '_' . $api_key );
				$integration_data                = $integration_data[ $this->slug ];
				if ( is_array( $integration_data ) && count( $integration_data ) > 0 ) {
					self::$current_integration_data = $integration_data;
					if ( isset( self::$current_integration_data['events'][ $form_id ]['wpcf7_before_send_mail'] ) ) {
						$all_events = self::$current_integration_data['events'][ $form_id ]['wpcf7_before_send_mail'];

						if ( is_array( $all_events ) && count( $all_events ) > 0 ) {
							foreach ( $all_events as $event_id => $value1 ) {
								$event_fields_value_temp = array();
								$event_fields_value      = null;
								$event_fields            = $value1['fields'];
								foreach ( $event_fields as $key1 => $field_value ) {
									if ( in_array( $field_value, $this->default_available_keys ) ) {
										unset( $event_fields[ $key1 ] );
									}
								}

								foreach ( $event_fields as $merge_tag ) {
									if ( isset( $posted_data[ $merge_tag ] ) ) {
										if ( isset( $form_fields_with_types[ $merge_tag ] ) ) {
											if ( 'select' == $form_fields_with_types[ $merge_tag ] || 'checkbox' == $form_fields_with_types[ $merge_tag ] || 'radio' == $form_fields_with_types[ $merge_tag ] ) {
												$event_fields_value_temp[ $merge_tag ] = implode( ', ', $posted_data[ $merge_tag ] );
											} else {
												$event_fields_value_temp[ $merge_tag ] = $posted_data[ $merge_tag ];
												if ( filter_var( $posted_data[ $merge_tag ], FILTER_VALIDATE_EMAIL ) ) {
													$event_fields_value_temp['email'] = $posted_data[ $merge_tag ];
												}
											}
										}
									}
								}

								if ( is_array( $event_fields_value_temp ) && count( $event_fields_value_temp ) > 0 ) {
									$event_fields_value_temp['ip'] = $_SERVER['REMOTE_ADDR'];
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
	 * Return all the contact form 7 forms on the WordPress site along with their fields
	 */

	public function get_all_forms_with_fields( $post_id = null ) {
		$exclude_field_types = array( 'submit', 'quiz', 'recaptcha', 'file', 'acceptance' );
		$forms_to_return     = array();
		$args                = array(
			'post_type'   => 'wpcf7_contact_form',
			'numberposts' => -1,
			'post_status' => 'publish',
		//          'fields'      => 'ids',
		);
		if ( ! is_null( $post_id ) ) {
			$args['post__in'] = array( $post_id );
		}
		$form_posts = get_posts( $args );

		if ( is_array( $form_posts ) && count( $form_posts ) > 0 ) {
			foreach ( $form_posts as $key1 => $form_post_details ) {
				$all_fields_of_form  = array();
				$single_field        = array();
				$form_name           = $form_post_details->post_title;
				$contact_form        = wpcf7_contact_form( $form_post_details->ID );
				$contact_form_fields = $contact_form->scan_form_tags();
				if ( is_array( $contact_form_fields ) && count( $contact_form_fields ) > 0 ) {
					$forms_to_return[ $form_post_details->ID ]['form_name'] = $form_name;
					foreach ( $contact_form_fields as $key2 => $single_field_details ) {
						if ( in_array( $single_field_details->basetype, $exclude_field_types ) ) {
							continue;
						}
						$single_field['label'] = $single_field_details->name;
						$single_field['type']  = $single_field_details->basetype;
						$all_fields_of_form[]  = $single_field;
					}
					$forms_to_return[ $form_post_details->ID ]['form_fields'] = $all_fields_of_form;
				}
			}
		}

		return $forms_to_return;
	}
}

return Emt_Cf7::get_instance();
