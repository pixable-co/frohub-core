<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class MyServices {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/my-services', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_my_services'],
            'permission_callback' => '__return_true', // ⚠️ Be careful, this makes it public!
            'args'     => [
                'partner_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    }
                ],
            ],
        ]);
    }

 * Handles retrieving services linked to a partner.
 *
 * @param \WP_REST_Request $request
 * @return \WP_REST_Response
 */
public function handle_my_services(\WP_REST_Request $request) {
    $user_id = get_current_user_id();

    // if (!$user_id) {
    //     return new \WP_REST_Response([
    //         'success' => false,
    //         'message' => 'Authentication failed.'
    //     ], 401);
    // }

    // Retrieve partner_id from request
    $partner_id = intval($request->get_param('partner_id'));

    // Query WooCommerce variable products linked to this partner
    $query_args = [
        'post_type'      => 'product',
        'post_status'    => ['publish', 'draft'],
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'partner_name', // ACF field storing the post object
                'value'   => $partner_id,
                'compare' => '='
            ]
        ],
        'tax_query'      => [
            [
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => 'variable' // Ensure only variable products are returned
            ]
        ]
    ];

    $products_query = new \WP_Query($query_args);
    $products_data = [];

    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);

            // Product Name
            $product_name = get_the_title();

            // Product Categories (Only Parent Categories)
            $categories = [];
            $terms = get_the_terms($product_id, 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if ($term->parent == 0) { // Only include parent categories
                        $categories[] = $term->name;
                    }
                }
            }

            // Product Price (from ACF 'display_price', formatted)
            $display_price = get_field('service_price', $product_id);
            $price = $display_price ? '£' . number_format((float)$display_price, 2, '.', '') : '£ 0.00';

            // Product Status
            $status = get_post_status($product_id);

            // Marketplace Visibility (ACF True/False field)
            $marketplace_visibility = get_field('marketplace_visibility', $product_id) ? true : false;

            // Public Product URL
            $url = get_permalink($product_id);

            // Product Thumbnail URL
            $thumbnail = get_the_post_thumbnail_url($product_id, 'full');

            // Retrieve variations (Only return if variation is published)
            $variations = [];
            if ($product->is_type('variable')) {
                $variation_ids = $product->get_children();

                foreach ($variation_ids as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation && $variation->get_status() === 'publish') { // Only return published variations
                        // Get attributes including label values
                        $attributes = $variation->get_attributes();
                        $service_type_label = '';

                        if (isset($attributes['pa_service-type'])) {
                            $service_type_slug = $attributes['pa_service-type'];

                            // Convert slug to human-readable label
                            $service_type_label = wc_attribute_label('pa_service-type');
                            $service_type_terms = get_terms([
                                'taxonomy'   => 'pa_service-type',
                                'slug'       => $service_type_slug,
                                'hide_empty' => false,
                            ]);

                            if (!is_wp_error($service_type_terms) && !empty($service_type_terms)) {
                                $service_type_label = $service_type_terms[0]->name;
                            }
                        }

                        $variations[] = [
                            'variation_id'      => $variation_id,
                            'variation_option'  => $service_type_label
                        ];
                    }
                }
            }

            $products_data[] = [
                'product_id'              => $product_id,
                'product_name'            => $product_name,
                'categories'              => $categories,
                'price'                   => $price,
                'status'                  => $status,
                'marketplace_visibility'  => $marketplace_visibility,
                'url'                     => $url,
                'thumbnail'               => $thumbnail,
                'variations'              => $variations // Only published variations included
            ];
        }
        wp_reset_postdata();
    }

    return new \WP_REST_Response([
        'success'   => true,
        'message'   => 'Products retrieved successfully!',
        'partner_id'=> $partner_id,
        'products'  => $products_data
    ], 200);
}


}
