<?php
namespace FECore;

use FECore\FrohubCalender;


use FECore\RenderAddToCart;
use FECore\FrohubProductPartnerPage;
use FECore\FrohubGetPartnerLocation;
use FECore\FrohubGetProductServiceTypes;
use FECore\FrohubGetPartnerName;

use FECore\FrohubGetPartnerServiceTypes;
use FECore\FrohubGetFaqs;

use FECore\GetTutorialsCategory;

use FECore\GetPostCategory;

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
		FrohubGetPartnerServiceTypes::init();
		FrohubGetProductServiceTypes::init();
		FrohubGetFaqs::init();
		FrohubGetPartnerName::init();
		GetTutorialsCategory::init();
		GetPostCategory::init();

		
	}
}
