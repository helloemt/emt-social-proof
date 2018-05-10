<?php

class Emt_Edd extends Emt_Integrations {

	private static $instance = null;
	public static $current_integration_data = array();
	public static $is_active = '0';
	public $slug = 'edd';
	public $slug_complete_purchase = 'edd_insert_payment';
	public $slug_new_customer = 'edd_new_customer';

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();

		$this->integrations_data = Emt_Common::emt_get_option( EMT_SHORT_SLUG . EMT_INTEGRATION_POSTFIX );
		if ( isset( $this->integrations_data[ $this->slug ] ) ) {
			self::$current_integration_data = $this->integrations_data[ $this->slug ];
		}

		if ( is_array( self::$current_integration_data ) && count( self::$current_integration_data ) > 0 ) {
			if ( isset( self::$current_integration_data['events'][ $this->slug_complete_purchase ] ) ) {
				add_action( 'edd_insert_payment', array( $this, 'create_single_feed_complete_purchase' ), 11, 2 );
			}
		}
	}

	public function create_single_feed_complete_purchase( $payment_id, $payment_data ) {
		$saved_event_details = self::$current_integration_data['events'][ $this->slug_complete_purchase ];
		if ( is_array( $saved_event_details ) && count( $saved_event_details ) > 0 ) {
			$event_id           = $saved_event_details['event_id'];
			$event_fields       = $saved_event_details['event_fields'];
			$event_fields_value = $this->get_edd_order_details( $payment_id, $event_fields );
			if ( is_array( $event_fields_value ) && count( $event_fields_value ) > 0 ) {
				$data_to_send[ $event_id ] = $event_fields_value;
				$this->send_feed($data_to_send);
			}
		}
	}

	public function get_edd_order_details( $payment_id, $event_fields ) {
		$details_to_return = array();
		$payment           = new EDD_Payment( $payment_id );
		$payment_data      = $payment->get_meta();
		$cart_details      = $payment_data["cart_details"];
		if ( $payment_data["downloads"] && is_array( $payment_data["downloads"] ) ) {
			$fname          = $payment_data["user_info"]['first_name'];
			$lname          = $payment_data["user_info"]['last_name'];
			$fullName       = $fname . " " . $lname;
			$city           = $payment_data["user_info"]['address']['city'];
			$countries_list = edd_get_country_list();
			$country        = $countries_list[ $payment_data["user_info"]['address']['country'] ];
			$address        = $city . ', ' . $country;
			$ip      = get_post_meta( $payment_id, "_edd_payment_user_ip", true );

			$product_info = array();
			if ( is_array( $cart_details ) && count( $cart_details ) > 0 ) {
				foreach ( $cart_details as $val ) {
					$feed_schema = array();
					if ( in_array( 'first_name', $event_fields ) ) {
						$feed_schema["first_name"] = $fullName;
					}
					if ( in_array( 'address', $event_fields ) ) {
						$feed_schema["address"] = $address;
					}
					if ( in_array( 'product', $event_fields ) ) {
						$feed_schema["product"] = $val["name"];
					}
					$feed_schema["ip"] = $ip;
					$product_info[] = $feed_schema;
				}
			}
		}

		if ( is_array( $product_info ) && count( $product_info ) > 0 ) {
			$details_to_return[ $payment_id ] = $product_info;
		}

		return $details_to_return;
	}

	public function get_edd_orders( $event_fields, $orders_count = 20 ) {
		$orders_to_return = array();
		$args             = array(
			'number'  => $orders_count,
			'status'  => 'publish',
			'orderby' => 'ID',
			'order'   => 'DESC',
		);
		$result           = edd_get_payments( $args );
		if ( is_array( $result ) && count( $result ) > 0 ) {
			foreach ( $result as $key1 => $value1 ) {
				$payment_details = $this->get_edd_order_details( $value1->ID, $event_fields );
				if ( is_array( $payment_details ) && count( $payment_details ) > 0 ) {
					$orders_to_return[ $value1->ID ] = $payment_details[ $value1->ID ];
				}
			}
		}

		return $orders_to_return;
	}

	public function getfeeds() {
		$hook_name    = $this->post_vars['hook_name'];
		$event_id     = $this->post_vars['event_id'];
		$event_fields = $this->post_vars['fields'];
		$feeds_count  = $this->post_vars['count'];

		if ( '' != $hook_name && '' != $event_id && '' != $event_fields ) {
			if ( $hook_name == $this->slug_complete_purchase ) {
				$feeds = $this->get_edd_orders( $event_fields, $feeds_count );
			} elseif ( $hook_name == $this->slug_new_customer ) {

			}
			$this->response = $feeds;
		} else {
			$this->response = $this->response_codes[10006];
		}
	}
}

return Emt_Edd::get_instance();

