<?php
namespace FECore;
use FECore\ProfileImage;
use FECore\UpdatePayoutStatus;
use FECore\PayoutTableColumns;
use FECore\HideJetpackStatColumn;
use FECore\CustomizedCheckoutField;
use FECore\SendOrderToEndpoint;
use FECore\ManagePartnerPostColumn;
use FECore\CreateReviewPost;
use FECore\CreateUpdatePayoutPost;
use FECore\CustomOrderStatus;
use FECore\ConversationAutoReply;
use FECore\UpdateStartEndDate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions {

	public static function init() {
		$self = new self();
		ProfileImage::init();
		UpdatePayoutStatus::init();
		PayoutTableColumns::init();
		HideJetpackStatColumn::init();
		CustomizedCheckoutField::init();
		SendOrderToEndpoint::init();
		ManagePartnerPostColumn::init();
		CreateReviewPost::init();
		CreateUpdatePayoutPost::init();
		CustomOrderStatus::init();
		ConversationAutoReply::init();
		UpdateStartEndDate::init();
	}
}
