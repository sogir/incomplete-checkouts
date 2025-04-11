<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

function render_settings_page() {
    // Save settings if the form is submitted
    if (isset($_POST['save_settings'])) {
        $cleanup_interval = intval($_POST['cleanup_interval']);
        if ($cleanup_interval < 3 || $cleanup_interval > 30) {
            echo '<div class="error"><p>Cleanup interval must be between 3 and 30 days.</p></div>';
        } else {
            update_option('abandoned_cleanup_interval', $cleanup_interval);
            act_update_cleanup_schedule(); // Update the scheduled event
            echo '<div class="updated"><p>Settings saved successfully!</p></div>';
        }
    }

    // Get current settings
    $cleanup_interval = get_option('abandoned_cleanup_interval', 7); // Default to 7 days

    echo '<div class="wrap">';
    echo '<h1>Incomplete Checkout Settings</h1>';
    echo '<form method="post">';
    echo '<table class="form-table">';
    
    // Cleanup Interval Setting
    echo '<tr>';
    echo '<th scope="row"><label for="cleanup_interval">Cleanup Interval (days)</label></th>';
    echo '<td>';
    echo '<input type="number" id="cleanup_interval" name="cleanup_interval" value="' . esc_attr($cleanup_interval) . '" min="3" max="30" />';
    echo '<p class="description">Abandoned checkouts older than this many days will be automatically deleted.</p>';
    echo '</td>';
    echo '</tr>';
    
    // Display next scheduled cleanup time
    act_display_next_cleanup_time();
    
    // Add manual cleanup button
    act_add_manual_cleanup_button();
    
    echo '</table>';
    echo '<p class="submit"><button type="submit" name="save_settings" class="button button-primary">Save Settings</button></p>';
    echo '</form>';
    echo '</div>';
}
