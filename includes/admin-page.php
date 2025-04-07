<?php
if (!defined('ABSPATH')) exit;

// Admin menu
add_action('admin_menu', function () {
    add_menu_page('Abandoned Checkouts', 'Abandoned Checkouts', 'manage_woocommerce', 'abandoned-checkouts', 'render_abandoned_admin_page', 'dashicons-cart');
});

// Admin page content
function render_abandoned_admin_page() {
    $nonce = wp_create_nonce("abandon_note_nonce");
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

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


        // Add custom CSS for pagination and hover effects
    echo '<style>
    .tablenav-pages {
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }
    .tablenav-pages a, .tablenav-pages span {
        display: inline-block;
        padding: 8px 12px;
        margin: 0 5px;
        border: 1px solid #007cba;
        border-radius: 4px;
        background-color: #007cba;
        color: #fff;
        text-decoration: none;
        font-size: 14px;
        transition: background-color 0.3s ease, color 0.3s ease;
    }
    .tablenav-pages a:hover {
        background-color: #005a9c;
        color: #fff;
    }
    .tablenav-pages .current {
        background-color: rgb(209, 236, 255);
        color: rgb(41, 133, 187);
        font-weight: bold;
        cursor: default;
    }

    /* Hide toggle button by default */
    .status-toggle {
        display: none;
    }

    /* Show toggle button on row hover */
    tr:hover .status-toggle {
        display: inline-block;
    }
    </style>';

    // Render the abandoned leads table
    render_abandoned_table($search_term, $paged);

    echo '</div></div>';

    // Add the JavaScript snippet for handling status updates
    echo '<script>
    const abandon_nonce = "' . $nonce . '";

    function updateStatus(id) {
        let statusElement = document.getElementById("status-" + id);
        let currentStatus = statusElement.textContent.trim();
        let newStatus = currentStatus === "❌" ? "✅" : "❌";

        let updateButton = document.querySelector("#row-" + id + " .button-small");
        updateButton.disabled = true;
        updateButton.textContent = "Updating...";

        fetch(ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "update_abandoned_status",
                lead_id: id,
                status: newStatus,
                nonce: abandon_nonce
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                statusElement.textContent = newStatus;
            } else {
                console.error(data.data?.reason || "Unknown error");
            }
        })
        .finally(() => {
            updateButton.disabled = false;
            updateButton.textContent = "Toggle";
        });
    }

    function deleteLead(id) {
    if (!confirm("Are you sure you want to delete this lead?")) {
        return;
    }

    let deleteButton = document.querySelector("#row-" + id + " .delete-button");
    deleteButton.disabled = true;
    deleteButton.textContent = "Deleting...";

    fetch(ajaxurl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action: "delete_abandoned_lead",
            lead_id: id,
            nonce: abandon_nonce
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById("row-" + id).remove();
        } else {
            alert(data.data?.reason || "Failed to delete lead.");
        }
    })
    .finally(() => {
        deleteButton.disabled = false;
        deleteButton.textContent = "Delete";
    });
}

    function saveNote(id) {
        let note = document.getElementById("note-" + id).value;
        let saveButton = document.querySelector("#row-" + id + " .button-small");
        let successMessage = document.getElementById("success-message-" + id);

        if (!successMessage) {
            successMessage = document.createElement("span");
            successMessage.id = "success-message-" + id;
            successMessage.style.marginLeft = "10px";
            successMessage.style.color = "green";
            successMessage.style.fontSize = "12px";
            saveButton.parentNode.appendChild(successMessage);
        }

        saveButton.disabled = true;
        saveButton.textContent = "Saving...";

        fetch(ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "update_abandoned_note",
                lead_id: id,
                note: note,
                nonce: abandon_nonce
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                successMessage.textContent = "Note saved ✅";
            } else {
                successMessage.textContent = "Failed to save ❌";
                successMessage.style.color = "red";
                console.error(data.data?.reason || "Unknown error");
            }
        })
        .finally(() => {
            saveButton.disabled = false;
            saveButton.textContent = "Update";
            setTimeout(() => {
                successMessage.textContent = "";
            }, 1000);
        });
    }
    </script>';
}


function render_abandoned_table($search = '', $paged = 1, $posts_per_page = 10) {
    $args = [
        'post_type' => 'abandoned_lead',
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
    ];

    if ($search) {
        $args['meta_query'] = [
            'relation' => 'OR',
            [
                'key' => 'first_name',
                'value' => $search,
                'compare' => 'LIKE',
            ],
            [
                'key' => 'last_name',
                'value' => $search,
                'compare' => 'LIKE',
            ],
            [
                'key' => 'phone',
                'value' => $search,
                'compare' => 'LIKE',
            ],
        ];
    }

    $query = new WP_Query($args);

    echo '<table class="widefat striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Address</th>
                <th>State</th>
                <th>IP Address</th>
                <th>Subtotal</th>
                <th>Products</th>
                <th>Status</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>';

    if (!$query->have_posts()) {
        echo '<tr><td colspan="10">No abandoned leads found.</td></tr>';
    } else {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $first = get_post_meta($id, 'first_name', true);
            $last = get_post_meta($id, 'last_name', true);
            $name = esc_html(trim("$first $last"));
            $phone = esc_html(get_post_meta($id, 'phone', true));
            $addr = esc_html(get_post_meta($id, 'address', true));
            $state_code = get_post_meta($id, 'state', true);
            $country_code = 'BD';
            $states = WC()->countries->get_states($country_code);
            $state = isset($states[$state_code]) ? $states[$state_code] : 'Unknown';
            $ip_address = esc_html(get_post_meta($id, 'ip_address', true));
            $subtotal_raw = floatval(get_post_meta($id, 'subtotal', true));
            $subtotal = esc_html(number_format($subtotal_raw, 2));
            $products = esc_html(get_post_meta($id, 'products', true));
            $date = esc_html(get_post_meta($id, 'timestamp', true));
            $note = esc_textarea(get_post_meta($id, 'note', true));
            $status = get_post_meta($id, 'status', true) === '✅' ? '✅' : '❌';

            echo "<tr id='row-$id'>
                <td>$date</td>
                <td>$name</td>
                <td>$phone</td>
                <td>$addr</td>
                <td>$state</td>
                <td>$ip_address</td>
                <td>$subtotal</td>
                <td>$products</td>
                <td>
                    <span id='status-$id'>$status</span>
                    <button class='button button-small' onclick='updateStatus($id)'>Toggle</button>
                    <button class='button button-small delete-button' onclick='deleteLead($id)'>Delete</button>
    
                </td>
                <td>
                    <textarea id='note-$id' rows='2' style='width:100%;'>$note</textarea>
                    <button class='button button-small' onclick='saveNote($id)'>Update</button>
                </td>
            </tr>";
        }
    }

    echo '</tbody></table>';

    $total_pages = $query->max_num_pages;
    if ($total_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'current' => $paged,
            'total' => $total_pages,
        ]);
        echo '</div></div>';
    }

    wp_reset_postdata();
}


// // Export CSV
// add_action('admin_post_export_abandoned_checkouts', function () {
//     $leads = get_posts([
//         'post_type' => 'abandoned_lead',
//         'post_status' => 'publish',
//         'numberposts' => -1
//     ]);

//     header('Content-Type: text/csv');
//     header('Content-Disposition: attachment; filename="abandoned-checkouts.csv"');
//     $output = fopen('php://output', 'w');
//     fputcsv($output, ['Name', 'Phone', 'Address', 'State', 'IP Address', 'Subtotal', 'Products', 'Date', 'Recovered', 'Note']);

//     foreach ($leads as $lead) {
//         $id = $lead->ID;

//         // Convert state code to state name
//         $state_code = get_post_meta($id, 'state', true);
//         $country_code = 'BD'; // Replace with your store's default country code
//         $states = WC()->countries->get_states($country_code);
//         $state = isset($states[$state_code]) ? $states[$state_code] : 'Unknown';

//         fputcsv($output, [
//             get_post_meta($id, 'first_name', true) . ' ' . get_post_meta($id, 'last_name', true),
//             get_post_meta($id, 'phone', true),
//             get_post_meta($id, 'address', true),
//             $state, // Use the readable state name
//             get_post_meta($id, 'ip_address', true), // Include IP address
//             get_post_meta($id, 'subtotal', true),
//             get_post_meta($id, 'products', true),
//             get_post_meta($id, 'timestamp', true),
//             get_post_meta($id, 'recovered', true) ? 'Yes' : 'No',
//             get_post_meta($id, 'note', true)
//         ]);
//     }

//     fclose($output);
//     exit;
// });


// Export CSV
add_action('admin_post_export_abandoned_checkouts', function () {
    $leads = get_posts([
        'post_type' => 'abandoned_lead',
        'post_status' => 'publish',
        'numberposts' => -1
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
        'Status', // Add the status column
        'Note'
    ]);

    // Loop through the leads and add rows to the CSV
    foreach ($leads as $lead) {
        $id = $lead->ID;

        // Convert state code to state name
        $state_code = get_post_meta($id, 'state', true);
        $country_code = 'BD'; // Replace with your store's default country code
        $states = WC()->countries->get_states($country_code);
        $state = isset($states[$state_code]) ? $states[$state_code] : 'Unknown';

        // Get the status
        $status = get_post_meta($id, 'status', true) === '✅' ? 'Confirmed' : 'Pending';

        // Add the row to the CSV
        fputcsv($output, [            
            get_post_meta($id, 'timestamp', true),
            get_post_meta($id, 'first_name', true) . ' ' . get_post_meta($id, 'last_name', true),
            get_post_meta($id, 'phone', true),
            get_post_meta($id, 'address', true),
            $state, // Use the readable state name
            get_post_meta($id, 'ip_address', true), // Include IP address
            get_post_meta($id, 'subtotal', true),
            get_post_meta($id, 'products', true),
            $status, // Include the status column
            get_post_meta($id, 'note', true)
        ]);
    }

    fclose($output);
    exit;
});
