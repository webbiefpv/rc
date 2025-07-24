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

// This query now gets all info about the event, its venue, and its assigned track layout
$stmt_event = $pdo->prepare("
    SELECT e.*, v.name as venue_name, t.name as track_name, t.track_image_url 
    FROM race_events e 
    JOIN venues v ON e.venue_id = v.id
    LEFT JOIN tracks t ON e.track_id = t.id 
    WHERE e.id = ? AND e.user_id = ?
");
$stmt_event->execute([$event_id, $user_id]);
$event = $stmt_event->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: events.php?error=notfound');
    exit;
}

// Check for success messages from redirects
if (isset($_GET['added'])) { $message = '<div class="alert alert-success">Race log added successfully!</div>'; }
if (isset($_GET['deleted'])) { $message = '<div class="alert alert-success">Race log deleted successfully.</div>'; }
if (isset($_GET['layout_assigned'])) { $message = '<div class="alert alert-success">Track layout assigned successfully!</div>'; }

// 2. Handle all POST actions for this page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Handle ASSIGNING a track layout to the event
    if ($_POST['action'] === 'assign_layout') {
        $track_id_to_assign = intval($_POST['track_id']);
        
        $pdo->beginTransaction();
        try {
            // Update the event itself
            $stmt_update_event = $pdo->prepare("UPDATE race_events SET track_id = ? WHERE id = ?");
            $stmt_update_event->execute([$track_id_to_assign, $event_id]);

            // Update all race logs within this event
            $stmt_update_logs = $pdo->prepare("UPDATE race_logs SET track_id = ? WHERE event_id = ?");
            $stmt_update_logs->execute([$track_id_to_assign, $event_id]);
            
            $pdo->commit();
            header("Location: view_event.php?event_id=" . $event_id . "&layout_assigned=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger">Error assigning layout: ' . $e->getMessage() . '</div>';
        }
    }

    // Handle ADDING a new log
    if ($_POST['action'] === 'add_log') {
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
                $stmt_insert_log = $pdo->prepare("INSERT INTO race_logs (user_id, event_id, track_id, setup_id, front_tires_id, rear_tires_id, race_date, event_type, laps_completed, total_race_time, best_lap_time, best_10_avg, best_3_consecutive_avg, finishing_position, track_conditions_notes, car_performance_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_insert_log->execute([$user_id, $event_id, $event['track_id'], $setup_id, $front_tires_id, $rear_tires_id, $race_date, $event_type, $laps_completed, $total_race_time, $best_lap_time, $best_10_avg, $best_3_consecutive_avg, $finishing_position, $track_conditions_notes, $car_performance_notes]);
                $new_race_log_id = $pdo->lastInsertId();

                $stmt_tire_log = $pdo->prepare("INSERT INTO tire_log (tire_set_id, race_log_id, user_id) VALUES (?, ?, ?)");
                if ($front_tires_id) { $stmt_tire_log->execute([$front_tires_id, $new_race_log_id, $user_id]); }
                if ($rear_tires_id && $rear_tires_id !== $front_tires_id) { $stmt_tire_log->execute([$rear_tires_id, $new_race_log_id, $user_id]); }
                
                if (!empty($lap_times_pasted)) {
                    $lap_times_array = preg_split('/[\s,]+/', $lap_times_pasted);
                    $stmt_lap = $pdo->prepare("INSERT INTO race_lap_times (race_log_id, lap_number, lap_time) VALUES (?, ?, ?)");
                    $lap_num = 1;
                    foreach ($lap_times_array as $lap_time) {
                        if (is_numeric($lap_time) && $lap_time > 0) {
                            $stmt_lap->execute([$new_race_log_id, $lap_num, $lap_time]);
                            $lap_num++;
                        }
                    }
                }
                
                $pdo->commit();
                header("Location: view_event.php?event_id=" . $event_id . "&added=1");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger">Error adding log: ' . $e->getMessage() . '</div>';
            }
        }
    }
    
    // Handle DELETING a log
    if ($_POST['action'] === 'delete_log') {
        $log_id_to_delete = intval($_POST['log_id_to_delete']);
        $stmt_check = $pdo->prepare("SELECT id FROM race_logs WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$log_id_to_delete, $user_id]);
        if ($stmt_check->fetch()) {
            $stmt_delete = $pdo->prepare("DELETE FROM race_logs WHERE id = ?");
            $stmt_delete->execute([$log_id_to_delete]);
            header("Location: view_event.php?event_id=" . $event_id . "&deleted=1");
            exit;
        }
    }
}

// 3. Fetch data needed for page display
$stmt_setups = $pdo->prepare("SELECT s.id, s.name as setup_name, m.name as model_name, s.is_baseline FROM setups s JOIN models m ON s.model_id = m.id WHERE m.user_id = ? ORDER BY m.name, s.is_baseline DESC, s.name");
$stmt_setups->execute([$user_id]);
$setups_list = $stmt_setups->fetchAll(PDO::FETCH_ASSOC);

$stmt_layouts = $pdo->prepare("SELECT id, name FROM tracks WHERE venue_id = ? AND user_id = ? ORDER BY name");
$stmt_layouts->execute([$event['venue_id'], $user_id]);
$layouts_list = $stmt_layouts->fetchAll(PDO::FETCH_ASSOC);

$stmt_tires = $pdo->prepare("SELECT id, set_name FROM tire_inventory WHERE user_id = ? AND is_retired = FALSE ORDER BY set_name");
$stmt_tires->execute([$user_id]);
$tires_list = $stmt_tires->fetchAll(PDO::FETCH_ASSOC);

$stmt_logs = $pdo->prepare("SELECT rl.*, s.name as setup_name, m.name as model_name, front_tires.set_name as front_tire_name, rear_tires.set_name as rear_tire_name FROM race_logs rl LEFT JOIN setups s ON rl.setup_id = s.id LEFT JOIN models m ON s.model_id = m.id LEFT JOIN tire_inventory front_tires ON rl.front_tires_id = front_tires.id LEFT JOIN tire_inventory rear_tires ON rl.rear_tires_id = rear_tires.id WHERE rl.event_id = ? ORDER BY rl.race_date ASC");
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
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    
    <div class="card bg-light p-3 mb-4">
        <div class="card-body py-0">
            <h2 class="card-title"><?php echo htmlspecialchars($event['event_name']); ?></h2>
            <p class="card-text">
                <strong>Date:</strong> <?php echo date("l, F j, Y", strtotime($event['event_date'])); ?> | 
                <strong>Venue:</strong> <a href="venues.php"><?php echo htmlspecialchars($event['venue_name']); ?></a>
            </p>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header"><h5>Track Layout</h5></div>
        <div class="card-body">
            <?php if ($event['track_id']): ?>
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <?php if ($event['track_image_url']): ?>
                            <img src="<?php echo htmlspecialchars($event['track_image_url']); ?>" class="img-fluid rounded" alt="Track Layout">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <h4><a href="view_track.php?track_id=<?php echo $event['track_id']; ?>"><?php echo htmlspecialchars($event['track_name']); ?></a></h4>
                        <p class="text-muted mb-0">This layout has been assigned to the event.</p>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="assign_layout">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <label for="track_id" class="form-label">Assign a Track Layout to this Event:</label>
                            <select class="form-select" name="track_id" id="track_id" required>
                                <option value="">-- Select a layout for <?php echo htmlspecialchars($event['venue_name']); ?> --</option>
                                <?php foreach ($layouts_list as $layout): ?>
                                    <option value="<?php echo $layout['id']; ?>"><?php echo htmlspecialchars($layout['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Assign Layout</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="accordion mb-4" id="addLogAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                    Add a Race/Practice Log to this Event
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#addLogAccordion">
                <div class="accordion-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_log">
                        <div class="row g-3">
                            <div class="col-md-4"><label for="event_category" class="form-label">Event Type</label><select class="form-select" id="event_category" name="event_category" required><option value="Practice" selected>Practice</option><option value="Qualifier">Qualifier</option><option value="Final">Final</option></select></div>
                            <div class="col-md-2" id="qualifier_round_div" style="display: none;"><label for="round_number" class="form-label">Round #</label><input type="number" class="form-control" id="round_number" name="round_number" min="1" value="1"></div>
                            <div class="col-md-2" id="final_letter_div" style="display: none;"><label for="final_letter" class="form-label">Final</label><input type="text" class="form-control" id="final_letter" name="final_letter" placeholder="e.g., A, B..." maxlength="2"></div>
                            <div class="col-md-4"><label for="setup_id" class="form-label">Setup Used</label><select class="form-select" id="setup_id" name="setup_id" required><option value="">-- Select a setup --</option><?php foreach ($setups_list as $setup): ?><option value="<?php echo $setup['id']; ?>"><?php echo htmlspecialchars($setup['model_name'] . ' - ' . $setup['setup_name']); ?><?php echo $setup['is_baseline'] ? ' ⭐' : ''; ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-4"><label for="front_tires_id" class="form-label">Front Tire Set</label><select class="form-select" id="front_tires_id" name="front_tires_id"><option value="">-- Select Front Tires --</option><?php foreach ($tires_list as $tire_set): ?><option value="<?php echo $tire_set['id']; ?>"><?php echo htmlspecialchars($tire_set['set_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-4"><label for="rear_tires_id" class="form-label">Rear Tire Set</label><select class="form-select" id="rear_tires_id" name="rear_tires_id"><option value="">-- Select Rear Tires --</option><?php foreach ($tires_list as $tire_set): ?><option value="<?php echo $tire_set['id']; ?>"><?php echo htmlspecialchars($tire_set['set_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-4"><label for="race_time" class="form-label">Time of Race</label><input type="time" class="form-control" id="race_time" name="race_time" required></div>
                            <hr class="my-3">
                            <div class="col-md-2"><label for="laps_completed" class="form-label">Laps</label><input type="number" class="form-control" id="laps_completed" name="laps_completed"></div>
                            <div class="col-md-2"><label for="total_race_time" class="form-label">Total Time</label><input type="text" class="form-control" id="total_race_time" name="total_race_time" placeholder="e.g., 305.54"></div>
                            <div class="col-md-2"><label for="best_lap_time" class="form-label">Best Lap</label><input type="text" class="form-control" id="best_lap_time" name="best_lap_time" placeholder="e.g., 11.05"></div>
                            <div class="col-md-2"><label for="best_10_avg" class="form-label">Best 10 Avg</label><input type="text" class="form-control" id="best_10_avg" name="best_10_avg" placeholder="e.g., 11.26"></div>
                            <div class="col-md-2"><label for="best_3_consecutive_avg" class="form-label">Best 3 Consec.</label><input type="text" class="form-control" id="best_3_consecutive_avg" name="best_3_consecutive_avg" placeholder="e.g., 34.54"></div>
                            <div class="col-md-2"><label for="finishing_position" class="form-label">Finishing Pos.</label><input type="number" class="form-control" id="finishing_position" name="finishing_position"></div>
                            <div class="col-md-6"><label for="track_conditions_notes" class="form-label">Track Conditions</label><textarea class="form-control" id="track_conditions_notes" name="track_conditions_notes" rows="3" placeholder="e.g., High grip, medium temperature..."></textarea></div>
                            <div class="col-md-6"><label for="car_performance_notes" class="form-label">Car Performance & Driver Feedback</label><textarea class="form-control" id="car_performance_notes" name="car_performance_notes" rows="3" placeholder="e.g., Pushed on corner entry..."></textarea></div>
                            <div class="col-md-12"><label for="lap_times" class="form-label">Paste Individual Lap Times (optional)</label><textarea class="form-control" id="lap_times" name="lap_times" rows="4" placeholder="Paste all lap times here, separated by a space, comma, or on new lines..."></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Add Log to Event</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <h3>Logged Sessions for this Event</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr><th>Session</th><th>Setup Used</th><th>Tires Used</th><th>Result</th><th>Best Lap</th><th>Notes</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($race_logs)): ?>
                    <tr><td colspan="7" class="text-center">No sessions logged for this event yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($race_logs as $log): ?>
                        <tr>
                            <td><a href="view_log.php?log_id=<?php echo $log['id']; ?>"><strong><?php echo htmlspecialchars($log['event_type']); ?></strong><br><small><?php echo date("g:i a", strtotime($log['race_date'])); ?></small></a></td>
                            <td>
                                <?php if ($log['setup_id']): ?>
                                    <a href="setup_form.php?setup_id=<?php echo $log['setup_id']; ?>"><?php echo htmlspecialchars($log['model_name'] . ' - ' . $log['setup_name']); ?></a>
                                <?php else: ?>
                                    <span class="text-muted">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8rem;"><?php if($log['front_tire_name']) echo '<strong>F:</strong> ' . htmlspecialchars($log['front_tire_name']); ?><?php if($log['rear_tire_name']) echo '<br><strong>R:</strong> ' . htmlspecialchars($log['rear_tire_name']); ?></td>
                            <td><?php echo ($log['laps_completed'] ? $log['laps_completed'] . ' / ' . $log['total_race_time'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['best_lap_time'] ?: 'N/A'); ?></td>
                            <td style="font-size: 0.8rem;"><?php if(!empty($log['car_performance_notes'])) { echo '<strong>Car:</strong> ' . nl2br(htmlspecialchars($log['car_performance_notes'])); } ?></td>
                            <td>
                                <a href="edit_log.php?log_id=<?php echo $log['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');"><input type="hidden" name="action" value="delete_log"><input type="hidden" name="log_id_to_delete" value="<?php echo $log['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventCategorySelect = document.getElementById('event_category');
    const qualifierDiv = document.getElementById('qualifier_round_div');
    const finalDiv = document.getElementById('final_letter_div');
    function toggleInputs() {
        const selectedCategory = eventCategorySelect.value;
        qualifierDiv.style.display = (selectedCategory === 'Qualifier') ? 'block' : 'none';
        finalDiv.style.display = (selectedCategory === 'Final') ? 'block' : 'none';
    }
    toggleInputs();
    eventCategorySelect.addEventListener('change', toggleInputs);
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>