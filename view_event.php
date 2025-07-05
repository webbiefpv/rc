<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// 1. Get the Event ID from the URL and verify it belongs to the user
if (!isset($_GET['event_id'])) {
    header('Location: events.php');
    exit;
}
$event_id = intval($_GET['event_id']);

$stmt_event = $pdo->prepare("SELECT e.*, t.name as track_name, t.track_image_url FROM race_events e JOIN tracks t ON e.track_id = t.id WHERE e.id = ? AND e.user_id = ?");
$stmt_event->execute([$event_id, $user_id]);
$event = $stmt_event->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: events.php?error=notfound');
    exit;
}

if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '<div class="alert alert-success">Race log added successfully!</div>';
}

// 2. Handle the "Add New Log" form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_log') {
    // Collect data from the form
    $setup_id = intval($_POST['setup_id']);
    $front_tires_id = !empty($_POST['front_tires_id']) ? intval($_POST['front_tires_id']) : null;
    $rear_tires_id = !empty($_POST['rear_tires_id']) ? intval($_POST['rear_tires_id']) : null;
    $race_time = trim($_POST['race_time']);
    $race_date = $event['event_date'] . ' ' . ($race_time ?: '00:00:00');
    $event_category = $_POST['event_category'] ?? 'Practice';
    $event_type = $event_category;
    if ($event_category === 'Qualifier') {
        $event_type = 'Qualifier ' . ($_POST['round_number'] ?? 1);
    } elseif ($event_category === 'Final') {
        $event_type = ($_POST['final_letter'] ?? 'A') . ' Final';
    }
    $laps_completed = !empty($_POST['laps_completed']) ? intval($_POST['laps_completed']) : null;
    $total_race_time = trim($_POST['total_race_time']) ?: null;
    $best_lap_time = trim($_POST['best_lap_time']) ?: null;
    $best_10_avg = trim($_POST['best_10_avg']) ?: null;
    $best_3_consecutive_avg = trim($_POST['best_3_consecutive_avg']) ?: null;
    $finishing_position = !empty($_POST['finishing_position']) ? intval($_POST['finishing_position']) : null;
    $track_conditions_notes = trim($_POST['track_conditions_notes']);
    $car_performance_notes = trim($_POST['car_performance_notes']);
    $lap_times_pasted = trim($_POST['lap_times']);

    if (empty($setup_id) || empty($event_type)) {
        $message = '<div class="alert alert-danger">Please select a Setup and Event Type.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            // Update the race_logs INSERT statement to include tire IDs
            $stmt_insert_log = $pdo->prepare("
                INSERT INTO race_logs (user_id, event_id, track_id, setup_id, front_tires_id, rear_tires_id, race_date, event_type, laps_completed, total_race_time, best_lap_time, best_10_avg, best_3_consecutive_avg, finishing_position, track_conditions_notes, car_performance_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert_log->execute([$user_id, $event_id, $event['track_id'], $setup_id, $front_tires_id, $rear_tires_id, $race_date, $event_type, $laps_completed, $total_race_time, $best_lap_time, $best_10_avg, $best_3_consecutive_avg, $finishing_position, $track_conditions_notes, $car_performance_notes]);
            $new_race_log_id = $pdo->lastInsertId();

            // Create log entries in the tire_log table
            $stmt_tire_log = $pdo->prepare("INSERT INTO tire_log (tire_set_id, race_log_id, user_id) VALUES (?, ?, ?)");
            if ($front_tires_id) {
                $stmt_tire_log->execute([$front_tires_id, $new_race_log_id, $user_id]);
            }
            if ($rear_tires_id && $rear_tires_id !== $front_tires_id) { // Avoid double logging if same set used for front and rear
                $stmt_tire_log->execute([$rear_tires_id, $new_race_log_id, $user_id]);
            }
            
            // ... (your existing logic for saving individual lap times remains here) ...

            $pdo->commit();
            header("Location: view_event.php?event_id=" . $event_id . "&added=1");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger">An error occurred while saving the log.</div>';
            error_log("Race log insert failed: " . $e->getMessage());
        }
    }
}

// 3. Fetch data needed for the page display
// Fetch Setups for the dropdown
$stmt_setups = $pdo->prepare("SELECT s.id, s.name as setup_name, m.name as model_name, s.is_baseline FROM setups s JOIN models m ON s.model_id = m.id WHERE m.user_id = ? ORDER BY m.name, s.is_baseline DESC, s.name");
$stmt_setups->execute([$user_id]);
$setups_list = $stmt_setups->fetchAll(PDO::FETCH_ASSOC);

// Fetch active tire sets for the new dropdowns
$stmt_tires = $pdo->prepare("SELECT id, set_name FROM tire_inventory WHERE user_id = ? AND is_retired = FALSE ORDER BY set_name");
$stmt_tires->execute([$user_id]);
$tires_list = $stmt_tires->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing race logs for THIS event, including tire set names
$stmt_logs = $pdo->prepare("
    SELECT 
        rl.*, 
        s.name as setup_name,
        m.name as model_name,
        front_tires.set_name as front_tire_name,
        rear_tires.set_name as rear_tire_name
    FROM race_logs rl
    JOIN setups s ON rl.setup_id = s.id
    JOIN models m ON s.model_id = m.id
    LEFT JOIN tire_inventory front_tires ON rl.front_tires_id = front_tires.id
    LEFT JOIN tire_inventory rear_tires ON rl.rear_tires_id = rear_tires.id
    WHERE rl.event_id = ?
    ORDER BY rl.race_date ASC
");
$stmt_logs->execute([$event_id]);
$race_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View Event: <?php echo htmlspecialchars($event['event_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <div class="card bg-light p-3 mb-4">
        </div>

    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header"><h5>Add a Race/Practice Log to this Event</h5></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_log">
                <div class="row g-3">
                    <div class="col-md-4">...</div>
                    <div class="col-md-2" id="qualifier_round_div" style="display: none;">...</div>
                    <div class="col-md-2" id="final_letter_div" style="display: none;">...</div>
                    
                    <div class="col-md-4">
                        <label for="setup_id" class="form-label">Setup Used</label>
                        <select class="form-select" id="setup_id" name="setup_id" required>
                            <option value="">-- Select a setup --</option>
                            <?php foreach ($setups_list as $setup): ?>
                                <option value="<?php echo $setup['id']; ?>"><?php echo htmlspecialchars($setup['model_name'] . ' - ' . $setup['setup_name']); ?><?php echo $setup['is_baseline'] ? ' ⭐' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="front_tires_id" class="form-label">Front Tire Set</label>
                        <select class="form-select" id="front_tires_id" name="front_tires_id">
                            <option value="">-- Select Front Tires --</option>
                            <?php foreach ($tires_list as $tire_set): ?>
                                <option value="<?php echo $tire_set['id']; ?>"><?php echo htmlspecialchars($tire_set['set_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="rear_tires_id" class="form-label">Rear Tire Set</label>
                        <select class="form-select" id="rear_tires_id" name="rear_tires_id">
                            <option value="">-- Select Rear Tires --</option>
                            <?php foreach ($tires_list as $tire_set): ?>
                                <option value="<?php echo $tire_set['id']; ?>"><?php echo htmlspecialchars($tire_set['set_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    </div>
                <button type="submit" class="btn btn-primary mt-3">Add Log to Event</button>
            </form>
        </div>
    </div>

    <h3>Logged Sessions for this Event</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Setup Used</th>
                    <th>Tires Used</th>
                    <th>Result</th>
                    <th>Best Lap</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($race_logs)): ?>
                    <tr><td colspan="7" class="text-center">No sessions logged yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($race_logs as $log): ?>
                        <tr>
                            <td>...</td>
                            <td>...</td>
                            <td style="font-size: 0.8rem;">
                                <?php if($log['front_tire_name']) echo '<strong>F:</strong> ' . htmlspecialchars($log['front_tire_name']); ?>
                                <?php if($log['rear_tire_name']) echo '<br><strong>R:</strong> ' . htmlspecialchars($log['rear_tire_name']); ?>
                            </td>
                            <td>...</td>
                            <td>...</td>
                            <td>...</td>
                            <td>...</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    // ... (Your existing JavaScript for the dynamic event type selector) ...
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>