<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// 1. Get the Log ID from the URL and verify it belongs to the user
if (!isset($_GET['log_id'])) {
    header('Location: events.php');
    exit;
}
$log_id = intval($_GET['log_id']);

// This query joins all necessary tables to get full details for this one log
$stmt_log = $pdo->prepare("
    SELECT 
        rl.*, 
        e.event_name, e.event_date,
        v.name as venue_name,
        t.name as track_name, t.track_image_url,
        s.name as setup_name, s.is_baseline,
        m.name as model_name,
        front_tires.set_name as front_tire_name,
        rear_tires.set_name as rear_tire_name
    FROM race_logs rl
    JOIN race_events e ON rl.event_id = e.id
    LEFT JOIN venues v ON e.venue_id = v.id
    LEFT JOIN tracks t ON rl.track_id = t.id
    LEFT JOIN setups s ON rl.setup_id = s.id
    LEFT JOIN models m ON s.model_id = m.id
    LEFT JOIN tire_inventory front_tires ON rl.front_tires_id = front_tires.id
    LEFT JOIN tire_inventory rear_tires ON rl.rear_tires_id = rear_tires.id
    WHERE rl.id = ? AND rl.user_id = ?
");
$stmt_log->execute([$log_id, $user_id]);
$log = $stmt_log->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    header('Location: events.php?error=lognotfound');
    exit;
}

// 2. Fetch the individual lap times for this log
$stmt_laps = $pdo->prepare("SELECT lap_number, lap_time FROM race_lap_times WHERE race_log_id = ? ORDER BY lap_number ASC");
$stmt_laps->execute([$log_id]);
$lap_times = $stmt_laps->fetchAll(PDO::FETCH_ASSOC);

// --- NEW: Advanced Lap Analysis ---
$slowest_lap_value = null;
$best_consec_laps = []; // Array to hold the lap numbers of the best 3 consecutive laps

if (!empty($lap_times)) {
    $lap_time_values_float = array_map('floatval', array_column($lap_times, 'lap_time'));
    $slowest_lap_value = max($lap_time_values_float);
    
    // Find the best 3 consecutive laps that match the recorded total
    $target_consec3_sum = floatval($log['best_3_consecutive_avg']);
    if ($target_consec3_sum > 0 && count($lap_time_values_float) >= 3) {
        for ($i = 0; $i <= count($lap_time_values_float) - 3; $i++) {
            $current_sum = $lap_time_values_float[$i] + $lap_time_values_float[$i+1] + $lap_time_values_float[$i+2];
            // Use a small tolerance for floating point comparison
            if (abs($current_sum - $target_consec3_sum) < 0.01) {
                $best_consec_laps = [
                    $lap_times[$i]['lap_number'],
                    $lap_times[$i+1]['lap_number'],
                    $lap_times[$i+2]['lap_number']
                ];
                break; // Stop once we find the first match
            }
        }
    }
}

// 3. Prepare the data for Chart.js
$chart_labels = [];
$chart_data = [];
foreach ($lap_times as $lap) {
    $chart_labels[] = "Lap " . $lap['lap_number'];
    $chart_data[] = floatval($lap['lap_time']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log Details: <?php echo htmlspecialchars($log['event_type']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">

    <h3>
        <a href="view_event.php?event_id=<?php echo $log['event_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($log['event_name']); ?></a> 
        <span class="text-muted">/</span> 
        <?php echo htmlspecialchars($log['event_type']); ?>
    </h3>
    <hr>
    
    <!-- Lap Time Chart -->
    <?php if (!empty($lap_times)): ?>
    <div class="card mb-4">
        <div class="card-header"><h5>Lap Time Analysis</h5></div>
        <div class="card-body"><canvas id="lapChart"></canvas></div>
    </div>
    <?php endif; ?>

    <!-- Log Details -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5>Session Details</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Performance Metrics</h6>
                            <ul class="list-group">
                                <li class="list-group-item"><strong>Result:</strong> <?php echo ($log['laps_completed'] ? $log['laps_completed'] . ' / ' . $log['total_race_time'] : 'N/A'); ?></li>
                                <li class="list-group-item"><strong>Finishing Position:</strong> <?php echo htmlspecialchars($log['finishing_position'] ?: 'N/A'); ?></li>
                                <li class="list-group-item"><strong>Best Lap:</strong> <?php echo htmlspecialchars($log['best_lap_time'] ?: 'N/A'); ?></li>
                                <li class="list-group-item"><strong>Best 10 Avg:</strong> <?php echo htmlspecialchars($log['best_10_avg'] ?: 'N/A'); ?></li>
                                <li class="list-group-item"><strong>Best 3 Consecutive:</strong> <?php echo htmlspecialchars($log['best_3_consecutive_avg'] ?: 'N/A'); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Setup & Conditions</h6>
                            <ul class="list-group">
                                <li class="list-group-item"><strong>Setup Used:</strong> 
                                    <?php if ($log['setup_id']): ?>
                                        <a href="setup_form.php?setup_id=<?php echo $log['setup_id']; ?>"><?php echo htmlspecialchars($log['model_name'] . ' - ' . $log['setup_name']); ?></a>
                                    <?php else: ?>
                                        Not Assigned
                                    <?php endif; ?>
                                </li>
                                <li class="list-group-item"><strong>Tires:</strong><br>
                                    <small>
                                        <?php if($log['front_tire_name']) echo '<strong>Front:</strong> ' . htmlspecialchars($log['front_tire_name']); ?>
                                        <?php if($log['rear_tire_name']) echo '<br><strong>Rear:</strong> ' . htmlspecialchars($log['rear_tire_name']); ?>
                                    </small>
                                </li>
                                <li class="list-group-item"><strong>Car Notes:</strong><br><?php echo nl2br(htmlspecialchars($log['car_performance_notes'] ?: 'N/A')); ?></li>
                                <li class="list-group-item"><strong>Track Notes:</strong><br><?php echo nl2br(htmlspecialchars($log['track_conditions_notes'] ?: 'N/A')); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Individual Lap Times Table -->
        <div class="col-lg-4 mb-4">
            <?php if (!empty($lap_times)): ?>
            <div class="card h-100">
                <div class="card-header"><h5>Individual Lap Times</h5></div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Lap</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php foreach ($lap_times as $lap): ?>
                                <?php
                                    $current_lap_float = floatval($lap['lap_time']);
                                    $current_lap_number = intval($lap['lap_number']);
                                    $row_class = '';
                                    
                                    // Use a small tolerance for comparing the official best lap
                                    if (abs($current_lap_float - floatval($log['best_lap_time'])) < 0.001) {
                                        $row_class = 'table-success fw-bold'; // Official Best Lap
                                    } elseif (in_array($current_lap_number, $best_consec_laps)) {
                                        $row_class = 'table-info'; // Best 3 Consecutive
                                    } elseif ($current_lap_float == $slowest_lap_value) {
                                        $row_class = 'table-danger'; // Slowest Lap
                                    }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo $lap['lap_number']; ?></td>
                                    <td><?php echo $lap['lap_time']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JavaScript for the Chart -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const lapData = <?php echo json_encode($chart_data); ?>;
    const lapLabels = <?php echo json_encode($chart_labels); ?>;
    
    if (lapData.length > 0) {
        const ctx = document.getElementById('lapChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: lapLabels,
                datasets: [{
                    label: 'Lap Time (seconds)',
                    data: lapData,
                    borderColor: 'rgb(13, 110, 253)',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        title: { display: true, text: 'Time (s) - Faster is higher' },
                        reverse: true
                    },
                    x: {
                         title: { display: true, text: 'Lap Number' }
                    }
                }
            }
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>