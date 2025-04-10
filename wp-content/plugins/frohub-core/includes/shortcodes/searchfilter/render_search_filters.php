<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RenderSearchFilters {

    public static function init() {
        $self = new self();
        add_shortcode( 'render_search_filters', array($self, 'render_search_filters_shortcode') );
    }

    public function render_search_filters_shortcode() {
 
        ob_start();
        ?>
        
        <div class="custom-filters">
            <?php echo do_shortcode('[category_autocomplete_filter]'); ?>
            <?php echo do_shortcode('[servicetype_filter]'); ?>
            <?php echo do_shortcode('[daterange_filter]'); ?>
            <?php echo do_shortcode('[location_filter]'); ?>
            <?php echo do_shortcode('[radius_filter]'); ?>

            <!-- Submit Button -->
            <button id="search_button" class="w-btn us-btn-style_1"><i class="far fa-search"></i></button>
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
    document.getElementById("search_button").addEventListener("click", function (e) {
        e.preventDefault(); // Prevent default form submission

        let params = {
            action: "filter_search_results",
            text: simpleText ? simpleText.value.trim() : "",
            dropdown: dropdown ? dropdown.value : "",
            category: category ? category.value : "",
            start_date: startDate ? startDate.value : "",
            end_date: endDate ? endDate.value : "",
            radius: radius ? radius.value : "",
            location_name: locationInput ? locationInput.value.trim() : "",
            lat: locationInput ? locationInput.getAttribute("data-lat") : "",
            lng: locationInput ? locationInput.getAttribute("data-lng") : ""
        };

        console.log("Sending AJAX request with params:", params);

        // Send AJAX request
        fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams(params)
        })
        .then(response => response.text())
        .then(data => {
            console.log("AJAX response:", data);
            document.querySelector(".results-grid").innerHTML = data; // Update grid content
        })
        .catch(error => console.error("AJAX error:", error));
    });
});
</script>
        <?php
        return ob_get_clean();
    }
}
