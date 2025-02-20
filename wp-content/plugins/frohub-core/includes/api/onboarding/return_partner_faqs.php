<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReturnPartnerFaqs {

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
            'callback'            => array($this, 'get_partner_faqs'),
            'permission_callback' => '__return_true', // Modify if authentication is needed
        ));
    }

    /**
     * Retrieves FAQs for a specific partner.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_partner_faqs(\WP_REST_Request $request) {
        $partner_id = $request['partner_id'];

        // Validate if the partner exists
        if (get_post_type($partner_id) !== 'partner') {
            return new \WP_Error('invalid_partner', 'Invalid partner ID', ['status' => 404]);
        }

        // Fetch the ACF repeater field 'faqs'
        $faqs = get_field('faqs', $partner_id);

        if (empty($faqs)) {
            return new \WP_Error('no_faqs', 'No FAQs found for this partner', ['status' => 404]);
        }

        $faq_data = [];
        foreach ($faqs as $faq) {
            $faq_post_id = $faq['faq_post_id'];
            $faq_title = get_the_title($faq_post_id);
            $faq_content = get_post_field('post_content', $faq_post_id);

            $faq_data[] = [
                'faq_post_id'   => $faq_post_id,
                'question'      => $faq_title,
                'answer'        => wpautop($faq_content), // Converts line breaks to paragraphs
            ];
        }

        return rest_ensure_response([
            'partner_id'   => $partner_id,
            'partner_name' => get_the_title($partner_id),
            'faqs'         => $faq_data
        ]);
    }
}
