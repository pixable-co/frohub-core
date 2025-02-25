<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RadiusField {

    public static function init() {
        $self = new self();
        add_shortcode( 'radius_field', array($self, 'radius_field_shortcode') );
    }

    public function radius_field_shortcode() {
        ob_start();
        ?>
        
        <!-- Radius Selection -->
        <select id="radius_selection" name="radius_selection">
            <option value="na">N/A</option> <!-- Fixed value to make it selectable -->
            <option value="">Select radius</option>
            <option value="5">5 miles</option>
            <option value="10">10 miles</option>
            <option value="25">25 miles</option>
            <option value="50">50 miles</option>
        </select>
        <?php
        return ob_get_clean();
    }
}
