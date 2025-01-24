<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductAttributes {

    public static function init() {
        add_action('rest_api_init', function () {
            register_rest_route('frohub/v1', '/product-attributes', array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'get_product_id'),
                'permission_callback' => '__return_true',
            ));
        });
    }

    public static function get_product_id(\WP_REST_Request $request) {
        $product_id = $request->get_param( 'product_id' );
		$product = wc_get_product( $product_id );
		$partner = null;
		$matched_add_ons = array();
		$product_price = 0;

		if ( $product ) {
			$product_price = $product->get_regular_price();
			$partner_id = get_field('partner_id', $product_id);
			if ($partner_id) {
				$partner_post = get_post($partner_id);
				if ($partner_post) {
					$partner = array(
						'title'   => $partner_post->post_title,
						'content' => $partner_post->post_content,
					);

					$add_ons = get_field('add_ons', $partner_id);
					$attributes = $product->get_attributes();

					if (!empty($attributes) && !empty($add_ons)) {
						foreach ($attributes as $attribute) {
							if ($attribute->is_taxonomy()) {
								$terms = wc_get_product_terms($product_id, $attribute->get_name(), array('fields' => 'all'));

								foreach ($terms as $term) {
									foreach ($add_ons as $add_on) {
										if ($term->term_id == $add_on['add_on']->term_id) {
											$matched_add_ons[] = array(
												'id' => $term->term_id,
												'name' => $term->name,
												'price' => $add_on['price'],
												'duration_minutes' => $add_on['duration_minutes']
											);
										}
									}
								}
							}
						}
					}
				}
			}
		}

		$response = array( 
			'product_id' => $product_id,
			'partner'    => $partner,
			'add_ons'    => $matched_add_ons,
			'product_price' => $product_price,
		);

		// Log the response for debugging
		error_log(print_r($response, true));

		return rest_ensure_response( $response );
    }
}
