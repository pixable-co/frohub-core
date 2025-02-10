<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PopulateOrderAcf {

    public static function init() {
        $self = new self();
        add_action('woocommerce_thankyou', array($self, 'populate_order_acf'), 10, 1);
    }

    public function populate_order_acf( $order_id ) {

    // Get the order object
    $order = wc_get_order( $order_id );

    if ( $order ) {
        // Initialize variables to store meta values
        $all_service_types = [];
        $all_add_ons = [];
        $all_partner_ids = []; // Initialize variable for partner IDs

        // Loop through the order items
        foreach ( $order->get_items() as $item_id => $item ) {
            // Retrieve meta data
            $selected_add_ons = $item->get_meta( 'Selected Add-Ons' ); // Meta key for add-ons
            $service_type = $item->get_meta( 'Service Type' ); // Meta key for service type

            // Get the product ID
            $product_id = $item->get_product_id();

            // Retrieve the ACF field "partner_id" from the product
            $partner_id = get_field( 'partner_id', $product_id ); // Use get_field to fetch ACF field

            if ( $partner_id ) {
                $all_partner_ids[] = $partner_id; // Collect partner IDs
            }

            // Add meta data to arrays if not empty
            if ( ! empty( $selected_add_ons ) ) {
                $all_add_ons[] = $selected_add_ons; // Collect add-ons
            }

            if ( ! empty( $service_type ) ) {
                $all_service_types[] = $service_type; // Collect service types
            }
        }

            // Update ACF fields with collected data
            if ( ! empty( $all_add_ons ) ) {
                update_field( 'selected_add_ons', implode( ', ', $all_add_ons ), $order_id ); // Combine add-ons into a string
            }

            if ( ! empty( $all_service_types ) ) {
                update_field( 'service_type', implode( ', ', $all_service_types ), $order_id ); // Combine service types into a string
            }

            if ( ! empty( $all_partner_ids ) ) {
                // Combine all partner IDs into a single string (if multiple products have partner IDs)
                $combined_partner_ids = implode( ', ', $all_partner_ids );

                // Update the ACF field "partner_id" in the order
                update_field( 'partner_id', $combined_partner_ids, $order_id );
            }
        }
    }
}
