<?php

namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Enqueue {

	public static function init() {
		$self = new self();
		add_action( 'wp_enqueue_scripts', array( $self, 'fpserver_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $self, 'fpserver_admin_scripts' ) );
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
					'nonce'           => wp_create_nonce( 'frohub_nonce' ),
				)
			);

		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_script' ), 10, 3 );
	}

    public function fpserver_admin_scripts() {
    	wp_enqueue_script(
    		'frohub-admin-script',
    		FHCORE_ROOT_DIR_URL . 'includes/assets/build/admin.js',
    		['jquery'],
    		'0.0.1',
    		true
    	);

    	// Determine current post type (edit screen or new post screen)
    	$post_type = '';
    	if (isset($_GET['post'])) {
    		$post_type = get_post_type($_GET['post']);
    	} elseif (isset($_GET['post_type'])) {
    		$post_type = sanitize_text_field($_GET['post_type']);
    	}

    	wp_localize_script(
    		'frohub-admin-script',
    		'frohub_settings',
    		array(
    			'ajax_url'   => admin_url('admin-ajax.php'),
    			'nonce'      => wp_create_nonce('frohub_nonce'),
    			'post_type'  => $post_type,
    		)
    	);

    	add_filter('script_loader_tag', array($this, 'add_module_type_to_script'), 10, 3);
    }

    public function add_module_type_to_script( $tag, $handle, $src ) {
    	$module_handles = array( 'frohub-build-script', 'frohub-admin-script' );

    	if ( in_array( $handle, $module_handles, true ) ) {
    		$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
    	}

    	return $tag;
    }

// 	public function add_module_type_to_script( $tag, $handle, $src ) {
// 		if ( 'frohub-build-script' === $handle ) {
// 			$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
// 		}
// 		return $tag;
// 	}
}
