<?php
namespace FECore;

use FECore\FrohubCalender;


use FECore\RenderAddToCart;
use FECore\FrohubProductPartnerPage;
use FECore\FrohubGetPartnerLocation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {

	public static function init() {
		$self = new self();
		RenderAddToCart::init();
		FrohubCalender::init();
		FrohubProductPartnerPage::init();
		FrohubGetPartnerLocation::init();
		
	}
}
