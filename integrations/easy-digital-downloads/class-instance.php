<?php

class Emt_Edd extends Emt_Integrations {

	private static $instance                = null;
	public static $current_integration_data = array();
	public static $is_active                = '0';
	public $slug                            = 'edd';
	public $plugin_class_name               = 'Easy_Digital_Downloads';
	public $integration_name                = 'Easy Digital Downloads';
	public $slug_complete_purchase          = 'edd_insert_payment';
	public $has_settings                    = true;
	public $search_posttype                 = 'download';

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();
		/*
		 * Send Feeds To EMT when order is placed
		 */
		add_action( 'edd_insert_payment', array( $this, 'create_single_feed_complete_purchase' ), 11, 2 );
	}

	public function create_single_feed_complete_purchase( $payment_id, $payment_data ) {
		if ( '1' == get_transient( 'emt_feed_' . $this->slug . '_' . $payment_id ) ) {
			// Do Nothing, feeds have been sent to EMT for this order
		} else {
			$all_domains = Emt_Common::emt_get_option( EMT_ALL_DOMAINS );
			foreach ( $all_domains as $api_key => $api_secret_key ) {
				$event_type                      = $this->slug_complete_purchase;
				Emt_Common::$user_api_key        = $api_key;
				Emt_Common::$user_api_secret_key = $api_secret_key;
				$integration_data                = Emt_Common::emt_get_option( EMT_SHORT_SLUG . EMT_INTEGRATION_POSTFIX . '_' . $api_key );
				$integration_data                = $integration_data[ $this->slug ];
				if ( is_array( $integration_data ) && isset( $integration_data['events'] ) && isset( $integration_data['events'][ $event_type ] ) ) {
					$all_emt_events = $integration_data['events'][ $event_type ];
					if ( is_array( $all_emt_events ) && count( $all_emt_events ) > 0 ) {
						$count = 1;
						foreach ( $all_emt_events as $event_id => $event_details ) {
							$data_to_send       = array();
							$single_feed        = array();
							$event_fields       = $event_details['fields'];
							$event_fields_value = $this->get_edd_order_details( $payment_id, $event_fields, $payment_data );
							if ( is_array( $event_fields_value ) && count( $event_fields_value ) > 0 ) {
								$data_to_send['trigger_id'] = $event_id;
								$single_feed['image']       = $event_fields_value['image'];
								$single_feed['timestamp']   = ( time() + $count );
								unset( $event_fields_value['image'] );
								$single_feed['fields']  = $event_fields_value;
								$data_to_send['data'][] = $single_feed;
								$this->send_feed( $data_to_send, true );
							}
							$count ++;
							set_transient( 'emt_feed_' . $this->slug . '_' . $payment_id, true, 1 * HOUR_IN_SECONDS );
						}
					}
				}
			}
		}
	}

	public function get_edd_order_details( $payment_id, $event_fields, $payment_data = null ) {
		$excluded_product_ids = Emt_Common::get_excluded_product_ids( $this->slug );
		$single_feed_data     = array();
		$cart_details         = $payment_data['cart_details'];
		$largest_item_price   = 0;
		$largest_item_key     = 0;
		if ( $payment_data['downloads'] && is_array( $payment_data['downloads'] ) ) {
			if ( is_array( $cart_details ) && count( $cart_details ) > 0 ) {
				// Get the max price item bcoz we will only made the feed for largest price item
				foreach ( $cart_details as $key1 => $val1 ) {
					$product_id = $val1['id'];
					if ( is_array( $excluded_product_ids ) && count( $excluded_product_ids ) > 0 ) {
						if ( in_array( $product_id, $excluded_product_ids ) ) {
							continue;
						}
					}
					if ( $largest_item_price <= $val1['price'] ) {
						$largest_item_price = $val1['price'];
						$largest_item_key   = $key1;
					}
				}

				// No product eligible for becoming a feed
				if ( 0 == $largest_item_price ) {
					return $single_feed_data;
				}

				$single_feed_data['customer_first_name'] = $payment_data['user_info']['first_name'];
				$single_feed_data['customer_last_name']  = $payment_data['user_info']['last_name'];
				$single_feed_data['customer_full_name']  = $single_feed_data['customer_first_name'] . ' ' . $single_feed_data['customer_last_name'];
				$single_feed_data['city']                = isset( $payment_data['user_info']['address']['city'] ) ? $payment_data['user_info']['address']['city'] : '';
				$countries_list                          = edd_get_country_list();
				if ( isset( $payment_data['user_info']['address']['country'] ) ) {
					$country                     = $countries_list[ $payment_data['user_info']['address']['country'] ];
					$single_feed_data['country'] = $country;
				} else {
					$single_feed_data['country'] = '';
				}

				if ( '' != $single_feed_data['city'] && '' != $single_feed_data['country'] ) {
					$single_feed_data['smart_address'] = $single_feed_data['city'] . ', ' . $single_feed_data['country'];
				} else {
					$single_feed_data['smart_address'] = '';
				}

				$single_feed_data['order_id'] = $payment_id;
				$single_feed_data['ip']       = get_post_meta( $payment_id, '_edd_payment_user_ip', true );
				if ( isset( $payment_data['user_email'] ) ) {
					$single_feed_data['email'] = $payment_data['user_email'];
				} elseif ( isset( $payment_data['email'] ) ) {
					$single_feed_data['email'] = $payment_data['email'];
				}
				$single_feed_data['order_total']  = $largest_item_price;
				$single_feed_data['product_name'] = $cart_details[ $largest_item_key ]['name'];
				$single_feed_data['product_id']   = $cart_details[ $largest_item_key ]['id'];
				$single_feed_data['product_link'] = get_permalink( $cart_details[ $largest_item_key ]['id'] );
				$image                            = wp_get_attachment_image_src( get_post_thumbnail_id( $cart_details[ $largest_item_key ]['id'] ) );
				$image_url                        = '';
				if ( is_array( $image ) && count( $image ) > 0 ) {
					$image_url = $image[0];
					if ( '' == $image_url ) {
						$image_url = '';
					}
				}
				$single_feed_data['image'] = array(
					'type' => 'dynamic',
					'url'  => $image_url,
				);

			}
		}

		return $single_feed_data;
	}

	public function get_edd_orders( $event_fields, $orders_count = 20 ) {
		$data_to_send = array();
		$args         = array(
			'number'  => $orders_count,
			'status'  => 'publish',
			'orderby' => 'ID',
			'order'   => 'DESC',
		);
		$result       = edd_get_payments( $args );
		if ( is_array( $result ) && count( $result ) > 0 ) {
			$count = 1;
			foreach ( $result as $key1 => $value1 ) {
				$payment                  = new EDD_Payment( $value1->ID );
				$payment_data             = $payment->get_meta();
				$single_feed              = array();
				$event_fields_value       = $this->get_edd_order_details( $value1->ID, $event_fields, $payment_data );
				$single_feed['image']     = $event_fields_value['image'];
				$single_feed['timestamp'] = ( strtotime( $payment_data['date'] ) + $count );
				unset( $event_fields_value['image'] );
				$single_feed['fields']  = $event_fields_value;
				$data_to_send['data'][] = $single_feed;
				$count ++;
			}
		}

		return $data_to_send;
	}

	/**
	 *
	 * This function returns the comment feeds to EMT for syncing. It takes feeds count and event_id for whom the comments
	 * have to be synced.
	 *
	 * @param $feed_count
	 * @param $event_id
	 *
	 * @return array
	 */
	public function get_data_for_syncing( $integration_type, $event_type, $feed_count, $event_id, $api_key, $api_secret_key ) {
		if ( 'edd_new_order' == $event_type ) {
			$event_type = 'edd_insert_payment';
		}
		$data_to_send = array();
		$all_domains  = Emt_Common::emt_get_option( EMT_ALL_DOMAINS );
		if ( isset( $all_domains[ $api_key ] ) && '' != $all_domains[ $api_key ] ) {
			$integration_data = Emt_Common::emt_get_option( EMT_SHORT_SLUG . EMT_INTEGRATION_POSTFIX . '_' . $api_key );
			$integration_data = $integration_data[ $integration_type ];
			if ( is_array( $integration_data ) && isset( $integration_data['events'] ) && isset( $integration_data['events'][ $event_type ] ) ) {
				$all_emt_events = $integration_data['events'][ $event_type ];
				if ( is_array( $all_emt_events ) && count( $all_emt_events ) > 0 ) {
					if ( isset( $all_emt_events[ $event_id ] ) ) {
						$event_fields               = $all_emt_events[ $event_id ]['fields'];
						$feeds                      = $this->get_edd_orders( $event_fields, $feed_count );
						$data_to_send               = $feeds;
						$data_to_send['trigger_id'] = $event_id;
					}
				}
			}
		}

		return $data_to_send;
	}

	/**
	 * Shows the settings page of the integration
	 */
	public function get_settings_page() {
		include_once 'settings.php';
	}
}

return Emt_Edd::get_instance();

