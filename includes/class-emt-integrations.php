<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Emt_Integrations {

	public $response       = array(
		'code' => 1006,
		'msg'  => 'Invalid Data',
	);
	public $response_codes = array(
		10001 => array(
			'code'    => 10001,
			'message' => 'Event Added',
		),
		10002 => array(
			'code'    => 10002,
			'message' => 'Event Updated',
		),
		10003 => array(
			'code'    => 10003,
			'message' => 'Event Deleted',
		),
		10004 => array(
			'code'    => 10004,
			'message' => 'Record Fetched',
		),
		10005 => array(
			'code'    => 10005,
			'message' => 'Event ID or Form ID or Event Fields Missing',
		),
		10006 => array(
			'code'    => 10006,
			'message' => 'Event ID or Hook Name or Event Fields Missing',
		),
	);
	public $request_ip     = '';
	public $post_vars;
	public $current_time;
	public $public_methods = array( 'add', 'get', 'delete', 'update', 'getfeeds' );
	public $integrations_data;
	public $default_available_keys = array( 'city', 'state', 'country' );

	public function __construct() {
		$input_vars = file_get_contents( 'php://input' );
		if ( isset( $_POST ) && count( $_POST ) > 0 ) {
			$this->post_vars = $_POST;
		} elseif ( '' != $input_vars ) {

			$this->post_vars = json_decode( $input_vars, ARRAY_A );
		} else {
			$this->post_vars = array();
		}
		$this->current_time = time();
		$this->http_reff    = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
	}

	public function get() {

	}

	public function add() {

	}

	public function update() {

	}

	public function delete() {

	}

	public function getfeeds() {

	}

	/**
	 * @param $schema
	 *
	 * This function sends feeds to EMT.com
	 *
	 * @param bool $is_bulk
	 */
	public function send_feed( $schema, $is_bulk = false ) {
		$final_output = null;
		$token        = Emt_Common::get_token();
		if ( $is_bulk ) {
			$api_url = EMT_DOMAIN . EMT_ADD_BULK_FEED_ENDPOINT;
			$data    = [
				'token'    => $token,
				'event_id' => $schema['trigger_id'],
				'data'     => $schema['data'],
			];
		} else {
			$api_url = EMT_DOMAIN . EMT_PUSH_SINGLE_FEED_ENDPOINT;
			$data    = [
				'token'    => $token,
				'event_id' => $schema['trigger_id'],
				'fields'   => $schema['fields'],
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
	}

	/**
	 * This function fires a call to EMT.com and collects all the integration's and event's details and save them in options table
	 */
	public static function get_integrations_data( $api_key, $user_id, $site_id ) {
		$final_output    = null;
		$api_url         = EMT_DOMAIN . EMT_GET_ALL_INFO_ENDPOINT;
		$data            = [
			'token'     => Emt_Common::get_token(),
			'wordpress' => true,
			'site_id'   => $site_id,
			'user_id'   => $user_id,
		];
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
				if ( isset( $response['result'] ) && 'empty' == $response['result'] ) {
					Emt_Common::emt_update_option( EMT_SHORT_SLUG . 'integrations_data_' . $api_key, array( 'gf' => 'gf' ) );
				} else {
					Emt_Common::emt_update_option( EMT_SHORT_SLUG . 'integrations_data_' . $api_key, $response );
				}
			}
		}
	}

}
