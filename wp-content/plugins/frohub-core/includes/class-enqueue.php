<?php

namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Enqueue {

	public static function init() {
		$self = new self();
		add_action( 'wp_enqueue_scripts', array( $self, 'fpserver_scripts' ) );
	}

	public function fpserver_scripts() {
	        wp_enqueue_style( 'frohub-shortcode-style', FHCORE_ROOT_DIR_URL . 'includes/assets/shortcode/style.css' );
	        wp_enqueue_script( 'frohub-shortcode-script', FHCORE_ROOT_DIR_URL . 'includes/assets/shortcode/scripts.js', 'jquery', '0.0.1', true );
			wp_enqueue_style( 'frohub-build-style', FHCORE_ROOT_DIR_URL . 'includes/assets/build/frontend.css' );
			wp_enqueue_script( 'frohub-build-script', FHCORE_ROOT_DIR_URL . 'includes/assets/build/frontend.js', 'jquery', '0.0.1', true );
			wp_localize_script(
				'frohub-build-script',
				'frohub_settings',
				array(
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'fpserver_nonce' ),
					'rest_url'        => esc_url_raw( rest_url() ),
				)
			);

		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_script' ), 10, 3 );
	}

	public function add_module_type_to_script( $tag, $handle, $src ) {
		if ( 'frohub-build-script' === $handle ) {
			$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
		}
		return $tag;
	}
}
