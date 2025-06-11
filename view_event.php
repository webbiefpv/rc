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

$stmt_event = $pdo->prepare("
    SELECT e.*, t.name as track_name, t.track_image_url 
    FROM race_events e 
    JOIN tracks t ON e.track_id = t.id 
    WHERE e.id = ? AND e.user_id = ?
");
$stmt_event->execute([$event_id, $user_id]);
$event = $stmt_event->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    // Event not found or doesn't belong to the user
    header('Location: events.php?error=notfound');
    exit;
}

// Check for a success message from the redirect
if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '<div class="alert alert-success">Race log added to the event successfully!</div>';
}

// 2. Handle the "Add New Log" form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_log') {
    // Collect data from the form
    $setup_id = intval($_POST['setup_id']);
    $event_type = trim($_POST['event_type']);
    $race_date = $event['event_date'] . ' ' . trim($_POST['race_time']); // Combine event date with submitted time
    $laps_completed = !empty($_POST['laps_completed']) ? intval($_POST['laps_completed']) : null;
    $total_race_time = trim($_POST['total_race_time']);
    $best_lap_time = trim($_POST['best_lap_time']);
    $best_10_avg = trim($_POST['best_10_avg']);
    $best_3_consecutive_avg = trim($_POST['best_3_consecutive_avg']);
    $track_conditions_notes = trim($_POST['track_conditions_notes']);
    $car_performance_notes = trim($_POST['car_performance_notes']);
    $lap_times_pasted = trim($_POST['lap_times']);

    if (empty($setup_id) || empty($event_type)) {
        $message = '<div class="alert alert-danger">Please select a Setup and Event Type.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            // Insert the main log entry
            $stmt_insert_log = $pdo->prepare("
                INSERT INTO race_logs (user_id, event_id, track_id, setup_id, race_date, event_type, laps_completed, total_race_time, best_lap_time, best_10_avg, best_3_consecutive_avg, track_conditions_notes, car_performance_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert_log->execute([$user_id, $event_id, $event['track_id'], $setup_id, $race_date, $event_type, $laps_completed, $total_race_time, $best_lap_time, $best_10_avg, $best_3_consecutive_avg, $track_conditions_notes, $car_performance_notes]);
            $new_race_log_id = $pdo->lastInsertId();

            // Parse and insert individual lap times
            if (!empty($lap_times_pasted)) {
                $lap_times_array = preg_split('/[\s,]+/', $lap_times_pasted); // Split by space, comma, or newline
                $stmt_insert_lap = $pdo->prepare("INSERT INTO race_lap_times (race_log_id, lap_number, lap_time) VALUES (?, ?, ?)");
                $lap_number = 1;
                foreach ($lap_times_array as $lap_time) {
                    if (is_numeric($lap_time) && $lap_time > 0) {
                        $stmt_insert_lap->execute([$new_race_log_id, $lap_number, $lap_time]);
                        $lap_number++;
                    }
                }
            }

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
// Fetch Setups for the "Add Log" form dropdown
$stmt_setups = $pdo->prepare("
    SELECT s.id, s.name as setup_name, m.name as model_name, s.is_baseline 
    FROM setups s 
    JOIN models m ON s.model_id = m.id 
    WHERE m.user_id = ? 
    ORDER BY m.name, s.is_baseline DESC, s.name
");
$stmt_setups->execute([$user_id]);
$setups_list = $stmt_setups->fetchAll();

// Fetch existing race logs for THIS event
$stmt_logs = $pdo->prepare("
    SELECT 
        rl.*, 
        s.name as setup_name,
        m.name as model_name
    FROM race_logs rl
    JOIN setups s ON rl.setup_id = s.id
    JOIN models m ON s.model_id = m.id
    WHERE rl.event_id = ?
    ORDER BY rl.race_date ASC
");
$stmt_logs->execute([$event_id]);
$race_logs = $stmt_logs->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Event: <?php echo htmlspecialchars($event['event_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">

    <div class="card bg-light p-3 mb-4">
        <div class="row g-0">
            <?php if (!empty($event['track_image_url'])): ?>
                <div class="col-md-2">
                    <img src="<?php echo htmlspecialchars($event['track_image_url']); ?>" class="img-fluid rounded-start" alt="Track Layout">
                </div>
            <?php endif; ?>
            <div class="col-md-10">
                <div class="card-body py-0">
                    <h2 class="card-title"><?php echo htmlspecialchars($event['event_name']); ?></h2>
                    <p class="card-text">
                        <strong>Date:</strong> <?php echo date("l, F j, Y", strtotime($event['event_date'])); ?><br>
                        <strong>Track:</strong> <?php echo htmlspecialchars($event['track_name']); ?>
                    </p>
                    <?php if (!empty($event['notes'])): ?>
                        <p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($event['notes']); ?></small></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Add a Race/Practice Log to this Event</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_log">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="race_time" class="form-label">Time of Race</label>
                        <input type="time" class="form-control" id="race_time" name="race_time" required>
                    </div>
                    <div class="col-md-5">
                        <label for="setup_id" class="form-label">Setup Used</label>
                        <select class="form-select" id="setup_id" name="setup_id" required>
                            <option value="">Select a setup...</option>
                            <?php foreach ($setups_list as $setup): ?>
                                <option value="<?php echo $setup['id']; ?>">
                                    <?php echo htmlspecialchars($setup['model_name'] . ' - ' . $setup['setup_name']); ?>
                                    <?php echo $setup['is_baseline'] ? ' ⭐' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="event_type" class="form-label">Event Type</label>
                        <select class="form-select" id="event_type" name="event_type" required>
                            <option value="Practice">Practice</option>
                            <option value="Qualifier 1">Qualifier 1</option>
                            <option value="Qualifier 2">Qualifier 2</option>
                            <option value="Qualifier 3">Qualifier 3</option>
                            <option value="Qualifier 4">Qualifier 4</option>
                            <option value="A Final">A Final</option>
                            <option value="B Final">B Final</option>
                            <option value="C Final">C Final</option>
                        </select>
                    </div>

                    <div class="col-md-2"><label for="laps_completed" class="form-label">Laps</label><input type="number" class="form-control" id="laps_completed" name="laps_completed"></div>
                    <div class="col-md-2"><label for="total_race_time" class="form-label">Total Time</label><input type="text" class="form-control" id="total_race_time" name="total_race_time" placeholder="e.g., 305.54"></div>
                    <div class="col-md-2"><label for="best_lap_time" class="form-label">Best Lap</label><input type="text" class="form-control" id="best_lap_time" name="best_lap_time" placeholder="e.g., 11.05"></div>
                    <div class="col-md-2"><label for="best_10_avg" class="form-label">Best 10 Avg</label><input type="text" class="form-control" id="best_10_avg" name="best_10_avg" placeholder="e.g., 11.26"></div>
                    <div class="col-md-2"><label for="best_3_consecutive_avg" class="form-label">Best 3 Consec.</label><input type="text" class="form-control" id="best_3_consecutive_avg" name="best_3_consecutive_avg" placeholder="e.g., 34.54"></div>
                    <div class="col-md-2"><label for="finishing_position" class="form-label">Finishing Pos.</label><input type="number" class="form-control" id="finishing_position" name="finishing_position"></div>

                    <div class="col-md-6">
                        <label for="track_conditions_notes" class="form-label">Track Conditions</label>
                        <textarea class="form-control" id="track_conditions_notes" name="track_conditions_notes" rows="3" placeholder="e.g., High grip, medium temperature..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="car_performance_notes" class="form-label">Car Performance & Driver Feedback</label>
                        <textarea class="form-control" id="car_performance_notes" name="car_performance_notes" rows="3" placeholder="e.g., Pushed on corner entry..."></textarea>
                    </div>
                    <div class="col-md-12">
                        <label for="lap_times" class="form-label">Paste Individual Lap Times (optional)</label>
                        <textarea class="form-control" id="lap_times" name="lap_times" rows="4" placeholder="Paste all lap times here, separated by a space, comma, or on new lines..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Add Log to Event</button>
            </form>
        </div>
    </div>

    <h3>Logged Sessions for this Event</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th>Session</th>
                <th>Setup Used</th>
                <th>Result</th>
                <th>Best Lap</th>
                <th>Best 10 Avg</th>
                <th>Best 3 Consec.</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($race_logs)): ?>
                <tr><td colspan="8" class="text-center">No sessions logged for this event yet.</td></tr>
            <?php else: ?>
                <?php foreach ($race_logs as $log): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($log['event_type']); ?></strong><br><small><?php echo date("g:i a", strtotime($log['race_date'])); ?></small></td>
                        <td><a href="setup_form.php?setup_id=<?php echo $log['setup_id']; ?>"><?php echo htmlspecialchars($log['model_name'] . ' - ' . $log['setup_name']); ?></a></td>
                        <td><?php echo ($log['laps_completed'] ? $log['laps_completed'] . ' / ' : '') . $log['total_race_time']; ?></td>
                        <td><?php echo htmlspecialchars($log['best_lap_time']); ?></td>
                        <td><?php echo htmlspecialchars($log['best_10_avg']); ?></td>
                        <td><?php echo htmlspecialchars($log['best_3_consecutive_avg']); ?></td>
                        <td style="font-size: 0.8rem;"><?php echo nl2br(htmlspecialchars($log['car_performance_notes'])); ?></td>
                        <td>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>