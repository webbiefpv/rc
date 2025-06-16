<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '<div class="alert alert-success">Race log entry added successfully!</div>';
}
// ADD THIS NEW CHECK
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = '<div class="alert alert-success">Race log entry deleted successfully!</div>';
}

// Section 1: Get the Event ID from the URL and verify it belongs to the user
// ==============================================================================
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
    // Event not found or doesn't belong to the user, redirect them.
    header('Location: events.php?error=notfound');
    exit;
}

// Check for a success message from a previous redirect
if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '<div class="alert alert-success">Race log added to the event successfully!</div>';
}


// Section 2: Handle the "Add New Log" form submission
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_log') {
    // --- Collect data from the form ---
    $setup_id = intval($_POST['setup_id']);
    $race_time = trim($_POST['race_time']);
    $race_date = $event['event_date'] . ' ' . ($race_time ?: '00:00:00');

    // --- Construct the event_type string from the dynamic inputs ---
    $event_category = $_POST['event_category'] ?? 'Practice';
    $event_type = $event_category; // Default to the category name

    if ($event_category === 'Qualifier') {
        $round_number = !empty($_POST['round_number']) ? intval($_POST['round_number']) : 1;
        $event_type = 'Qualifier ' . $round_number;
    } elseif ($event_category === 'Final') {
        $final_letter = !empty($_POST['final_letter']) ? strtoupper(trim($_POST['final_letter'])) : 'A';
        $event_type = $final_letter . ' Final';
    }

    // --- Collect the rest of the form data ---
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
            // --- Insert the main log entry into 'race_logs' table ---
            $stmt_insert_log = $pdo->prepare("
                INSERT INTO race_logs (user_id, event_id, track_id, setup_id, race_date, event_type, laps_completed, total_race_time, best_lap_time, best_10_avg, best_3_consecutive_avg, finishing_position, track_conditions_notes, car_performance_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert_log->execute([$user_id, $event_id, $event['track_id'], $setup_id, $race_date, $event_type, $laps_completed, $total_race_time, $best_lap_time, $best_10_avg, $best_3_consecutive_avg, $finishing_position, $track_conditions_notes, $car_performance_notes]);
            $new_race_log_id = $pdo->lastInsertId();

            // --- Parse and insert individual lap times into 'race_lap_times' table ---
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

            // If everything was successful, commit the changes and redirect
            $pdo->commit();
            header("Location: view_event.php?event_id=" . $event_id . "&added=1");
            exit;

        } catch (PDOException $e) {
            // If anything failed, roll back all changes and show an error
            $pdo->rollBack();
            $message = '<div class="alert alert-danger">An error occurred while saving the log.</div>';
            error_log("Race log insert failed: " . $e->getMessage());
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_log') {
    $log_id_to_delete = intval($_POST['log_id_to_delete']);

    // Security check: Ensure the log belongs to the user before deleting
    $stmt_check = $pdo->prepare("SELECT id FROM race_logs WHERE id = ? AND user_id = ?");
    $stmt_check->execute([$log_id_to_delete, $user_id]);
    if ($stmt_check->fetch()) {
        // Log exists and belongs to the user, so proceed with deletion
        $stmt_delete = $pdo->prepare("DELETE FROM race_logs WHERE id = ?");
        $stmt_delete->execute([$log_id_to_delete]);

        // Because we used ON DELETE CASCADE when creating the tables,
        // all related lap times in 'race_lap_times' will be deleted automatically.

        // Redirect back to the same page with a success message
        header("Location: view_event.php?event_id=" . $event_id . "&deleted=1");
        exit;
    }
}


// Section 3: Fetch Data needed for Page Display
// ==============================================================================
// Fetch Setups for the "Add Log" form dropdown
$stmt_setups = $pdo->prepare("
    SELECT s.id, s.name as setup_name, m.name as model_name, s.is_baseline 
    FROM setups s 
    JOIN models m ON s.model_id = m.id 
    WHERE m.user_id = ? 
    ORDER BY m.name, s.is_baseline DESC, s.name
");
$stmt_setups->execute([$user_id]);
$setups_list = $stmt_setups->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing race logs for THIS event to display in the table
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
$race_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

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
            <div class="col-md-10 ps-md-3">
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
                    <div class="col-md-4">
                        <label for="event_category" class="form-label">Event Type</label>
                        <select class="form-select" id="event_category" name="event_category" required>
                            <option value="Practice" selected>Practice</option>
                            <option value="Qualifier">Qualifier</option>
                            <option value="Final">Final</option>
                        </select>
                    </div>

                    <div class="col-md-2" id="qualifier_round_div" style="display: none;">
                        <label for="round_number" class="form-label">Round #</label>
                        <input type="number" class="form-control" id="round_number" name="round_number" min="1" value="1">
                    </div>

                    <div class="col-md-2" id="final_letter_div" style="display: none;">
                        <label for="final_letter" class="form-label">Final</label>
                        <input type="text" class="form-control" id="final_letter" name="final_letter" placeholder="e.g., A, B..." maxlength="2">
                    </div>
                    <div class="col-md-4">
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
                    <div class="col-md-2">
                        <label for="race_time" class="form-label">Time of Race</label>
                        <input type="time" class="form-control" id="race_time" name="race_time" required>
                    </div>
                    <hr class="my-3">
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
        <table class="table table-striped table-hover align-middle">
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
                        <td>
                            <a href="view_log.php?log_id=<?php echo $log['id']; ?>">
                                <strong><?php echo htmlspecialchars($log['event_type']); ?></strong><br>
                                <small><?php echo date("g:i a", strtotime($log['race_date'])); ?></small>
                            </a>
                        </td>
                        <td><a href="setup_form.php?setup_id=<?php echo $log['setup_id']; ?>"><?php echo htmlspecialchars($log['model_name'] . ' - ' . $log['setup_name']); ?></a></td>
                        <td><?php echo ($log['laps_completed'] ? $log['laps_completed'] . ' / ' . $log['total_race_time'] : 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($log['best_lap_time'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($log['best_10_avg'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($log['best_3_consecutive_avg'] ?: 'N/A'); ?></td>
                        <td style="font-size: 0.8rem;">
                            <?php if(!empty($log['car_performance_notes'])) { echo '<strong>Car:</strong> ' . nl2br(htmlspecialchars($log['car_performance_notes'])); } ?>
                            <?php if(!empty($log['track_conditions_notes'])) { echo '<br><strong>Track:</strong> ' . nl2br(htmlspecialchars($log['track_conditions_notes'])); } ?>
                        </td>
                        <td>
                            <a href="edit_log.php?log_id=<?php echo $log['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this log entry?');">
                                <input type="hidden" name="action" value="delete_log">
                                <input type="hidden" name="log_id_to_delete" value="<?php echo $log['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript to dynamically show/hide the round number and final letter inputs
    document.addEventListener('DOMContentLoaded', function() {
        const eventCategorySelect = document.getElementById('event_category');
        const qualifierDiv = document.getElementById('qualifier_round_div');
        const finalDiv = document.getElementById('final_letter_div');

        function toggleInputs() {
            const selectedCategory = eventCategorySelect.value;

            qualifierDiv.style.display = (selectedCategory === 'Qualifier') ? 'block' : 'none';
            finalDiv.style.display = (selectedCategory === 'Final') ? 'block' : 'none';
        }

        // Call it once on page load to set the initial state
        toggleInputs();

        // Add event listener for when the user changes the selection
        eventCategorySelect.addEventListener('change', toggleInputs);
    });
</script>
</body>
</html>