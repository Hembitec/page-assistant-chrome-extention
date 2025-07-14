<?php

class Essenca_Analytics_Page {

    public static function render() {
        // Enqueue Chart.js
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

        // Handle date filtering and set defaults
        $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');

        // Fetch data
        $total_tokens = Essenca_Db_Manager::get_total_tokens_used($start_date, $end_date);
        $usage_by_action = Essenca_Db_Manager::get_usage_by_action($start_date, $end_date);
        $top_users = Essenca_Db_Manager::get_top_users(10, $start_date, $end_date);
        $daily_usage = Essenca_Db_Manager::get_daily_usage($start_date, $end_date);

        // Prepare data for charts
        $usage_labels = wp_list_pluck($usage_by_action, 'request_action');
        $usage_data = wp_list_pluck($usage_by_action, 'total_tokens');
        
        $top_users_labels = [];
        foreach ($top_users as $row) {
            $user = get_user_by('id', $row['user_id']);
            $top_users_labels[] = $user ? $user->user_login : 'Unknown';
        }
        $top_users_data = wp_list_pluck($top_users, 'total_tokens');

        $daily_labels = array_keys($daily_usage);
        $daily_data = array_values($daily_usage);
        ?>
        <style>
            .essenca-analytics-header {
                display: flex;
                gap: 20px;
                margin-bottom: 20px;
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .essenca-analytics-header .filter-form {
                flex: 2;
            }
            .essenca-analytics-header .summary-box {
                flex: 1;
                text-align: center;
                border-left: 1px solid #eee;
                padding-left: 20px;
            }
            .essenca-analytics-header .summary-box h2 {
                margin-top: 0;
            }
            .essenca-analytics-header .summary-box p {
                font-size: 2em;
                font-weight: bold;
                margin: 0;
            }
        </style>
        <div class="wrap">
            <h1>Essenca Analytics</h1>

            <div class="essenca-analytics-header">
                <div class="filter-form">
                    <form method="get">
                        <input type="hidden" name="page" value="essenca-analytics">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                        <input type="submit" class="button-primary" value="Filter">
                        <a href="?page=essenca-analytics" class="button">Clear</a>
                    </form>
                </div>
                <div class="summary-box">
                    <h2>Total Tokens Used</h2>
                    <p><?php echo esc_html($total_tokens); ?></p>
                </div>
            </div>

            <!-- Daily Usage Graph -->
            <h2>Daily Usage Trend</h2>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
                <canvas id="dailyUsageChart"></canvas>
            </div>

            <!-- Other Charts -->
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px; max-width: 400px;">
                    <h2>Usage by Action</h2>
                    <canvas id="usageByActionChart"></canvas>
                </div>
                <div style="flex: 2; min-width: 400px;">
                    <h2>Top Users by Token Usage</h2>
                    <canvas id="topUsersChart"></canvas>
                </div>
            </div>
            <hr>

            <!-- Data Tables (for reference) -->
            <h2>Data Tables</h2>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1;">
                    <h3>Usage by Action</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th>Action</th><th>Tokens Used</th></tr></thead>
                        <tbody>
                            <?php foreach ($usage_by_action as $row) : ?>
                                <tr><td><?php echo esc_html($row['request_action']); ?></td><td><?php echo esc_html($row['total_tokens']); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="flex: 1;">
                    <h3>Top Users</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th>User</th><th>Tokens Used</th></tr></thead>
                        <tbody>
                            <?php foreach ($top_users as $row) : 
                                $user = get_user_by('id', $row['user_id']);
                                $user_display = $user ? $user->user_login : 'Unknown';
                            ?>
                                <tr><td><?php echo esc_html($user_display); ?></td><td><?php echo esc_html($row['total_tokens']); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    // Daily Usage Chart (Line)
                    const dailyCtx = document.getElementById('dailyUsageChart').getContext('2d');
                    new Chart(dailyCtx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($daily_labels); ?>,
                            datasets: [{
                                label: 'Tokens Used',
                                data: <?php echo json_encode($daily_data); ?>,
                                borderColor: '#FF6384',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                fill: true,
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            aspectRatio: 3, // Makes the chart wider than it is tall
                            plugins: {
                                legend: { display: false },
                                title: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });

                    // Usage by Action Chart (Doughnut)
                    const usageCtx = document.getElementById('usageByActionChart').getContext('2d');
                    new Chart(usageCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode($usage_labels); ?>,
                            datasets: [{
                                label: 'Tokens Used',
                                data: <?php echo json_encode($usage_data); ?>,
                                backgroundColor: [
                                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: false
                                }
                            }
                        }
                    });

                    // Top Users Chart (Bar)
                    const topUsersCtx = document.getElementById('topUsersChart').getContext('2d');
                    new Chart(topUsersCtx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($top_users_labels); ?>,
                            datasets: [{
                                label: 'Tokens Used',
                                data: <?php echo json_encode($top_users_data); ?>,
                                backgroundColor: '#36A2EB'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                });
            </script>
        </div>
        <?php
    }
}
