<?php
namespace FECore;

use FECore\GetUpcomingBooking;

use FECore\GetLocationData;
use FECore\UpdateLocationData;
use FECore\ProductAttributes;
use FECore\GetMyProducts;
use FECore\ReturnOrderDetails;
use FECore\CreatePartnerPost;
use FECore\PublishPartnerCreateProduct;
use FECore\GetPartnerData;
use FECore\UpdateZohoAccountId;
use FECore\UpsertProduct;
use FECore\CreateComment;
use FECore\GetConversationsComments;
use FECore\UnreadCount;
use FECore\MarkRead;
use FECore\ReturnProductCategories;
use FECore\ReturnPartnerFaqs;
use FECore\ReturnSpecificPartnerAddOns;
use FECore\ReturnAllProductTags;


// use FECore\Payouts;
use FECore\MyServices;
use FECore\ConfirmOrder;
use FECore\RescheduleOrder;
use FECore\UpdatePartnerStripeDetails;
use FECore\StripeAccount;
use FECore\ReturnPayoutsPost;

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
		UpsertProduct::init();
		CreateComment::init();
		GetConversationsComments::init();
		UpdateLocationData::init();
		GetLocationData::init();
		UnreadCount::init();
		MarkRead::init();
		ReturnProductCategories::init();
		ReturnPartnerFaqs::init();
		ReturnSpecificPartnerAddOns::init();
		ReturnAllProductTags::init();


// 		Payouts::init();
		MyServices::init();
		ConfirmOrder::init();
		RescheduleOrder::init();
		UpdatePartnerStripeDetails::init();
		StripeAccount::init();
		ReturnPayoutsPost::init();

		GetUpcomingBooking::init();
	}
}