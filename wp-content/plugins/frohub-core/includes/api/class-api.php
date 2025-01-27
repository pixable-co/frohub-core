<?php
namespace FECore;

use FECore\ProductAttributes;
use FECore\ProductServiceType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function init() {
		$self = new self();
		ProductAttributes::init();
		ProductServiceType::init();
	}
}