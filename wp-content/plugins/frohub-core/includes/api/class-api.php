<?php
namespace FECore;

use FECore\GetLocationData;


use FECore\UpdateLocationData;

use FECore\ProductAttributes;
use FECore\GetMyProducts;
use FECore\ReturnOrderDetails;
use FECore\CreatePartnerPost;
use FECore\PublishPartnerCreateProduct;
use FECore\GetPartnerData;
use FECore\UpdateZohoAccountId;
use FECore\CreateProduct;
use FECore\CreateComment;
use FECore\GetConversationsComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function init() {
		$self = new self();
		ProductAttributes::init();
		GetMyProducts::init();
		ReturnOrderDetails::init();
		CreatePartnerPost::init();
		PublishPartnerCreateProduct::init();
		GetPartnerData::init();
		UpdateZohoAccountId::init();
		CreateProduct::init();
		CreateComment::init();
		GetConversationsComments::init();
		UpdateLocationData::init();
		GetLocationData::init();
	}
}