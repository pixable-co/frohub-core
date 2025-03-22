<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomEvents {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/custom-events/fetch', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'get_custom_event'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('frohub/v1', '/custom-events/create', array(
                        'methods'             => 'POST',
                        'callback'            => array($this, 'create_custom_event'),
                        'permission_callback' => '__return_true',
        ));

        // Delete event
        register_rest_route('frohub/v1', '/custom-events/delete', array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'delete_custom_event'),
                    'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_custom_event(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');

        if (!$partner_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Partner ID is required',
            ], 400);
        }

        // Get the repeater field from ACF
        $unavailable_dates = get_field('unavailable_dates', $partner_id);

        if (empty($unavailable_dates)) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => [],
            ], 200);
        }

        // Format the data properly and include the event index
        $events = [];
        foreach ($unavailable_dates as $index => $event) {
            $events[] = [
                'event_index' => $index, // Add the event index
                'event_title' => $event['event_title'],
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date'],
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $events,
        ], 200);
    }

    public function create_custom_event(\WP_REST_Request $request) {
        // Extract parameters from the request
        $partner_id  = $request->get_param('partner_id');
        $event_title = $request->get_param('event_title');
        $start_date  = $request->get_param('start_date');
        $end_date    = $request->get_param('end_date');

        // Log received data for debugging
        error_log("🔹 Received Data: " . print_r($request->get_params(), true));

        // Validate required fields
        if (!$partner_id || !$event_title || !$start_date || !$end_date) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Missing required fields',
            ], 400);
        }

        // Create the new event array
        $new_event = [
            'event_title' => sanitize_text_field($event_title),
            'start_date'  => sanitize_text_field($start_date),
            'end_date'    => sanitize_text_field($end_date),
        ];

        // Log the new event for debugging
        error_log("🔹 New Event: " . print_r($new_event, true));

        // Append the new event to the ACF Repeater field
        $row_index = add_row('unavailable_dates', $new_event, $partner_id);

        // Check if the row was added successfully
        if (!$row_index) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to add event to ACF Repeater field',
            ], 500);
        }

        // Fetch the updated data to verify correct storage
        $updated_dates = get_field('unavailable_dates', $partner_id, true);
        error_log("✅ After Update: " . print_r($updated_dates, true));

        // Return a success response
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Event added successfully',
            'data'    => $updated_dates,
        ], 200);
    }

    public function delete_custom_event(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');
        $event_index = $request->get_param('event_index'); // 0-based index

        // Log received data
        error_log("🔹 Delete Request: " . print_r($request->get_params(), true));

        // Validate input
        if (!$partner_id || !is_numeric($event_index)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Partner ID and event index are required',
            ], 400);
        }

        // Get current events
        $unavailable_dates = get_field('unavailable_dates', $partner_id);

        if (empty($unavailable_dates) || !isset($unavailable_dates[$event_index])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid event index or no events found',
            ], 404);
        }

        // ACF's delete_row uses 1-based index
        $acf_index = $event_index + 1;

        // Delete the row
        $result = delete_row('unavailable_dates', $acf_index, $partner_id);

        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to delete the event',
            ], 500);
        }

        // Fetch updated data
        $updated_dates = get_field('unavailable_dates', $partner_id, true);
        error_log("✅ After Deletion: " . print_r($updated_dates, true));

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Event deleted successfully',
            'data'    => $updated_dates,
        ], 200);
    }

//     public function delete_custom_event(\WP_REST_Request $request) {
//             // Extract parameters from the request
//             $partner_id = $request->get_param('partner_id');
//             $event_index = $request->get_param('event_index'); // Index of the event in the repeater field
//
//             // Log received data for debugging
//             error_log("🔹 Delete Request: " . print_r($request->get_params(), true));
//
//             // Validate required fields
//             if (!$partner_id || !is_numeric($event_index)) {
//                 return new \WP_REST_Response([
//                     'success' => false,
//                     'message' => 'Partner ID and event index are required',
//                 ], 400);
//             }
//
//             // Retrieve existing events from the ACF Repeater field
//             $unavailable_dates = get_field('unavailable_dates', $partner_id, true);
//
//             if (!is_array($unavailable_dates) || empty($unavailable_dates)) {
//                 return new \WP_REST_Response([
//                     'success' => false,
//                     'message' => 'No events found for the given partner ID',
//                 ], 404);
//             }
//
//             // Check if the event index is valid
//             if (!isset($unavailable_dates[$event_index])) {
//                 return new \WP_REST_Response([
//                     'success' => false,
//                     'message' => 'Invalid event index',
//                 ], 400);
//             }
//
//             // Remove the event at the specified index
//             unset($unavailable_dates[$event_index]);
//
//             // Re-index the array to ensure it remains sequential
//             $unavailable_dates = array_values($unavailable_dates);
//
//             // Update the ACF Repeater field with the modified array
//             update_field('unavailable_dates', $unavailable_dates, $partner_id);
//
//             // Fetch the updated data to verify deletion
//             $updated_dates = get_field('unavailable_dates', $partner_id, true);
//             error_log("✅ After Deletion: " . print_r($updated_dates, true));
//
//             // Return a success response
//             return new \WP_REST_Response([
//                 'success' => true,
//                 'message' => 'Event deleted successfully',
//                 'data'    => $updated_dates,
//             ], 200);
//         }
}