<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ServicetypeField {

    public static function init() {
        $self = new self();
        add_shortcode( 'servicetype_field', array($self, 'servicetype_field_shortcode') );
    }

    public function servicetype_field_shortcode() {
        $unique_key = 'servicetype_field' . uniqid();
        
        ob_start();
        ?>        
        <!-- Dropdown Field -->
        <select id="dropdown_field" name="dropdown_field">
            <option value="">Select an option</option>
            <option value="home-based">Home Based</option>
            <option value="salon-based">Salon Based</option>
            <option value="mobile">Mobile</option>
        </select>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let dropdownField = document.getElementById("dropdown_field");
                let radiusField = document.getElementById("radius_selection");

                if (dropdownField && radiusField) {
                    dropdownField.addEventListener("change", function () {
                        if (dropdownField.value === "mobile") {
                            radiusField.value = ""; // Reset value
                            radiusField.disabled = true; // Disable selection
                        } else {
                            radiusField.disabled = false; // Enable selection
                        }
                    });

                    // Ensure the correct state on page load
                    if (dropdownField.value === "mobile") {
                        radiusField.value = "";
                        radiusField.disabled = true;
                    }
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }
}