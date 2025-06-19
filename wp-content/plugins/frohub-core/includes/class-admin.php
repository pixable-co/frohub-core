<?php

namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public static function init() {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'add_admin_menu' ) );
	}

	public function add_admin_menu() {
		$parent = 'frohub-admin';

		add_menu_page(
			__( 'Frohub Options', 'frohub-core' ),
			'Frohub Options',
			'manage_options',
			$parent,
			array( $this, 'frohub_callback' ),
			plugin_dir_url( __FILE__ ) . 'library/icon-16x16.png',
			30
		);
	}

	public function frohub_callback() {
		?>
		<div id="frohub-admin"></div>
		<?php
	}
}