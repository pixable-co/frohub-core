<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreateConversionProcessPopulate {

    public static function init() {
        $self = new self();
        add_action('woocommerce_payment_complete', [$self, 'handle_order'], 10, 1);
        //add_action('woocommerce_thankyou', [$self, 'handle_order'], 10, 1);
    }

    public function handle_order($order_id) {
        $logger = wc_get_logger();
        $context = ['source' => 'create_conversation_populate_acf_fields'];

        $logger->info("Function triggered for Order ID: $order_id", $context);

        if (!$order_id) {
            $logger->error("No Order ID received", $context);
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $logger->error("Order not found: $order_id", $context);
            return;
        }

        $user_id = $order->get_user_id();
        $logger->info("Processing Order ID: $order_id for User ID: $user_id", $context);

        $last_post_id = null;

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $partner_id = get_field('partner_id', $product_id);

            if (!$partner_id) {
                $logger->warning("No partner ID found for product ID: $product_id", $context);
                continue;
            }

            $existing_convo_id = $this->get_existing_conversation($partner_id, $user_id);

            if ($existing_convo_id) {
                $logger->info("Existing conversation found: $existing_convo_id", $context);
                $this->create_conversation_comment($existing_convo_id, $order, $item, $partner_id);
                $last_post_id = $existing_convo_id;
            } else {
                $last_post_id = $this->create_conversation_post($partner_id, $user_id, $order_id);

                if ($last_post_id) {
                    $logger->info("Created new conversation post: $last_post_id", $context);
                    $this->send_conversation_to_endpoint($last_post_id, $partner_id, $user_id, $order_id);
                    $this->create_conversation_comment($last_post_id, $order, $item, $partner_id);
                } else {
                    $logger->error("Failed to create conversation post for Partner ID: $partner_id", $context);
                }
            }

            update_post_meta($order_id, 'partner_id', $partner_id);
            update_post_meta($order_id, 'partner_name', intval($partner_id));
            update_post_meta($order_id, 'conversation', intval($last_post_id));

            $logger->info("Updated order meta for Order ID: $order_id with Partner ID: $partner_id and Conversation ID: $last_post_id", $context);
        }
    }

    private function get_existing_conversation($partner_id, $user_id) {
        $args = [
            'post_type'      => 'conversation',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'partner',
                    'value'   => $partner_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'customer',
                    'value'   => $user_id,
                    'compare' => '=',
                ]
            ]
        ];

        $query = new \WP_Query($args);

        return $query->have_posts() ? $query->posts[0] : false;
    }

    private function create_conversation_post($partner_id, $user_id, $order_id) {
        $partner_name = get_the_title($partner_id);
        $user = get_userdata($user_id);
        $user_full_name = $user ? trim($user->first_name . ' ' . $user->last_name) : 'Unknown User';
        $user_full_name = $user_full_name ?: $user->display_name;

        $post_id = wp_insert_post([
            'post_title'   => $user_full_name . ' x ' . $partner_name,
            'post_type'    => 'conversation',
            'post_status'  => 'publish',
            'post_content' => ''
        ]);

        if ($post_id) {
            update_field('partner', $partner_id, $post_id);
            update_field('customer', $user_id, $post_id);
            update_field('order_id', $order_id, $post_id);
        }

        return $post_id ?: false;
    }

    private function send_conversation_to_endpoint($post_id, $partner_id, $user_id, $order_id) {
        $endpoint_url = FHCORE_PARTNER_BASE_API_URL . '/wp-json/frohub/v1/create-client-post';

        $user_info = get_userdata($user_id);
        $email = $user_info->user_email ?? '';
        $phone = get_user_meta($user_id, 'billing_phone', true);
        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);

        $response = wp_remote_post($endpoint_url, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'title'                          => get_the_title($post_id),
                'partner_id'                     => $partner_id,
                'ecommerce_conversation_post_id' => $post_id,
                'order_id'                       => $order_id,
                'user_id'                        => $user_id,
                'phone'                          => $phone,
                'email'                          => $email,
                'first_name'                     => $first_name,
                'last_name'                      => $last_name
            ]),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            error_log('Failed to send conversation to endpoint: ' . $response->get_error_message());
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data['post_id'])) {
            update_field('partner_client_post_id', $data['post_id'], $post_id);
            error_log('Stored partner_conversation_post_id: ' . $data['post_id']);
        } else {
            error_log('No partner_conversation_post_id found in response.');
        }
    }

    private function create_conversation_comment($post_id, $order, $item, $partner_id) {
        $partner_name = get_the_title($partner_id);
        $partner_email = get_field('partner_email', $partner_id);

        $product_id = $item->get_product_id();
        if ($product_id == 28990) {
            error_log("Skipping product ID 28990 for item: " . $item->get_name());
            return; // Skip this item
        }

        $product_name = $item->get_name();
        $selected_add_ons = $item->get_meta('Selected Add-Ons');
        $service_type = $item->get_meta('Service Type');
        $start_date_time = $item->get_meta('Start Date Time');
        $start_time = $formatted_date = 'N/A';

        if (!empty($start_date_time)) {
            // Example: "12:00, 23 Jun 2025"
            [$time, $raw_date] = array_map('trim', explode(',', $start_date_time, 2));
            $timestamp = strtotime($raw_date);
            $formatted_date = $timestamp ? date_i18n('jS F Y', $timestamp) : 'N/A';
            $start_time = $time;
        }

        $content = '<div class="booking-comment">';
        $content .= "<p class='booking-title'>New Booking Requested</p>";
        $content .= "<p class='booking-detail'><strong>Order #" . $order->get_order_number() . "</strong></p>";
        $content .= "<p class='booking-datetime'><strong>Date:</strong> " . $formatted_date . " at " . $start_time . "</p>";
        $content .= "<p class='booking-detail'>" . esc_html($product_name) . "</p>";

        if (!empty($selected_add_ons)) {
            $content .= "<p><strong>Product Add Ons:</strong> " . esc_html($selected_add_ons) . "</p>";
        }

        if (!empty($service_type)) {
            $content .= "<p><strong>Product Type:</strong> " . esc_html($service_type) . "</p>";
        }

        $comment_id = wp_insert_comment([
            'comment_post_ID'      => $post_id,
            'comment_content'      => wp_kses_post($content),
            'comment_author'       => $partner_name,
            'comment_author_email' => $partner_email,
            'comment_approved'     => 1
        ]);

        if ($comment_id) {
            update_comment_meta($comment_id, 'sent_from', 'partner');
            update_comment_meta($comment_id, 'partner', $partner_id);
            update_comment_meta($comment_id, 'has_been_read_by_customer', false);
            error_log('Comment created with ID: ' . $comment_id);
        } else {
            error_log('Failed to create comment.');
        }
    }
}
