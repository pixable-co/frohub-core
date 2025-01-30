<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubGetFaqs {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_get_faqs', array($self, 'frohub_get_faqs_shortcode') );
    }

    public function frohub_get_faqs_shortcode() {
        $unique_key = 'frohub_get_faqs' . uniqid();
        $accordion = '[vc_tta_accordion c_icon="plus"]'; //Start of Accordion shortcode

        // Populate the Accordion Tabs shortcode with Title and Text
        $faqs = get_field('faqs'); // Get repeater field
        foreach ($faqs as $faq_row)
        {
            // Get the FAQ Post ID associated to the partner
            $faq_post_id = $faq_row['faq_post_id'];

            // Get the title and body of the FAQ
            $faqTitle = get_the_title($faq_post_id);
            $faqBody = get_the_content($faq_post_id);

            // Put it in the shortcode
            // We use ".=" to concatenate strings.
            $accordion .= '[vc_tta_section title="'.$faqTitle.'" tab_link="%7B%22url%22%3A%22%23%22%7D"]'; // Start of Section
            
            $accordion .= '[vc_column_text]'.$faqBody.'[/vc_column_text]'; // Text shortcode
            
            $accordion .= '[/vc_tta_section]';  // End of section
        }
        $accordion .= '[/vc_tta_accordion]'; // End of Accordion shortcode

        // echo do_shortcode($accordion);
        return $accordion;
        // return '<div class="frohub_get_faqs" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
