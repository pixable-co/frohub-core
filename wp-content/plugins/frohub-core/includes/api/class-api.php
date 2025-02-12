<?php
namespace FECore;

use FECore\ProductAttributes;
use FECore\ProductServiceType;
use FECore\GetMyProducts;
use FECore\ReturnOrderDetails;
use FECore\CreatePartnerPost;
use FECore\PublishPartnerCreateProduct;
use FECore\GetPartnerData;
use FECore\UpdateZohoAccountId;
use FECore\CreateDraftServiceProduct;
use FECore\CreateComment;
use FECore\GetConversations;
use FECore\GetConversationsComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function init() {
		$self = new self();
		ProductAttributes::init();
		ProductServiceType::init();
		GetMyProducts::init();
		ReturnOrderDetails::init();
		CreatePartnerPost::init();
		PublishPartnerCreateProduct::init();
		GetPartnerData::init();
		UpdateZohoAccountId::init();
		CreateDraftServiceProduct::init();
		CreateComment::init();
		GetConversations::init();
		GetConversationsComments::init();
	}
}