<?php
namespace FECore;

use FECore\SubmitComment;

use FECore\SubmitLike;

use FECore\SubmitDislike;

use FECore\GetCategoryTerms;

use FECore\AcceptNewTime;

use FECore\CancelOrder;

use FECore\EarlyCancelOrder;

use FECore\LateCancelOrder;

use FECore\DeclineNewProposedTime;

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
		DeclineNewProposedTime::init();
		LateCancelOrder::init();
		EarlyCancelOrder::init();
		CancelOrder::init();
		AcceptNewTime::init();
		GetCategoryTerms::init();
		SubmitDislike::init();
		SubmitLike::init();
		SubmitComment::init();
	}
}
