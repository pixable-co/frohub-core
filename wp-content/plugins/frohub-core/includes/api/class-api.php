<?php
namespace FECore;

use FECore\ProductAttributes;
use FECore\ProductServiceType;
use FECore\GetMyProducts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function init() {
		$self = new self();
		ProductAttributes::init();
		ProductServiceType::init();
		GetMyProducts::init();
	}
}