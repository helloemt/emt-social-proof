<?php

class Emt_Cf7 extends Emt_Integrations{
	private static $instance = null;
	public $slug = 'cf7';

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new self;
		}

		return self::$instance;
	}
}

return Emt_Cf7::get_instance();
