<?php
if (!defined('ABSPATH')) exit;

// Render the analytics page
function render_abandoned_analytics_page() {
    // Get current time in Unix timestamp
    $current_time = time();
    
    // Calculate time periods
    $one_day_ago = $current_time - (24 * 60 * 60);
    $seven_days_ago = $current_time - (7 * 24 * 60 * 60);
    $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
    
    // Get analytics data
    $analytics = get_abandoned_analytics($one_day_ago, $seven_days_ago, $thirty_days_ago);
    
    // Calculate conversion ratios
    $one_day_ratio = ($analytics['one_day_total'] > 0) ? 
        round(($analytics['one_day_success'] / $analytics['one_day_total']) * 100, 2) : 0;
    
    $seven_day_ratio = ($analytics['seven_day_total'] > 0) ? 
        round(($analytics['seven_day_success'] / $analytics['seven_day_total']) * 100, 2) : 0;
    
    $thirty_day_ratio = ($analytics['thirty_day_total'] > 0) ? 
        round(($analytics['thirty_day_success'] / $analytics['thirty_day_total']) * 100, 2) : 0;
    
    $all_time_ratio = ($analytics['all_time_total'] > 0) ? 
        round(($analytics['all_time_success'] / $analytics['all_time_total']) * 100, 2) : 0;
    
    // Render the page
    ?>
    <div class="wrap">
        <h1>Abandoned Checkout Analytics</h1>
        
        <div class="analytics-container">
            <style>
                .analytics-container {
                    margin-top: 20px;
                }
                .analytics-cards {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .analytics-card {
                    background-color: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                    padding: 20px;
                    min-width: 250px;
                    flex: 1;
                }
                .analytics-card h2 {
                    margin-top: 0;
                    color: #23282d;
                    font-size: 18px;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 10px;
                }
                .analytics-stat {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    font-size: 14px;
                }
                .analytics-stat-label {
                    color: #555;
                }
                .analytics-stat-value {
                    font-weight: bold;
                    color: #23282d;
                }
                .analytics-stat-success {
                    color: #46b450;
                }
                .analytics-stat-pending {
                    color: #dc3232;
                }
                .analytics-stat-ratio {
                    color: #0073aa;
                }
                .analytics-section {
                    margin-bottom: 30px;
                }
                .analytics-section h2 {
                    margin-bottom: 20px;
                    color: #23282d;
                    font-size: 20px;
                }
                .analytics-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                .analytics-table th, .analytics-table td {
                    padding: 12px 15px;
                    text-align: left;
                    border-bottom: 1px solid #e5e5e5;
                }
                .analytics-table th {
                    background-color: #f5f5f5;
                    font-weight: bold;
                }
                .analytics-table tr:hover {
                    background-color: #f9f9f9;
                }
                .analytics-progress-bar {
                    height: 8px;
                    background-color: #e5e5e5;
                    border-radius: 4px;
                    margin-top: 5px;
                    overflow: hidden;
                }
                .analytics-progress-fill {
                    height: 100%;
                    background-color: #0073aa;
                }
            </style>
            
            <!-- Summary Cards -->
            <div class="analytics-section">
                <h2>Summary</h2>
                <div class="analytics-cards">
                    <!-- Last 24 Hours Card -->
                    <div class="analytics-card">
                        <h2>Last 24 Hours</h2>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Total Tracked:</span>
                            <span class="analytics-stat-value"><?php echo $analytics['one_day_total']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Completed (✅):</span>
                            <span class="analytics-stat-value analytics-stat-success"><?php echo $analytics['one_day_success']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Pending (❌):</span>
                            <span class="analytics-stat-value analytics-stat-pending"><?php echo $analytics['one_day_pending']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Conversion Rate:</span>
                            <span class="analytics-stat-value analytics-stat-ratio"><?php echo $one_day_ratio; ?>%</span>
                        </div>
                        <div class="analytics-progress-bar">
                            <div class="analytics-progress-fill" style="width: <?php echo $one_day_ratio; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Last 7 Days Card -->
                    <div class="analytics-card">
                        <h2>Last 7 Days</h2>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Total Tracked:</span>
                            <span class="analytics-stat-value"><?php echo $analytics['seven_day_total']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Completed (✅):</span>
                            <span class="analytics-stat-value analytics-stat-success"><?php echo $analytics['seven_day_success']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Pending (❌):</span>
                            <span class="analytics-stat-value analytics-stat-pending"><?php echo $analytics['seven_day_pending']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Conversion Rate:</span>
                            <span class="analytics-stat-value analytics-stat-ratio"><?php echo $seven_day_ratio; ?>%</span>
                        </div>
                        <div class="analytics-progress-bar">
                            <div class="analytics-progress-fill" style="width: <?php echo $seven_day_ratio; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Last 30 Days Card -->
                    <div class="analytics-card">
                        <h2>Last 30 Days</h2>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Total Tracked:</span>
                            <span class="analytics-stat-value"><?php echo $analytics['thirty_day_total']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Completed (✅):</span>
                            <span class="analytics-stat-value analytics-stat-success"><?php echo $analytics['thirty_day_success']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Pending (❌):</span>
                            <span class="analytics-stat-value analytics-stat-pending"><?php echo $analytics['thirty_day_pending']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Conversion Rate:</span>
                            <span class="analytics-stat-value analytics-stat-ratio"><?php echo $thirty_day_ratio; ?>%</span>
                        </div>
                        <div class="analytics-progress-bar">
                            <div class="analytics-progress-fill" style="width: <?php echo $thirty_day_ratio; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- All Time Card -->
                    <div class="analytics-card">
                        <h2>All Time</h2>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Total Tracked:</span>
                            <span class="analytics-stat-value"><?php echo $analytics['all_time_total']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Completed (✅):</span>
                            <span class="analytics-stat-value analytics-stat-success"><?php echo $analytics['all_time_success']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Pending (❌):</span>
                            <span class="analytics-stat-value analytics-stat-pending"><?php echo $analytics['all_time_pending']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Conversion Rate:</span>
                            <span class="analytics-stat-value analytics-stat-ratio"><?php echo $all_time_ratio; ?>%</span>
                        </div>
                        <div class="analytics-progress-bar">
                            <div class="analytics-progress-fill" style="width: <?php echo $all_time_ratio; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Analytics Table -->
            <div class="analytics-section">
                <h2>Detailed Analytics</h2>
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Time Period</th>
                            <th>Total Tracked</th>
                            <th>Completed (✅)</th>
                            <th>Pending (❌)</th>
                            <th>Conversion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Last 24 Hours</td>
                            <td><?php echo $analytics['one_day_total']; ?></td>
                            <td><?php echo $analytics['one_day_success']; ?></td>
                            <td><?php echo $analytics['one_day_pending']; ?></td>
                            <td><?php echo $one_day_ratio; ?>%</td>
                        </tr>
                        <tr>
                            <td>Last 7 Days</td>
                            <td><?php echo $analytics['seven_day_total']; ?></td>
                            <td><?php echo $analytics['seven_day_success']; ?></td>
                            <td><?php echo $analytics['seven_day_pending']; ?></td>
                            <td><?php echo $seven_day_ratio; ?>%</td>
                        </tr>
                        <tr>
                            <td>Last 30 Days</td>
                            <td><?php echo $analytics['thirty_day_total']; ?></td>
                            <td><?php echo $analytics['thirty_day_success']; ?></td>
                            <td><?php echo $analytics['thirty_day_pending']; ?></td>
                            <td><?php echo $thirty_day_ratio; ?>%</td>
                        </tr>
                        <tr>
                            <td>All Time</td>
                            <td><?php echo $analytics['all_time_total']; ?></td>
                            <td><?php echo $analytics['all_time_success']; ?></td>
                            <td><?php echo $analytics['all_time_pending']; ?></td>
                            <td><?php echo $all_time_ratio; ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Database Stats -->
            <div class="analytics-section">
                <h2>Database Statistics</h2>
                <div class="analytics-card">
                    <div class="analytics-stat">
                        <span class="analytics-stat-label">Current Active Leads:</span>
                        <span class="analytics-stat-value"><?php echo $analytics['current_leads']; ?></span>
                    </div>
                    <div class="analytics-stat">
                        <span class="analytics-stat-label">Oldest Lead Date:</span>
                        <span class="analytics-stat-value">
                            <?php 
                            if (!empty($analytics['oldest_lead_date'])) {
                                echo act_convert_to_bangladesh_time($analytics['oldest_lead_date']);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="analytics-stat">
                        <span class="analytics-stat-label">Newest Lead Date:</span>
                        <span class="analytics-stat-value">
                            <?php 
                            if (!empty($analytics['newest_lead_date'])) {
                                echo act_convert_to_bangladesh_time($analytics['newest_lead_date']);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Get analytics data for abandoned leads
 * 
 * @param int $one_day_ago Timestamp for 24 hours ago
 * @param int $seven_days_ago Timestamp for 7 days ago
 * @param int $thirty_days_ago Timestamp for 30 days ago
 * @return array Analytics data
 */
function get_abandoned_analytics($one_day_ago, $seven_days_ago, $thirty_days_ago) {
    global $wpdb;
    
    // Initialize analytics array
    $analytics = [
        'one_day_total' => 0,
        'one_day_success' => 0,
        'one_day_pending' => 0,
        
        'seven_day_total' => 0,
        'seven_day_success' => 0,
        'seven_day_pending' => 0,
        
        'thirty_day_total' => 0,
        'thirty_day_success' => 0,
        'thirty_day_pending' => 0,
        
        'all_time_total' => 0,
        'all_time_success' => 0,
        'all_time_pending' => 0,
        
        'current_leads' => 0,
        'oldest_lead_date' => null,
        'newest_lead_date' => null,
    ];
    
    // Get post type ID for abandoned_lead
    $post_type_id = get_post_type_object('abandoned_lead')->name;
    
    // Get current leads count
    $current_leads_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
        $post_type_id
    );
    $analytics['current_leads'] = $wpdb->get_var($current_leads_query);
    
    // Get oldest and newest lead dates
    if ($analytics['current_leads'] > 0) {
        // Get oldest lead
        $oldest_lead_query = $wpdb->prepare(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = 'timestamp'
            ORDER BY pm.meta_value ASC LIMIT 1",
            $post_type_id
        );
        $analytics['oldest_lead_date'] = $wpdb->get_var($oldest_lead_query);
        
        // Get newest lead
        $newest_lead_query = $wpdb->prepare(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = 'timestamp'
            ORDER BY pm.meta_value DESC LIMIT 1",
            $post_type_id
        );
        $analytics['newest_lead_date'] = $wpdb->get_var($newest_lead_query);
    }
    
    // Get all leads with their timestamps and statuses
    $leads_query = $wpdb->prepare(
        "SELECT p.ID, 
            MAX(CASE WHEN pm.meta_key = 'timestamp' THEN pm.meta_value END) as timestamp,
            MAX(CASE WHEN pm.meta_key = 'status' THEN pm.meta_value END) as status
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = %s AND p.post_status = 'publish'
        GROUP BY p.ID",
        $post_type_id
    );
    
    $leads = $wpdb->get_results($leads_query);
    
    // Process leads for analytics
    foreach ($leads as $lead) {
        $timestamp = is_numeric($lead->timestamp) ? intval($lead->timestamp) : strtotime($lead->timestamp);
        $is_success = ($lead->status === '✅');
        
        // All time stats
        $analytics['all_time_total']++;
        if ($is_success) {
            $analytics['all_time_success']++;
        } else {
            $analytics['all_time_pending']++;
        }
        
        // Last 30 days
        if ($timestamp >= $thirty_days_ago) {
            $analytics['thirty_day_total']++;
            if ($is_success) {
                $analytics['thirty_day_success']++;
            } else {
                $analytics['thirty_day_pending']++;
            }
            
            // Last 7 days
            if ($timestamp >= $seven_days_ago) {
                $analytics['seven_day_total']++;
                if ($is_success) {
                    $analytics['seven_day_success']++;
                } else {
                    $analytics['seven_day_pending']++;
                }
                
                // Last 24 hours
                if ($timestamp >= $one_day_ago) {
                    $analytics['one_day_total']++;
                    if ($is_success) {
                        $analytics['one_day_success']++;
                    } else {
                        $analytics['one_day_pending']++;
                    }
                }
            }
        }
    }
    
    // Get data from the database for deleted leads (if you're tracking them)
    // This would require a separate table to track deleted leads
    // For now, we'll use the option table to store aggregate data
    
    // Get stored analytics data for deleted leads
    $deleted_analytics = get_option('abandoned_checkout_deleted_analytics', [
        'one_day_total' => 0,
        'one_day_success' => 0,
        'seven_day_total' => 0,
        'seven_day_success' => 0,
        'thirty_day_total' => 0,
        'thirty_day_success' => 0,
        'all_time_total' => 0,
        'all_time_success' => 0,
    ]);
    
    // Add deleted leads data to analytics
    $analytics['one_day_total'] += $deleted_analytics['one_day_total'];
    $analytics['one_day_success'] += $deleted_analytics['one_day_success'];
    $analytics['one_day_pending'] = $analytics['one_day_total'] - $analytics['one_day_success'];
    
    $analytics['seven_day_total'] += $deleted_analytics['seven_day_total'];
    $analytics['seven_day_success'] += $deleted_analytics['seven_day_success'];
    $analytics['seven_day_pending'] = $analytics['seven_day_total'] - $analytics['seven_day_success'];
    
    $analytics['thirty_day_total'] += $deleted_analytics['thirty_day_total'];
    $analytics['thirty_day_success'] += $deleted_analytics['thirty_day_success'];
    $analytics['thirty_day_pending'] = $analytics['thirty_day_total'] - $analytics['thirty_day_success'];
    
    $analytics['all_time_total'] += $deleted_analytics['all_time_total'];
    $analytics['all_time_success'] += $deleted_analytics['all_time_success'];
    $analytics['all_time_pending'] = $analytics['all_time_total'] - $analytics['all_time_success'];
    
    return $analytics;
}

/**
 * Track deleted leads for analytics
 * This function should be called before deleting a lead
 */
function track_deleted_lead($lead_id) {
    $timestamp = get_post_meta($lead_id, 'timestamp', true);
    $status = get_post_meta($lead_id, 'status', true);
    
    if (empty($timestamp)) return;
    
    $timestamp = is_numeric($timestamp) ? intval($timestamp) : strtotime($timestamp);
    $is_success = ($status === '✅');
    
    // Get current time
    $current_time = time();
    $one_day_ago = $current_time - (24 * 60 * 60);
    $seven_days_ago = $current_time - (7 * 24 * 60 * 60);
    $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
    
    // Get stored analytics
    $deleted_analytics = get_option('abandoned_checkout_deleted_analytics', [
        'one_day_total' => 0,
        'one_day_success' => 0,
        'seven_day_total' => 0,
        'seven_day_success' => 0,
        'thirty_day_total' => 0,
        'thirty_day_success' => 0,
        'all_time_total' => 0,
        'all_time_success' => 0,
    ]);
    
    // Update analytics based on lead timestamp
    $deleted_analytics['all_time_total']++;
    if ($is_success) {
        $deleted_analytics['all_time_success']++;
    }
    
    if ($timestamp >= $thirty_days_ago) {
        $deleted_analytics['thirty_day_total']++;
        if ($is_success) {
            $deleted_analytics['thirty_day_success']++;
        }
        
        if ($timestamp >= $seven_days_ago) {
            $deleted_analytics['seven_day_total']++;
            if ($is_success) {
                $deleted_analytics['seven_day_success']++;
            }
            
            if ($timestamp >= $one_day_ago) {
                $deleted_analytics['one_day_total']++;
                if ($is_success) {
                    $deleted_analytics['one_day_success']++;
                }
            }
        }
    }
    
    // Save updated analytics
    update_option('abandoned_checkout_deleted_analytics', $deleted_analytics);
}

// Hook into lead deletion to track analytics
add_action('before_delete_post', function($post_id) {
    if (get_post_type($post_id) === 'abandoned_lead') {
        track_deleted_lead($post_id);
    }
});

// Schedule daily cleanup of old analytics data
if (!wp_next_scheduled('abandoned_checkout_analytics_cleanup')) {
    wp_schedule_event(time(), 'daily', 'abandoned_checkout_analytics_cleanup');
}

add_action('abandoned_checkout_analytics_cleanup', 'cleanup_abandoned_analytics');

function cleanup_abandoned_analytics() {
    // Get current time
    $current_time = time();
    $one_day_ago = $current_time - (24 * 60 * 60);
    $seven_days_ago = $current_time - (7 * 24 * 60 * 60);
    $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
    
    // Get stored analytics
    $deleted_analytics = get_option('abandoned_checkout_deleted_analytics', [
        'one_day_total' => 0,
        'one_day_success' => 0,
        'seven_day_total' => 0,
        'seven_day_success' => 0,
        'thirty_day_total' => 0,
        'thirty_day_success' => 0,
        'all_time_total' => 0,
        'all_time_success' => 0,
    ]);
    
    // Reset daily stats (they'll be older than 24 hours now)
    $deleted_analytics['one_day_total'] = 0;
    $deleted_analytics['one_day_success'] = 0;
    
    // Save updated analytics
    update_option('abandoned_checkout_deleted_analytics', $deleted_analytics);
}
