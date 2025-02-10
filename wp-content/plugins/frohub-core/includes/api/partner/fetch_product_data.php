<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FetchProductData {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/fetch-product-data', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'fetch_product_data'),
            'permission_callback' => function () {
                                return current_user_can('edit_products');
            },
            'args' => [
                   'partner_id' => [
                   'required' => true,
                   'validate_callback' => function($param, $request, $key) {
                       return is_numeric($param);
                   }
               ]
           ]
        ));
    }

    public function fetch_product_data($request) {
        // Get partner ID from the REST request
        $partner_id_requested = $request->get_param('partner_id');

        // Define the query arguments
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
            'meta_query'     => [
                [
                    'key'   => 'partner_id',
                    'value' => $partner_id_requested,
                    'compare' => '='
                ]
            ]
        ];


        // Execute the query
        $query = new WP_Query($args);
        $product_data = [];

        // Loop through the post IDs and get additional data
        foreach ($query->posts as $post_id) {
            // Fetch ACF and other product details
            $sizes = get_field('sizes', $post_id) ?: null;
            $length = get_field('length', $post_id) ?: null;
            $service_types = get_field('service_types', $post_id) ?: null;
            $notice_needed = get_field('notice_needed', $post_id) ?: null;
            $booking_far_in_advance = get_field('booking_far_in_advance', $post_id) ?: null;
            $clients_can_serve_per_time_slot = get_field('clients_can_serve_per_time_slot', $post_id) ?: null;
            $duration_hours = get_field('duration_hours', $post_id) ?: null;
            $duration_minutes = get_field('duration_minutes', $post_id) ?: null;
            $gallery = get_field('gallery', $post_id) ?: null;
            $private_service = get_field('private_service', $post_id) ?: null;
            $partner_name = get_field('partner_name', $post_id) ?: null;
            $partner_id = get_field('partner_id', $post_id) ?: null;
            $price = get_post_meta($post_id, '_price', true) ?: null; // Adjust the meta key '_price' as per your setup
            $categories = wp_get_post_terms($post_id, 'product_cat', ['fields' => 'names']) ?: null; // Ensure 'product_cat' is your taxonomy
            $tags = wp_get_post_terms($post_id, 'product_tag', ['fields' => 'names']) ?: null; // Ensure 'product_tag' is your taxonomy
            $featured_image_url = get_the_post_thumbnail_url($post_id, 'full') ?: null;

            $availability = []; // Handle repeater fields separately
            if (have_rows('availability', $post_id)) {
                while (have_rows('availability', $post_id)) {
                    the_row();
                    $availability[] = [
                        'day' => get_sub_field('day') ?: null,
                        'from' => get_sub_field('from') ?: null,
                        'to' => get_sub_field('to') ?: null,
                        'extra_charge' => get_sub_field('extra_charge') ?: null,
                    ];
                }
            }

            $product_data[] = [
                'ID' => $post_id,
                'Sizes' => $sizes,
                'Length' => $length,
                'Service Types' => $service_types,
                'Notice Needed' => $notice_needed,
                'Booking Far In Advance' => $booking_far_in_advance,
                'Clients Can Serve Per Time Slot' => $clients_can_serve_per_time_slot,
                'Duration Hours' => $duration_hours,
                'Duration Minutes' => $duration_minutes,
                'Gallery' => $gallery,
                'Private Service' => $private_service,
                'Partner Name' => $partner_name,
                'Partner ID' => $partner_id,
                'Price' => $price,
                'Categories' => $categories,
                'Tags' => $tags,
                'Featured Image URL' => $featured_image_url,
                'Availability' => $availability
            ];
        }

     // Return a REST response
        return new WP_REST_Response($product_data, 200);
    }
}