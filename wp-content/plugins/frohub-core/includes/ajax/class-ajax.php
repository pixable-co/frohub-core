<?php
namespace FECore;

use FECore\GetDuration;


use FECore\GetAddons;

use FECore\GetMobileLocationData;

use FECore\GetServiceType;

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
		GetServiceType::init();
		GetMobileLocationData::init();
		GetAddons::init();
		GetDuration::init();
	}
}
