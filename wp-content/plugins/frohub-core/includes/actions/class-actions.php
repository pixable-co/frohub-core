<?php
namespace FECore;
use FECore\ProfileImage;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions {

	public static function init() {
		$self = new self();
		ProfileImage::init();
	}
}
