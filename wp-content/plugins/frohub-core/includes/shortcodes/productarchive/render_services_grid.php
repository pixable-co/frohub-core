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
        ob_start();

        // Fetch URL parameters and sanitize them
        $dropdown   = isset($_GET['dropdown']) ? strtolower(sanitize_text_field($_GET['dropdown'])) : '';
        $category   = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $radius     = isset($_GET['radius']) ? intval($_GET['radius']) : '';
        $lat        = isset($_GET['lat']) ? floatval($_GET['lat']) : '';
        $lng        = isset($_GET['lng']) ? floatval($_GET['lng']) : '';

        // Convert start_date & end_date to PHP DateTime objects
        $start_date_obj = !empty($start_date) ? new \DateTime($start_date) : null;
        $end_date_obj   = !empty($end_date) ? new \DateTime($end_date) : null;

        // Generate all weekdays between the selected date range
        $selected_days = [];
        if ($start_date_obj && $end_date_obj) {
            $interval    = new \DateInterval('P1D');
            $date_range  = new \DatePeriod($start_date_obj, $interval, $end_date_obj->modify('+1 day'));

            foreach ($date_range as $date) {
                $selected_days[] = $date->format('l');
            }
        }

        // Base product query
        $product_query_args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(),
            'meta_query'     => array(),
        );

        // Filter by category
        if (!empty($category)) {
            $product_query_args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'name',
                'terms'    => $category,
            );
        }

        // Fetch base products
        $product_query = new \WP_Query($product_query_args);
        $product_ids   = $product_query->posts;

        // Filter by service-type via variation attributes
        if (!empty($dropdown)) {
            $filtered_products = [];

            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);

                if ($product && $product->is_type('variable')) {
                    foreach ($product->get_children() as $variation_id) {
                        $variation  = wc_get_product($variation_id);
                        $attributes = $variation->get_attributes();

                        if (isset($attributes['pa_service-type']) && strtolower($attributes['pa_service-type']) === $dropdown) {
                            if ($variation->is_purchasable()) {
                                $filtered_products[] = $product_id;
                                break;
                            }
                        }
                    }
                }
            }

            $product_ids = $filtered_products;
        }

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

        // Filter by location radius
        if (!empty($radius) && !empty($lat) && !empty($lng) && in_array($dropdown, ['home-based', 'salon-based'])) {
            $product_ids = $this->filterByRadius($product_ids, $lat, $lng, $radius);
        }

        // Filter for mobile services (partner-defined radius)
        if (!empty($lat) && !empty($lng) && $dropdown === 'mobile') {
            $product_ids = $this->filterByPartnerRadius($product_ids, $lat, $lng);
        }

        // Remove invalid or missing products
        $product_ids = array_filter($product_ids, function($id) {
            return wc_get_product($id) !== false;
        });

        // Now paginate the filtered list
        $paged         = max(1, get_query_var('paged') ?: get_query_var('page'));
        $per_page      = 20;
        $offset        = ($paged - 1) * $per_page;
        $total_products = count($product_ids);
        $paged_ids     = array_slice($product_ids, $offset, $per_page);

        // Convert to comma-separated list for us_grid
        $idList = implode(",", $paged_ids);

        // Output the grid
        $grid_shortcode = '[us_grid post_type="ids" ids="' . esc_attr($idList) . '" items_layout="28802" columns="4" items_quantity="20"]';
        echo do_shortcode($grid_shortcode);

        // Add pagination links
        $total_pages = ceil($total_products / $per_page);
        echo paginate_links(array(
            'total'   => $total_pages,
            'current' => $paged,
            'base'    => add_query_arg('paged', '%#%'),
            'format'  => '',
        ));

        return ob_get_clean();
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
            $max_radius  = $this->get_max_partner_radius($partner_id);

            if ($partner_lat && $partner_lng) {
                $distance = $this->haversine_distance($lat, $lng, $partner_lat, $partner_lng);
                if ($distance <= $max_radius) {
                    $filtered_ids[] = $product_id;
                }
            }
        }

        return $filtered_ids;
    }

    private function get_max_partner_radius($partner_id) {
        $max_radius   = 0;
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
        $lat_delta    = deg2rad($lat2 - $lat1);
        $lon_delta    = deg2rad($lon2 - $lon1);

        $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lon_delta / 2) * sin($lon_delta / 2);

        return (2 * atan2(sqrt($a), sqrt(1 - $a))) * $earth_radius * 0.621371; // miles
    }
}
