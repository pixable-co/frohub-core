<?php
namespace FECore;

use FECore\ProductAttributes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		ProductAttributes::init();
	}
}