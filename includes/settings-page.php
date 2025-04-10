<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

function render_settings_page() {
    // Save settings if the form is submitted
    if (isset($_POST['save_settings'])) {
        update_option('abandoned_cleanup_interval', intval($_POST['cleanup_interval']));
        update_option('enable_email_notifications', isset($_POST['enable_email_notifications']) ? 1 : 0);
        echo '<div class="updated"><p>Settings saved successfully!</p></div>';
    }

    // Get current settings
    $cleanup_interval = get_option('abandoned_cleanup_interval', 7); // Default to 7 days
    $enable_email_notifications = get_option('enable_email_notifications', 0);

    echo '<div class="wrap">';
    echo '<h1>Settings</h1>';
    echo '<form method="post">';
    echo '<table class="form-table">';
    
    // Cleanup Interval Setting
    echo '<tr>';
    echo '<th scope="row"><label for="cleanup_interval">Cleanup Interval (days)</label></th>';
    echo '<td><input type="number" id="cleanup_interval" name="cleanup_interval" value="' . esc_attr($cleanup_interval) . '" min="3" max="30" /></td>';
    echo '</tr>';
    
       
    echo '</table>';
    echo '<p class="submit"><button type="submit" name="save_settings" class="button button-primary">Save Settings</button></p>';
    echo '</form>';
    echo '</div>';
}
