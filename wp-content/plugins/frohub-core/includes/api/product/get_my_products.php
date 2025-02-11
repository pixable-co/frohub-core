<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetMyProducts {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/get-my-products', array(
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
            ],
        ));
    }

    /**
     * Fetches and returns product data including both published and draft statuses, 
     * with specific ACF fields and additional product information.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function fetch_product_data(\WP_REST_Request $request) {
        // Get partner ID from the REST request
        $partner_id_requested = $request->get_param('partner_id');

        // Define the query arguments
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
            'meta_query'     => [
                [
                    'key'     => 'partner_id',
                    'value'   => $partner_id_requested,
                    'compare' => '='
                ]
            ]
        ];

        // Execute the query
        $query = new \WP_Query($args);
        $product_data = [];

        // Loop through the posts and get product details
        foreach ($query->posts as $post_id) {
            // Fetch ACF and other product details
            $product_data[] = [
                'ID' => $post_id,
                'Sizes' => get_field('sizes', $post_id),
                'Length' => get_field('length', $post_id),
                'Service Types' => get_field('service_types', $post_id),
                'Notice Needed' => get_field('notice_needed', $post_id),
                'Booking Far In Advance' => get_field('booking_far_in_advance', $post_id),
                'Clients Can Serve Per Time Slot' => get_field('clients_can_serve_per_time_slot', $post_id),
                'Duration Hours' => get_field('duration_hours', $post_id),
                'Duration Minutes' => get_field('duration_minutes', $post_id),
                'Gallery' => get_field('gallery', $post_id),
                'Private Service' => get_field('private_service', $post_id),
                'Partner Name' => get_field('partner_name', $post_id),
                'Partner ID' => get_field('partner_id', $post_id),
                'Price' => get_post_meta($post_id, '_price', true),
                'Categories' => wp_get_post_terms($post_id, 'product_cat', ['fields' => 'names']),
                'Tags' => wp_get_post_terms($post_id, 'product_tag', ['fields' => 'names']),
                'Featured Image URL' => get_the_post_thumbnail_url($post_id, 'full'),
                'Availability' => $this->get_availability_data($post_id)
            ];
        }

        // Return the product data in a REST response
        return new \WP_REST_Response($product_data, 200);
    }

    /**
     * Fetches availability data from ACF repeater fields.
     *
     * @param int $post_id
     * @return array
     */
    private function get_availability_data($post_id) {
        $availability = [];
        if (have_rows('availability', $post_id)) {
            while (have_rows('availability', $post_id)) {
                the_row();
                $availability[] = [
                    'day' => get_sub_field('day'),
                    'from' => get_sub_field('from'),
                    'to' => get_sub_field('to'),
                    'extra_charge' => get_sub_field('extra_charge'),
                ];
            }
        }
        return $availability;
    }
}
