<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emt_Public {

	protected static $instance = null;

	/**
	 * construct
	 */
	public function __construct() {
		/** Load Hooks **/
		$this->hooks();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since  1.0.0
	 * @return object    A single instance of this class.
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fires the public side hooks
	 */
	public function hooks() {
		add_action( 'wp_footer', array( $this, 'plugin_enqueue_scripts' ) );
	}

	/**
	 * Include all the public side scripts if any
	 */
	public function plugin_enqueue_scripts() {
		echo '<script>var emt_social_proof_site_url = "' . site_url() . '";</script>';
	}

}

Emt_Public::get_instance();
