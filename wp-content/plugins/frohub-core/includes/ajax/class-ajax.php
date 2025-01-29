<?php
namespace FECore;

use FECore\GetAvailibility;

use FECore\AddToCart;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ajax {

	public static function init() {
		$self = new self();
		AddToCart::init();
		GetAvailibility::init();
	}
}
