<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CategoryAutocompleteFilter {

    public static function init() {
        $self = new self();
        add_shortcode( 'category_autocomplete_filter', array($self, 'category_autocomplete_filter_shortcode') );
    }

    public function category_autocomplete_filter_shortcode() {
        $unique_key = 'category_autocomplete_filter' . uniqid();
        ob_start();
        ?>
    
        <div class="autocomplete-wrapper">
            <input type="text" id="category_autocomplete" name="category_autocomplete" placeholder="Search Treatments" />
            <span class="spinner"></span>
        </div>

        <!-- Include jQuery UI for Autocomplete -->
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

        <script>
        jQuery(document).ready(function($) {
            console.log("Autocomplete script loaded"); // Debugging

            $("#category_autocomplete").autocomplete({
                source: function(request, response) {
                    console.log("Autocomplete triggered with:", request.term); // Log input value

                    $(".spinner").show(); // Show spinner when request starts

                    $.ajax({
                        url: "<?php echo admin_url('admin-ajax.php'); ?>",
                        type: "POST",
                        dataType: "json",
                        data: {
                            action: "get_category_terms",
                            term: request.term
                        },
                        success: function(data) {
                            console.log("AJAX success - Received Data:", data); // Debugging
                            response(data);
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error("AJAX error:", textStatus, errorThrown);
                        },
                        complete: function() {
                            $(".spinner").hide(); // Hide spinner when request completes
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    console.log("Category selected:", ui.item.value);
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
        }
}

add_action('wp_ajax_get_category_terms', function () {
    $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
    $categories = get_terms([
        'taxonomy' => 'category',
        'name__like' => $term,
        'hide_empty' => false,
    ]);

    $results = [];
    foreach ($categories as $category) {
        $results[] = ['label' => $category->name, 'value' => $category->term_id];
    }

    wp_send_json($results);
});

add_action('wp_ajax_nopriv_get_category_terms', 'wp_ajax_get_category_terms');
