<?php
// File: includes/admin-page.php

if (!defined('ABSPATH')) exit;

// Admin menu
add_action('admin_menu', function () {
    add_menu_page('Abandoned Checkouts', 'Abandoned Checkouts', 'manage_woocommerce', 'abandoned-checkouts', 'render_abandoned_admin_page', 'dashicons-cart');
});

// Admin page content
function render_abandoned_admin_page() {
    $nonce = wp_create_nonce("abandon_table_nonce");
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    echo '<div class="wrap">
        <h1>Abandoned Checkouts</h1>
        
        <form method="get" style="margin-bottom: 10px;">
            <input type="hidden" name="page" value="abandoned-checkouts">
            <input type="text" name="s" value="' . esc_attr($search_term) . '" placeholder="Search by name or phone" style="padding: 5px; width: 300px;" />
            <button type="submit" class="button">Search</button>
        </form>

        <form method="post" action="' . admin_url('admin-post.php') . '">
            <input type="hidden" name="action" value="export_abandoned_checkouts">
            <input type="submit" class="button button-primary" value="Export CSV">
        </form><br>

        <div id="abandoned-table-wrap">';

    render_abandoned_table($search_term);

    echo '</div></div>'; ?>

    <script>
    const abandon_nonce = '<?php echo $nonce; ?>';

    function saveNote(id) {
        let note = document.getElementById('note-' + id).value;
        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'update_abandoned_note',
                lead_id: id,
                note: note,
                nonce: '<?php echo wp_create_nonce("abandon_note_nonce"); ?>'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Note saved!");
            } else {
                alert("Failed to save: " + (data.data?.reason || 'Unknown error'));
            }
        });
    }

   
    </script>
<?php }

function render_abandoned_table($search = '') {
    $args = [
        'post_type' => 'abandoned_lead',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ];

    $leads = get_posts($args);
    if ($search) {
        $search = strtolower($search);
        $leads = array_filter($leads, function ($lead) use ($search) {
            $name = strtolower(get_post_meta($lead->ID, 'first_name', true) . ' ' . get_post_meta($lead->ID, 'last_name', true));
            $phone = strtolower(get_post_meta($lead->ID, 'phone', true));
            return strpos($name, $search) !== false || strpos($phone, $search) !== false;
        });
    }

    echo '<table class="widefat striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Address</th>
                <th>State</th>
                <th>Subtotal</th>
                <th>Products</th>
                <th>Date</th>
                <th>Recovered?</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($leads)) {
        echo '<tr><td colspan="10">No abandoned leads found.</td></tr>';
    } else {
        foreach ($leads as $lead) {
            $id = $lead->ID;
            $first = get_post_meta($id, 'first_name', true);
            $last = get_post_meta($id, 'last_name', true);
            $name = esc_html(trim("$first $last"));
            $phone = esc_html(get_post_meta($id, 'phone', true));
            $addr = esc_html(get_post_meta($id, 'address', true));
            $state = esc_html(get_post_meta($id, 'state', true));
            $subtotal_raw = floatval(get_post_meta($id, 'subtotal', true));
            $subtotal = esc_html(number_format($subtotal_raw, 2));
            $products = esc_html(get_post_meta($id, 'products', true));
            $date = esc_html(get_post_meta($id, 'timestamp', true));
            $note = esc_textarea(get_post_meta($id, 'note', true));
            $recovered = get_post_meta($id, 'recovered', true) ? '✅ Yes' : '❌ No';

            echo "<tr id='row-$id'>
                <td>$name</td>
                <td>$phone</td>
                <td>$addr</td>
                <td>$state</td>
                <td>$subtotal</td>
                <td>$products</td>
                <td>$date</td>
                <td>$recovered</td>
                <td>
                    <textarea id='note-$id' rows='2' style='width:100%;'>$note</textarea>
                    <button class='button button-small' onclick='saveNote($id)'>Update</button>
                </td>
            </tr>";
        }
    }

    echo '</tbody></table>';
}

// Export CSV
add_action('admin_post_export_abandoned_checkouts', function () {
    $leads = get_posts([
        'post_type' => 'abandoned_lead',
        'post_status' => 'publish',
        'numberposts' => -1
    ]);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="abandoned-checkouts.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Phone', 'Address', 'State', 'Subtotal', 'Products', 'Date', 'Recovered', 'Note']);

    foreach ($leads as $lead) {
        $id = $lead->ID;
        fputcsv($output, [
            get_post_meta($id, 'first_name', true) . ' ' . get_post_meta($id, 'last_name', true),
            get_post_meta($id, 'phone', true),
            get_post_meta($id, 'address', true),
            get_post_meta($id, 'state', true),
            get_post_meta($id, 'subtotal', true),
            get_post_meta($id, 'products', true),
            get_post_meta($id, 'timestamp', true),
            get_post_meta($id, 'recovered', true) ? 'Yes' : 'No',
            get_post_meta($id, 'note', true)
        ]);
    }

    fclose($output);
    exit;
});
