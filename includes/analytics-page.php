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
            
            <?php render_additional_analytics_sections(); ?>
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

/**
 * Get top abandoned products
 * 
 * @param int $limit Number of products to return
 * @param int $days_ago Timestamp for filtering by days
 * @return array Top abandoned products data
 */
function get_top_abandoned_products($limit = 10, $days_ago = null) {
    global $wpdb;
    
    $post_type_id = get_post_type_object('abandoned_lead')->name;
    
    // Base query to get product data from abandoned carts
    $query = "
        SELECT 
            pm_products.meta_value as product_data,
            COUNT(p.ID) as abandon_count
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm_products ON p.ID = pm_products.post_id AND pm_products.meta_key = 'cart_products'
        JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status'
    ";
    
    // Add time filter if specified
    if ($days_ago) {
        $query .= $wpdb->prepare("
            JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'timestamp'
            WHERE p.post_type = %s AND p.post_status = 'publish' 
            AND pm_status.meta_value != '✅'
            AND pm_time.meta_value >= %d
        ", $post_type_id, $days_ago);
    } else {
        $query .= $wpdb->prepare("
            WHERE p.post_type = %s AND p.post_status = 'publish' 
            AND pm_status.meta_value != '✅'
        ", $post_type_id);
    }
    
    $query .= "
        GROUP BY pm_products.meta_value
        ORDER BY abandon_count DESC
        LIMIT %d
    ";
    
    $results = $wpdb->get_results($wpdb->prepare($query, $limit));
    
    // Process results to extract product information
    $products = [];
    foreach ($results as $result) {
        $cart_products = maybe_unserialize($result->product_data);
        if (is_array($cart_products)) {
            foreach ($cart_products as $product) {
                $product_id = isset($product['product_id']) ? $product['product_id'] : 0;
                $quantity = isset($product['quantity']) ? $product['quantity'] : 1;
                
                if ($product_id) {
                    if (!isset($products[$product_id])) {
                        $products[$product_id] = [
                            'id' => $product_id,
                            'name' => get_the_title($product_id),
                            'count' => 0,
                            'quantity' => 0,
                            'price' => get_post_meta($product_id, '_price', true),
                            'image' => get_the_post_thumbnail_url($product_id, 'thumbnail') ?: '',
                            'permalink' => get_permalink($product_id)
                        ];
                    }
                    
                    $products[$product_id]['count']++;
                    $products[$product_id]['quantity'] += $quantity;
                }
            }
        }
    }
    
    // Sort by count
    usort($products, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    // Limit to requested number
    return array_slice($products, 0, $limit);
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
    
    // Get all time orders count
    $all_time_orders_query = "
        SELECT COUNT(ID) FROM {$wpdb->posts}
        WHERE post_type = 'shop_order'
        AND post_status IN ('wc-completed', 'wc-processing')
    ";
    $all_time_orders_count = $wpdb->get_var($all_time_orders_query);
    
    // Calculate rates
    $one_day_total = count($one_day_orders) + $abandoned_analytics['one_day_pending'];
    $one_day_rate = $one_day_total > 0 ? round(($abandoned_analytics['one_day_pending'] / $one_day_total) * 100, 2) : 0;
    
    $seven_day_total = count($seven_day_orders) + $abandoned_analytics['seven_day_pending'];
    $seven_day_rate = $seven_day_total > 0 ? round(($abandoned_analytics['seven_day_pending'] / $seven_day_total) * 100, 2) : 0;
    
    $thirty_day_total = count($thirty_day_orders) + $abandoned_analytics['thirty_day_pending'];
    $thirty_day_rate = $thirty_day_total > 0 ? round(($abandoned_analytics['thirty_day_pending'] / $thirty_day_total) * 100, 2) : 0;
    
    $all_time_total = $all_time_orders_count + $abandoned_analytics['all_time_pending'];
    $all_time_rate = $all_time_total > 0 ? round(($abandoned_analytics['all_time_pending'] / $all_time_total) * 100, 2) : 0;
    
    return [
        'one_day' => [
            'completed_orders' => count($one_day_orders),
            'abandoned_carts' => $abandoned_analytics['one_day_pending'],
            'total_sessions' => $one_day_total,
            'abandon_rate' => $one_day_rate
        ],
        'seven_day' => [
            'completed_orders' => count($seven_day_orders),
            'abandoned_carts' => $abandoned_analytics['seven_day_pending'],
            'total_sessions' => $seven_day_total,
            'abandon_rate' => $seven_day_rate
        ],
        'thirty_day' => [
            'completed_orders' => count($thirty_day_orders),
            'abandoned_carts' => $abandoned_analytics['thirty_day_pending'],
            'total_sessions' => $thirty_day_total,
            'abandon_rate' => $thirty_day_rate
        ],
        'all_time' => [
            'completed_orders' => $all_time_orders_count,
            'abandoned_carts' => $abandoned_analytics['all_time_pending'],
            'total_sessions' => $all_time_total,
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
    
    $post_type_id = get_post_type_object('abandoned_lead')->name;
    
    // Get current time in Unix timestamp
    $current_time = time();
    
    // Calculate time periods
    $one_day_ago = $current_time - (24 * 60 * 60);
    $seven_days_ago = $current_time - (7 * 24 * 60 * 60);
    $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
    
    // Query to get recovered carts with their values
    $query = "
        SELECT 
            p.ID,
            MAX(CASE WHEN pm.meta_key = 'timestamp' THEN pm.meta_value END) as timestamp,
            MAX(CASE WHEN pm.meta_key = 'cart_total' THEN pm.meta_value END) as cart_total
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status' AND pm_status.meta_value = '✅'
        WHERE p.post_type = %s AND p.post_status = 'publish'
        GROUP BY p.ID
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
    
    // Process results
    foreach ($results as $result) {
        $timestamp = is_numeric($result->timestamp) ? intval($result->timestamp) : strtotime($result->timestamp);
        $cart_total = floatval($result->cart_total);
        
        // All time
        $recovery_data['all_time']['count']++;
        $recovery_data['all_time']['value'] += $cart_total;
        
        // Last 30 days
        if ($timestamp >= $thirty_days_ago) {
            $recovery_data['thirty_day']['count']++;
            $recovery_data['thirty_day']['value'] += $cart_total;
            
            // Last 7 days
            if ($timestamp >= $seven_days_ago) {
                $recovery_data['seven_day']['count']++;
                $recovery_data['seven_day']['value'] += $cart_total;
                
                // Last 24 hours
                if ($timestamp >= $one_day_ago) {
                    $recovery_data['one_day']['count']++;
                    $recovery_data['one_day']['value'] += $cart_total;
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
function get_top_recovery_days($days = 30, $top_count = 7) {
    global $wpdb;
    
    $post_type_id = get_post_type_object('abandoned_lead')->name;
    
    // Calculate start date
    $start_date = strtotime("-{$days} days");
    
    // Query to get recovered carts with their timestamps
    $query = "
        SELECT 
            DATE(FROM_UNIXTIME(pm_time.meta_value)) as recovery_date,
            COUNT(p.ID) as recovery_count,
            SUM(pm_total.meta_value) as recovery_value
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'timestamp'
        JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'status' AND pm_status.meta_value = '✅'
        LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'cart_total'
        WHERE p.post_type = %s AND p.post_status = 'publish'
        AND pm_time.meta_value >= %d
        GROUP BY recovery_date
        ORDER BY recovery_count DESC
        LIMIT %d
    ";
    
    $results = $wpdb->get_results($wpdb->prepare($query, $post_type_id, $start_date, $top_count));
    
    // Process results
    $recovery_days = [];
    foreach ($results as $result) {
        $recovery_days[] = [
            'date' => $result->recovery_date,
            'count' => intval($result->recovery_count),
            'value' => floatval($result->recovery_value)
        ];
    }
    
    return $recovery_days;
}

/**
 * Render additional analytics sections
 */
function render_additional_analytics_sections() {
    // Get current time in Unix timestamp
    $current_time = time();
    $thirty_days_ago = $current_time - (30 * 24 * 60 * 60);
    
    // Get top abandoned products
    $top_products = get_top_abandoned_products(10, $thirty_days_ago);
    
    // Get abandoned checkout rate
    $checkout_rate = get_abandoned_checkout_rate();
    
    // Get revenue recovery analysis
    $revenue_recovery = get_revenue_recovery_analysis();
    
    // Get top recovery days
    $top_recovery_days = get_top_recovery_days(30, 7);
    
    ?>
    <!-- Top Abandoned Products -->
    <div class="analytics-section">
        <h2>Top Abandoned Products (Last 30 Days)</h2>
        <table class="analytics-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Image</th>
                    <th>Abandoned Count</th>
                    <th>Total Quantity</th>
                    <th>Price</th>
                    <th>Potential Revenue Loss</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top_products)): ?>
                <tr>
                    <td colspan="6">No abandoned products data available</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($top_products as $product): ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($product['permalink']); ?>" target="_blank">
                                <?php echo esc_html($product['name']); ?>
                            </a>
                        </td>
                        <td>
                            <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo esc_url($product['image']); ?>" width="50" height="50" alt="<?php echo esc_attr($product['name']); ?>">
                            <?php else: ?>
                            <span>No image</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($product['count']); ?></td>
                        <td><?php echo esc_html($product['quantity']); ?></td>
                        <td><?php echo wc_price($product['price']); ?></td>
                        <td><?php echo wc_price($product['price'] * $product['quantity']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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
    <?php
}
