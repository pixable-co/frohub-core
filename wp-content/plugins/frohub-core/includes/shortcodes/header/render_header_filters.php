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
    function getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param) || "";
    }

    // Auto-populate fields safely
    let simpleText = document.getElementById("simple_text");
    if (simpleText) simpleText.value = getQueryParam("text");

    let dropdown = document.getElementById("dropdown_field");
    if (dropdown) dropdown.value = getQueryParam("dropdown");

    let category = document.getElementById("category_autocomplete");
    if (category) category.value = getQueryParam("category");

    let startDate = document.getElementById("start_date");
    let endDate = document.getElementById("end_date");
    let dateRange = document.getElementById("date_range");

    if (startDate && endDate) {
        startDate.value = getQueryParam("start_date") || startDate.value;
        endDate.value = getQueryParam("end_date") || endDate.value;

        if (dateRange) {
            if(dateRange.value === "")
            {
            }
            else
            {
            dateRange.value = `${startDate.value} to ${endDate.value}`;

            }
        }
    }

    let radius = document.getElementById("radius_selection");
    if (radius) radius.value = getQueryParam("radius");

    let locationInput = document.getElementById("location_selector");
    if (locationInput) {
        locationInput.value = getQueryParam("location_name");
        locationInput.setAttribute("data-lat", getQueryParam("lat"));
        locationInput.setAttribute("data-lng", getQueryParam("lng"));
    }

    // Search Button Click Event
    document.getElementById("search_button").addEventListener("click", function () {
        let baseUrl = "/book-black-afro-hair-stylist-beauty-appointments";
        let params = new URLSearchParams();

        if (simpleText && simpleText.value.trim()) params.append("text", simpleText.value.trim());
        if (dropdown && dropdown.value) params.append("dropdown", dropdown.value);
        if (category && category.value) params.append("category", category.value);
        if (startDate && startDate.value) params.append("start_date", startDate.value);
        if (endDate && endDate.value) params.append("end_date", endDate.value);
        if (radius && radius.value) params.append("radius", radius.value);

        if (locationInput) {
            let locationName = locationInput.value.trim();
            let lat = locationInput.getAttribute("data-lat");
            let lng = locationInput.getAttribute("data-lng");

            if (locationName) params.append("location_name", locationName);
            if (lat) params.append("lat", lat);
            if (lng) params.append("lng", lng);
        }

        console.log("Final Redirect URL:", baseUrl + "?" + params.toString());

        // Redirect to search results page with parameters
        window.location.href = baseUrl + "?" + params.toString();
    });
});
</script>
        <?php
        return ob_get_clean();
    }
}