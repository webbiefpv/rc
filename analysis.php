<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$selected_track_id = null;
$chart_labels = '[]';
$chart_data = '[]';
$track_name = '';

// Fetch all tracks for the selection dropdown
$stmt_tracks = $pdo->prepare("SELECT id, name FROM tracks WHERE user_id = ? ORDER BY name");
$stmt_tracks->execute([$user_id]);
$tracks_list = $stmt_tracks->fetchAll(PDO::FETCH_ASSOC);

// Check if the form has been submitted to generate a report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_id'])) {
    $selected_track_id = intval($_POST['track_id']);

    if ($selected_track_id) {
        // Fetch the performance data for the selected track
        $stmt_performance = $pdo->prepare("
            SELECT e.event_date, rl.best_lap_time
            FROM race_logs rl
            JOIN race_events e ON rl.event_id = e.id
            WHERE rl.track_id = ? AND rl.user_id = ? AND rl.best_lap_time > 0
            ORDER BY e.event_date ASC
        ");
        $stmt_performance->execute([$selected_track_id, $user_id]);
        $performance_data = $stmt_performance->fetchAll(PDO::FETCH_ASSOC);

        // Fetch the track name for the chart title
        $stmt_track_name = $pdo->prepare("SELECT name FROM tracks WHERE id = ?");
        $stmt_track_name->execute([$selected_track_id]);
        $track_name = $stmt_track_name->fetchColumn();

        // Prepare the data for Chart.js
        $labels = [];
        $data = [];
        foreach ($performance_data as $row) {
            $labels[] = date("M j, Y", strtotime($row['event_date']));
            $data[] = floatval($row['best_lap_time']);
        }
        $chart_labels = json_encode($labels);
        $chart_data = json_encode($data);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Performance Analysis - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Performance Analysis</h1>
    <p>Analyze your performance over time and compare setups to find what works best.</p>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5>Track Performance Timeline</h5>
        </div>
        <div class="card-body">
            <p>Select a track to see how your best lap times have improved over time.</p>
            <form method="POST">
                <div class="row align-items-end">
                    <div class="col-md-5">
                        <label for="track_id" class="form-label">Select a Track:</label>
                        <select class="form-select" id="track_id" name="track_id" required>
                            <option value="">-- Choose a track --</option>
                            <?php foreach ($tracks_list as $track): ?>
                                <option value="<?php echo $track['id']; ?>" <?php echo ($track['id'] == $selected_track_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($track['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_track_id): ?>
        <div class="card-footer">
            <h5>Report for: <?php echo htmlspecialchars($track_name); ?></h5>
            <?php if (empty(json_decode($chart_data))): ?>
                <p class="text-muted">No race logs with best lap times found for this track.</p>
            <?php else: ?>
                <canvas id="performanceChart"></canvas>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // This block of JS will only run if there is chart data to display
    const chartData = <?php echo $chart_data; ?>;
    const chartLabels = <?php echo $chart_labels; ?>;
    
    if (chartData.length > 0) {
        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Best Lap Time (seconds)',
                    data: chartData,
                    borderColor: 'rgb(220, 53, 69)',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
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
                    }
                },
                scales: {
                    y: {
                        title: { display: true, text: 'Time (s) - Faster is higher' },
                        // This 'reverse' option makes the Y-axis inverted, so lower (faster) times are at the top.
                        reverse: true 
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