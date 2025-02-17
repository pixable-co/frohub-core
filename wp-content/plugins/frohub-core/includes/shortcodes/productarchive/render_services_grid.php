<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RenderServicesGrid {

    public static function init() {
        $self = new self();
        add_shortcode( 'render_services_grid', array($self, 'render_services_grid_shortcode') );
    }

    public function render_services_grid_shortcode() {
        $unique_key = 'render_services_grid' . uniqid();

        // Fetch URL parameters and sanitize them
        $dropdown = isset($_GET['dropdown']) ? strtolower(sanitize_text_field($_GET['dropdown'])) : '';
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $radius = isset($_GET['radius']) ? intval($_GET['radius']) : '';
        $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : '';
        $lng = isset($_GET['lng']) ? floatval($_GET['lng']) : '';

        // Convert start_date & end_date to PHP DateTime objects
        $start_date_obj = !empty($start_date) ? new \DateTime($start_date) : null;
        $end_date_obj = !empty($end_date) ? new \DateTime($end_date) : null;

        // Generate all weekdays between the selected date range
        $selected_days = [];
        if ($start_date_obj && $end_date_obj) {
            $interval = new \DateInterval('P1D');
            $date_range = new \DatePeriod($start_date_obj, $interval, $end_date_obj->modify('+1 day'));

            foreach ($date_range as $date) {
                $selected_days[] = $date->format('l');
            }
        }

        // Define base query parameters
        $product_query_args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(),
            'meta_query'     => array(),
        );

        // Apply category filter
        if (!empty($category)) {
            $product_query_args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'name',
                'terms'    => $category,
            );
        }

        // Apply service type filter
        if (!empty($dropdown)) {
            $product_query_args['meta_query'][] = array(
                'key'     => 'service_types',
                'value'   => $dropdown,
                'compare' => 'LIKE',
            );
        }

        // Fetch products
        $product_query = new \WP_Query($product_query_args);
        $product_ids = $product_query->posts;

        // Filter by availability days
        if (!empty($selected_days)) {
            $filtered_ids = [];

            foreach ($product_ids as $product_id) {
                $availability = get_field('availability', $product_id);

                if (!empty($availability) && is_array($availability)) {
                    foreach ($availability as $entry) {
                        if (in_array($entry['day'] ?? '', $selected_days)) {
                            $filtered_ids[] = $product_id;
                            break;
                        }
                    }
                }
            }

            $product_ids = $filtered_ids;
        }

        // Filtering based on radius and location
        if (!empty($radius) && !empty($lat) && !empty($lng) && in_array($dropdown, ["home-based", "salon-based"])) {
            $product_ids = $this->filterByRadius($product_ids, $lat, $lng, $radius);
        }

        // Mobile service filtering
        if (!empty($lat) && !empty($lng) && $dropdown === "mobile") {
            $product_ids = $this->filterByPartnerRadius($product_ids, $lat, $lng);
        }

        // Convert product IDs into a usable format
        $idList = implode(",", $product_ids);

        // Define grid shortcode
        $grid_shortcode = '[us_grid post_type="ids" ids="' . esc_attr($idList) . '" items_quantity="0" items_layout="197" pagination="regular" columns="4"]';

        // Output filtered parameters and grid
        echo do_shortcode($grid_shortcode);

        echo "This is a test";
    }

    private function filterByRadius($product_ids, $lat, $lng, $radius) {
        $filtered_ids = [];

        foreach ($product_ids as $product_id) {
            $partner_id = get_field('partner_id', $product_id);
            if (!$partner_id) continue;

            $partner_lat = floatval(get_field('latitude', $partner_id));
            $partner_lng = floatval(get_field('longitude', $partner_id));

            if ($partner_lat && $partner_lng) {
                $distance = $this->haversine_distance($lat, $lng, $partner_lat, $partner_lng);
                if ($distance <= $radius) {
                    $filtered_ids[] = $product_id;
                }
            }
        }

        return $filtered_ids;
    }

    private function filterByPartnerRadius($product_ids, $lat, $lng) {
        $filtered_ids = [];

        foreach ($product_ids as $product_id) {
            $partner_id = get_field('partner_id', $product_id);
            if (!$partner_id) continue;

            $partner_lat = floatval(get_field('latitude', $partner_id));
            $partner_lng = floatval(get_field('longitude', $partner_id));
            $max_partner_radius = $this->get_max_partner_radius($partner_id);

            if ($partner_lat && $partner_lng) {
                $distance = $this->haversine_distance($lat, $lng, $partner_lat, $partner_lng);
                if ($distance <= $max_partner_radius) {
                    $filtered_ids[] = $product_id;
                }
            }
        }

        return $filtered_ids;
    }

    private function get_max_partner_radius($partner_id) {
        $max_radius = 0;
        $radius_fees = get_field('radius_fees', $partner_id);

        if ($radius_fees && is_array($radius_fees)) {
            foreach ($radius_fees as $entry) {
                $radius = intval($entry['radius'] ?? 0);
                if ($radius > $max_radius) {
                    $max_radius = $radius;
                }
            }
        }

        return $max_radius;
    }

    private function haversine_distance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371;
        $lat_delta = deg2rad($lat2 - $lat1);
        $lon_delta = deg2rad($lon2 - $lon1);

        $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lon_delta / 2) * sin($lon_delta / 2);

        return (2 * atan2(sqrt($a), sqrt(1 - $a))) * $earth_radius * 0.621371;
    }
}