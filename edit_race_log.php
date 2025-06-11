<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// 1. Get the Log ID from the URL and verify it belongs to the user
if (!isset($_GET['log_id'])) {
    header('Location: events.php');
    exit;
}
$log_id = intval($_GET['log_id']);

$stmt_log = $pdo->prepare("SELECT * FROM race_logs WHERE id = ? AND user_id = ?");
$stmt_log->execute([$log_id, $user_id]);
$log = $stmt_log->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    // Log not found or doesn't belong to the user
    header('Location: events.php?error=lognotfound');
    exit;
}
$event_id = $log['event_id']; // Get the event_id for redirection

// Fetch individual lap times for this log to pre-fill the textarea
$stmt_laps = $pdo->prepare("SELECT lap_time FROM race_lap_times WHERE race_log_id = ? ORDER BY lap_number ASC");
$stmt_laps->execute([$log_id]);
$lap_times_array = $stmt_laps->fetchAll(PDO::FETCH_COLUMN);
$lap_times_pasted = implode("\n", $lap_times_array);


// 2. Handle the form submission for updating the log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_log') {
    // Collect data from the form
    $setup_id = intval($_POST['setup_id']);
    $event_type = trim($_POST['event_type']);
    $race_date = $log['race_date']; // Keep original date, but update time
    $race_time = trim($_POST['race_time']);
    if(!empty($race_time)){
        $race_date = date('Y-m-d', strtotime($race_date)) . ' ' . $race_time;
    }
    $laps_completed = !empty($_POST['laps_completed']) ? intval($_POST['laps_completed']) : null;
    $total_race_time = trim($_POST['total_race_time']);
    $best_lap_time = trim($_POST['best_lap_time']);
    $best_10_avg = trim($_POST['best_10_avg']);
    $best_3_consecutive_avg = trim($_POST['best_3_consecutive_avg']);
    $track_conditions_notes = trim($_POST['track_conditions_notes']);
    $car_performance_notes = trim($_POST['car_performance_notes']);
    $lap_times_pasted = trim($_POST['lap_times']);

    $pdo->beginTransaction();
    try {
        // Update the main log entry
        $stmt_update_log = $pdo->prepare("
            UPDATE race_logs SET setup_id = ?, race_date = ?, event_type = ?, laps_completed = ?, total_race_time = ?, best_lap_time = ?, best_10_avg = ?, best_3_consecutive_avg = ?, track_conditions_notes = ?, car_performance_notes = ? 
            WHERE id = ? AND user_id = ?
        ");
        $stmt_update_log->execute([$setup_id, $race_date, $event_type, $laps_completed, $total_race_time, $best_lap_time, $best_10_avg, $best_3_consecutive_avg, $track_conditions_notes, $car_performance_notes, $log_id, $user_id]);

        // Sync lap times: easiest way is to delete old ones and insert new ones
        $stmt_delete_laps = $pdo->prepare("DELETE FROM race_lap_times WHERE race_log_id = ?");
        $stmt_delete_laps->execute([$log_id]);

        if (!empty($lap_times_pasted)) {
            $lap_times_array = preg_split('/[\s,]+/', $lap_times_pasted);
            $stmt_insert_lap = $pdo->prepare("INSERT INTO race_lap_times (race_log_id, lap_number, lap_time) VALUES (?, ?, ?)");
            $lap_number = 1;
            foreach ($lap_times_array as $lap_time) {
                if (is_numeric($lap_time) && $lap_time > 0) {
                    $stmt_insert_lap->execute([$log_id, $lap_number, $lap_time]);
                    $lap_number++;
                }
            }
        }

        $pdo->commit();
        header("Location: view_event.php?event_id=" . $event_id . "&updated=1");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">An error occurred while updating the log.</div>';
        error_log("Race log update failed: " . $e->getMessage());
    }
}

// 3. Fetch data needed for the form dropdowns
$stmt_setups = $pdo->prepare("SELECT s.id, s.name as setup_name, m.name as model_name, s.is_baseline FROM setups s JOIN models m ON s.model_id = m.id WHERE m.user_id = ? ORDER BY m.name, s.is_baseline DESC, s.name");
$stmt_setups->execute([$user_id]);
$setups_list = $stmt_setups->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Race Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Edit Race Log</h1>
    <p>
        <a href="view_event.php?event_id=<?php echo $event_id; ?>">&laquo; Back to Event</a>
    </p>
    <?php echo $message; ?>

    <div class="card">
        <div class="card-header"><h5>Update Log Entry</h5></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_log">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="race_time" class="form-label">Time of Race</label>
                        <input type="time" class="form-control" id="race_time" name="race_time" value="<?php echo date('H:i', strtotime($log['race_date'])); ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label for="setup_id" class="form-label">Setup Used</label>
                        <select class="form-select" id="setup_id" name="setup_id" required>
                            <option value="">Select a setup...</option>
                            <?php foreach ($setups_list as $setup): ?>
                                <option value="<?php echo $setup['id']; ?>" <?php echo ($log['setup_id'] == $setup['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($setup['model_name'] . ' - ' . $setup['setup_name']); ?>
                                    <?php echo $setup['is_baseline'] ? ' ⭐' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="event_type" class="form-label">Event Type</label>
                        <select class="form-select" id="event_type" name="event_type" required>
                            <?php $event_types = ["Practice", "Qualifier 1", "Qualifier 2", "Qualifier 3", "Qualifier 4", "A Final", "B Final", "C Final"]; ?>
                            <?php foreach ($event_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo ($log['event_type'] == $type) ? 'selected' : ''; ?>><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2"><label for="laps_completed" class="form-label">Laps</label><input type="number" class="form-control" id="laps_completed" name="laps_completed" value="<?php echo htmlspecialchars($log['laps_completed']); ?>"></div>
                    <div class="col-md-2"><label for="total_race_time" class="form-label">Total Time</label><input type="text" class="form-control" id="total_race_time" name="total_race_time" placeholder="e.g., 305.54" value="<?php echo htmlspecialchars($log['total_race_time']); ?>"></div>
                    <div class="col-md-2"><label for="best_lap_time" class="form-label">Best Lap</label><input type="text" class="form-control" id="best_lap_time" name="best_lap_time" placeholder="e.g., 11.05" value="<?php echo htmlspecialchars($log['best_lap_time']); ?>"></div>
                    <div class="col-md-2"><label for="best_10_avg" class="form-label">Best 10 Avg</label><input type="text" class="form-control" id="best_10_avg" name="best_10_avg" placeholder="e.g., 11.26" value="<?php echo htmlspecialchars($log['best_10_avg']); ?>"></div>
                    <div class="col-md-2"><label for="best_3_consecutive_avg" class="form-label">Best 3 Consec.</label><input type="text" class="form-control" id="best_3_consecutive_avg" name="best_3_consecutive_avg" placeholder="e.g., 34.54" value="<?php echo htmlspecialchars($log['best_3_consecutive_avg']); ?>"></div>
                    <div class="col-md-2"><label for="finishing_position" class="form-label">Finishing Pos.</label><input type="number" class="form-control" id="finishing_position" name="finishing_position" value="<?php echo htmlspecialchars($log['finishing_position']); ?>"></div>

                    <div class="col-md-6">
                        <label for="track_conditions_notes" class="form-label">Track Conditions</label>
                        <textarea class="form-control" id="track_conditions_notes" name="track_conditions_notes" rows="3"><?php echo htmlspecialchars($log['track_conditions_notes']); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="car_performance_notes" class="form-label">Car Performance & Driver Feedback</label>
                        <textarea class="form-control" id="car_performance_notes" name="car_performance_notes" rows="3"><?php echo htmlspecialchars($log['car_performance_notes']); ?></textarea>
                    </div>
                    <div class="col-md-12">
                        <label for="lap_times" class="form-label">Individual Lap Times (optional)</label>
                        <textarea class="form-control" id="lap_times" name="lap_times" rows="4"><?php echo htmlspecialchars($lap_times_pasted); ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Update Log Entry</button>
            </form>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>