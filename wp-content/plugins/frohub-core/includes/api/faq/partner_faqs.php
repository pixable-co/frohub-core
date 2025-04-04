<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PartnerFaqs {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/partner-faqs/(?P<partner_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_faqs_by_partner_id'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Fetches FAQs by Partner ID.
     *
     * Example usage: /wp-json/frohub/v1/partner-faqs?partner_id=123
     */
    public function get_faqs_by_partner_id(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');

        if (!$partner_id) {
            return new \WP_Error('no_partner_id', 'Partner ID is required', array('status' => 400));
        }

        $args = array(
            'post_type'      => 'faq',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'partner_id',
                    'value'   => $partner_id,
                    'compare' => '='
                )
            )
        );

        $query = new \WP_Query($args);
        $faqs = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $faqs[] = array(
                    'id'         => get_the_ID(),
                    'title'      => get_the_title(),
                    'content'    => apply_filters('the_content', get_the_content()),
                    'partner_id' => get_field('partner_id'),
                    'permalink'  => get_permalink(),
                );
            }
        }

        wp_reset_postdata();

        return rest_ensure_response($faqs);
    }
}
