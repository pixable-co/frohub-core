<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RenderHeaderFilters {

    public static function init() {
        $self = new self();
        add_shortcode( 'render_header_filters', array($self, 'render_header_filters_shortcode') );
    }

    public function render_header_filters_shortcode() {
        $unique_key = 'render_header_filters' . uniqid();
        
        ob_start();
        ?>
        
        <div class="custom-filters">
            <?php echo do_shortcode('[autocomplete_field]'); ?>
            <?php echo do_shortcode('[servicetype_field]'); ?>
            <?php echo do_shortcode('[daterange_field]'); ?>
            <?php echo do_shortcode('[location_field]'); ?>
            <?php echo do_shortcode('[radius_field]'); ?>

            <!-- Submit Button -->
            <button id="search_button"><i class="far fa-search"></i></button>
        </div>

        <!-- Include Flatpickr -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let locationInput = document.getElementById("location_selector");

                // Search Button Click Event
                document.getElementById("search_button").addEventListener("click", function () {
                    let baseUrl = "/book-black-afro-hair-stylist-beauty-appointments"; // Change this to your actual search results page
                    let params = new URLSearchParams();

                    // Get values from input fields and dropdowns
                    let simpleText = document.getElementById("simple_text")?.value.trim();
                    let dropdown = document.getElementById("dropdown_field")?.value;
                    let category = document.getElementById("category_autocomplete")?.value;
                    let startDate = document.getElementById("start_date")?.value;
                    let endDate = document.getElementById("end_date")?.value;
                    let radius = document.getElementById("radius_selection")?.value;

                    // Add non-empty values to the URL parameters
                    if (simpleText) params.append("text", simpleText);
                    if (dropdown) params.append("dropdown", dropdown);
                    if (category) params.append("category", category);
                    if (startDate) params.append("start_date", startDate);
                    if (endDate) params.append("end_date", endDate);
                    if (radius) params.append("radius", radius);

                    // Retrieve lat & lng from the input field's data attributes (set by external script)
                    let locationName = locationInput?.value.trim();
                    let lat = locationInput?.getAttribute("data-lat");
                    let lng = locationInput?.getAttribute("data-lng");

                    // Add location details to URL
                    if (locationName) params.append("location_name", locationName);
                    if (lat) params.append("lat", lat);
                    if (lng) params.append("lng", lng);

                    console.log("Final Redirect URL:", baseUrl + "?" + params.toString()); // Debugging

                    // Redirect to search results page with parameters
                    window.location.href = baseUrl + "?" + params.toString();
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }
}