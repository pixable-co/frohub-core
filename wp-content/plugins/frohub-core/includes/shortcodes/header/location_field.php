<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LocationField {

    public static function init() {
        $self = new self();
        add_shortcode( 'location_field', array($self, 'location_field_shortcode') );
    }

    public function location_field_shortcode() {
        $unique_key = 'location_field' . uniqid();
        
        ob_start();
        ?>

        <div class="location-input-wrapper">
            <input type="text" id="location_selector" name="location_selector" placeholder="Enter location" 
                data-location="" data-lat="" data-lng="" />
        </div>

        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyD-QttD8cnf68wH9ggZwDKnNZnY6w90Mbc&libraries=places"></script>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                function initAutocomplete() {
                    var input = document.getElementById("location_selector");

                    if (!input) {
                        console.error("Location input field not found!");
                        return;
                    }

                    var autocomplete = new google.maps.places.Autocomplete(input, {
                        types: ['geocode'], // Restrict to addresses
                        componentRestrictions: { country: "UK" } // Change "UK" to your target country or remove for global
                    });

                    autocomplete.addListener("place_changed", function () {
                        var place = autocomplete.getPlace();

                        if (!place.geometry) {
                            console.warn("No details available for input:", place);
                            return;
                        }

                        var latitude = place.geometry.location.lat().toFixed(6);
                        var longitude = place.geometry.location.lng().toFixed(6);
                        var formattedAddress = place.formatted_address;

                        // Set data attributes on the input field
                        input.setAttribute("data-location", formattedAddress);
                        input.setAttribute("data-lat", latitude);
                        input.setAttribute("data-lng", longitude);

                        console.log("Selected Location:", formattedAddress);
                        console.log("Latitude:", latitude, "Longitude:", longitude);
                    });
                }

                // Initialize Google Places API
                initAutocomplete();
            });
        </script>
        <?php
        return ob_get_clean();
    }
}
