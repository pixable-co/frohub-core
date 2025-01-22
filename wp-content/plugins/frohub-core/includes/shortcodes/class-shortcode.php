<?php
namespace FECore;

use FECore\FhSubmitForm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {

	public static function init() {
		$self = new self();
		FhSubmitForm::init();
	}
}
