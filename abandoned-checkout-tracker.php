<?php
/**
 * Plugin Name: Abandoned Checkout Tracker for WooCommerce
 * Description: Tracks WooCommerce checkout entries and stores details of users who didn't complete their order.
 * Version: 1.3
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

        function isValidPhone(phone) {
            return /^01[0-9]{9}$/.test(phone);
        }

        function getData() {
            return {
                phone: $('#billing_phone').val(),
                first_name: $('#billing_first_name').val(),
                last_name: $('#billing_last_name').val(),
                address: $('#billing_address_1').val(),
                state: $('#billing_state').val()
            };
        }

        function sendData(data) {
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'save_abandoned_lead',
                nonce: '<?php echo wp_create_nonce('abandon_nonce'); ?>',
                ...data
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

    $existing = get_posts([
        'post_type' => 'abandoned_lead',
        'meta_key' => 'phone',
        'meta_value' => $phone,
        'numberposts' => 1
    ]);

    $allow_retrack = true;
    if (!empty($existing)) {
        $existing_time = strtotime(get_post_meta($existing[0]->ID, 'timestamp', true));
        $diff = time() - $existing_time;
        $allow_retrack = ($diff > 600); // 10 minutes
    }

    if (empty($existing) || $allow_retrack) {
        $post_id = wp_insert_post([
            'post_type' => 'abandoned_lead',
            'post_title' => "$first $last - $phone",
            'post_status' => 'publish'
        ]);

        update_post_meta($post_id, 'phone', $phone);
        update_post_meta($post_id, 'first_name', $first);
        update_post_meta($post_id, 'last_name', $last);
        update_post_meta($post_id, 'address', $addr);
        update_post_meta($post_id, 'state', $state);
        update_post_meta($post_id, 'products', $product_str);
        update_post_meta($post_id, 'subtotal', $subtotal);
        update_post_meta($post_id, 'timestamp', current_time('mysql'));
        update_post_meta($post_id, 'ip_address', $ip_address); // Save IP address
    }

    wp_send_json_success('Saved');
}


// Mark lead as recovered on order placed
add_action('woocommerce_checkout_order_processed', function ($order_id) {
    $order = wc_get_order($order_id);
    $phone = sanitize_text_field($order->get_billing_phone());
    $first_name = sanitize_text_field($order->get_billing_first_name());
    $last_name = sanitize_text_field($order->get_billing_last_name());

    if ($phone || $first_name || $last_name) {
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
            'post_status' => 'any',
            'numberposts' => -1
        ]);

        foreach ($leads as $lead) {
            // Retrieve the timestamp from meta or fallback to post_date
            $timestamp = get_post_meta($lead->ID, 'timestamp', true);
            $timestamp = is_numeric($timestamp) ? intval($timestamp) : strtotime($timestamp);

            // Fallback to post_date if timestamp is invalid
            if (!$timestamp) {
                $post_date = strtotime(get_post_field('post_date', $lead->ID));
                $timestamp = $post_date ? $post_date : time(); // Use post_date or current time
            }

            // Skip processing if the timestamp is invalid
            if (!$timestamp) {
                continue;
            }

            $time_diff = time() - $timestamp;

            // Mark the lead as recovered
            update_post_meta($lead->ID, 'recovered', 1);
            update_post_meta($lead->ID, 'recovered_order_id', $order_id);

            // Retain leads if the order is placed after 1 hour
            if ($time_diff > 3600) {
            } else {
                // Delete leads if the order is placed within 1 hour
                wp_trash_post($lead->ID);
            }
        }
    }
});


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
