<?php

class Emt_Wc extends Emt_Integrations {

	private static $instance                = null;
	public static $current_integration_data = array();
	public static $is_active                = '0';
	public $slug                            = 'wc';
	public $plugin_class_name               = 'WooCommerce';
	public $comment_post_default_keys       = array( 'product_id', 'email', 'ip', 'is_verified' );
	public $integration_name                = 'WooCommerce';
	public $current_event_type              = null;
	public $has_settings                    = true;
	public $search_posttype                 = 'product';

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();
		/*
		 * Send Feeds To EMT when order is placed
		 */
		add_action( 'woocommerce_thankyou', array( $this, 'create_feeds_after_new_order' ), 11, 1 );

		add_action( 'comment_post', array( $this, 'product_review' ), 10, 2 );
		add_action( 'comment_unapproved_to_approved', array( $this, 'my_approve_comment_callback' ) );

		add_action(
			'woocommerce_order_status_completed', array(
				$this,
				'create_feed_when_order_is_completed',
			), 10, 1
		);

		//      add_action( 'wp_loaded', array( $this, 'testing_process' ) );
	}

	public function testing_process() {

	}

	/**
	 * @param $order_id
	 *
	 * This function sends feeds to EMT when order is placed
	 */
	public function create_feeds_after_new_order( $order_id ) {
		if ( '1' == get_transient( 'emt_feed_' . $order_id ) ) {
			// Do Nothing, feeds have been sent to EMT for this order
		} else {
			$all_domains = Emt_Common::emt_get_option( EMT_ALL_DOMAINS );
			if ( is_array( $all_domains ) && count( $all_domains ) > 0 ) {
				$order       = wc_get_order( $order_id );
				$items       = $order->get_items( array( 'line_item' ) );
				$order_items = $items;
				$order_data  = $this->get_order_data( $order );
				foreach ( $all_domains as $api_key => $api_secret_key ) {
					$event_type                      = 'woocommerce_thankyou';
					$this->current_event_type        = $event_type;
					Emt_Common::$user_api_key        = $api_key;
					Emt_Common::$user_api_secret_key = $api_secret_key;
					$integration_data                = Emt_Common::emt_get_option( EMT_SHORT_SLUG . EMT_INTEGRATION_POSTFIX . '_' . $api_key );
					$integration_data                = $integration_data[ $this->slug ];
					if ( is_array( $integration_data ) && isset( $integration_data['events'] ) && isset( $integration_data['events'][ $event_type ] ) ) {
						$all_emt_events = $integration_data['events'][ $event_type ];
						if ( is_array( $all_emt_events ) && count( $all_emt_events ) > 0 ) {
							if ( is_array( $items ) && count( $items ) > 0 ) {
								$count = 1;
								foreach ( $all_emt_events as $event_id => $event_details ) {
									$data_to_send = $this->get_new_order_data( $order_id, $order, $order_data, $items, $event_id, $event_details, $count );
									$count ++;
									if ( is_array( $data_to_send ) && count( $data_to_send ) > 0 ) {
										foreach ( $data_to_send as $real_data ) {
											  $this->send_feed( $real_data, true );
										}
										set_transient( 'emt_feed_' . $order_id, true, 1 * HOUR_IN_SECONDS );
									}
								}
							}
						}
					}

					$event_type = 'woocommerce_coupon_used';
					$real_data  = array();
					if ( is_array( $integration_data ) && isset( $integration_data['events'] ) && isset( $integration_data['events'][ $event_type ] ) ) {
						$this->current_event_type = $event_type;
						$all_emt_events           = $integration_data['events'][ $event_type ];
						$items                    = $order->get_items( array( 'coupon' ) );
						if ( is_array( $all_emt_events ) && count( $all_emt_events ) > 0 ) {
							if ( is_array( $items ) && count( $items ) > 0 ) {
								$coupons_details      = array();
								$largest_coupon_value = 0;
								foreach ( $items as $item_id => $item_values ) {
									$order_discount_amount = wc_get_order_item_meta( $item_id, 'discount_amount', true );
									if ( $order_discount_amount > $largest_coupon_value ) {
										$largest_coupon_value             = $order_discount_amount;
										$coupons_details['coupon_code']   = $item_values['name'];
										$coupons_details['coupon_amount'] = get_woocommerce_currency_symbol() . $largest_coupon_value;
									}
								}

								if ( is_array( $coupons_details ) && count( $coupons_details ) > 0 ) {
									$count = 1;
									foreach ( $all_emt_events as $event_id => $event_details ) {
										$feeds                                        = array();
										$data_to_send                                 = $this->get_new_order_data( $order_id, $order, $order_data, $order_items, $event_id, $event_details, $count );
										$extra_data                                   = array(
											'timestamp'   => ( time() + $count ),
											'order_id'    => $order_id,
											'order_total' => EMT_Compatibility::get_order_data( $order, '_order_total' ),
											'payment_method' => EMT_Compatibility::get_payment_gateway_from_order( $order ),
										);
										$event_fields_value                           = $this->get_single_feed_data( $event_type, $order_data, array(), $event_details['fields'], $extra_data );
										$event_fields_value['fields']                 = array_merge( $event_fields_value['fields'], $coupons_details );
										$event_fields_value['fields']['product_id']   = $data_to_send[0]['data'][0]['fields']['product_id'];
										$event_fields_value['fields']['product_link'] = apply_filters( 'emt_alter_product_link', $data_to_send[0]['data'][0]['fields']['product_link'], $data_to_send[0]['data'][0]['fields']['product_id'] );
										$event_fields_value['fields']['product_name'] = $data_to_send[0]['data'][0]['fields']['product_name'];
										$event_fields_value['image']                  = $data_to_send[0]['data'][0]['image'];
										$feeds[]                                      = $event_fields_value;
										$count ++;
										if ( is_array( $feeds ) && count( $feeds ) > 0 ) {
											$real_data['data']       = $feeds;
											$real_data['trigger_id'] = $event_id;
											$this->send_feed( $real_data, true );
										}
									}
									set_transient( 'emt_feed_' . $order_id, true, 1 * HOUR_IN_SECONDS );
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * This function is the callback function for comment hook
	 *
	 * @param $comment_id
	 * @param $status
	 */
	public function product_review( $comment_id, $status ) {
		if ( 1 === $status ) {
			$this->send_single_product_review_feed( $comment_id );
		}
	}

	/**
	 * This function gets fired when state of a comment is changed to approved
	 *
	 * @param $comment
	 */
	public function my_approve_comment_callback( $comment ) {
		$comment_id = $comment->comment_ID;
		$this->send_single_product_review_feed( $comment_id );
	}

	/**
	 * Function created a single feed when an order is completed
	 *
	 * @param $order_id
	 */
	public function create_feed_when_order_is_completed( $order_id ) {
		$event_type  = 'woocommerce_order_status_completed';
		$all_domains = Emt_Common::emt_get_option( EMT_ALL_DOMAINS );
		if ( is_array( $all_domains ) && count( $all_domains ) > 0 ) {
			foreach ( $all_domains as $api_key => $api_secret_key ) {
				Emt_Common::$user_api_key        = $api_key;
				Emt_Common::$user_api_secret_key = $api_secret_key;
				$integration_data                = Emt_Common::emt_get_option( EMT_SHORT_SLUG . EMT_INTEGRATION_POSTFIX . '_' . $api_key );
				$integration_data                = $integration_data[ $this->slug ];

				if ( is_array( $integration_data ) && isset( $integration_data['events'] ) && isset( $integration_data['events'][ $event_type ] ) ) {
					$all_emt_events = $integration_data['events'][ $event_type ];
					if ( is_array( $all_emt_events ) && count( $all_emt_events ) > 0 ) {
						$order = wc_get_order( $order_id );
						//                      $order_data = $order->get_data(); // The Order data
						$order_data = $this->get_order_data( $order );
						$items      = $order->get_items();
						if ( is_array( $items ) && count( $items ) > 0 ) {
							$count = 1;
							foreach ( $all_emt_events as $event_id => $event_details ) {
								$data_to_send = $this->get_new_order_data( $order_id, $order, $order_data, $items, $event_id, $event_details, $count );
								$count ++;
								if ( is_array( $data_to_send ) && count( $data_to_send ) > 0 ) {
									foreach ( $data_to_send as $real_data ) {
										$this->send_feed( $real_data, true );
									}
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * This function is fired when a comment is approved or a approved comment is posted
	 *
	 * @param $comment_id
	 */
	public function send_single_product_review_feed( $comment_id ) {
		$event_type               = 'comment_post';
		$this->current_event_type = $event_type;
		$all_domains              = Emt_Common::emt_get_option( EMT_ALL_DOMAINS );
		if ( is_array( $all_domains ) && count( $all_domains ) > 0 ) {
			foreach ( $all_domains as $api_key => $api_secret_key ) {
				Emt_Common::$user_api_key        = $api_key;
				Emt_Common::$user_api_secret_key = $api_secret_key;
				$integration_data                = Emt_Common::emt_get_option( EMT_SHORT_SLUG . EMT_INTEGRATION_POSTFIX . '_' . $api_key );
				$integration_data                = $integration_data[ $this->slug ];

				if ( is_array( $integration_data ) && isset( $integration_data['events'] ) && isset( $integration_data['events'][ $event_type ] ) ) {
					$data_to_send   = array();
					$all_emt_events = $integration_data['events'][ $event_type ];
					if ( is_array( $all_emt_events ) && count( $all_emt_events ) > 0 ) {
						$count = 1;
						foreach ( $all_emt_events as $event_id => $event_details ) {
							$event_fields                   = $event_details['fields'];
							$event_fields_merged            = array_merge( $event_fields, $this->comment_post_default_keys );
							$feed_data                      = $this->get_comment_feed( $comment_id );
							$single_feed_array              = array();
							$single_feed_array['timestamp'] = time() + $count;
							$single_feed_array['image']     = $feed_data['image'];
							unset( $feed_data['image'] );
							$single_feed_array['fields'] = $feed_data;
							$data_to_send['trigger_id']  = $event_id;
							$data_to_send['data'][]      = $single_feed_array;
							$this->send_feed( $data_to_send, true );
							$count ++;
						}
					}
				}
			}
		}
	}


	/**
	 *
	 * This function is a wrapper function and it returns a single feed for comment
	 *
	 * @param $comment_id
	 *
	 * @return array
	 */
	public function get_comment_feed( $comment_id ) {
		$final_data      = array();
		$args            = array(
			'comment__in' => array( $comment_id ),
			'post_type'   => 'product',
		);
		$comment_details = get_comments( $args );
		if ( is_array( $comment_details ) && count( $comment_details ) > 0 ) {
			$comment_details  = $comment_details[0];
			$single_feed_data = $this->get_single_comment_data( $comment_details );
			if ( is_array( $single_feed_data ) && count( $single_feed_data ) > 0 ) {
				$final_data = $single_feed_data;
			}
		}

		return $final_data;
	}

	/**
	 *
	 * This function makes a single feed data for a comment
	 *
	 * @param $comment_details
	 *
	 * @return array
	 */

	public function get_single_comment_data( $comment_details ) {
		$comment_details      = (array) $comment_details;
		$single_feed_details  = array();
		$post_id              = $comment_details['comment_post_ID'];
		$product_details      = get_post( $post_id );
		$rating               = get_comment_meta( $comment_details['comment_ID'], 'rating', true );
		$excluded_product_ids = Emt_Common::get_excluded_product_ids( $this->slug );
		if ( is_array( $excluded_product_ids ) && count( $excluded_product_ids ) > 0 ) {
			if ( in_array( $product_details->ID, $excluded_product_ids ) ) {
				// the comment is not eligible for converting into a feed as the product associated with this comment is exluded
				return $single_feed_details;
			}
		}
		$single_feed_details['product_id']         = $product_details->ID;
		$single_feed_details['product_name']       = $product_details->post_title;
		$single_feed_details['customer_full_name'] = $this->capitalize_word( $comment_details['comment_author'] );
		$single_feed_details['comment_message']    = $comment_details['comment_content'];
		$single_feed_details['email']              = $comment_details['comment_author_email'];
		$single_feed_details['ip']                 = $comment_details['comment_author_IP'];
		$single_feed_details['rating_star']        = $rating;
		$single_feed_details['rating_number']      = $rating;
		$single_feed_details['is_verified']        = get_comment_meta( $comment_details['comment_ID'], 'verified', true );
		$single_feed_details['product_link']       = apply_filters( 'emt_alter_product_link', get_permalink( $product_details->ID ), $product_details->ID );
		//      $image                                     = wp_get_attachment_image_src( get_post_thumbnail_id( $product_details->ID ), 'single-post-thumbnail' );
		$image     = wp_get_attachment_image_src( get_post_thumbnail_id( $product_details->ID ) );
		$image_url = '';
		if ( is_array( $image ) && count( $image ) > 0 ) {
			$image_url = $image[0];
			if ( '' == $image_url ) {
				$image_url = '';
			}
		}
		$single_feed_details['image'] = array(
			'type' => 'dynamic',
			'url'  => $image_url,
		);

		return $single_feed_details;
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
		$this->current_event_type = $event_type;
		$data_to_send             = array();
		$all_domains              = Emt_Common::emt_get_option( EMT_ALL_DOMAINS );
		if ( isset( $all_domains[ $api_key ] ) && '' != $all_domains[ $api_key ] ) {
			$integration_data = Emt_Common::emt_get_option( EMT_SHORT_SLUG . EMT_INTEGRATION_POSTFIX . '_' . $api_key );
			$integration_data = $integration_data[ $integration_type ];
			if ( is_array( $integration_data ) && isset( $integration_data['events'] ) && isset( $integration_data['events'][ $event_type ] ) ) {
				$all_emt_events = $integration_data['events'][ $event_type ];
				if ( is_array( $all_emt_events ) && count( $all_emt_events ) > 0 ) {
					if ( isset( $all_emt_events[ $event_id ] ) ) {
						$event_details              = $all_emt_events[ $event_id ];
						$data_to_send['trigger_id'] = $event_id;
						switch ( $event_type ) {
							case 'woocommerce_thankyou':
								$orders = wc_get_orders(
									array(
										'limit'   => $feed_count,
										'orderby' => 'date',
										'order'   => 'DESC',
										'status'  => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' ),
									)
								);
								if ( is_array( $orders ) && count( $orders ) > 0 ) {
									$count = 1;
									foreach ( $orders as $order ) {
										//                                      $order_id   = $order->get_id();
										$order_id = EMT_Compatibility::get_order_id( $order );
										//                                      $order_data = $order->get_data(); // The Order data
										$order_data = $this->get_order_data( $order );
										$items      = $order->get_items();
										if ( is_array( $items ) && count( $items ) > 0 ) {
											$single_feed_data = $this->get_new_order_data( $order_id, $order, $order_data, $items, $event_id, $event_details, $count );

											if ( is_array( $single_feed_data ) && count( $single_feed_data ) > 0 ) {
												$data_to_send['data'][] = $single_feed_data[0]['data'][0];
											}
											$count ++;
										}
									}
								}
								break;
							case 'comment_post':
								$args            = array(
									'post_type' => 'product',
									'number'    => $feed_count,
									'status'    => 'approve',
								);
								$comment_details = get_comments( $args );
								if ( is_array( $comment_details ) && count( $comment_details ) > 0 ) {
									$data_to_send['trigger_id'] = $event_id;
									$count                      = 1;
									$event_fields               = $event_details['fields'];
									$event_fields_merged        = array_merge( $event_fields, $this->comment_post_default_keys );
									foreach ( $comment_details as $key1 => $comment_details ) {
										$feed_data                      = $this->get_single_comment_data( $comment_details );
										$single_feed_array              = array();
										$single_feed_array['timestamp'] = strtotime( $comment_details->comment_date_gmt );
										$single_feed_array['image']     = $feed_data['image'];
										unset( $feed_data['image'] );
										$single_feed_array['fields'] = $feed_data;
										$data_to_send['data'][]      = $single_feed_array;
										$count ++;
									}
								}
								break;
							case 'woocommerce_order_status_completed':
								$orders = wc_get_orders(
									array(
										'limit'   => $feed_count,
										'orderby' => 'date',
										'order'   => 'DESC',
										'status'  => array( 'wc-completed' ),
									)
								);
								if ( is_array( $orders ) && count( $orders ) > 0 ) {
									$count = 1;
									foreach ( $orders as $key1 => $order ) {
										//                                      $order_id   = $order->get_id();
										$order_id = EMT_Compatibility::get_order_id( $order );
										//                                      $order_data = $order->get_data(); // The Order data
										$order_data = $this->get_order_data( $order );
										$items      = $order->get_items();
										if ( is_array( $items ) && count( $items ) > 0 ) {
											$single_feed_data = $this->get_new_order_data( $order_id, $order, $order_data, $items, $event_id, $event_details, $count );
											if ( is_array( $single_feed_data ) && count( $single_feed_data ) > 0 ) {
												$data_to_send['trigger_id'] = $event_id;
												$data_to_send['data'][]     = $single_feed_data[0]['data'][0];
											}
											$count ++;
										}
									}
								}
								break;
							default:
								break;
						}
					}
				}
			}
		}

		return $data_to_send;
	}

	/**
	 * @param $order_id
	 * @param $order_data
	 * @param $items
	 * @param $event_id
	 * @param $event_details
	 * @param $count
	 *
	 * @return array
	 *
	 * This function takes order and event details and return all the feeds for a event with feed schema
	 */
	public function get_new_order_data( $order_id, $order, $order_data, $items, $event_id, $event_details, $count ) {
		$excluded_product_ids = Emt_Common::get_excluded_product_ids( $this->slug );
		$final_data           = array();
		$event_type           = 'woocommerce_thankyou';
		if ( is_array( $items ) && count( $items ) > 0 ) {
			$data_to_send               = array();
			$data_to_send['trigger_id'] = $event_id;
			$feeds                      = array();
			$max_price_item             = array();
			foreach ( $items as $items_key => $items_value ) {
				$product_id = $items_value->get_product_id();
				if ( is_array( $excluded_product_ids ) && count( $excluded_product_ids ) > 0 ) {
					if ( in_array( $product_id, $excluded_product_ids ) ) {
						continue;
					}
				}
				$max_price_item[ $items_key ] = EMT_Compatibility::get_item_subtotal( $order, $items_value );
			}

			// No product eligible for becoming a feed
			if ( is_array( $max_price_item ) && 0 == count( $max_price_item ) ) {
				return $final_data;
			}

			// Get the max price item bcoz we will only made the feed for largest price item
			$max_item_key = array_keys( $max_price_item, max( $max_price_item ) );
			$max_item_key = $max_item_key[0];
			foreach ( $items as $items_key => $items_value ) {
				if ( $max_item_key == $items_key ) {
					$timestamp          = ( isset( $order_data['timestamp'] ) ) ? $order_data['timestamp'] : time();
					$extra_data         = array(
						'timestamp'      => ( $timestamp + $count ),
						'order_id'       => $order_id,
						'order_total'    => EMT_Compatibility::get_order_data( $order, '_order_total' ),
						'payment_method' => EMT_Compatibility::get_payment_gateway_from_order( $order ),
					);
					$event_fields_value = $this->get_single_feed_data( $event_type, $order_data, $items_value, $event_details['fields'], $extra_data );
					$feeds[]            = $event_fields_value;
				}
			}
			if ( is_array( $feeds ) && count( $feeds ) > 0 ) {
				$data_to_send['data'] = $feeds;
			}

			if ( is_array( $data_to_send ) && count( $data_to_send ) > 0 ) {
				$final_data[] = $data_to_send;
			}
		}

		return $final_data;
	}

	/**
	 * @param $event_type
	 * @param $order_data
	 * @param $items_value
	 * @param $event_fields
	 * @param $extra_data
	 *
	 * This function is the wrapper function for making a single feed
	 *
	 * @return array
	 */
	public function get_single_feed_data( $event_type, $order_data, $items_value, $event_fields, $extra_data ) {
		$final_data       = array();
		$single_feed_data = array();
		switch ( $event_type ) {
			case 'woocommerce_thankyou':
				$single_feed_data                 = $this->make_feed_data( $order_data, $extra_data );
				$single_feed_data['product_name'] = $items_value['name'];
				$single_feed_data['product_id']   = $items_value['product_id'];
				$single_feed_data['product_link'] = apply_filters( 'emt_alter_product_link', get_permalink( $items_value['product_id'] ), $items_value['product_id'] );
				//              $image                            = wp_get_attachment_image_src( get_post_thumbnail_id( $items_value['product_id'] ), 'single-post-thumbnail' );
				$image     = wp_get_attachment_image_src( get_post_thumbnail_id( $items_value['product_id'] ) );
				$image_url = '';
				if ( is_array( $image ) && count( $image ) > 0 ) {
					$image_url = $image[0];
					if ( '' == $image_url ) {
						$image_url = '';
					}
				}
				$final_data['image']     = array(
					'type' => 'dynamic',
					'url'  => $image_url,
				);
				$final_data['timestamp'] = $extra_data['timestamp'];
				$final_data['fields']    = $single_feed_data;
				break;
			case 'woocommerce_coupon_used':
				$single_feed_data        = $this->make_feed_data( $order_data, $extra_data );
				$final_data['timestamp'] = $extra_data['timestamp'];
				$final_data['fields']    = $single_feed_data;
				break;
			default:
				break;
		}

		return $final_data;
	}


	/**
	 * @param $order_data
	 * @param $extra_data
	 *
	 * This function makes a single feed data
	 *
	 * @return array
	 */
	public function make_feed_data( $order_data, $extra_data ) {
		$single_feed_data                        = array();
		$single_feed_data['customer_full_name']  = $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'];
		$single_feed_data['customer_first_name'] = $order_data['billing']['first_name'];
		$single_feed_data['customer_last_name']  = $order_data['billing']['last_name'];
		$single_feed_data['city']                = $order_data['billing']['city'];
		$single_feed_data['country']             = WC()->countries->countries[ $order_data['billing']['country'] ];
		if ( '' != $order_data['billing']['state'] ) {
			$countries_obj             = new WC_Countries();
			$states                    = $countries_obj->get_states();
			$single_feed_data['state'] = $states[ $order_data['billing']['country'] ][ $order_data['billing']['state'] ];
		}

		if ( '' != $single_feed_data['city'] && '' != $single_feed_data['state'] ) {
			$smart_address = $order_data['billing']['city'] . ', ' . $order_data['billing']['state'];
		} elseif ( '' != $single_feed_data['state'] && '' != $single_feed_data['country'] ) {
			$smart_address = $order_data['billing']['state'] . ', ' . $order_data['billing']['country'];
		} else {
			$smart_address = $single_feed_data['country'];
		}

		$single_feed_data['smart_address']    = $smart_address;
		$single_feed_data['billing_city']     = $order_data['billing']['city'];
		$single_feed_data['billing_country']  = WC()->countries->countries[ $order_data['billing']['country'] ];
		$countries_obj                        = new WC_Countries();
		$states                               = $countries_obj->get_states();
		$single_feed_data['billing_state']    = ( isset( $states[ $order_data['billing']['country'] ][ $order_data['billing']['state'] ] ) ) ? $states[ $order_data['billing']['country'] ][ $order_data['billing']['state'] ] : 'NA';
		$single_feed_data['shipping_city']    = $order_data['shipping']['city'];
		$single_feed_data['shipping_country'] = WC()->countries->countries[ $order_data['shipping']['country'] ];
		if ( '' != $order_data['shipping']['state'] ) {
			$countries_obj                      = new WC_Countries();
			$states                             = $countries_obj->get_states();
			$single_feed_data['shipping_state'] = $states[ $order_data['shipping']['country'] ][ $order_data['shipping']['state'] ];
		}
		$single_feed_data['order_id']       = $extra_data['order_id'];
		$single_feed_data['order_total']    = $extra_data['order_total'];
		$single_feed_data['payment_method'] = $extra_data['payment_method'];
		$single_feed_data['ip']             = $order_data['customer_ip_address'];
		$single_feed_data['email']          = $order_data['email'];

		return $single_feed_data;
	}

	public function capitalize_word( $text ) {
		return ucwords( strtolower( $text ) );
	}

	/**
	 * @param $order
	 *
	 * This function takes the original Woocommerce Order Object and return the compatible object for wc versions greater than 2.6 .
	 *
	 * @return array
	 */
	public function get_order_data( $order ) {
		$actual_timestamp                    = time();
		$order_data                          = array();
		$order_data['billing']['first_name'] = EMT_Compatibility::get_order_data( $order, '_billing_first_name' );
		$order_data['billing']['last_name']  = EMT_Compatibility::get_order_data( $order, '_billing_last_name' );

		$order_data['billing']['city']    = EMT_Compatibility::get_order_data( $order, '_billing_city' );
		$order_data['billing']['country'] = EMT_Compatibility::get_order_data( $order, '_billing_country' );
		$order_data['billing']['state']   = EMT_Compatibility::get_order_data( $order, '_billing_state' );

		$order_data['shipping']['city']    = EMT_Compatibility::get_order_data( $order, '_shipping_city' );
		$order_data['shipping']['country'] = EMT_Compatibility::get_order_data( $order, '_shipping_country' );
		$order_data['shipping']['state']   = EMT_Compatibility::get_order_data( $order, '_shipping_state' );

		$order_data['customer_ip_address'] = EMT_Compatibility::get_order_data( $order, '_customer_ip_address' );
		$order_data['email']               = EMT_Compatibility::get_order_data( $order, '_billing_email' );

		if ( 'woocommerce_thankyou' == $this->current_event_type ) {
			$timestamp = EMT_Compatibility::get_order_date( $order );
			if ( $timestamp instanceof DateTime ) {
				$actual_timestamp = strtotime( $timestamp->format( 'Y-m-d H:i:s' ) );
			} else {
				$actual_timestamp = strtotime( $timestamp );
			}
		} else {
			$timestamp = $order->get_date_completed();
			if ( $timestamp instanceof DateTime ) {
				$actual_timestamp = strtotime( $timestamp->format( 'Y-m-d H:i:s' ) );
			}
		}

		$order_data['timestamp'] = $actual_timestamp;

		return $order_data;
	}

	/**
	 * Shows the settings page of the integration
	 */
	public function get_settings_page() {
		include_once 'settings.php';
	}

}

return Emt_Wc::get_instance();

