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
                        let naOption = radiusField.querySelector('option[value="na"]');

                        if (dropdownField.value === "mobile") {
                            radiusField.value = "na"; // Set to N/A
                            radiusField.disabled = true; // Disable selection
                            radiusField.classList.add("field_disabled"); // Add disabled class
                        } else {
                            radiusField.disabled = false; // Enable selection
                            naOption.disabled = true; // Make N/A unselectable
                        }
                    });

                    // Ensure the correct state on page load
                    let naOption = radiusField.querySelector('option[value="na"]');
                    if (dropdownField.value === "mobile") {
                        radiusField.value = "na";
                        radiusField.disabled = true;
                    } else {
                        naOption.disabled = true; // Make N/A unselectable when not Mobile
                    }
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }
}
