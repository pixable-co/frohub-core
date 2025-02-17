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

        // Apply category filter if present
        if (!empty($category)) {
            $product_query_args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'name',
                'terms'    => $category,
            );
        }

        // Apply service type filter (Dropdown)
        if (!empty($dropdown)) {
            $product_query_args['meta_query'][] = array(
                'key'     => 'service_types',
                'value'   => $dropdown,
                'compare' => 'LIKE',
            );
        }

        // Get products
        $product_query = new \WP_Query($product_query_args);
        $product_ids = $product_query->posts;

        // **Filter Products Based on Availability Days**
        if (!empty($selected_days)) {
            $filtered_ids = [];
            foreach ($product_ids as $product_id) {
                $availability = get_field('availability', $product_id);
                if (!empty($availability) && is_array($availability)) {
                    foreach ($availability as $entry) {
                        if (in_array($entry['day'], $selected_days)) {
                            $filtered_ids[] = $product_id;
                            break;
                        }
                    }
                }
            }
            $product_ids = $filtered_ids;
        }

        // **Home-based & Salon-based Filtering using Partner Post**
        if (!empty($radius) && !empty($lat) && !empty($lng) && in_array($dropdown, ["home-based", "salon-based"])) {
            $product_ids = $this->filter_by_radius($product_ids, $lat, $lng, $radius);
        }

        // **Mobile Service Filtering using Partner Max Radius**
        if (!empty($lat) && !empty($lng) && $dropdown === "mobile") {
            $product_ids = $this->filter_by_max_partner_radius($product_ids, $lat, $lng);
        }

        // Convert array of IDs into a comma-separated string
        $idList = implode(",", $product_ids);

        // Define shortcode parameters
        return '<div class="render_services_grid" data-key="' . esc_attr($unique_key) . '" data-ids="' . esc_attr($idList) . '"></div>';
    }

    private function filter_by_radius($product_ids, $lat, $lng, $radius) {
        $filtered_ids = [];
        foreach ($product_ids as $product_id) {
            $partner_id = get_field('partner_id', $product_id);
            if (!$partner_id) continue;
            $partner_lat = get_field('latitude', $partner_id);
            $partner_lng = get_field('longitude', $partner_id);
            if (!$partner_lat || !$partner_lng) continue;
            $distance = $this->haversine_distance($lat, $lng, $partner_lat, $partner_lng);
            if ($distance <= $radius) {
                $filtered_ids[] = $product_id;
            }
        }
        return $filtered_ids;
    }

    private function filter_by_max_partner_radius($product_ids, $lat, $lng) {
        $filtered_ids = [];
        foreach ($product_ids as $product_id) {
            $partner_id = get_field('partner_id', $product_id);
            if (!$partner_id) continue;
            $partner_lat = get_field('latitude', $partner_id);
            $partner_lng = get_field('longitude', $partner_id);
            if (!$partner_lat || !$partner_lng) continue;
            $max_radius = $this->get_max_partner_radius($partner_id);
            $distance = $this->haversine_distance($lat, $lng, $partner_lat, $partner_lng);
            if ($distance <= $max_radius) {
                $filtered_ids[] = $product_id;
            }
        }
        return $filtered_ids;
    }

    private function get_max_partner_radius($partner_id) {
        $max_radius = 0;
        $radius_fees = get_field('radius_fees', $partner_id);
        if ($radius_fees && is_array($radius_fees)) {
            foreach ($radius_fees as $radius_entry) {
                $radius = isset($radius_entry['radius']) ? intval($radius_entry['radius']) : 0;
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
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth_radius * $c * 0.621371;
    }
}
?>
