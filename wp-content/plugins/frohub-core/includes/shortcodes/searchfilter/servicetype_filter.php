<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ServicetypeFilter {

    public static function init() {
        $self = new self();
        add_shortcode( 'servicetype_filter', array($self, 'servicetype_filter_shortcode') );
    }

    public function servicetype_filter_shortcode() {
        $unique_key = 'servicetype_filter' . uniqid();
        ob_start();
        ?>        
        <!-- Dropdown Field -->
        <select id="dropdown_field" name="dropdown_field">
            <option value="">Service Type</option>
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
                            naOption.style.display = "block";

                        } 
                        else if(radiusField.value == "")
                        {
                            radiusField.value = "5"; // Set to N/A

                        }
                        else {
                            radiusField.value = "5"; // Set to N/A
                            radiusField.disabled = false; // Enable selection
                            naOption.disabled = true; // Make N/A unselectable
                            radiusField.classList.remove("field_disabled"); // Add disabled class
                            naOption.style.display = "none"; 
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
        return ob_get_clean();    }
}
