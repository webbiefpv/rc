<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// --- AJAX ACTION: Fetch available events from scraper ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_events_ajax') {
    header('Content-Type: application/json');
    $venue_id_to_check = intval($_POST['venue_id']);
    
    // Fetch venue's official ID
    $stmt_venue_info = $pdo->prepare("SELECT official_venue_id FROM venues WHERE id = ? AND user_id = ?");
    $stmt_venue_info->execute([$venue_id_to_check, $user_id]);
    $official_venue_id = $stmt_venue_info->fetchColumn();
    
    // Fetch user settings
    $stmt_user_settings = $pdo->prepare("SELECT default_driver_name, default_race_class FROM users WHERE id = ?");
    $stmt_user_settings->execute([$user_id]);
    $user_settings = $stmt_user_settings->fetch(PDO::FETCH_ASSOC);

    if (!$official_venue_id || !$user_settings['default_driver_name']) {
        echo json_encode(['error' => 'Venue official ID or default driver name is not set.']);
        exit;
    }

    // --- Call Scraper API ---
    // IMPORTANT: This assumes the scraper API is updated to return a JSON object with an "events" key,
    // which contains an array of all recent events, not just the latest.
    // Example: {"events": [{"event_name": "Event 1", "event_date": "dd/mm/yyyy", "races": [...]}, ...]}
    $api_url = "http://rcscraper.ddns.net/scrape?fetchAll=true"; // Added fetchAll=true parameter
    $api_url .= "&venueId=" . urlencode($official_venue_id);
    $api_url .= "&driverName=" . urlencode($user_settings['default_driver_name']);
    $api_url .= "&raceClass=" . urlencode($user_settings['default_race_class'] ?? 'Mini BL');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $json_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error || $http_code != 200) {
        echo json_encode(['error' => 'Failed to connect to the scraper API. ' . $curl_error]);
        exit;
    }

    $data = json_decode($json_data, true);
    if (empty($data['events'])) {
        echo json_encode(['error' => 'No events found by the scraper for your criteria.']);
        exit;
    }
    
    // --- Compare with existing events ---
    $stmt_existing = $pdo->prepare("SELECT event_name, event_date FROM race_events WHERE user_id = ? AND venue_id = ?");
    $stmt_existing->execute([$user_id, $venue_id_to_check]);
    $existing_events_raw = $stmt_existing->fetchAll(PDO::FETCH_ASSOC);
    $existing_events_lookup = [];
    foreach ($existing_events_raw as $event) {
        $existing_events_lookup[$event['event_name'] . '|' . $event['event_date']] = true;
    }

    $new_events = [];
    foreach ($data['events'] as $scraped_event) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $scraped_event['event_date']);
        if ($date_obj) {
            $event_date_for_db = $date_obj->format('Y-m-d');
            $lookup_key = $scraped_event['event_name'] . '|' . $event_date_for_db;
            if (!isset($existing_events_lookup[$lookup_key])) {
                // This event is new, add it to the list to be returned
                $new_events[] = $scraped_event;
            }
        }
    }

    echo json_encode(['success' => true, 'events' => $new_events]);
    exit;
}


// --- Fetch user's default importer settings ---
$stmt_user_settings = $pdo->prepare("SELECT default_venue_id, default_driver_name, default_race_class FROM users WHERE id = ?");
$stmt_user_settings->execute([$user_id]);
$user_settings = $stmt_user_settings->fetch(PDO::FETCH_ASSOC);

// --- Handle ALL POST actions for this page ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- NEW LOGIC: Use 'if/elseif' to handle actions exclusively ---
    
    // Handle DELETING a Race Event
    if ($_POST['action'] === 'delete_event') {
        $event_id_to_delete = intval($_POST['event_id']);

        // Ensure the event belongs to the current user before deleting
        $stmt_check = $pdo->prepare("SELECT id FROM race_events WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$event_id_to_delete, $user_id]);
        if ($stmt_check->fetch()) {
            $pdo->beginTransaction();
            try {
                // ... (existing delete logic is fine)
            } catch (Exception $e) {
                // ... (existing delete logic is fine)
            }
        }
    } 
    // Handle IMPORTING selected events
    elseif ($_POST['action'] === 'import_selected') {
        $events_to_import = isset($_POST['events']) ? $_POST['events'] : [];
        $venue_id_to_import = intval($_POST['venue_id']);
        $imported_count = 0;

        if (empty($events_to_import)) {
            $message = '<div class="alert alert-warning">No events were selected to import.</div>';
        } else {
            foreach ($events_to_import as $event_json) {
                $event_data = json_decode($event_json, true);
                if (!$event_data) continue;

                $event_name = $event_data['event_name'];
                $date_obj = DateTime::createFromFormat('d/m/Y', $event_data['event_date']);
                $event_date_for_db = $date_obj ? $date_obj->format('Y-m-d') : null;

                if ($event_name && $event_date_for_db) {
                    $pdo->beginTransaction();
                    try {
                         $stmt_check = $pdo->prepare("SELECT id FROM race_events WHERE user_id = ? AND event_name = ? AND event_date = ?");
                         $stmt_check->execute([$user_id, $event_name, $event_date_for_db]);
                         if ($stmt_check->fetch()) {
                             // Skip if it somehow already exists
                             $pdo->rollBack();
                             continue;
                         }

                        $stmt_event = $pdo->prepare("INSERT INTO race_events (user_id, venue_id, event_name, event_date) VALUES (?, ?, ?, ?)");
                        $stmt_event->execute([$user_id, $venue_id_to_import, $event_name, $event_date_for_db]);
                        $new_event_id = $pdo->lastInsertId();

                        foreach ($event_data['races'] as $race) {
                            $stmt_log = $pdo->prepare("INSERT INTO race_logs (user_id, event_id, race_date, event_type, laps_completed, total_race_time, best_lap_time, best_10_avg, best_3_consecutive_avg, finishing_position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_log->execute([$user_id, $new_event_id, $event_date_for_db, $race['race_name'], $race['laps'], $race['total_time'], $race['best_lap'], $race['best_10_avg'], $race['best_3_consecutive'], $race['position']]);
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
                        $imported_count++;
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message .= '<div class="alert alert-danger">Error importing event ' . htmlspecialchars($event_name) . '. DB Error: ' . $e->getMessage() . '</div>';
                    }
                }
            }
            if ($imported_count > 0) {
                $message = '<div class="alert alert-success">Successfully imported ' . $imported_count . ' new event(s)!</div>';
            }
        }
    }
}


// Fetch venues that have an official ID, for the import dropdown
$stmt_importable_venues = $pdo->prepare("SELECT id, name, official_venue_id FROM venues WHERE user_id = ? AND official_venue_id IS NOT NULL ORDER BY name");
$stmt_importable_venues->execute([$user_id]);
$importable_venues = $stmt_importable_venues->fetchAll();

// Fetch all existing Race Events to display in the list
$stmt_events = $pdo->prepare("
    SELECT e.*, t.name as track_name, v.name as venue_name 
    FROM race_events e 
    LEFT JOIN venues v ON e.venue_id = v.id 
    LEFT JOIN tracks t ON e.track_id = t.id 
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
        <div class="card-header"><h5>Import Race Events</h5></div>
        <div class="card-body">
            <?php if (empty($importable_venues)): ?>
                <div class="alert alert-secondary">To use the importer, you must first <a href="venues.php">create a venue</a> and add its "Official Venue ID".</div>
            <?php else: ?>
                <div class="row align-items-end">
                    <div class="col-md-8">
                        <label for="venue_id_fetch" class="form-label">Select Venue to Check:</label>
                        <select class="form-select" id="venue_id_fetch" required>
                            <?php foreach ($importable_venues as $venue): ?>
                                <option value="<?php echo $venue['id']; ?>" <?php echo (isset($user_settings['default_venue_id']) && $user_settings['default_venue_id'] == $venue['official_venue_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($venue['name']); ?> (ID: <?php echo $venue['official_venue_id']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" id="fetch-events-btn" class="btn btn-primary w-100">Check for New Events</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div id="import-results-container" class="card-footer" style="display: none;">
            </div>
    </div>

    <h3>Your Logged Events</h3>
    <div class="list-group">
        <?php if (empty($events_list)): ?>
            <p class="text-muted">No events logged yet.</p>
        <?php else: ?>
            <?php foreach ($events_list as $event): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="view_event.php?event_id=<?php echo $event['id']; ?>" class="text-decoration-none text-reset flex-grow-1">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1"><?php echo htmlspecialchars($event['event_name']); ?></h5>
                            <small><?php echo date("D, M j, Y", strtotime($event['event_date'])); ?></small>
                        </div>
                        <p class="mb-1">
                            Venue: <?php echo htmlspecialchars($event['venue_name'] ?? 'Venue Not Assigned'); ?> | 
                            Layout: 
                            <?php if ($event['track_id']): ?>
                                <span class="fw-bold"><?php echo htmlspecialchars($event['track_name']); ?></span>
                            <?php else: ?>
                                <span class="text-warning">Layout not assigned</span>
                            <?php endif; ?>
                        </p>
                    </a>
                    <form method="POST" class="ms-3" onsubmit="return confirm('WARNING: This will permanently delete this event and ALL associated race logs. Are you sure?');">
                        <input type="hidden" name="action" value="delete_event">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('fetch-events-btn').addEventListener('click', function() {
    const venueId = document.getElementById('venue_id_fetch').value;
    const resultsContainer = document.getElementById('import-results-container');
    const btn = this;
    
    resultsContainer.style.display = 'block';
    resultsContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div><p>Checking for new events...</p></div>';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'fetch_events_ajax');
    formData.append('venue_id', venueId);

    fetch('events.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            resultsContainer.innerHTML = `<div class="alert alert-danger mb-0">${data.error}</div>`;
        } else if (data.success && data.events.length > 0) {
            let html = '<form method="POST"><input type="hidden" name="action" value="import_selected"><input type="hidden" name="venue_id" value="' + venueId + '"><h5>New Events Found:</h5><ul class="list-group mb-3">';
            data.events.forEach(event => {
                const eventJson = JSON.stringify(event).replace(/'/g, "&apos;");
                html += `<li class="list-group-item">
                            <input class="form-check-input me-2" type="checkbox" name="events[]" value='${eventJson}' id="event_${event.event_name.replace(/\s+/g, '')}" checked>
                            <label class="form-check-label" for="event_${event.event_name.replace(/\s+/g, '')}"><strong>${event.event_name}</strong> (${event.event_date})</label>
                         </li>`;
            });
            html += '</ul><button type="submit" class="btn btn-success">Import Selected Events</button></form>';
            resultsContainer.innerHTML = html;
        } else {
            resultsContainer.innerHTML = '<div class="alert alert-info mb-0">No new events found to import. Your logs are up to date.</div>';
        }
    })
    .catch(error => {
        resultsContainer.innerHTML = `<div class="alert alert-danger mb-0">An unexpected error occurred. Check the console for details.</div>`;
        console.error('Error:', error);
    })
    .finally(() => {
        btn.disabled = false;
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>