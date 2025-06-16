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

$stmt_event_check = $pdo->prepare("SELECT id FROM race_events WHERE id = ? AND user_id = ?");
$stmt_event_check->execute([$event_id, $user_id]);
if (!$stmt_event_check->fetch()) {
    // Event not found or doesn't belong to the user
    header('Location: events.php?error=notfound');
    exit;
}

// 2. Handle form submission to UPDATE the event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_event') {
    $event_name = trim($_POST['event_name']);
    $event_date = trim($_POST['event_date']);
    $track_id = intval($_POST['track_id']);
    $notes = trim($_POST['notes']);

    if (empty($event_name) || empty($event_date) || empty($track_id)) {
        $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    } else {
        $stmt_update = $pdo->prepare("UPDATE race_events SET event_name = ?, event_date = ?, track_id = ?, notes = ? WHERE id = ? AND user_id = ?");
        $stmt_update->execute([$event_name, $event_date, $track_id, $notes, $event_id, $user_id]);

        // Redirect back to the events list with a success message
        header("Location: events.php?updated=1");
        exit;
    }
}

// 3. Fetch existing event data to pre-populate the form
$stmt_event_details = $pdo->prepare("SELECT * FROM race_events WHERE id = ?");
$stmt_event_details->execute([$event_id]);
$event_data = $stmt_event_details->fetch(PDO::FETCH_ASSOC);

// Fetch all tracks for the dropdown menu
$stmt_tracks = $pdo->prepare("SELECT id, name FROM tracks WHERE user_id = ? ORDER BY name");
$stmt_tracks->execute([$user_id]);
$tracks_list = $stmt_tracks->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Race Event</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h3>Edit Race Event</h3>
    <a href="events.php">&laquo; Back to Events List</a>
    <hr>
    <?php echo $message; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="edit_event">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="event_name" class="form-label">Event Name</label>
                        <input type="text" class="form-control" id="event_name" name="event_name" value="<?php echo htmlspecialchars($event_data['event_name']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="event_date" class="form-label">Event Date</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" value="<?php echo htmlspecialchars($event_data['event_date']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="track_id" class="form-label">Track</label>
                        <select class="form-select" id="track_id" name="track_id" required>
                            <option value="">Select a track...</option>
                            <?php foreach ($tracks_list as $track): ?>
                                <option value="<?php echo $track['id']; ?>" <?php echo ($track['id'] == $event_data['track_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($track['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="notes" class="form-label">Event Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($event_data['notes']); ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
                <a href="events.php" class="btn btn-secondary mt-3">Cancel</a>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>