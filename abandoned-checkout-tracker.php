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

        $('#billing_phone, #billing_first_name, #billing_last_name, #billing_address_1, #billing_state').on('change keyup', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                const data = getData();

                if (!isValidPhone(data.phone)) return;

                if (JSON.stringify(data) === JSON.stringify(lastData)) return;

                lastData = data;
                formData = data; // Store the latest data for batching
            }, 1500);
        });

        // Send data when the user leaves the page
        $(window).on('beforeunload', function () {
            if (formData.phone && isValidPhone(formData.phone)) {
                sendData(formData);
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

    $first = sanitize_text_field($_POST['first_name'] ?? '');
    $last = sanitize_text_field($_POST['last_name'] ?? '');
    $addr = sanitize_text_field($_POST['address'] ?? '');
    $state = sanitize_text_field($_POST['state'] ?? '');

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
    }

    wp_send_json_success('Saved');
}

// Mark lead as recovered on order placed
add_action('woocommerce_checkout_order_processed', function ($order_id) {
    $order = wc_get_order($order_id);
    $phone = $order->get_billing_phone();

    if ($phone) {
        $leads = get_posts([
            'post_type' => 'abandoned_lead',
            'meta_key' => 'phone',
            'meta_value' => $phone,
            'post_status' => 'any',
            'numberposts' => -1
        ]);

        foreach ($leads as $lead) {
            update_post_meta($lead->ID, 'recovered', 1);
            update_post_meta($lead->ID, 'recovered_order_id', $order_id);
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
    wp_send_json_success(['updated' => $updated]);
});





// Limit WooCommerce phone field to 11 digits number
add_action('woocommerce_checkout_process', 'limited_custom_checkout_field_process');

function limited_custom_checkout_field_process() {
    $phone = isset($_POST['billing_phone']) ? trim($_POST['billing_phone']) : '';
    if ($phone && !preg_match('/^01[0-9]{9}$/', $phone)) {
        wc_add_notice("মোবইল নাম্বর ভুল হয়েছে ১১ ডিিটের সঠিক নম্বা িন।", 'error');
    }
}


