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
        t.name as track_name, t.track_image_url,
        s.name as setup_name, s.is_baseline,
        m.name as model_name
    FROM race_logs rl
    JOIN race_events e ON rl.event_id = e.id
    JOIN tracks t ON rl.track_id = t.id
    JOIN setups s ON rl.setup_id = s.id
    JOIN models m ON s.model_id = m.id
    WHERE rl.id = ? AND rl.user_id = ?
");
$stmt_log->execute([$log_id, $user_id]);
$log = $stmt_log->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    // Log not found or doesn't belong to the user
    header('Location: events.php?error=lognotfound');
    exit;
}

// 2. Fetch the individual lap times for this log
$stmt_laps = $pdo->prepare("SELECT lap_number, lap_time FROM race_lap_times WHERE race_log_id = ? ORDER BY lap_number ASC");
$stmt_laps->execute([$log_id]);
$lap_times = $stmt_laps->fetchAll(PDO::FETCH_ASSOC);

// 3. Prepare the data for Chart.js
$chart_labels = []; // e.g., ["Lap 1", "Lap 2", ...]
$chart_data = [];   // e.g., [12.5, 11.8, 11.9, ...]
foreach ($lap_times as $lap) {
    $chart_labels[] = "Lap " . $lap['lap_number'];
    $chart_data[] = floatval($lap['lap_time']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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

    <?php if (!empty($lap_times)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5>Lap Time Analysis</h5>
            </div>
            <div class="card-body">
                <canvas id="lapChart"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5>Session Details</h5>
        </div>
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
                        <li class="list-group-item"><strong>Setup Used:</strong> <a href="setup_form.php?setup_id=<?php echo $log['setup_id']; ?>"><?php echo htmlspecialchars($log['model_name'] . ' - ' . $log['setup_name']); ?></a></li>
                        <li class="list-group-item"><strong>Car Notes:</strong><br><?php echo nl2br(htmlspecialchars($log['car_performance_notes'] ?: 'N/A')); ?></li>
                        <li class="list-group-item"><strong>Track Notes:</strong><br><?php echo nl2br(htmlspecialchars($log['track_conditions_notes'] ?: 'N/A')); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($lap_times)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5>Individual Lap Times</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Lap</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($lap_times as $lap): ?>
                        <tr>
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
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            title: { display: true, text: 'Time (s)' },
                            ticks: {
                                // To make the chart more readable, we can reverse it so lower (faster) times are at the top
                                // However, standard is lower values at the bottom. Let's stick to standard for now.
                            }
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