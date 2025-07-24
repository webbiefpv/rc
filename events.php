<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// --- Fetch user's default importer settings ---
$stmt_user_settings = $pdo->prepare("SELECT default_venue_id, default_driver_name, default_race_class FROM users WHERE id = ?");
$stmt_user_settings->execute([$user_id]);
$user_settings = $stmt_user_settings->fetch(PDO::FETCH_ASSOC);

// --- Handle the "Import Latest Race Event" action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_latest') {
    $venue_id_to_import = intval($_POST['venue_id']); // This is the local venue ID from our 'venues' table

    // 1. Get the official_venue_id from our local venue record
    $stmt_venue_info = $pdo->prepare("SELECT official_venue_id FROM venues WHERE id = ? AND user_id = ?");
    $stmt_venue_info->execute([$venue_id_to_import, $user_id]);
    $venue_id_to_process = $stmt_venue_info->fetchColumn();

    $driver_name_to_process = $user_settings['default_driver_name'] ?? '';
    $race_class_to_process = $user_settings['default_race_class'] ?? 'Mini BL';

    if ($venue_id_to_process && $driver_name_to_process) {
        // 2. Build the API URL and call the Python scraper
        $api_url = "http://109.155.110.165/scrape"; // Your Python server IP
        $api_url .= "?venueId=" . urlencode($venue_id_to_process);
        $api_url .= "&driverName=" . urlencode($driver_name_to_process);
        $api_url .= "&raceClass=" . urlencode($race_class_to_process);

        // Use cURL for a more robust connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $json_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $message = '<div class="alert alert-danger"><strong>Connection Error:</strong> Could not connect to the scraper API. <br><strong>Details:</strong> ' . htmlspecialchars($curl_error) . '</div>';
        } elseif ($http_code != 200) {
            $message = '<div class="alert alert-danger"><strong>API Error:</strong> Connected to the server, but it returned an HTTP error code: ' . $http_code . '</div>';
        } else {
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
                            // Insert the new event, linking it to the VENUE, with a NULL track_id for now
                            $stmt_event = $pdo->prepare("INSERT INTO race_events (user_id, venue_id, event_name, event_date, track_id) VALUES (?, ?, ?, ?, ?)");
                            $stmt_event->execute([$user_id, $venue_id_to_import, $event_name, $event_date_for_db, null]);
                            $new_event_id = $pdo->lastInsertId();
                            
                            foreach ($data['latest_event']['races'] as $race) {
                                $stmt_log = $pdo->prepare("INSERT INTO race_logs (user_id, event_id, track_id, setup_id, race_date, event_type, laps_completed, total_race_time, best_lap_time, best_10_avg, best_3_consecutive_avg, finishing_position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt_log->execute([$user_id, $new_event_id, null, null, $event_date_for_db, $race['race_name'], $race['laps'], $race['total_time'], $race['best_lap'], $race['best_10_avg'], $race['best_3_consecutive'], $race['position']]);
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
                            $message = '<div class="alert alert-success">Successfully imported the latest race event! Please go to the event to assign a track layout.</div>';
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = '<div class="alert alert-danger">A database error occurred during import: ' . $e->getMessage() . '</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Could not parse the event date from the scraper.</div>';
                }
            } else {
                $message = '<div class="alert alert-warning">Scraper connected, but returned no new event data. Please check the details on rc-results.com.</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">Could not import. Ensure the selected venue has an Official ID and your profile has a default driver name set.</div>';
    }
}

// Fetch venues that have an official ID, for the import dropdown
$stmt_importable_venues = $pdo->prepare("SELECT id, name, official_venue_id FROM venues WHERE user_id = ? AND official_venue_id IS NOT NULL ORDER BY name");
$stmt_importable_venues->execute([$user_id]);
$importable_venues = $stmt_importable_venues->fetchAll();

// Fetch all existing Race Events to display in the list
$stmt_events = $pdo->prepare("SELECT e.*, t.name as track_name, v.name as venue_name FROM race_events e JOIN venues v ON e.venue_id = v.id LEFT JOIN tracks t ON e.track_id = t.id WHERE e.user_id = ? ORDER BY e.event_date DESC");
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
    <p>Import your latest results automatically from rc-results.com.</p>
    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header"><h5>Import Latest Race Event</h5></div>
        <div class="card-body">
            <?php if (empty($importable_venues)): ?>
                <div class="alert alert-secondary">To use the importer, you must first <a href="venues.php">create a venue</a> and add its "Official Venue ID".</div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="import_latest">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <label for="venue_id" class="form-label">Select Venue to Import From:</label>
                            <select class="form-select" name="venue_id" id="venue_id" required>
                                <?php foreach ($importable_venues as $venue): ?>
                                    <option value="<?php echo $venue['id']; ?>" <?php echo (isset($user_settings['default_venue_id']) && $user_settings['default_venue_id'] == $venue['official_venue_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($venue['name']); ?> (ID: <?php echo $venue['official_venue_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success w-100">Import Latest Results</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
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
                    <p class="mb-1">
                        Venue: <?php echo htmlspecialchars($event['venue_name']); ?> | 
                        Layout: 
                        <?php if ($event['track_id']): ?>
                            <span class="fw-bold"><?php echo htmlspecialchars($event['track_name']); ?></span>
                        <?php else: ?>
                            <span class="text-warning">Layout not assigned</span>
                        <?php endif; ?>
                    </p>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>