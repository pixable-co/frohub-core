<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', array( $self, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'frohub/v1', '/product-attributes', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_product_attributes' ),
		) );
	}

	public function get_product_attributes( $request ) {
		$product_id = $request->get_param( 'product_id' );
		// Fetch product attributes logic here
		$attributes = array(
			array( 'id' => 1, 'name' => 'Color', 'value' => 'Red' ),
			array( 'id' => 2, 'name' => 'Size', 'value' => 'Medium' ),
		);
		return rest_ensure_response( $attributes );
	}
}
