<?php
if (!defined('ABSPATH')) exit;

// Admin menu
add_action('admin_menu', function () {
    add_menu_page('Incomplete Checkouts', 'Incomplete Checkouts', 'manage_woocommerce', 'incomplete-checkouts', 'render_abandoned_admin_page', 'dashicons-cart');
    
    // Submenu for Analytics
    add_submenu_page(
        'incomplete-checkouts',
        'Incomplete Checkout Analytics',
        'Analytics',
        'manage_woocommerce',
        'incomplete-checkout-analytics',
        'render_abandoned_analytics_page'
    );

    // Submenu for Settings
    add_submenu_page(
        'incomplete-checkouts', // Parent slug
        'Settings', // Page title
        'Settings', // Menu title
        'manage_woocommerce', // Capability
        'incomplete-checkouts-settings', // Menu slug
        'render_settings_page' // Callback function
    );
});


// Callback functions for the pages
require_once plugin_dir_path(__FILE__) . 'analytics-page.php';
require_once plugin_dir_path(__FILE__) . 'settings-page.php';

// Admin page content
function render_abandoned_admin_page() {
    $nonce = wp_create_nonce("abandon_note_nonce");
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $date_filter = isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : '';
    $items_per_page = isset($_GET['items_per_page']) ? intval($_GET['items_per_page']) : 10;

    echo '<div class="wrap">
    <h1>Incomplete Checkouts</h1>
        <form method="get" style="margin-bottom: 20px; display: flex; align-items: center; gap: 20px;">
            <input type="hidden" name="page" value="incomplete-checkouts">

            <!-- Search Field -->
            <div>
                <label for="search_term" style="font-weight: bold; margin-right: 5px;">Search:</label>
                <input type="text" id="search_term" name="s" value="' . esc_attr($search_term) . '" placeholder="Search by name or phone" style="padding: 5px; width: 300px;" />
            </div>

            <!-- Date Filter -->
            <div>
                <label for="date_filter" style="font-weight: bold; margin-right: 5px;">Filter by Date:</label>
                <input type="date" id="date_filter" name="date_filter" value="' . esc_attr($date_filter) . '" style="padding: 5px;" />
            </div>

            <!-- Items Per Page -->
            <div>
                <label for="items_per_page" style="font-weight: bold; margin-right: 5px;">Items Per Page:</label>
                <select id="items_per_page" name="items_per_page" style="padding: 5px;">
                    <option value="10" ' . selected($items_per_page, 10, false) . '>10</option>
                    <option value="20" ' . selected($items_per_page, 20, false) . '>20</option>
                    <option value="50" ' . selected($items_per_page, 50, false) . '>50</option>
                    <option value="100" ' . selected($items_per_page, 100, false) . '>100</option>
                </select>
            </div>

            <!-- Submit Button -->
            <div>
                <button type="submit" class="button button-primary">Filter</button>
            </div>
        </form>

        <!-- Bulk Actions and Export Buttons -->
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1">Bulk Actions</option>
                    <option value="delete">Delete</option>
                    <option value="export">Export Selected</option>
                </select>
                <button id="doaction" class="button action">Apply</button>
            </div>
            
            <!-- Export All CSV Button -->
            <div class="alignright">
                <form method="post" action="' . admin_url('admin-post.php') . '" style="margin-bottom: 20px;">
                    <input type="hidden" name="action" value="export_abandoned_checkouts">
                    <input type="submit" class="button button-secondary" value="Export All as CSV">
                </form>
            </div>
            <div class="clear"></div>
        </div>

        <div id="abandoned-table-wrap">';

        // Add custom CSS for pagination, hover effects, and bulk actions
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

        /* Style the form container */
        .wrap form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
        }

        /* Style labels */
        label {
            font-weight: bold;
            margin-right: 5px;
        }

        /* Style inputs and dropdowns */
        input[type="text"], input[type="date"], select {
            padding: 5px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        /* Style buttons */
        .button-primary {
            background-color: #007cba;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            cursor: pointer;
        }

        .button-primary:hover {
            background-color: #005a9c;
        }

        .button-secondary {
            background-color: #f3f3f3;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            cursor: pointer;
        }

        .button-secondary:hover {
            background-color: #e2e2e2;
        }

        select {
            padding: 5px 10px; /* Add padding for spacing */
            font-size: 14px; /* Ensure readable font size */
            border: 1px solid #ccc; /* Add border for clarity */
            border-radius: 4px; /* Rounded corners */
            width: auto; /* Automatically adjust width */
            min-width: 150px; /* Ensure minimum width to prevent overlap */
            text-align: left; /* Center-align the text */
        }   

        /* Bulk actions styling */
        .bulkactions {
            margin-bottom: 10px;
        }

        #bulk-action-selector-top {
            margin-right: 5px;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .lead-checkbox {
            margin-left: 8px;
        }

        #select-all-checkouts {
            margin-left: 8px;
        }
        </style>';

    // Render the abandoned leads table
    render_abandoned_table($search_term, $paged, $items_per_page, $date_filter);

    echo '</div></div>';
    
    // Add JavaScript for Copy and Call buttons
    echo '<script>
    // Copy to clipboard function
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text).then(function () {
            // Change button text to "Copied"
            button.textContent = "Copied";

            // Reset button text after 2 seconds
            setTimeout(function () {
                button.textContent = "Copy";
            }, 2000);
        }).catch(function (err) {
            console.error("Could not copy text: ", err);
        });
    }

    // Call phone function
    function callPhone(phone) {
        window.location.href = "tel:" + phone;
    }
</script>';

    // Add CSS for hover effects
    echo '<style>
    /* Base styles for text and button containers */
    .text-container {
        margin-bottom: 5px;
    }

    .button-container {
        display: none;
        margin-top: 5px;
    }

    /* Show buttons on row hover */
    tr:hover .button-container {
        display: block;
    }

    /* Base button styles */
    .copy-button, .call-button {
        margin-right: 5px;
        padding: 5px 10px;
        font-size: 12px;
        cursor: pointer;
        background-color: #007cba;
        color: #fff;
        border: none;
        border-radius: 3px;
    }

    .copy-button:hover, .call-button:hover {
        background-color: #005a9c;
    }

    /* Status action buttons */
    .button-container .button-small {
        margin-right: 5px;
        padding: 5px 10px;
        font-size: 12px;
        cursor: pointer;
        background-color: #007cba;
        color: #fff;
        border: none;
        border-radius: 3px;
    }

    .button-container .button-small:hover {
        background-color: #005a9c;
        
        color: white;
    }

    /* Specific styling for delete button */
    .button-container .delete-button {
        background-color: #dc3545;
        color: white;
    }

    .button-container .delete-button:hover {
        background-color: #c82333;
    }

    /* Note update button - always visible */
    .button-save-note {
        margin-top: 5px;
        padding: 5px 10px;
        font-size: 12px;
        cursor: pointer;
        background-color: #007cba;
        color: #fff;
        border: none;
        border-radius: 3px;
    }

    .button-save-note:hover {
        background-color: #005a9c;
        color: white;
    }
</style>';

    // Add the JavaScript snippet for handling status updates and bulk actions
    echo '<script>
    jQuery(document).ready(function($) {
        // Select all checkboxes
        $("#select-all-checkouts").on("change", function() {
            $(".lead-checkbox").prop("checked", $(this).prop("checked"));
        });
        
        // Handle bulk actions
        $("#doaction").on("click", function(e) {
            e.preventDefault();
            
            const selectedAction = $("#bulk-action-selector-top").val();
            if (selectedAction === "-1") {
                alert("Please select an action");
                return;
            }
            
            const selectedLeads = $(".lead-checkbox:checked").map(function() {
                return $(this).val();
            }).get();
            
            if (selectedLeads.length === 0) {
                alert("Please select at least one checkout");
                return;
            }
            
            if (selectedAction === "delete") {
                if (confirm(`Are you sure you want to delete ${selectedLeads.length} selected checkouts?`)) {
                    bulkDeleteLeads(selectedLeads);
                }
            } else if (selectedAction === "export") {
                bulkExportLeads(selectedLeads);
            }
        });
    });

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
        let saveButton = document.querySelector("#row-" + id + " .button-save-note");
        let noteBox = document.getElementById("note-" + id); // Reference to the note textarea
        let successMessage = document.getElementById("success-message-" + id);

        // If the success message doesnt exist, create it
        if (!successMessage) {
            successMessage = document.createElement("div");
            successMessage.id = "success-message-" + id;
            successMessage.style.marginTop = "5px"; // Add spacing between the note box and the message
            successMessage.style.color = "green";
            successMessage.style.fontSize = "12px";
            noteBox.parentNode.appendChild(successMessage); // Append the message beneath the note box
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
        .then((res) => res.json())
        .then((data) => {
            if (data.success) {
                successMessage.textContent = "Note saved ✅";
                successMessage.style.color = "green";
            } else {
                successMessage.textContent = "Failed to save ❌";
                successMessage.style.color = "red";
                console.error(data.data?.reason || "Unknown error");
            }
        })
        .finally(() => {
            saveButton.disabled = false;
            saveButton.textContent = "Update";

            // Clear the success message after 2 seconds
            setTimeout(() => {
                successMessage.textContent = "";
            }, 1500);
        });
    }
   
    // Function to handle bulk deletion
    function bulkDeleteLeads(leadIds) {
        const $button = jQuery("#doaction");
        $button.prop("disabled", true).text("Deleting...");
        
        jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "bulk_delete_abandoned_leads",
                lead_ids: leadIds,
                nonce: abandon_nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove deleted rows from the table
                    leadIds.forEach(id => {
                        jQuery(`#row-${id}`).remove();
                    });
                    alert(`Successfully deleted ${response.data.deleted} checkouts`);
                } else {
                    alert("Error: " + (response.data?.reason || "Unknown error"));
                }
            },
            error: function() {
                alert("Server error occurred");
            },
            complete: function() {
                $button.prop("disabled", false).text("Apply");
                // Uncheck the select all checkbox
                jQuery("#select-all-checkouts").prop("checked", false);
            }
        });
    }

    // Function to handle bulk export
    function bulkExportLeads(leadIds) {
        // Create a form to submit for CSV download
        const form = document.createElement("form");
        form.method = "POST";
        form.action = ajaxurl;
        
        // Add action parameter
        const actionInput = document.createElement("input");
        actionInput.type = "hidden";
        actionInput.name = "action";
        actionInput.value = "export_selected_abandoned_checkouts";
        form.appendChild(actionInput);
        
        // Add nonce parameter
        const nonceInput = document.createElement("input");
        nonceInput.type = "hidden";
        nonceInput.name = "nonce";
        nonceInput.value = abandon_nonce;
        form.appendChild(nonceInput);
        
        // Add lead IDs parameter
        leadIds.forEach(id => {
            const leadInput = document.createElement("input");
            leadInput.type = "hidden";
            leadInput.name = "lead_ids[]";
            leadInput.value = id;
            form.appendChild(leadInput);
        });
        
        // Submit the form
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        
        // Uncheck the select all checkbox
        jQuery("#select-all-checkouts").prop("checked", false);
    }
    </script>';
}

function render_abandoned_table($search = '', $paged = 1, $posts_per_page = 10, $date_filter = '') {
    $args = [
        'post_type' => 'abandoned_lead',
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        'meta_query' => [
            // Exclude leads that were recovered within 1 hour
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
    ];

    if ($search) {
        // Add search conditions while maintaining the exclusion
        $args['meta_query'] = [
            'relation' => 'AND',
            // Keep the exclusion condition
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
            ],
            // Add the search condition
            [
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
            ]
        ];
    }

    if ($date_filter) {
        $args['date_query'] = [
            [
                'after' => $date_filter,
                'before' => $date_filter . ' 23:59:59',
                'inclusive' => true,
            ],
        ];
    }


    // Query for the current page
    $query = new WP_Query($args);

    // Query for the total number of leads (without pagination)
    $total_args = $args;
    unset($total_args['posts_per_page']);
    unset($total_args['paged']);
    $total_query = new WP_Query($total_args);
    $total_count = $total_query->found_posts;

    // Calculate the range of items being shown
    $start_item = ($paged - 1) * $posts_per_page + 1;
    $end_item = min($paged * $posts_per_page, $total_count);

    echo '<table class="widefat striped">
    <thead>
        <tr>
            <th><input type="checkbox" id="select-all-checkouts"></th>
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
        echo '<tr><td colspan="11">No abandoned leads found.</td></tr>';
    } else {
        while ($query->have_posts()) {
            $query->the_post();
        $id = get_the_ID();
        $first = get_post_meta($id, 'first_name', true);
        $last = get_post_meta($id, 'last_name', true);
        
        // Fix for empty name fields
        $first = !empty($first) ? esc_html($first) : '';
        $last = !empty($last) ? esc_html($last) : '';
        
        // Create a proper name display
        $name = trim("$first $last");
        if (empty($name)) {
            $name = '<em>No Name Provided</em>';
        }
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
            
            // Get timestamp in readable format with Bangladesh timezone
            $timestamp_readable = get_post_meta($id, 'timestamp_readable', true);
            if (empty($timestamp_readable)) {
                // If timestamp_readable doesn't exist, convert the Unix timestamp to Bangladesh time
                $unix_timestamp = get_post_meta($id, 'timestamp', true);
                if (is_numeric($unix_timestamp)) {
                    $timestamp_readable = act_convert_to_bangladesh_time($unix_timestamp);
                } else {
                    $timestamp_readable = $unix_timestamp; // Just use whatever is stored
                }
            }
            $date = esc_html($timestamp_readable);
            
            $note = esc_textarea(get_post_meta($id, 'note', true));
            $status = get_post_meta($id, 'status', true) === '✅' ? '✅' : '❌';

            echo "<tr id='row-$id'>
    <td><input type='checkbox' class='lead-checkbox' value='$id'></td>
    <td>$date</td>
    <td>
        <div class='text-container'>$name</div>
        <div class='button-container'>
            <button class='copy-button' onclick='copyToClipboard(\"$name\", this)'>Copy</button>
        </div>
    </td>
    <td>
        <div class='text-container'>$phone</div>
        <div class='button-container'>
            <button class='call-button' onclick='callPhone(\"$phone\")'>Call</button>
            <button class='copy-button' onclick='copyToClipboard(\"$phone\", this)'>Copy</button>
        </div>
    </td>
    <td>
        <div class='text-container'>$addr</div>
        <div class='button-container'>
            <button class='copy-button' onclick='copyToClipboard(\"$addr\", this)'>Copy</button>
        </div>
    </td>
    <td>
        <div class='text-container'>$state</div>
        <div class='button-container'>
            <button class='copy-button' onclick='copyToClipboard(\"$state\", this)'>Copy</button>
        </div>
    </td>
    <td>$ip_address</td>
    <td>$subtotal</td>
    <td>$products</td>
    <td>
        <div class='text-container'><span id='status-$id'>$status</span></div>
        <div class='button-container'>
            <button class='button button-small' onclick='updateStatus($id)'>Toggle</button>
            <button class='button button-small delete-button' onclick='deleteLead($id)'>Delete</button>
        </div>
    </td>
    <td>
        <textarea id='note-$id' rows='2' style='width:100%;'>$note</textarea>
        <button class='button button-small button-save-note' onclick='saveNote($id)'>Update</button>
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

    // Showing X of Y abandoned checkouts
    echo '<div class="table-summary">
        Showing ' . $start_item . ' to ' . $end_item . ' of ' . $total_count . ' incomplete checkouts
    </div>';

    wp_reset_postdata();
}


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
        
        // Get timestamp in readable format for CSV with Bangladesh timezone
        $timestamp_readable = get_post_meta($id, 'timestamp_readable', true);
        if (empty($timestamp_readable)) {
            // If timestamp_readable doesn't exist, convert the Unix timestamp to Bangladesh time
            $unix_timestamp = get_post_meta($id, 'timestamp', true);
            if (is_numeric($unix_timestamp)) {
                $timestamp_readable = act_convert_to_bangladesh_time($unix_timestamp);
            } else {
                $timestamp_readable = $unix_timestamp; // Just use whatever is stored
            }
        }

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
