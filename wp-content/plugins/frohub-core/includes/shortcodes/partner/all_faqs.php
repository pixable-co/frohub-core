<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AllFaqs {

    public static function init() {
        $self = new self();
        add_shortcode( 'all_faqs', array($self, 'all_faqs_shortcode') );
    }

    public function all_faqs_shortcode() {
        ob_start();

        $accordion = '[vc_tta_accordion c_icon="plus"]';

        $faqs = get_field('faqs');

        if ($faqs) {
            foreach ($faqs as $faq_row) {
                $faq_post_id = $faq_row['faq_post_id'];
                $faq_post = get_post($faq_post_id);

                if ($faq_post && $faq_post->post_type === 'faq') {
                    $faqTitle = get_the_title($faq_post);
                    $faqBody = apply_filters('the_content', $faq_post->post_content);

                    $accordion .= '[vc_tta_section title="' . esc_attr($faqTitle) . '" tab_link="%7B%22url%22%3A%22%23%22%7D"]';
                    $accordion .= '[vc_column_text]' . $faqBody . '[/vc_column_text]';
                    $accordion .= '[/vc_tta_section]';
                } else {
                    echo '<p>No FAQ found for Post ID: ' . esc_html($faq_post_id) . '</p>';
                }
            }

            $accordion .= '[/vc_tta_accordion]';
            echo do_shortcode($accordion);
        } else {
            echo '<p>No FAQs found.</p>';
        }

        return ob_get_clean();
    }
}
