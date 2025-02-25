<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DaterangeFilter {

    public static function init() {
        $self = new self();
        add_shortcode( 'daterange_filter', array($self, 'daterange_filter_shortcode') );
    }

    public function daterange_filter_shortcode() {
        ob_start();
        ?>

        <!-- Date Range Picker -->
        <div class="custom-date-range">
            <input type="text" id="date_range" placeholder="Select Date(s)" />
            <input type="hidden" id="start_date" name="start_date">
            <input type="hidden" id="end_date" name="end_date">
        </div>

        <!-- Include Flatpickr -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            flatpickr("#date_range", {
                mode: "range",
                dateFormat: "d-m-Y",
                locale: {
                    firstDayOfWeek: 1 // Start week on Monday
                },
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        // Store values in hidden fields in "Y-m-d" format
                        document.getElementById("start_date").value = selectedDates[0].toISOString().split('T')[0];
                        document.getElementById("end_date").value = selectedDates[1].toISOString().split('T')[0];

                        // Format the displayed value as "27 Feb - 29 Feb"
                        const options = { day: 'numeric', month: 'short' };
                        let formattedStartDate = selectedDates[0].toLocaleDateString('en-GB', options);
                        let formattedEndDate = selectedDates[1].toLocaleDateString('en-GB', options);
                        
                        document.getElementById("date_range").value = formattedStartDate + " - " + formattedEndDate;
                    }
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();    }
}
