<?php
namespace FECore;

use FECore\ShowComments;

use FECore\GetUserAvatar;

use FECore\GetUpcomingBookings;

use FECore\ShowNextBookings;

use FECore\CommunityPostType;

use FECore\CommunityPostGrid;

use FECore\PlayButton;

use FECore\GetUserPastBookings;

use FECore\PartnerVerifiedBadge;

use FECore\OrderMetaData;

use FECore\SubCategoryCarousel;

use FECore\PrintOrderId;

use FECore\GetOrderStatus;

use FECore\GetOrderStartDate;

use FECore\GetOrderPrices;

use FECore\GetOrderServiceType;

use FECore\GetOrderShippingAddress;

use FECore\GetOrderBeauticianDetails;

use FECore\GetOrderNotes;

use FECore\GetOrderServiceName;

use FECore\GetPartnerName;

use FECore\GetProductRating;

use FECore\PrintOverallRating;

use FECore\ReviewTab;

use FECore\ReviewButton;

use FECore\AllFaqs;

use FECore\MyQna;

use FECore\PartnerLogoAndBio;

use FECore\PoliciesTab;

use FECore\ReviewAuthor;

use FECore\FhServiceAverageRating;

use FECore\RequestBookButton;

// Product Template Shortcodes
use FECore\FrohubCalender;
use FECore\RenderAddToCart;

// Partner Template Shortcodes
use FECore\FrohubProductPartnerPage;
use FECore\FrohubGetPartnerLocation;
use FECore\FrohubGetProductServiceTypes;
use FECore\FrohubGetPartnerName;
use FECore\FrohubGetPartnerServiceTypes;
use FECore\FrohubGetFaqs;

// Tutorials Shortcodes
use FECore\GetTutorialsCategory;

// Blogs Shortcode
use FECore\GetPostCategory;

// Conversation Shortcodes
use FECore\DisplayComments;
use FECore\DisplayExistingConversations;
use FECore\SubmitCommentForm;

// Header shortcodes
use FECore\RenderSearchFilters;
use FECore\CategoryAutocompleteFilter;
use FECore\ServicetypeFilter;
use FECore\DaterangeFilter;
use FECore\RadiusFilter;
use FECore\LocationFilter;

// Product Archive Page
use FECore\RenderServicesGrid;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {

	public static function init() {
		$self = new self();

		// Initialise shortcodes
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
		DisplayComments::init();
		DisplayExistingConversations::init();
		RenderHeaderFilters::init();
		SubmitCommentForm::init();
		RenderServicesGrid::init();
		RenderSearchFilters::init();
		ServicetypeFilter::init();
		CategoryAutocompleteFilter::init();
		DaterangeFilter::init();
		RadiusFilter::init();
		LocationFilter::init();
		RequestBookButton::init();
		FhServiceAverageRating::init();
		ReviewAuthor::init();
		PoliciesTab::init();
		PartnerLogoAndBio::init();
		MyQna::init();
		AllFaqs::init();
		ReviewButton::init();
		ReviewTab::init();
		PrintOverallRating::init();
		GetProductRating::init();
		GetOrderServiceName::init();
		GetOrderNotes::init();
		GetOrderBeauticianDetails::init();
		GetOrderShippingAddress::init();
		GetOrderServiceType::init();
		GetOrderPrices::init();
		GetOrderStartDate::init();
		GetOrderStatus::init();
		PrintOrderId::init();
		SubCategoryCarousel::init();
		OrderMetaData::init();
		PartnerVerifiedBadge::init();
		GetUserPastBookings::init();
		PlayButton::init();
		CommunityPostGrid::init();
		CommunityPostType::init();
		ShowNextBookings::init();
		GetUpcomingBookings::init();
		GetUserAvatar::init();
		ShowComments::init();
	}
}
