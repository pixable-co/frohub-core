<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PartnerVerifiedBadge {

    public static function init() {
        $self = new self();
        add_shortcode('partner_verified_badge', array($self, 'partner_verified_badge_shortcode'));
    }

    public function partner_verified_badge_shortcode() {
        $values = get_field('frohub_verified');

        if ($values && is_array($values)) {
            foreach ($values as $value) {
                if ($value === 'Yes') {
                    return do_shortcode('[us_text text="FroHub Verified" link="%7B%22url%22%3A%22%22%7D" tag="span" icon="fas|check-circle" css="%7B%22default%22%3A%7B%22background-color%22%3A%22_content_bg_alt%22%2C%22padding-left%22%3A%2210px%22%2C%22padding-top%22%3A%2210px%22%2C%22padding-bottom%22%3A%2210px%22%2C%22padding-right%22%3A%2210px%22%2C%22border-radius%22%3A%225px%22%7D%7D"]');
                }
            }
        }

        return '';
    }
}
