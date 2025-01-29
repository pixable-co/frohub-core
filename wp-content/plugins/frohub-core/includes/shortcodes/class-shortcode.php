<?php
namespace FECore;

use FECore\FrohubCalender;


use FECore\RenderAddToCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {

	public static function init() {
		$self = new self();
		RenderAddToCart::init();
		FrohubCalender::init();
	}
}
