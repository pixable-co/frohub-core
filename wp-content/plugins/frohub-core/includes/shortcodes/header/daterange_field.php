<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DaterangeField {

    public static function init() {
        $self = new self();
        add_shortcode( 'daterange_field', array($self, 'daterange_field_shortcode') );
    }

    public function daterange_field_shortcode() {
        $unique_key = 'daterange_field' . uniqid();
        
        ob_start();
        ?>
        <div class="daterange_field" data-key="<?php echo esc_attr($unique_key); ?>"></div>

        <!-- Date Range Picker -->
        <div class="custom-date-range">
            <input type="text" id="date_range" placeholder="Select Date Range" />
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
                dateFormat: "Y-m-d",
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        // Store values in hidden fields in "Y-m-d" format
                        document.getElementById("start_date").value = selectedDates[0].toISOString().split('T')[0];
                        document.getElementById("end_date").value = selectedDates[1].toISOString().split('T')[0];

                        // Format the displayed value
                        const options = { year: 'numeric', month: 'long', day: 'numeric' };
                        let formattedStartDate = selectedDates[0].toLocaleDateString('en-US', options);
                        let formattedEndDate = selectedDates[1].toLocaleDateString('en-US', options);
                        
                        document.getElementById("date_range").value = formattedStartDate + " - " + formattedEndDate;
                    }
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
