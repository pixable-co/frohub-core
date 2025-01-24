<?php
namespace FECore;

use FECore\ProductAttributes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function init() {
		$self = new self();
		ProductAttributes::init();
	}
}