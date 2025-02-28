<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class Payouts {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/payouts', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_payout_posts_with_acf_filter'],
            'permission_callback' => '__return_true', // Public access, modify as needed
        ]);
    }

    /**
     * Handles fetching payout posts with ACF fields and filtering by partner_id.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_payout_posts_with_acf_filter(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');

        if (!$partner_id) {
            return new \WP_Error('missing_param', 'partner_id is required', ['status' => 400]);
        }

        $args = [
            'post_type'      => 'payout', // Modify if needed
            'posts_per_page' => -1,       
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'partner_name', // ACF field name
                    'value'   => $partner_id,
                    'compare' => '='
                ]
            ]
        ];

        $posts = get_posts($args);
        $data  = [];

        foreach ($posts as $post) {
            $acf_fields = get_fields($post->ID);

            $data[] = [
                'id'      => $post->ID,
                'title'   => get_the_title($post->ID),
                'content' => apply_filters('the_content', $post->post_content),
                'acf'     => $acf_fields ? $acf_fields : [],
                'date'    => get_the_date('Y-m-d H:i:s', $post->ID)
            ];
        }

        return rest_ensure_response($data);
    }
}


