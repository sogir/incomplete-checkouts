<?php
if (!defined('ABSPATH')) exit;


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
    $post_type_id = 'abandoned_lead'; // Ensure this is the correct post type for abandoned checkouts
    
    // Get current leads count - excluding recovered within 1 hour
    $current_leads_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_recovered ON p.ID = pm_recovered.post_id AND pm_recovered.meta_key = 'recovered_within_hour'
        WHERE p.post_type = %s 
        AND p.post_status = 'publish'
        AND (pm_recovered.meta_value IS NULL OR pm_recovered.meta_value != '1')",
        $post_type_id
    );
    $analytics['current_leads'] = $wpdb->get_var($current_leads_query);
    
    // Get oldest and newest lead dates - excluding recovered within 1 hour
    if ($analytics['current_leads'] > 0) {
        // Get oldest lead
        $oldest_lead_query = $wpdb->prepare(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} pm_recovered ON p.ID = pm_recovered.post_id AND pm_recovered.meta_key = 'recovered_within_hour'
            WHERE p.post_type = %s 
            AND p.post_status = 'publish' 
            AND pm.meta_key = 'timestamp'
            AND (pm_recovered.meta_value IS NULL OR pm_recovered.meta_value != '1')
            ORDER BY pm.meta_value ASC LIMIT 1",
            $post_type_id
        );
        $analytics['oldest_lead_date'] = $wpdb->get_var($oldest_lead_query);
        
        // Get newest lead
        $newest_lead_query = $wpdb->prepare(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} pm_recovered ON p.ID = pm_recovered.post_id AND pm_recovered.meta_key = 'recovered_within_hour'
            WHERE p.post_type = %s 
            AND p.post_status = 'publish' 
            AND pm.meta_key = 'timestamp'
            AND (pm_recovered.meta_value IS NULL OR pm_recovered.meta_value != '1')
            ORDER BY pm.meta_value DESC LIMIT 1",
            $post_type_id
        );
        $analytics['newest_lead_date'] = $wpdb->get_var($newest_lead_query);
    }
    
    // Get all leads with their timestamps, statuses, and customer info - excluding recovered within 1 hour
    $leads_query = $wpdb->prepare(
        "SELECT p.ID, 
            MAX(CASE WHEN pm.meta_key = 'timestamp' THEN pm.meta_value END) as timestamp,
            MAX(CASE WHEN pm.meta_key = 'status' THEN pm.meta_value END) as status,
            MAX(CASE WHEN pm.meta_key = 'customer_email' THEN pm.meta_value END) as email,
            MAX(CASE WHEN pm.meta_key = 'customer_phone' THEN pm.meta_value END) as phone
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        LEFT JOIN {$wpdb->postmeta} pm_recovered ON p.ID = pm_recovered.post_id AND pm_recovered.meta_key = 'recovered_within_hour'
        WHERE p.post_type = %s 
        AND p.post_status = 'publish'
        AND (pm_recovered.meta_value IS NULL OR pm_recovered.meta_value != '1')
        GROUP BY p.ID
        ORDER BY timestamp ASC",
        $post_type_id
    );
    
    $leads = $wpdb->get_results($leads_query);
    
    // Track unique customers by email/phone within 1-hour window
    $tracked_customers = [];
    
    // Process leads for analytics
    foreach ($leads as $lead) {
        $timestamp = is_numeric($lead->timestamp) ? intval($lead->timestamp) : strtotime($lead->timestamp);
        $is_success = ($lead->status === '✅');
        $customer_key = !empty($lead->email) ? $lead->email : (!empty($lead->phone) ? $lead->phone : 'unknown_' . $lead->ID);
        
        // Check if this customer has been tracked within the last hour
        $is_revisit = false;
        if (isset($tracked_customers[$customer_key])) {
            $last_visit = $tracked_customers[$customer_key];
            // If this visit is within 1 hour of the last one, consider it a revisit
            if ($timestamp - $last_visit < 3600) { // 3600 seconds = 1 hour
                $is_revisit = true;
            }
        }
        
        // Update the last visit time for this customer
        $tracked_customers[$customer_key] = $timestamp;
        
        // Skip counting if it's a revisit within 1 hour
        if ($is_revisit) {
            continue;
        }
        
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
 * Get abandoned checkout rate compared to successful orders
 * 
 * @return array Checkout rate data for different time periods
 */
function get_abandoned_checkout_rate() {
    global $wpdb;
    
    // Get current time in Unix timestamp
    $current_time = time();
    
    // Calculate time periods
    $one_day_ago = $current_time - (24 * 60 * 60);
    $seven_days_ago = $current_time - (7 * 24 * 60 * 60);
    $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
    
    // Get analytics data for abandoned checkouts
    $abandoned_analytics = get_abandoned_analytics($one_day_ago, $seven_days_ago, $thirty_days_ago);
    
    // Get WooCommerce orders for the same time periods
    $one_day_orders = wc_get_orders([
        'date_created' => '>=' . date('Y-m-d', $one_day_ago),
        'status' => ['wc-completed', 'wc-processing'],
        'limit' => -1,
        'return' => 'ids',
    ]);
    
    $seven_day_orders = wc_get_orders([
        'date_created' => '>=' . date('Y-m-d', $seven_days_ago),
        'status' => ['wc-completed', 'wc-processing'],
        'limit' => -1,
        'return' => 'ids',
    ]);
    
    $thirty_day_orders = wc_get_orders([
        'date_created' => '>=' . date('Y-m-d', $thirty_days_ago),
        'status' => ['wc-completed', 'wc-processing'],
        'limit' => -1,
        'return' => 'ids',
    ]);
    
    // Get all time orders count (no date restriction)
    $all_time_orders = wc_get_orders([
        'status' => ['wc-completed', 'wc-processing'], // No date filter for all time
        'limit' => -1,
        'return' => 'ids',
    ]);
    
    // Calculate rates
    $one_day_total_sessions = count($one_day_orders) + $abandoned_analytics['one_day_total'];
    $one_day_rate = $one_day_total_sessions > 0 ? round(($abandoned_analytics['one_day_total'] / $one_day_total_sessions) * 100, 2) : 0;
    
    $seven_day_total_sessions = count($seven_day_orders) + $abandoned_analytics['seven_day_total'];
    $seven_day_rate = $seven_day_total_sessions > 0 ? round(($abandoned_analytics['seven_day_total'] / $seven_day_total_sessions) * 100, 2) : 0;
    
    $thirty_day_total_sessions = count($thirty_day_orders) + $abandoned_analytics['thirty_day_total'];
    $thirty_day_rate = $thirty_day_total_sessions > 0 ? round(($abandoned_analytics['thirty_day_total'] / $thirty_day_total_sessions) * 100, 2) : 0;
    
    $all_time_total_sessions = count($all_time_orders) + $abandoned_analytics['all_time_total'];
    $all_time_rate = $all_time_total_sessions > 0 ? round(($abandoned_analytics['all_time_total'] / $all_time_total_sessions) * 100, 2) : 0;
    
    return [
        'one_day' => [
            'completed_orders' => count($one_day_orders),
            'abandoned_carts' => $abandoned_analytics['one_day_total'], // Same as Total Tracked
            'total_sessions' => $one_day_total_sessions, // Completed Orders + Abandoned Carts
            'abandon_rate' => $one_day_rate
        ],
        'seven_day' => [
            'completed_orders' => count($seven_day_orders),
            'abandoned_carts' => $abandoned_analytics['seven_day_total'], // Same as Total Tracked
            'total_sessions' => $seven_day_total_sessions, // Completed Orders + Abandoned Carts
            'abandon_rate' => $seven_day_rate
        ],
        'thirty_day' => [
            'completed_orders' => count($thirty_day_orders),
            'abandoned_carts' => $abandoned_analytics['thirty_day_total'], // Same as Total Tracked
            'total_sessions' => $thirty_day_total_sessions, // Completed Orders + Abandoned Carts
            'abandon_rate' => $thirty_day_rate
        ],
        'all_time' => [
            'completed_orders' => count($all_time_orders), // All orders without date restriction
            'abandoned_carts' => $abandoned_analytics['all_time_total'], // Same as Total Tracked
            'total_sessions' => $all_time_total_sessions, // Completed Orders + Abandoned Carts
            'abandon_rate' => $all_time_rate
        ]
    ];
}





/**
 * Get revenue recovery analysis
 * 
 * @return array Revenue recovery data
 */

 function get_revenue_recovery_analysis() {
    global $wpdb;
    
    $post_type_id = 'abandoned_lead'; // Ensure this is the correct post type for abandoned leads
    
    // Get current time in Unix timestamp
    $current_time = time();
    
    // Calculate time periods
    $one_day_ago = $current_time - (24 * 60 * 60);
    $seven_days_ago = $current_time - (7 * 24 * 60 * 60);
    $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
    
    // Query to get recovered carts with their values - excluding recovered within 1 hour
    $query = "
        SELECT 
            p.ID,
            MAX(CASE WHEN pm.meta_key = 'timestamp' THEN pm.meta_value END) as timestamp,
            MAX(CASE WHEN pm.meta_key = 'subtotal' THEN pm.meta_value END) as subtotal,
            MAX(CASE WHEN pm.meta_key = 'customer_email' THEN pm.meta_value END) as email,
            MAX(CASE WHEN pm.meta_key = 'customer_phone' THEN pm.meta_value END) as phone
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status' AND pm_status.meta_value = '✅'
        LEFT JOIN {$wpdb->postmeta} pm_recovered ON p.ID = pm_recovered.post_id AND pm_recovered.meta_key = 'recovered_within_hour'
        WHERE p.post_type = %s 
        AND p.post_status = 'publish'
        AND (pm_recovered.meta_value IS NULL OR pm_recovered.meta_value != '1')
        GROUP BY p.ID
        ORDER BY timestamp ASC
    ";
    
    $results = $wpdb->get_results($wpdb->prepare($query, $post_type_id));
    
    // Initialize data
    $recovery_data = [
        'one_day' => [
            'count' => 0,
            'value' => 0
        ],
        'seven_day' => [
            'count' => 0,
            'value' => 0
        ],
        'thirty_day' => [
            'count' => 0,
            'value' => 0
        ],
        'all_time' => [
            'count' => 0,
            'value' => 0
        ]
    ];
    
    // Track unique customers by email/phone within 1-hour window
    $tracked_customers = [];
    
    // Process results
    foreach ($results as $result) {
        $timestamp = is_numeric($result->timestamp) ? intval($result->timestamp) : strtotime($result->timestamp);
        $subtotal = floatval($result->subtotal); // Fetch subtotal directly
        $customer_key = !empty($result->email) ? $result->email : (!empty($result->phone) ? $result->phone : 'unknown_' . $result->ID);
        
        // Check if this customer has been tracked within the last hour
        $is_revisit = false;
        if (isset($tracked_customers[$customer_key])) {
            $last_visit = $tracked_customers[$customer_key];
            // If this visit is within 1 hour of the last one, consider it a revisit
            if ($timestamp - $last_visit < 3600) { // 3600 seconds = 1 hour
                $is_revisit = true;
            }
        }
        
        // Update the last visit time for this customer
        $tracked_customers[$customer_key] = $timestamp;
        
        // Skip counting if it's a revisit within 1 hour
        if ($is_revisit) {
            continue;
        }
        
        // All time
        $recovery_data['all_time']['count']++;
        $recovery_data['all_time']['value'] += $subtotal; // Add subtotal to revenue
        
        // Last 30 days
        if ($timestamp >= $thirty_days_ago) {
            $recovery_data['thirty_day']['count']++;
            $recovery_data['thirty_day']['value'] += $subtotal; // Add subtotal to revenue
            
            // Last 7 days
            if ($timestamp >= $seven_days_ago) {
                $recovery_data['seven_day']['count']++;
                $recovery_data['seven_day']['value'] += $subtotal; // Add subtotal to revenue
                
                // Last 24 hours
                if ($timestamp >= $one_day_ago) {
                    $recovery_data['one_day']['count']++;
                    $recovery_data['one_day']['value'] += $subtotal; // Add subtotal to revenue
                }
            }
        }
    }
    
    return $recovery_data;
}




/**
 * Get top recovery days
 * 
 * @param int $days Number of days to analyze
 * @param int $top_count Number of top days to return
 * @return array Top recovery days data
 */
function get_top_recovery_days($days = 30, $top_count = 3) {
    global $wpdb;
    
    $post_type_id = 'abandoned_lead'; // Ensure this is the correct post type for abandoned leads
    
    // Calculate start date
    $start_date = strtotime("-{$days} days");
    
    // Query to get top recovery days
    $carts_query = "
        SELECT 
            DATE(FROM_UNIXTIME(pm_time.meta_value)) as recovery_date,
            COUNT(p.ID) as recovered_count,
            SUM(pm_subtotal.meta_value) as recovered_revenue
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'timestamp'
        JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status' AND pm_status.meta_value = '✅'
        LEFT JOIN {$wpdb->postmeta} pm_subtotal ON p.ID = pm_subtotal.post_id AND pm_subtotal.meta_key = 'subtotal'
        LEFT JOIN {$wpdb->postmeta} pm_recovered ON p.ID = pm_recovered.post_id AND pm_recovered.meta_key = 'recovered_within_hour'
        WHERE p.post_type = %s 
        AND p.post_status = 'publish'
        AND pm_time.meta_value >= %d
        AND (pm_recovered.meta_value IS NULL OR pm_recovered.meta_value != '1')
        GROUP BY recovery_date
        ORDER BY recovered_count DESC, recovered_revenue DESC
        LIMIT %d
    ";
    
    $carts = $wpdb->get_results($wpdb->prepare($carts_query, $post_type_id, $start_date, $top_count));
    
    // Process results into a simple array
    $recovery_days = [];
    foreach ($carts as $cart) {
        $recovery_days[] = [
            'date' => $cart->recovery_date,
            'count' => intval($cart->recovered_count),
            'value' => floatval($cart->recovered_revenue),
        ];
    }
    
    return $recovery_days;
}



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
    
    // Enqueue the CSS file
    wp_enqueue_style('abandoned-analytics-styles', plugin_dir_url(__FILE__) . '../css/abandoned-analytics.css');
    
    // Get abandoned checkout rate
    $checkout_rate = get_abandoned_checkout_rate();
    
    // Get revenue recovery analysis
    $revenue_recovery = get_revenue_recovery_analysis();
    
    // Get top recovery days
    $top_recovery_days = get_top_recovery_days(30, 7);
    
    // Render the page
    ?>
    <div class="wrap">
    <h1>Incomplete Checkout Analytics</h1>
        
        <div class="analytics-container">
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
            
            <!-- Abandoned Checkout Rate -->
            <div class="analytics-section">
                <h2>Abandoned Checkout Rate</h2>
                <div class="analytics-cards">
                    <!-- Last 24 Hours Card -->
                    <div class="analytics-card">
                        <h2>Last 24 Hours</h2>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Completed Orders:</span>
                            <span class="analytics-stat-value analytics-stat-success"><?php echo $checkout_rate['one_day']['completed_orders']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Abandoned Carts:</span>
                            <span class="analytics-stat-value analytics-stat-pending"><?php echo $checkout_rate['one_day']['abandoned_carts']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Total Sessions:</span>
                            <span class="analytics-stat-value"><?php echo $checkout_rate['one_day']['total_sessions']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Abandonment Rate:</span>
                            <span class="analytics-stat-value analytics-stat-ratio"><?php echo $checkout_rate['one_day']['abandon_rate']; ?>%</span>
                        </div>
                        <div class="analytics-progress-bar">
                            <div class="analytics-progress-fill" style="width: <?php echo $checkout_rate['one_day']['abandon_rate']; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Last 7 Days Card -->
                    <div class="analytics-card">
                        <h2>Last 7 Days</h2>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Completed Orders:</span>
                            <span class="analytics-stat-value analytics-stat-success"><?php echo $checkout_rate['seven_day']['completed_orders']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Abandoned Carts:</span>
                            <span class="analytics-stat-value analytics-stat-pending"><?php echo $checkout_rate['seven_day']['abandoned_carts']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Total Sessions:</span>
                            <span class="analytics-stat-value"><?php echo $checkout_rate['seven_day']['total_sessions']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Abandonment Rate:</span>
                            <span class="analytics-stat-value analytics-stat-ratio"><?php echo $checkout_rate['seven_day']['abandon_rate']; ?>%</span>
                        </div>
                        <div class="analytics-progress-bar">
                            <div class="analytics-progress-fill" style="width: <?php echo $checkout_rate['seven_day']['abandon_rate']; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Last 30 Days Card -->
                    <div class="analytics-card">
                        <h2>Last 30 Days</h2>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Completed Orders:</span>
                            <span class="analytics-stat-value analytics-stat-success"><?php echo $checkout_rate['thirty_day']['completed_orders']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Abandoned Carts:</span>
                            <span class="analytics-stat-value analytics-stat-pending"><?php echo $checkout_rate['thirty_day']['abandoned_carts']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Total Sessions:</span>
                            <span class="analytics-stat-value"><?php echo $checkout_rate['thirty_day']['total_sessions']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Abandonment Rate:</span>
                            <span class="analytics-stat-value analytics-stat-ratio"><?php echo $checkout_rate['thirty_day']['abandon_rate']; ?>%</span>
                        </div>
                        <div class="analytics-progress-bar">
                            <div class="analytics-progress-fill" style="width: <?php echo $checkout_rate['thirty_day']['abandon_rate']; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- All Time Card -->
                    <div class="analytics-card">
                        <h2>All Time</h2>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Completed Orders:</span>
                            <span class="analytics-stat-value analytics-stat-success"><?php echo $checkout_rate['all_time']['completed_orders']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Abandoned Carts:</span>
                            <span class="analytics-stat-value analytics-stat-pending"><?php echo $checkout_rate['all_time']['abandoned_carts']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Total Sessions:</span>
                            <span class="analytics-stat-value"><?php echo $checkout_rate['all_time']['total_sessions']; ?></span>
                        </div>
                        <div class="analytics-stat">
                            <span class="analytics-stat-label">Abandonment Rate:</span>
                            <span class="analytics-stat-value analytics-stat-ratio"><?php echo $checkout_rate['all_time']['abandon_rate']; ?>%</span>
                        </div>
                        <div class="analytics-progress-bar">
                            <div class="analytics-progress-fill" style="width: <?php echo $checkout_rate['all_time']['abandon_rate']; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Revenue Recovery Analysis -->
            <div class="analytics-section">
    <h2>Revenue Recovery Analysis</h2>
    <div class="analytics-cards">
        <!-- Last 24 Hours Card -->
        <div class="analytics-card">
            <h2>Last 24 Hours</h2>
            <div class="analytics-stat">
                <span class="analytics-stat-label">Recovered Carts:</span>
                <span class="analytics-stat-value analytics-stat-success"><?php echo $revenue_recovery['one_day']['count']; ?></span>
            </div>
            <div class="analytics-stat">
                <span class="analytics-stat-label">Recovered Revenue:</span>
                <span class="analytics-stat-value analytics-stat-success"><?php echo wc_price($revenue_recovery['one_day']['value']); ?></span>
            </div>
        </div>
        
        <!-- Last 7 Days Card -->
        <div class="analytics-card">
            <h2>Last 7 Days</h2>
            <div class="analytics-stat">
                <span class="analytics-stat-label">Recovered Carts:</span>
                <span class="analytics-stat-value analytics-stat-success"><?php echo $revenue_recovery['seven_day']['count']; ?></span>
            </div>
            <div class="analytics-stat">
                <span class="analytics-stat-label">Recovered Revenue:</span>
                <span class="analytics-stat-value analytics-stat-success"><?php echo wc_price($revenue_recovery['seven_day']['value']); ?></span>
            </div>
        </div>
        
        <!-- Last 30 Days Card -->
        <div class="analytics-card">
            <h2>Last 30 Days</h2>
            <div class="analytics-stat">
                <span class="analytics-stat-label">Recovered Carts:</span>
                <span class="analytics-stat-value analytics-stat-success"><?php echo $revenue_recovery['thirty_day']['count']; ?></span>
            </div>
            <div class="analytics-stat">
                <span class="analytics-stat-label">Recovered Revenue:</span>
                <span class="analytics-stat-value analytics-stat-success"><?php echo wc_price($revenue_recovery['thirty_day']['value']); ?></span>
            </div>
        </div>
        
        <!-- All Time Card -->
        <div class="analytics-card">
            <h2>All Time</h2>
            <div class="analytics-stat">
                <span class="analytics-stat-label">Recovered Carts:</span>
                <span class="analytics-stat-value analytics-stat-success"><?php echo $revenue_recovery['all_time']['count']; ?></span>
            </div>
            <div class="analytics-stat">
                <span class="analytics-stat-label">Recovered Revenue:</span>
                <span class="analytics-stat-value analytics-stat-success"><?php echo wc_price($revenue_recovery['all_time']['value']); ?></span>
            </div>
        </div>
    </div>
</div>

            
            <!-- Top Recovery Days -->
            <div class="analytics-section">
    <h2>Top Recovery Days (Last 30 Days)</h2>
    <table class="analytics-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Recovered Carts</th>
                <th>Recovered Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($top_recovery_days)): ?>
            <tr>
                <td colspan="3">No recovery data available</td>
            </tr>
            <?php else: ?>
                <?php foreach ($top_recovery_days as $day): ?>
                <tr>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($day['date'])); ?></td>
                    <td><?php echo esc_html($day['count']); ?></td>
                    <td><?php echo wc_price($day['value']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>



        </div>
    </div>
    <?php
}

/**
 * Track deleted leads for analytics
 * This function should be called before deleting a lead
 */
function track_deleted_lead($lead_id) {
    // Check if this lead was recovered within 1 hour
    $recovered_within_hour = get_post_meta($lead_id, 'recovered_within_hour', true);
    if ($recovered_within_hour === '1') {
        return; // Skip tracking for leads recovered within 1 hour
    }
    
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
add_action('before_delete_post', function($post_id): void {
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
