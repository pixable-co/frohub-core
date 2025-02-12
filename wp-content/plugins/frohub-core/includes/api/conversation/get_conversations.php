<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetConversations {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/get-conversations', [
            'methods'             => 'POST',
            'callback'            => array($this, 'get_filtered_conversations'),
            'permission_callback' => function () {
                return current_user_can('manage_options'); // Adjust permission as needed
            },
            'args'                => [
                'partner_id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }

    /**
     * Fetches filtered conversations based on the partner ID.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_filtered_conversations(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');

        // Define the query arguments with meta_query to filter by partner ACF field
        $args = [
            'post_type'      => 'conversation',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'partner',
                    'value'   => $partner_id,
                    'compare' => '='
                ],
            ],
        ];

        $query = new \WP_Query($args);
        $conversations = [];

        // Format the response
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                // Ensure the ACF field 'partner' is properly retrieved
                $partner = get_field('partner');
                $partner_id_value = is_object($partner) && isset($partner->ID) ? $partner->ID : null;

                $conversations[] = [
                    'id'      => get_the_ID(),
                    'title'   => get_the_title(),
                    'partner' => $partner_id_value,
                    'date'    => get_the_date(),
                ];
            }
            wp_reset_postdata();
        }

        return rest_ensure_response($conversations);
    }
}