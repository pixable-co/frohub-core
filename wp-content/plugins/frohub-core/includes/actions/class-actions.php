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
use FECore\CreateConversionProcessPopulate;
use FECore\CustomMenuItems;
use FECore\CustomMessagesContent;
use FECore\CustomFavouritesContent;
use FECore\CustomQnAContent;
use FECore\CustomFlushRewriteRules;


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
		CreateConversionProcessPopulate::init();
		CustomMenuItems::init();
		CustomMessagesContent::init();
		CustomFavouritesContent::init();
		CustomQnAContent::init();
		CustomFlushRewriteRules::init();
	}
}
