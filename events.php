<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// --- NEW: Fetch user's default importer settings ---
$stmt_user_settings = $pdo->prepare("SELECT default_venue_id, default_driver_name, default_race_class FROM users WHERE id = ?");
$stmt_user_settings->execute([$user_id]);
$user_settings = $stmt_user_settings->fetch(PDO::FETCH_ASSOC);

// --- Handle the "Import Latest Race Event" action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_latest') {
    $venue_id_to_process = trim($_POST['venue_id']);
    $driver_name_to_process = trim($_POST['driver_name']);
    $race_class_to_process = trim($_POST['race_class']);

    // Find the corresponding local track in our DB to link the event to.
    $stmt_track_info = $pdo->prepare("SELECT id FROM tracks WHERE official_venue_id = ? AND user_id = ?");
    $stmt_track_info->execute([$venue_id_to_process, $user_id]);
    $local_track_id = $stmt_track_info->fetchColumn();

    if (!$local_track_id) {
        $message = '<div class="alert alert-danger">Error: No track found in your app with the Official Venue ID: ' . htmlspecialchars($venue_id_to_process) . '. You must have a corresponding track saved to import its events.</div>';
    } elseif (!empty($venue_id_to_process) && !empty($driver_name_to_process) && !empty($race_class_to_process)) {
        // Build the API URL and call the Python scraper
        $api_url = "http://109.155.110.165/scrape"; // Your Python server IP
        $api_url .= "?venueId=" . urlencode($venue_id_to_process);
        $api_url .= "&driverName=" . urlencode($driver_name_to_process);
        $api_url .= "&raceClass=" . urlencode($race_class_to_process);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $json_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($json_data && $http_code == 200) {
            $data = json_decode($json_data, true);
            if ($data && !empty($data['latest_event']['event_name'])) {
                $event_name = $data['latest_event']['event_name'];
                $event_date_str = $data['latest_event']['event_date'];
                $date_obj = DateTime::createFromFormat('d/m/Y', $event_date_str);
                $event_date_for_db = $date_obj ? $date_obj->format('Y-m-d') : null;

                if ($event_date_for_db) {
                    $pdo->beginTransaction();
                    try {
                        $stmt_check = $pdo->prepare("SELECT id FROM race_events WHERE user_id = ? AND event_name = ? AND event_date = ?");
                        $stmt_check->execute([$user_id, $event_name, $event_date_for_db]);
                        if ($stmt_check->fetch()) {
                            $message = '<div class="alert alert-warning">This event already exists in your log. Import skipped.</div>';
                            $pdo->rollBack();
                        } else {
                            $stmt_event = $pdo->prepare("INSERT INTO race_events (user_id, event_name, event_date, track_id) VALUES (?, ?, ?, ?)");
                            $stmt_event->execute([$user_id, $event_name, $event_date_for_db, $local_track_id]);
                            $new_event_id = $pdo->lastInsertId();
                            
                            foreach ($data['latest_event']['races'] as $race) {
                                $stmt_log = $pdo->prepare("INSERT INTO race_logs (user_id, event_id, track_id, setup_id, race_date, event_type, laps_completed, total_race_time, best_lap_time, best_10_avg, best_3_consecutive_avg, finishing_position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt_log->execute([$user_id, $new_event_id, $local_track_id, null, $event_date_for_db, $race['race_name'], $race['laps'], $race['total_time'], $race['best_lap'], $race['best_10_avg'], $race['best_3_consecutive'], $race['position']]);
                                $new_race_log_id = $pdo->lastInsertId();
                                if (!empty($race['lap_times'])) {
                                    $stmt_lap = $pdo->prepare("INSERT INTO race_lap_times (race_log_id, lap_number, lap_time) VALUES (?, ?, ?)");
                                    $lap_num = 1;
                                    foreach ($race['lap_times'] as $lap_time) {
                                        $stmt_lap->execute([$new_race_log_id, $lap_num, $lap_time]);
                                        $lap_num++;
                                    }
                                }
                            }
                            $pdo->commit();
                            $message = '<div class="alert alert-success">Successfully imported the latest race event!</div>';
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = '<div class="alert alert-danger">A database error occurred during import: ' . $e->getMessage() . '</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Could not parse the event date from the scraper.</div>';
                }
            } else {
                $message = '<div class="alert alert-warning">Scraper ran, but returned no new event data to import. Check the details and try again.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Could not connect to the scraper API. Please ensure the Python server is running.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Venue ID, Driver Name, and Race Class are all required fields.</div>';
    }
}

// Fetch tracks that have an official ID, for the import dropdown
$stmt_importable_tracks = $pdo->prepare("SELECT id, name, official_venue_id FROM tracks WHERE user_id = ? AND official_venue_id IS NOT NULL ORDER BY name");
$stmt_importable_tracks->execute([$user_id]);
$importable_tracks = $stmt_importable_tracks->fetchAll();

// Fetch all existing Race Events to display in the list
$stmt_events = $pdo->prepare("SELECT e.*, t.name as track_name FROM race_events e JOIN tracks t ON e.track_id = t.id WHERE e.user_id = ? ORDER BY e.event_date DESC");
$stmt_events->execute([$user_id]);
$events_list = $stmt_events->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Race Events - Tweak Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Race Hub</h1>
    <p>Create new events manually or import your latest results automatically from rc-results.com.</p>
    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Import Latest Race Event</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="import_latest">
                <div class="row g-3 align-items-end">
                    <div class="col-md-12">
                        <label for="saved_track_selector" class="form-label">Select a Saved Venue to pre-fill ID (Optional)</label>
                        <select class="form-select" id="saved_track_selector" onchange="updateVenueId(this)">
                            <option value="">-- Or use your default settings below --</option>
                            <?php foreach ($importable_tracks as $track): ?>
                                <option value="<?php echo $track['id']; ?>" data-venue-id="<?php echo $track['official_venue_id']; ?>">
                                    <?php echo htmlspecialchars($track['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="venue_id" class="form-label">Venue ID</label>
                        <input type="text" class="form-control" id="venue_id" name="venue_id" value="<?php echo htmlspecialchars($user_settings['default_venue_id'] ?? ''); ?>" placeholder="e.g., 1053" required>
                    </div>
                    <div class="col-md-4">
                        <label for="driver_name" class="form-label">Driver Name</label>
                        <input type="text" class="form-control" id="driver_name" name="driver_name" value="<?php echo htmlspecialchars($user_settings['default_driver_name'] ?? ''); ?>" placeholder="e.g., Paul Webb" required>
                    </div>
                    <div class="col-md-4">
                        <label for="race_class" class="form-label">Race Class</label>
                        <input type="text" class="form-control" id="race_class" name="race_class" value="<?php echo htmlspecialchars($user_settings['default_race_class'] ?? ''); ?>" placeholder="e.g., Mini BL" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success w-100">Import Latest Results</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <h3>Your Logged Events</h3>
    <div class="list-group">
        <?php if (empty($events_list)): ?>
            <p class="text-muted">No events logged yet.</p>
        <?php else: ?>
            <?php foreach ($events_list as $event): ?>
                <a href="view_event.php?event_id=<?php echo $event['id']; ?>" class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                        <small><?php echo date("D, M j, Y", strtotime($event['event_date'])); ?></small>
                    </div>
                    <p class="mb-1">Track: <?php echo htmlspecialchars($event['track_name']); ?></p>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script>
function updateVenueId(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const venueId = selectedOption.getAttribute('data-venue-id');
    document.getElementById('venue_id').value = venueId || '<?php echo htmlspecialchars($user_settings['default_venue_id'] ?? ''); ?>';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>