<?php
/**
 * Plugin Name: Abandoned Checkout Tracker for WooCommerce
 * Description: Tracks WooCommerce checkout entries and stores details of users who didn't complete their order.
 * Version: 1.5
 * Author: Sogir Mahmud
 */

if (!defined('ABSPATH')) exit;

// Include admin page
add_action('plugins_loaded', function () {
    require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
});

// Register custom post type
add_action('init', function () {
    register_post_type('abandoned_lead', [
        'public' => false,
        'show_ui' => false,
        'label' => 'Abandoned Leads',
        'supports' => ['title', 'custom-fields']
    ]);
});

// Capture checkout form data with JS
add_action('woocommerce_after_checkout_form', function () {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function ($) {
        let timer;
        let lastData = {};
        let formData = {};
        let sessionId = getCookie('abandoned_session_id');

        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return '';
        }

        function isValidPhone(phone) {
            return /^01[0-9]{9}$/.test(phone);
        }

        function getData() {
            return {
                phone: $('#billing_phone').val(),
                first_name: $('#billing_first_name').val(),
                last_name: $('#billing_last_name').val(),
                address: $('#billing_address_1').val(),
                state: $('#billing_state').val(),
                session_id: sessionId
            };
        }

        function sendData(data) {
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'save_abandoned_lead',
                nonce: '<?php echo wp_create_nonce('abandon_nonce'); ?>',
                ...data
            }).done(function(response) {
                // If we get a new session ID back, store it
                if (response.data && response.data.session_id) {
                    sessionId = response.data.session_id;
                    document.cookie = "abandoned_session_id=" + sessionId + "; path=/; max-age=86400";
                }
            });
        }

        // Save data periodically (every 10 seconds)
        setInterval(function () {
            const data = getData();

            if (data.phone && isValidPhone(data.phone)) {
                sendData(data);
            }
        }, 10000); // 10 seconds

        // Capture data on field change
        $('#billing_phone, #billing_first_name, #billing_last_name, #billing_address_1, #billing_state').on('change keyup', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                const data = getData();

                if (!isValidPhone(data.phone)) return;

                if (JSON.stringify(data) === JSON.stringify(lastData)) return;

                lastData = data;
                formData = data; // Store the latest data for periodic saving
            }, 1500);
        });

        // Use visibilitychange to detect when the page becomes hidden
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                const data = getData();

                if (data.phone && isValidPhone(data.phone)) {
                    sendData(data);
                }
            }
        });

        // Send data when the user leaves the page
        $(window).on('beforeunload', function () {
            const data = getData();

            if (data.phone && isValidPhone(data.phone)) {
                // Use navigator.sendBeacon for more reliable data sending on page unload
                if (navigator.sendBeacon) {
                    const formData = new FormData();
                    formData.append('action', 'save_abandoned_lead');
                    formData.append('nonce', '<?php echo wp_create_nonce('abandon_nonce'); ?>');
                    
                    // Append all data fields
                    Object.keys(data).forEach(key => {
                        formData.append(key, data[key]);
                    });
                    
                    navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', formData);
                } else {
                    // Fallback to synchronous AJAX if sendBeacon is not available
                    sendData(data);
                }
            }
        });
    });
    </script>
    <?php
});

// Save abandoned lead
add_action('wp_ajax_nopriv_save_abandoned_lead', 'act_save_abandoned_lead');
add_action('wp_ajax_save_abandoned_lead', 'act_save_abandoned_lead'); // Also handle logged-in users

function act_save_abandoned_lead() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'abandon_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    $phone = sanitize_text_field($_POST['phone'] ?? '');
    if (!preg_match('/^01[0-9]{9}$/', $phone)) {
        wp_send_json_error('Invalid phone format');
    }

    // Proceed with saving the abandoned lead
    $first = sanitize_text_field($_POST['first_name'] ?? '');
    $last = sanitize_text_field($_POST['last_name'] ?? '');
    $addr = sanitize_text_field($_POST['address'] ?? '');
    $state = sanitize_text_field($_POST['state'] ?? '');

    // Capture customer IP address
    $ip_address = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    // Safely handle IP address extraction
    if (!empty($ip_address) && strpos($ip_address, ',') !== false) {
        $ip_address = explode(',', $ip_address)[0]; // Get the first IP
    }
    $ip_address = filter_var($ip_address, FILTER_VALIDATE_IP) ?: ''; // Validate the IP address

    $cart = WC()->cart ? WC()->cart->get_cart() : [];
    $products = [];
    foreach ($cart as $item) {
        $products[] = $item['data']->get_name() . ' x' . $item['quantity'];
    }
    $product_str = implode(', ', $products);
    $subtotal = WC()->cart ? WC()->cart->get_subtotal() : 0.00;

    // Always use Unix timestamp for storage
    $current_time = time();
    
    // Only generate readable time when needed for display
    $bangladesh_time = act_convert_to_bangladesh_time($current_time);
    
    // Get or create session ID
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    if (empty($session_id)) {
        $session_id = md5($phone . $ip_address . $current_time);
    }
    
    // Find existing leads with this phone number
    $existing = get_posts([
        'post_type' => 'abandoned_lead',
        'meta_key' => 'phone',
        'meta_value' => $phone,
        'post_status' => 'publish',
        'numberposts' => -1 // Get all matching leads
    ]);
    
    // Check if we have an existing lead from the same session
    $same_session_lead = null;
    foreach ($existing as $lead) {
        $lead_session = get_post_meta($lead->ID, 'session_id', true);
        if ($lead_session === $session_id) {
            $same_session_lead = $lead;
            break;
        }
    }

    // If we found a lead from the same session, update it
    if ($same_session_lead) {
        $post_id = $same_session_lead->ID;
        
        // Update the lead with new data
        wp_update_post([
            'ID' => $post_id,
            'post_title' => "$first $last - $phone",
            'post_modified' => current_time('mysql')
        ]);
        
        // Update meta fields only if they have values
        if (!empty($first)) update_post_meta($post_id, 'first_name', $first);
        if (!empty($last)) update_post_meta($post_id, 'last_name', $last);
        if (!empty($addr)) update_post_meta($post_id, 'address', $addr);
        if (!empty($state)) update_post_meta($post_id, 'state', $state);
        if (!empty($product_str)) update_post_meta($post_id, 'products', $product_str);
        if (!empty($subtotal)) update_post_meta($post_id, 'subtotal', $subtotal);
        
        // Always update the last_updated timestamp to show the latest activity
        update_post_meta($post_id, 'last_updated', $current_time);
        update_post_meta($post_id, 'last_updated_readable', $bangladesh_time);
        
        wp_send_json_success([
            'message' => 'Updated existing lead',
            'session_id' => $session_id
        ]);
        return;
    }
    
    // If we have existing leads for this phone but from different sessions,
    // check if any are recent enough to update instead of creating a new one
    $recent_lead = null;
    foreach ($existing as $lead) {
        $lead_time = intval(get_post_meta($lead->ID, 'timestamp', true));
        
        // If lead is less than 24 hours old, update it instead of creating a new one
        if ($current_time - $lead_time < 86400) {
            $recent_lead = $lead;
            break;
        }
    }
    
    if ($recent_lead) {
        $post_id = $recent_lead->ID;
        
        // Update the lead with new data
        wp_update_post([
            'ID' => $post_id,
            'post_title' => "$first $last - $phone",
            'post_modified' => current_time('mysql')
        ]);
        
        // Update meta fields
        if (!empty($first)) update_post_meta($post_id, 'first_name', $first);
        if (!empty($last)) update_post_meta($post_id, 'last_name', $last);
        if (!empty($addr)) update_post_meta($post_id, 'address', $addr);
        if (!empty($state)) update_post_meta($post_id, 'state', $state);
        if (!empty($product_str)) update_post_meta($post_id, 'products', $product_str);
        if (!empty($subtotal)) update_post_meta($post_id, 'subtotal', $subtotal);
        
        // Update session ID and timestamp
        update_post_meta($post_id, 'session_id', $session_id);
        update_post_meta($post_id, 'last_updated', $current_time);
        update_post_meta($post_id, 'last_updated_readable', $bangladesh_time);
        update_post_meta($post_id, 'ip_address', $ip_address);
        
        wp_send_json_success([
            'message' => 'Updated recent lead',
            'session_id' => $session_id
        ]);
        return;
    }
    
    // If no existing lead to update, create a new one
    $post_id = wp_insert_post([
        'post_type' => 'abandoned_lead',
        'post_title' => "$first $last - $phone",
        'post_status' => 'publish'
    ]);

    // Save all meta data
    update_post_meta($post_id, 'phone', $phone);
    update_post_meta($post_id, 'first_name', $first);
    update_post_meta($post_id, 'last_name', $last);
    update_post_meta($post_id, 'address', $addr);
    update_post_meta($post_id, 'state', $state);
    update_post_meta($post_id, 'products', $product_str);
    update_post_meta($post_id, 'subtotal', $subtotal);
    update_post_meta($post_id, 'timestamp', $current_time); // Unix timestamp for calculations
    update_post_meta($post_id, 'timestamp_readable', $bangladesh_time); // Bangladesh time for display
    update_post_meta($post_id, 'last_updated', $current_time);
    update_post_meta($post_id, 'last_updated_readable', $bangladesh_time);
    update_post_meta($post_id, 'ip_address', $ip_address);
    update_post_meta($post_id, 'session_id', $session_id);
    update_post_meta($post_id, 'status', '❌'); // Default status

    // Clean up old duplicate leads for this phone number
    if (count($existing) > 0) {
        foreach ($existing as $old_lead) {
            // Skip the lead we just created
            if ($old_lead->ID === $post_id) continue;
            
            // Delete old leads that are older than 24 hours
            $old_timestamp = intval(get_post_meta($old_lead->ID, 'timestamp', true));
            
            if ($current_time - $old_timestamp > 86400) {
                wp_delete_post($old_lead->ID, true);
            }
        }
    }

    wp_send_json_success([
        'message' => 'Created new lead',
        'session_id' => $session_id
    ]);
}

// Mark lead as recovered on order placed
add_action('woocommerce_checkout_order_processed', 'act_process_recovered_leads');

function act_process_recovered_leads($order_id) {
    $order = wc_get_order($order_id);
    $phone = sanitize_text_field($order->get_billing_phone());
    $first_name = sanitize_text_field($order->get_billing_first_name());
    $last_name = sanitize_text_field($order->get_billing_last_name());

    if (empty($phone) && empty($first_name) && empty($last_name)) {
        return; // No identifiable information
    }

    // Normalize the phone number to avoid mismatches
    $phone = preg_replace('/\D/', '', $phone); // Remove non-numeric characters

    // Query abandoned leads matching the phone or name
    $leads = get_posts([
        'post_type' => 'abandoned_lead',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => 'phone',
                'value' => $phone,
                'compare' => '='
            ],
            [
                'relation' => 'AND',
                [
                    'key' => 'first_name',
                    'value' => $first_name,
                    'compare' => '='
                ],
                [
                    'key' => 'last_name',
                    'value' => $last_name,
                    'compare' => '='
                ]
            ]
        ],
        'post_status' => 'publish',
        'numberposts' => -1
    ]);
    
    if (empty($leads)) {
        return; // No leads found
    }
    
    // Get current session ID if available
    $session_id = isset($_COOKIE['abandoned_session_id']) ? $_COOKIE['abandoned_session_id'] : '';
    
    // First, try to find a lead from the current session
    $session_lead = null;
    if (!empty($session_id)) {
        foreach ($leads as $lead) {
            $lead_session = get_post_meta($lead->ID, 'session_id', true);
            if ($lead_session === $session_id) {
                $session_lead = $lead;
                break;
            }
        }
    }
    
    // If we found a lead from the current session, process only that one
    if ($session_lead) {
        process_recovered_lead($session_lead, $order_id);
        
        // Delete all other leads for this phone number to avoid duplicates
        foreach ($leads as $lead) {
            if ($lead->ID !== $session_lead->ID) {
                wp_delete_post($lead->ID, true);
            }
        }
    } 
    // Otherwise, find the most recent lead
    else if (count($leads) > 0) {
        // Sort leads by timestamp (most recent first)
        usort($leads, function($a, $b) {
            $time_a = intval(get_post_meta($a->ID, 'timestamp', true));
            $time_b = intval(get_post_meta($b->ID, 'timestamp', true));
            
            return $time_b - $time_a; // Descending order (newest first)
        });
        
        // Process the most recent lead
        $most_recent = $leads[0];
        process_recovered_lead($most_recent, $order_id);
        
        // Delete all other leads for this phone number
        for ($i = 1; $i < count($leads); $i++) {
            wp_delete_post($leads[$i]->ID, true);
        }
    }
    
    // Clear the session cookie
    if (!empty($session_id)) {
        setcookie('abandoned_session_id', '', time() - 3600, '/');
    }
}

// Helper function to process a recovered lead
function process_recovered_lead($lead, $order_id) {
    // Always get the timestamp as an integer
    $timestamp = intval(get_post_meta($lead->ID, 'timestamp', true));
    
    // If no valid timestamp found, use post date as fallback
    if (!$timestamp) {
        $timestamp = strtotime($lead->post_date);
    }
    
    // Calculate time difference in seconds
    $current_time = time();
    $time_diff = $current_time - $timestamp;
    
    // Generate readable time only for logging
    $readable_timestamp = act_convert_to_bangladesh_time($timestamp);
   
    
    // Mark the lead as recovered with common metadata for both cases
    update_post_meta($lead->ID, 'recovered', 1);
    update_post_meta($lead->ID, 'recovered_order_id', $order_id);
    update_post_meta($lead->ID, 'recovery_time', $current_time);
    update_post_meta($lead->ID, 'recovery_time_readable', act_convert_to_bangladesh_time($current_time));
    
    // Handle differently based on time difference
    if ($time_diff <= 3600) {
        // For leads recovered within 1 hour, mark them to be hidden
        update_post_meta($lead->ID, 'recovered_within_hour', 1);
    } else {
        // For leads recovered after 1 hour, mark them as confirmed
        update_post_meta($lead->ID, 'status', '✅');
    }
}



// AJAX: Save note
add_action('wp_ajax_update_abandoned_note', function () {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'abandon_note_nonce')) {
        wp_send_json_error(['reason' => 'Invalid nonce']);
    }

    $id = intval($_POST['lead_id'] ?? 0);
    $note = sanitize_textarea_field($_POST['note'] ?? '');

    if (!$id || get_post_type($id) !== 'abandoned_lead') {
        wp_send_json_error(['reason' => 'Invalid ID or post type']);
    }

    $updated = update_post_meta($id, 'note', $note);
    if (!$updated) {
        wp_send_json_error(['reason' => 'Failed to update post meta']);
    }

    wp_send_json_success(['updated' => $updated]);
});

// Limit WooCommerce phone field to 11 digits number
add_action('woocommerce_checkout_process', 'njengah_custom_checkout_field_process');

function njengah_custom_checkout_field_process() {
    $phone = isset($_POST['billing_phone']) ? trim($_POST['billing_phone']) : '';
    if ($phone && !preg_match('/^01[0-9]{9}$/', $phone)) {
        wc_add_notice("Mobile Number Must be at least 11 digit and start with 01", 'error');
    }
}

// status toggle button php ajax functionality
add_action('wp_ajax_update_abandoned_status', function () {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'abandon_note_nonce')) {
        wp_send_json_error(['reason' => 'Invalid nonce']);
    }

    $id = intval($_POST['lead_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');

    if (!$id || get_post_type($id) !== 'abandoned_lead') {
        wp_send_json_error(['reason' => 'Invalid ID or post type']);
    }

    if (!in_array($status, ['❌', '✅'])) {
        wp_send_json_error(['reason' => 'Invalid status value']);
    }

    $updated = update_post_meta($id, 'status', $status);
    if (!$updated) {
        wp_send_json_error(['reason' => 'Failed to update post meta']);
    }

    wp_send_json_success(['updated' => $updated]);
});

// Delete button php ajax functionality
add_action('wp_ajax_delete_abandoned_lead', function () {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'abandon_note_nonce')) {
        wp_send_json_error(['reason' => 'Invalid nonce']);
    }

    $id = intval($_POST['lead_id'] ?? 0);

    if (!$id || get_post_type($id) !== 'abandoned_lead') {
        wp_send_json_error(['reason' => 'Invalid ID or post type']);
    }

    $deleted = wp_delete_post($id, true); // Force delete the post
    if (!$deleted) {
        wp_send_json_error(['reason' => 'Failed to delete lead']);
    }

    wp_send_json_success(['deleted' => $deleted]);
});

// Bulk delete AJAX handler
add_action('wp_ajax_bulk_delete_abandoned_leads', function () {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'abandon_note_nonce')) {
        wp_send_json_error(['reason' => 'Invalid nonce']);
    }

    if (!isset($_POST['lead_ids']) || !is_array($_POST['lead_ids'])) {
        wp_send_json_error(['reason' => 'No leads selected']);
    }

    $lead_ids = array_map('intval', $_POST['lead_ids']);
    $deleted_count = 0;

    foreach ($lead_ids as $id) {
        if (get_post_type($id) === 'abandoned_lead') {
            if (wp_delete_post($id, true)) {
                $deleted_count++;
            }
        }
    }

    wp_send_json_success(['deleted' => $deleted_count]);
});

// Export selected checkouts
add_action('wp_ajax_export_selected_abandoned_checkouts', function () {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'abandon_note_nonce')) {
        wp_die('Security check failed');
    }

    if (!isset($_POST['lead_ids']) || !is_array($_POST['lead_ids'])) {
        wp_die('No leads selected');
    }

    $lead_ids = array_map('intval', $_POST['lead_ids']);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="selected-abandoned-checkouts.csv"');

    $output = fopen('php://output', 'w');

    // Add the CSV header row
    fputcsv($output, [        
        'Date',
        'Name',
        'Phone',
        'Address',
        'State',
        'IP Address',
        'Subtotal',
        'Products',
        'Status',
        'Note'
    ]);

    // Loop through the selected leads and add rows to the CSV
    foreach ($lead_ids as $id) {
        if (get_post_type($id) !== 'abandoned_lead') {
            continue;
        }

        // Convert state code to state name
        $state_code = get_post_meta($id, 'state', true);
        $country_code = 'BD';
        $states = WC()->countries->get_states($country_code);
        $state = isset($states[$state_code]) ? $states[$state_code] : 'Unknown';

        // Get the status
        $status = get_post_meta($id, 'status', true) === '✅' ? 'Confirmed' : 'Pending';
        
        // Always get timestamp as integer and convert for display
        $timestamp = intval(get_post_meta($id, 'timestamp', true));
        $timestamp_readable = act_convert_to_bangladesh_time($timestamp);

        // Add the row to the CSV
        fputcsv($output, [            
            $timestamp_readable,
            get_post_meta($id, 'first_name', true) . ' ' . get_post_meta($id, 'last_name', true),
            get_post_meta($id, 'phone', true),
            get_post_meta($id, 'address', true),
            $state,
            get_post_meta($id, 'ip_address', true),
            get_post_meta($id, 'subtotal', true),
            get_post_meta($id, 'products', true),
            $status,
            get_post_meta($id, 'note', true)
        ]);
    }

    fclose($output);
    exit;
});

/**
 * Cleanup abandoned leads older than 7 days.
 */
function act_cleanup_old_abandoned_leads() {
    $seven_days_ago = time() - 604800; // 7 days in seconds

    $old_leads = get_posts([
        'post_type' => 'abandoned_lead',
        'meta_query' => [
            [
                'key' => 'timestamp',
                'value' => $seven_days_ago,
                'compare' => '<',
                'type' => 'NUMERIC'
            ]
        ],
        'post_status' => 'publish',
        'numberposts' => -1 // Get all matching leads
    ]);

    foreach ($old_leads as $lead) {
        wp_delete_post($lead->ID, true); // Force delete the post
    }
}

// Schedule the cleanup function on plugin activation
register_activation_hook(__FILE__, 'act_plugin_activated');

function act_plugin_activated() {
    // Set up scheduled events
    if (!wp_next_scheduled('act_cleanup_old_abandoned_leads_event')) {
        wp_schedule_event(time(), 'daily', 'act_cleanup_old_abandoned_leads_event');
    }
}

// Clear the scheduled event on plugin deactivation
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('act_cleanup_old_abandoned_leads_event');
});

// Hook the cleanup function to the scheduled event
add_action('act_cleanup_old_abandoned_leads_event', 'act_cleanup_old_abandoned_leads');

/**
 * Convert a timestamp to Bangladesh time
 * 
 * @param int $timestamp Unix timestamp
 * @return string Formatted date/time in Bangladesh timezone
 */
function act_convert_to_bangladesh_time($timestamp) {
    // Ensure we have a valid integer timestamp
    $unix_timestamp = intval($timestamp);
    
    // If we couldn't get a valid timestamp, return current time
    if (!$unix_timestamp) {
        $unix_timestamp = time();
    }
    
    // Set the timezone to Bangladesh
    $date = new DateTime();
    $date->setTimestamp($unix_timestamp);
    $date->setTimezone(new DateTimeZone('Asia/Dhaka'));
    
    // Return formatted date/time
    return $date->format('Y-m-d H:i:s');
}
// Export CSV
add_action('admin_post_export_abandoned_checkouts', function () {
    // Query leads but exclude those recovered within 1 hour
    $leads = get_posts([
        'post_type' => 'abandoned_lead',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_query' => [
            [
                'relation' => 'OR',
                [
                    'key' => 'recovered_within_hour',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'recovered_within_hour',
                    'value' => '1',
                    'compare' => '!='
                ]
            ]
        ]
    ]);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="abandoned-checkouts.csv"');

    $output = fopen('php://output', 'w');

    // Add the CSV header row
    fputcsv($output, [        
        'Date',
        'Name',
        'Phone',
        'Address',
        'State',
        'IP Address',
        'Subtotal',
        'Products',
        'Status',
        'Note'
    ]);

    // Loop through the leads and add rows to the CSV
    foreach ($leads as $lead) {
        $id = $lead->ID;

        // Convert state code to state name
        $state_code = get_post_meta($id, 'state', true);
        $country_code = 'BD';
        $states = WC()->countries->get_states($country_code);
        $state = isset($states[$state_code]) ? $states[$state_code] : 'Unknown';

        // Get the status
        $status = get_post_meta($id, 'status', true) === '✅' ? 'Confirmed' : 'Pending';
        
        // Always get timestamp as integer and convert for display
        $timestamp = intval(get_post_meta($id, 'timestamp', true));
        $timestamp_readable = act_convert_to_bangladesh_time($timestamp);

        // Add the row to the CSV
        fputcsv($output, [            
            $timestamp_readable,
            get_post_meta($id, 'first_name', true) . ' ' . get_post_meta($id, 'last_name', true),
            get_post_meta($id, 'phone', true),
            get_post_meta($id, 'address', true),
            $state,
            get_post_meta($id, 'ip_address', true),
            get_post_meta($id, 'subtotal', true),
            get_post_meta($id, 'products', true),
            $status,
            get_post_meta($id, 'note', true)
        ]);
    }

    fclose($output);
    exit;
});

// Export selected checkouts
add_action('wp_ajax_export_selected_abandoned_checkouts', function () {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'abandon_note_nonce')) {
        wp_die('Security check failed');
    }

    if (!isset($_POST['lead_ids']) || !is_array($_POST['lead_ids'])) {
        wp_die('No leads selected');
    }

    $lead_ids = array_map('intval', $_POST['lead_ids']);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="selected-abandoned-checkouts.csv"');

    $output = fopen('php://output', 'w');

    // Add the CSV header row
    fputcsv($output, [        
        'Date',
        'Name',
        'Phone',
        'Address',
        'State',
        'IP Address',
        'Subtotal',
        'Products',
        'Status',
        'Note'
    ]);

    // Loop through the selected leads and add rows to the CSV
    foreach ($lead_ids as $id) {
        if (get_post_type($id) !== 'abandoned_lead') {
            continue;
        }
        
        // Skip leads that were recovered within 1 hour
        $recovered_within_hour = get_post_meta($id, 'recovered_within_hour', true);
        if ($recovered_within_hour === '1') {
            continue;
        }

        // Convert state code to state name
        $state_code = get_post_meta($id, 'state', true);
        $country_code = 'BD';
        $states = WC()->countries->get_states($country_code);
        $state = isset($states[$state_code]) ? $states[$state_code] : 'Unknown';

        // Get the status
        $status = get_post_meta($id, 'status', true) === '✅' ? 'Confirmed' : 'Pending';
        
        // Always get timestamp as integer and convert for display
        $timestamp = intval(get_post_meta($id, 'timestamp', true));
        $timestamp_readable = act_convert_to_bangladesh_time($timestamp);

        // Add the row to the CSV
        fputcsv($output, [            
            $timestamp_readable,
            get_post_meta($id, 'first_name', true) . ' ' . get_post_meta($id, 'last_name', true),
            get_post_meta($id, 'phone', true),
            get_post_meta($id, 'address', true),
            $state,
            get_post_meta($id, 'ip_address', true),
            get_post_meta($id, 'subtotal', true),
            get_post_meta($id, 'products', true),
            $status,
            get_post_meta($id, 'note', true)
        ]);
    }

    fclose($output);
    exit;
});
