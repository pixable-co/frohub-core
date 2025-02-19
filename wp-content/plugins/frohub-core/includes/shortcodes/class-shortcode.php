<?php
namespace FECore;

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
use FECore\RenderHeaderFilters;
use FECore\AutocompleteField;
use FECore\ServicetypeField;
use FECore\DaterangeField;
use FECore\RadiusField;
use FECore\LocationField;

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
		ServicetypeField::init();
		AutocompleteField::init();
		DaterangeField::init();
		RadiusField::init();
		LocationField::init();
		RequestBookButton::init();
	}
}
