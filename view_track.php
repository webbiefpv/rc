<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// 1. Get the Track ID from the URL and verify it belongs to the user
if (!isset($_GET['track_id'])) {
    header('Location: tracks.php');
    exit;
}
$track_id = intval($_GET['track_id']);

$stmt_track = $pdo->prepare("SELECT * FROM tracks WHERE id = ? AND user_id = ?");
$stmt_track->execute([$track_id, $user_id]);
$track = $stmt_track->fetch(PDO::FETCH_ASSOC);

if (!$track) {
    // Track not found or doesn't belong to the user
    header('Location: tracks.php?error=notfound');
    exit;
}

// 2. Fetch the performance data for the selected track for the chart
$stmt_performance = $pdo->prepare("
    SELECT e.event_date, rl.best_lap_time
    FROM race_logs rl
    JOIN race_events e ON rl.event_id = e.id
    WHERE rl.track_id = ? AND rl.user_id = ? AND rl.best_lap_time > 0
    ORDER BY e.event_date ASC
");
$stmt_performance->execute([$track_id, $user_id]);
$performance_data = $stmt_performance->fetchAll(PDO::FETCH_ASSOC);

// 3. Prepare the data for Chart.js
$chart_labels = [];
$chart_data = [];
foreach ($performance_data as $row) {
    $chart_labels[] = date("M j, Y", strtotime($row['event_date']));
    $chart_data[] = floatval($row['best_lap_time']);
}
$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track Details: <?php echo htmlspecialchars($track['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">

    <!-- Track Details Header -->
    <div class="card bg-light p-3 mb-4">
        <div class="row g-0 align-items-center">
            <?php if (!empty($track['track_image_url'])): ?>
            <div class="col-md-3">
                <img src="<?php echo htmlspecialchars($track['track_image_url']); ?>" class="img-fluid rounded" alt="Track Layout">
            </div>
            <?php endif; ?>
            <div class="col-md-9 ps-md-4">
                <div class="card-body py-0">
                    <h2 class="card-title"><?php echo htmlspecialchars($track['name']); ?></h2>
                    <p class="card-text mb-1">
                        <strong>Surface:</strong> <?php echo ucfirst(htmlspecialchars($track['surface_type'])); ?> | 
                        <strong>Grip:</strong> <?php echo ucfirst(htmlspecialchars($track['grip_level'])); ?> | 
                        <strong>Layout:</strong> <?php echo ucfirst(htmlspecialchars($track['layout_type'])); ?>
                    </p>
                    <?php if (!empty($track['notes'])): ?>
                        <p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($track['notes']); ?></small></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Timeline Chart -->
    <div class="card">
        <div class="card-header">
            <h5>Performance Timeline</h5>
        </div>
        <div class="card-body">
            <?php if (empty($performance_data)): ?>
                <p class="text-muted">No race logs with best lap times found for this track yet. Once you log some races, your performance chart will appear here.</p>
            <?php else: ?>
                <canvas id="performanceChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartData = <?php echo $chart_data_json; ?>;
    const chartLabels = <?php echo $chart_labels_json; ?>;
    
    if (chartData.length > 0) {
        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Best Lap Time (seconds)',
                    data: chartData,
                    borderColor: 'rgb(13, 110, 253)',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Best Lap Time Progression'
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        title: { display: true, text: 'Time (s) - Faster is higher' },
                        reverse: true // Invert Y-axis so lower (faster) times are at the top
                    },
                    x: {
                         title: { display: true, text: 'Event Date' }
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