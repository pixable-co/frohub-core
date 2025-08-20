<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateStartEndDate {

    public static function init() {
        $self = new self();

        // Display the datetime fields in order admin
        add_action( 'woocommerce_admin_order_data_after_order_details', array($self, 'display_datetime_fields') );

        // Save the datetime fields
        add_action( 'woocommerce_process_shop_order_meta', array($self, 'save_datetime_fields'), 20, 2 );

        // Add CSS styling
        add_action( 'admin_head', array($self, 'add_admin_styles') );

        // Add JavaScript and SweetAlert2
        add_action( 'admin_footer', array($self, 'add_admin_scripts') );
    }

    public function display_datetime_fields( $order ) {
        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = $item->get_product_id();
            if ( $product_id == 28990 ) continue; // skip service fee

            $start_raw = wc_get_order_item_meta( $item_id, 'Start Date Time', true );
            $end_raw   = wc_get_order_item_meta( $item_id, 'End Date Time', true );

            // Debug: Let's see what format we're actually getting
            error_log("Start Date Raw: " . $start_raw);
            error_log("End Date Raw: " . $end_raw);

            // Convert stored format "14:30, 25 Aug 2025" → "2025-08-25T14:30"
            $start_val = '';
            if ( $start_raw ) {
                // Try the expected format first
                $dt = \DateTime::createFromFormat('H:i, j M Y', $start_raw);
                if ( !$dt ) {
                    // Try alternative formats that might be used
                    $dt = \DateTime::createFromFormat('H:i, d M Y', $start_raw);
                }
                if ( !$dt ) {
                    $dt = \DateTime::createFromFormat('G:i, j M Y', $start_raw);
                }
                if ( $dt ) {
                    $start_val = $dt->format('Y-m-d\TH:i');
                } else {
                    error_log("Failed to parse start date: " . $start_raw);
                }
            }

            $end_val = '';
            if ( $end_raw ) {
                // Try the expected format first
                $dt = \DateTime::createFromFormat('H:i, j M Y', $end_raw);
                if ( !$dt ) {
                    // Try alternative formats
                    $dt = \DateTime::createFromFormat('H:i, d M Y', $end_raw);
                }
                if ( !$dt ) {
                    $dt = \DateTime::createFromFormat('G:i, j M Y', $end_raw);
                }
                if ( $dt ) {
                    $end_val = $dt->format('Y-m-d\TH:i');
                } else {
                    error_log("Failed to parse end date: " . $end_raw);
                }
            }

            // Skip products that don't have booking dates (like screenshots, etc.)
            if ( !$start_raw && !$end_raw ) {
                continue;
            }

            echo '<div class="order-start-end-dates">';
            echo '<div class="form-field form-field-wide">';
            echo '<label for="start_date_time_' . $item_id . '">Start Date & Time:</label>';
            echo '<input type="datetime-local" id="start_date_time_' . $item_id . '" name="start_date_time[' . $item_id . ']" value="' . esc_attr($start_val) . '" />';
            echo '</div>';

            echo '<div class="form-field form-field-wide">';
            echo '<label for="end_date_time_' . $item_id . '">End Date & Time:</label>';
            echo '<input type="datetime-local" id="end_date_time_' . $item_id . '" name="end_date_time[' . $item_id . ']" value="' . esc_attr($end_val) . '" />';
            echo '</div>';

            echo '</div>';
        }
    }

    public function save_datetime_fields( $order_id, $post = null ) {
        // Check if this is an autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Verify we have the right data
        if ( ! isset( $_POST['start_date_time'] ) && ! isset( $_POST['end_date_time'] ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $updated = false;

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = $item->get_product_id();
            if ( $product_id == 28990 ) continue; // skip service fee

            // Handle Start Date Time
            if ( isset($_POST['start_date_time'][$item_id]) && !empty($_POST['start_date_time'][$item_id]) ) {
                $input_datetime = sanitize_text_field($_POST['start_date_time'][$item_id]);
                $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $input_datetime);
                if ( $dt ) {
                    $formatted_date = $dt->format('H:i, j M Y');
                    wc_update_order_item_meta( $item_id, 'Start Date Time', $formatted_date );
                    $updated = true;
                    error_log("Updated Start Date Time for item {$item_id}: {$formatted_date}");
                } else {
                    error_log("Failed to parse start datetime input: {$input_datetime}");
                }
            }

            // Handle End Date Time
            if ( isset($_POST['end_date_time'][$item_id]) && !empty($_POST['end_date_time'][$item_id]) ) {
                $input_datetime = sanitize_text_field($_POST['end_date_time'][$item_id]);
                $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $input_datetime);
                if ( $dt ) {
                    $formatted_date = $dt->format('H:i, j M Y');
                    wc_update_order_item_meta( $item_id, 'End Date Time', $formatted_date );
                    $updated = true;
                    error_log("Updated End Date Time for item {$item_id}: {$formatted_date}");
                } else {
                    error_log("Failed to parse end datetime input: {$input_datetime}");
                }
            }

            // Recalculate duration if both dates were updated
            if ( isset($_POST['start_date_time'][$item_id]) && isset($_POST['end_date_time'][$item_id]) &&
                 !empty($_POST['start_date_time'][$item_id]) && !empty($_POST['end_date_time'][$item_id]) ) {

                $start_dt = \DateTime::createFromFormat('Y-m-d\TH:i', sanitize_text_field($_POST['start_date_time'][$item_id]));
                $end_dt = \DateTime::createFromFormat('Y-m-d\TH:i', sanitize_text_field($_POST['end_date_time'][$item_id]));

                if ( $start_dt && $end_dt ) {
                    $duration_minutes = ($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60;
                    $hours = floor($duration_minutes / 60);
                    $minutes = $duration_minutes % 60;
                    $duration_string = ($hours > 0 ? "{$hours} hrs " : '') . ($minutes > 0 ? "{$minutes} mins" : '');

                    if ( trim($duration_string) ) {
                        wc_update_order_item_meta( $item_id, 'Duration', trim($duration_string) );
                        error_log("Updated Duration for item {$item_id}: {$duration_string}");
                    }
                }
            }
        }

        // Add order note if any updates were made
        if ( $updated ) {
            $order->add_order_note( 'Booking dates updated via admin panel at ' . current_time('mysql') );
        }
    }

    public function add_admin_styles() {
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'shop_order' ) {
            ?>
            <style>
            .order-start-end-dates .form-field {
                margin-bottom: 15px;
            }
            .order-start-end-dates label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
                color: #23282d;
                font-size: 13px;
            }
            .order-start-end-dates input[type="datetime-local"] {
                width: 100% !important;
                max-width: 300px;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 13px;
                background: #fff;
                box-shadow: inset 0 1px 2px rgba(0,0,0,.07);
            }
            .order-start-end-dates input[type="datetime-local"]:focus {
                border-color: #007cba;
                box-shadow: 0 0 0 1px #007cba;
                outline: none;
            }
            </style>
            <?php
        }
    }

    public function add_admin_scripts() {
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'shop_order' ) {
            ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
            jQuery(document).ready(function($) {
                var hasChanges = false;

                // Track changes to date/time fields
                $('input[name^="start_date_time"], input[name^="end_date_time"]').on('change', function() {
                    $(this).css('background-color', '#ffffcc');
                    $(this).css('border-color', '#ff9800');
                    hasChanges = true;
                });

                // Show loading when form is submitted with changes
                $('#post').on('submit', function(e) {
                    if (hasChanges) {
                        Swal.fire({
                            title: 'Updating...',
                            text: 'Saving booking date changes',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Show success message after form submission
                        setTimeout(function() {
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated!',
                                text: 'Booking dates have been updated successfully',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }, 1000);
                    }
                });
            });
            </script>
            <?php
        }
    }
}
?>