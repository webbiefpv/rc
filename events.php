<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '<div class="alert alert-success">Race Event created successfully!</div>';
}
// ADD THIS NEW CHECK
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = '<div class="alert alert-success">Race Event and all its logs have been deleted.</div>';
}

// Check for a success message from the redirect
if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '<div class="alert alert-success">Race Event created successfully!</div>';
}

// In events.php, near where you check for 'added' and 'deleted'
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = '<div class="alert alert-success">Race Event updated successfully!</div>';
}

// --- Handle Add New Event ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $event_name = trim($_POST['event_name']);
    $event_date = trim($_POST['event_date']);
    $track_id = intval($_POST['track_id']);
    $notes = trim($_POST['notes']);

    if (empty($event_name) || empty($event_date) || empty($track_id)) {
        $message = '<div class="alert alert-danger">Please fill in all required fields (Event Name, Date, Track).</div>';
    } else {
        $stmt = $pdo->prepare("INSERT INTO race_events (user_id, event_name, event_date, track_id, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $event_name, $event_date, $track_id, $notes]);

        header("Location: events.php?added=1");
        exit;
    }
}

// --- Fetch Data for the Page ---
// Fetch Tracks for the "Add Event" form dropdown
$stmt_tracks = $pdo->prepare("SELECT id, name FROM tracks WHERE user_id = ? ORDER BY name");
$stmt_tracks->execute([$user_id]);
$tracks_list = $stmt_tracks->fetchAll();

// Fetch all existing Race Events to display in the list
$stmt_events = $pdo->prepare("
    SELECT e.*, t.name as track_name, t.track_image_url 
    FROM race_events e
    JOIN tracks t ON e.track_id = t.id
    WHERE e.user_id = ?
    ORDER BY e.event_date DESC
");
$stmt_events->execute([$user_id]);
$events_list = $stmt_events->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Race Events - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Race Events</h1>
    <p>Create a new race event to start logging your practice, qualifying, and race results for a specific day.</p>
    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Create New Race Event</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="event_name" class="form-label">Event Name</label>
                        <input type="text" class="form-control" id="event_name" name="event_name" placeholder="e.g., Club Race - Week 4" required>
                    </div>
                    <div class="col-md-3">
                        <label for="event_date" class="form-label">Event Date</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" required>
                    </div>
                    <div class="col-md-3">
                        <label for="track_id" class="form-label">Track</label>
                        <select class="form-select" id="track_id" name="track_id" required>
                            <option value="">Select a track...</option>
                            <?php foreach ($tracks_list as $track): ?>
                                <option value="<?php echo $track['id']; ?>"><?php echo htmlspecialchars($track['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="notes" class="form-label">Event Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="e.g., Summer championship, running in 17.5T class..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Create Event</button>
            </form>
        </div>
    </div>

    <h3>Your Events</h3>
    <div class="list-group">
        <?php if (empty($events_list)): ?>
            <p>No events created yet.</p>
        <?php else: ?>
            <?php foreach ($events_list as $event): ?>
                <div class="list-group-item">
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <a href="view_event.php?event_id=<?php echo $event['id']; ?>" class="text-decoration-none text-dark">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                                    <small><?php echo date("D, M j, Y", strtotime($event['event_date'])); ?></small>
                                </div>
                                <p class="mb-1">Track: <?php echo htmlspecialchars($event['track_name']); ?></p>
                                <?php if (!empty($event['notes'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($event['notes']); ?></small>
                                <?php endif; ?>
                            </a>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="edit_event.php?event_id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" style="display:inline-block; margin-left: 5px;" onsubmit="return confirm('WARNING: Deleting this event will also delete ALL associated race logs. This cannot be undone. Are you sure?');">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="event_id_to_delete" value="<?php echo $event['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>