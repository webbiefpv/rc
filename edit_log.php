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

$stmt_log_check = $pdo->prepare("SELECT id, event_id FROM race_logs WHERE id = ? AND user_id = ?");
$stmt_log_check->execute([$log_id, $user_id]);
$log_check = $stmt_log_check->fetch();

if (!$log_check) {
    // Log not found or doesn't belong to user
    header('Location: events.php?error=lognotfound');
    exit;
}
$event_id = $log_check['event_id']; // Get the event_id for redirects

// 2. Handle form submission to UPDATE the log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_log') {
    // Collect all data from the form
    $setup_id = !empty($_POST['setup_id']) ? intval($_POST['setup_id']) : null;
    $front_tires_id = !empty($_POST['front_tires_id']) ? intval($_POST['front_tires_id']) : null;
    $rear_tires_id = !empty($_POST['rear_tires_id']) ? intval($_POST['rear_tires_id']) : null;
    $laps_completed = !empty($_POST['laps_completed']) ? intval($_POST['laps_completed']) : null;
    $total_race_time = trim($_POST['total_race_time']) ?: null;
    $best_lap_time = trim($_POST['best_lap_time']) ?: null;
    $best_10_avg = trim($_POST['best_10_avg']) ?: null;
    $best_3_consecutive_avg = trim($_POST['best_3_consecutive_avg']) ?: null;
    $finishing_position = !empty($_POST['finishing_position']) ? intval($_POST['finishing_position']) : null;
    $track_conditions_notes = trim($_POST['track_conditions_notes']);
    $car_performance_notes = trim($_POST['car_performance_notes']);
    $lap_times_pasted = trim($_POST['lap_times']);

    $pdo->beginTransaction();
    try {
        // --- Update the main race_logs table ---
        $stmt_update_log = $pdo->prepare("
            UPDATE race_logs SET 
                setup_id = ?, front_tires_id = ?, rear_tires_id = ?, laps_completed = ?, 
                total_race_time = ?, best_lap_time = ?, best_10_avg = ?, best_3_consecutive_avg = ?, 
                finishing_position = ?, track_conditions_notes = ?, car_performance_notes = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt_update_log->execute([$setup_id, $front_tires_id, $rear_tires_id, $laps_completed, $total_race_time, $best_lap_time, $best_10_avg, $best_3_consecutive_avg, $finishing_position, $track_conditions_notes, $car_performance_notes, $log_id, $user_id]);

        // --- Sync the tire_log table (delete old, insert new) ---
        $stmt_delete_tire_logs = $pdo->prepare("DELETE FROM tire_log WHERE race_log_id = ?");
        $stmt_delete_tire_logs->execute([$log_id]);
        
        $stmt_insert_tire_log = $pdo->prepare("INSERT INTO tire_log (tire_set_id, race_log_id, user_id) VALUES (?, ?, ?)");
        if ($front_tires_id) { $stmt_insert_tire_log->execute([$front_tires_id, $log_id, $user_id]); }
        if ($rear_tires_id && $rear_tires_id !== $front_tires_id) { $stmt_insert_tire_log->execute([$rear_tires_id, $log_id, $user_id]); }

        // --- Sync the lap_times table (delete old, insert new) ---
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
        $message = '<div class="alert alert-danger">An error occurred while saving the log.</div>';
        error_log("Race log update failed: " . $e->getMessage());
    }
}

// 3. Fetch existing data to pre-populate the form
$stmt_log_details = $pdo->prepare("SELECT * FROM race_logs WHERE id = ?");
$stmt_log_details->execute([$log_id]);
$log_data = $stmt_log_details->fetch(PDO::FETCH_ASSOC);

// Fetch existing lap times to pre-populate the textarea
$stmt_lap_details = $pdo->prepare("SELECT lap_time FROM race_lap_times WHERE race_log_id = ? ORDER BY lap_number ASC");
$stmt_lap_details->execute([$log_id]);
$lap_times_array = $stmt_lap_details->fetchAll(PDO::FETCH_COLUMN);
$lap_times_string = implode("\n", $lap_times_array);

// Fetch setups for the dropdown
$stmt_setups = $pdo->prepare("SELECT s.id, s.name as setup_name, m.name as model_name, s.is_baseline FROM setups s JOIN models m ON s.model_id = m.id WHERE m.user_id = ? ORDER BY m.name, s.is_baseline DESC, s.name");
$stmt_setups->execute([$user_id]);
$setups_list = $stmt_setups->fetchAll();

// Fetch active tire sets for the dropdowns
$stmt_tires = $pdo->prepare("SELECT id, set_name FROM tire_inventory WHERE user_id = ? AND is_retired = FALSE ORDER BY set_name");
$stmt_tires->execute([$user_id]);
$tires_list = $stmt_tires->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Race Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h3>Edit Log for: <span class="text-primary"><?php echo htmlspecialchars($log_data['event_type']); ?></span></h3>
    <a href="view_event.php?event_id=<?php echo $event_id; ?>">&laquo; Back to Event</a>
    <hr>
    <?php echo $message; ?>

    <form method="POST">
        <input type="hidden" name="action" value="edit_log">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="setup_id" class="form-label">Setup Used</label>
                <select class="form-select" id="setup_id" name="setup_id">
                    <option value="">-- Not Assigned --</option>
                    <?php foreach ($setups_list as $setup): ?>
                        <option value="<?php echo $setup['id']; ?>" <?php echo ($setup['id'] == $log_data['setup_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($setup['model_name'] . ' - ' . $setup['setup_name']); ?>
                            <?php echo $setup['is_baseline'] ? ' ⭐' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="front_tires_id" class="form-label">Front Tire Set</label>
                <select class="form-select" id="front_tires_id" name="front_tires_id">
                    <option value="">-- Not Assigned --</option>
                    <?php foreach ($tires_list as $tire): ?>
                        <option value="<?php echo $tire['id']; ?>" <?php echo ($tire['id'] == $log_data['front_tires_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tire['set_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="rear_tires_id" class="form-label">Rear Tire Set</label>
                <select class="form-select" id="rear_tires_id" name="rear_tires_id">
                    <option value="">-- Not Assigned --</option>
                    <?php foreach ($tires_list as $tire): ?>
                        <option value="<?php echo $tire['id']; ?>" <?php echo ($tire['id'] == $log_data['rear_tires_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tire['set_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <hr class="my-3">
            <div class="col-md-2"><label for="laps_completed" class="form-label">Laps</label><input type="number" class="form-control" id="laps_completed" name="laps_completed" value="<?php echo htmlspecialchars($log_data['laps_completed']); ?>"></div>
            <div class="col-md-2"><label for="total_race_time" class="form-label">Total Time</label><input type="text" class="form-control" id="total_race_time" name="total_race_time" value="<?php echo htmlspecialchars($log_data['total_race_time']); ?>"></div>
            <div class="col-md-2"><label for="best_lap_time" class="form-label">Best Lap</label><input type="text" class="form-control" id="best_lap_time" name="best_lap_time" value="<?php echo htmlspecialchars($log_data['best_lap_time']); ?>"></div>
            <div class="col-md-2"><label for="best_10_avg" class="form-label">Best 10 Avg</label><input type="text" class="form-control" id="best_10_avg" name="best_10_avg" value="<?php echo htmlspecialchars($log_data['best_10_avg']); ?>"></div>
            <div class="col-md-2"><label for="best_3_consecutive_avg" class="form-label">Best 3 Consec.</label><input type="text" class="form-control" id="best_3_consecutive_avg" name="best_3_consecutive_avg" value="<?php echo htmlspecialchars($log_data['best_3_consecutive_avg']); ?>"></div>
            <div class="col-md-2"><label for="finishing_position" class="form-label">Finishing Pos.</label><input type="number" class="form-control" id="finishing_position" name="finishing_position" value="<?php echo htmlspecialchars($log_data['finishing_position']); ?>"></div>
            
            <div class="col-md-6">
                <label for="track_conditions_notes" class="form-label">Track Conditions</label>
                <textarea class="form-control" id="track_conditions_notes" name="track_conditions_notes" rows="3"><?php echo htmlspecialchars($log_data['track_conditions_notes']); ?></textarea>
            </div>
            <div class="col-md-6">
                <label for="car_performance_notes" class="form-label">Car Performance & Driver Feedback</label>
                <textarea class="form-control" id="car_performance_notes" name="car_performance_notes" rows="3"><?php echo htmlspecialchars($log_data['car_performance_notes']); ?></textarea>
            </div>
             <div class="col-md-12">
                <label for="lap_times" class="form-label">Individual Lap Times</label>
                <textarea class="form-control" id="lap_times" name="lap_times" rows="5"><?php echo htmlspecialchars($lap_times_string); ?></textarea>
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
        <a href="view_event.php?event_id=<?php echo $event_id; ?>" class="btn btn-secondary mt-3">Cancel</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>