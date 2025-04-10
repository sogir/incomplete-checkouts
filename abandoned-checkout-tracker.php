<?php
/**
 * Plugin Name: Abandoned Checkout Tracker for WooCommerce
 * Description: Tracks WooCommerce checkout entries and stores details of users who didn't complete their order.
 * Version: 1.4
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
                sendData(data);
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

    // Check if the phone number exists in a completed order
    $orders = wc_get_orders([
        'billing_phone' => $phone,
        'status' => ['completed', 'processing', 'on-hold'], // Add other statuses if needed
        'limit' => 1,
    ]);

    if (!empty($orders)) {
        wp_send_json_error('Order already exists for this phone number');
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
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    $ip_address = explode(',', $ip_address)[0]; // Get the first IP
    $ip_address = filter_var($ip_address, FILTER_VALIDATE_IP); // Validate the IP address

    $cart = WC()->cart ? WC()->cart->get_cart() : [];
    $products = [];
    foreach ($cart as $item) {
        $products[] = $item['data']->get_name() . ' x' . $item['quantity'];
    }
    $product_str = implode(', ', $products);
    $subtotal = WC()->cart ? WC()->cart->get_subtotal() : 0.00;

    $current_time = time();
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
        $lead_time = get_post_meta($lead->ID, 'timestamp', true);
        if (is_numeric($lead_time)) {
            $lead_time = intval($lead_time);
        } else {
            $lead_time = strtotime($lead_time);
        }
        
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
            $old_timestamp = get_post_meta($old_lead->ID, 'timestamp', true);
            if (is_numeric($old_timestamp)) {
                $old_timestamp = intval($old_timestamp);
            } else {
                $old_timestamp = strtotime($old_timestamp);
            }
            
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
            $time_a = get_post_meta($a->ID, 'timestamp', true);
            $time_b = get_post_meta($b->ID, 'timestamp', true);
            
            if (is_numeric($time_a)) {
                $time_a = intval($time_a);
            } else {
                $time_a = strtotime($time_a);
            }
            
            if (is_numeric($time_b)) {
                $time_b = intval($time_b);
            } else {
                $time_b = strtotime($time_b);
            }
            
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
    // Get the timestamp from meta
    $timestamp_str = get_post_meta($lead->ID, 'timestamp', true);
    $timestamp = 0;
    
    // Convert timestamp string to Unix timestamp
    if (!empty($timestamp_str)) {
        if (is_numeric($timestamp_str)) {
            $timestamp = intval($timestamp_str);
        } else {
            $timestamp = strtotime($timestamp_str);
        }
    }
    
    // If still no valid timestamp, use post date
    if (!$timestamp) {
        $timestamp = strtotime($lead->post_date);
    }
    
    // Calculate time difference in seconds
    $current_time = time();
    $time_diff = $current_time - $timestamp;
    
    // Get Bangladesh time for display
    $recovery_time_readable = act_convert_to_bangladesh_time($current_time);
        
    // Mark the lead as recovered
    update_post_meta($lead->ID, 'recovered', 1);
    update_post_meta($lead->ID, 'recovered_order_id', $order_id);
    update_post_meta($lead->ID, 'recovery_time', $current_time);
    update_post_meta($lead->ID, 'recovery_time_readable', $recovery_time_readable);
    
    // Delete leads if the order is placed within 1 hour (3600 seconds)
    if ($time_diff <= 3600) {
        wp_trash_post($lead->ID);
    } else {
        // Update status to ✅ for leads recovered after 1 hour
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

// Add cleanup routine to remove old abandoned leads (older than 30 days)
add_action('wp_scheduled_delete', 'act_cleanup_old_abandoned_leads');

function act_cleanup_old_abandoned_leads() {
    $thirty_days_ago = time() - (30 * 86400); // 30 days in seconds
    
    $old_leads = get_posts([
        'post_type' => 'abandoned_lead',
        'meta_query' => [
            [
                'key' => 'timestamp',
                'value' => $thirty_days_ago,
                'compare' => '<',
                'type' => 'NUMERIC'
            ]
        ],
        'post_status' => 'publish',
        'numberposts' => 100 // Process in batches to avoid timeout
    ]);
    
    foreach ($old_leads as $lead) {
        wp_delete_post($lead->ID, true);
    }
}

/**
 * Convert a timestamp to Bangladesh time
 * 
 * @param int|string $timestamp Unix timestamp or date string
 * @return string Formatted date/time in Bangladesh timezone
 */
function act_convert_to_bangladesh_time($timestamp) {
    // If it's a numeric timestamp, use it directly
    if (is_numeric($timestamp)) {
        $unix_timestamp = intval($timestamp);
    } 
    // Otherwise try to convert the string to a timestamp
    else {
        $unix_timestamp = strtotime($timestamp);
    }
    
    // If we couldn't get a valid timestamp, return the original
    if (!$unix_timestamp) {
        return $timestamp;
    }
    
    // Set the timezone to Bangladesh
    $date = new DateTime();
    $date->setTimestamp($unix_timestamp);
    $date->setTimezone(new DateTimeZone('Asia/Dhaka'));
    
    // Return formatted date/time
    return $date->format('Y-m-d H:i:s');
}
