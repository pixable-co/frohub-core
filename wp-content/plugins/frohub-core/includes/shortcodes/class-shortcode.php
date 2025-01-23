<?php
namespace FECore;

use FECore\RenderProductAddOns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {

	public static function init() {
		$self = new self();
		RenderProductAddOns::init();
	}
}
