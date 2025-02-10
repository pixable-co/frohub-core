<?php
namespace FECore;

use FECore\FetchProductData;
use FECore\GetOrdersByPartnerId;
use FECore\CreatePartnerPost;
use FECore\PublishPartnerCreateProduct;
use FECore\GetPartnerData;
use FECore\UpdateUserId;
use FECore\CreateComment;
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
		CreateComment::init();
		UpdateUserId::init();
		GetPartnerData::init();
		PublishPartnerCreateProduct::init();
		CreatePartnerPost::init();
		GetOrdersByPartnerId::init();
		FetchProductData::init();
	}
}