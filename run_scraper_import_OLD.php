<?php
// This script is designed to be run by a cron job, not accessed in a browser.
require 'db_config.php';

// --- Configuration ---
$user_id_to_process = 1; // Your user ID
$venue_id_to_process = '1053'; // The official venue ID from rc-results.com
$driver_name_to_process = 'Paul Webb';
$race_class_to_process = 'Mini BL';
$api_url = "http://109.155.110.165/scrape"; // Your Python server IP

$api_url .= "?venueId=" . urlencode($venue_id_to_process);
$api_url .= "&driverName=" . urlencode($driver_name_to_process);
$api_url .= "&raceClass=" . urlencode($race_class_to_process);

echo "Starting import process for user ID: $user_id_to_process...\n";

// 1. Call the Python API to get the data
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$json_data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch) || $http_code != 200) {
    die("Error: Could not connect to the scraper API. cURL error: " . curl_error($ch) . " | HTTP Code: " . $http_code . "\n");
}
curl_close($ch);

$data = json_decode($json_data, true);

if (!$data || !isset($data['latest_event']['event_name']) || empty($data['latest_event']['event_name'])) {
    die("Error: Received invalid or empty data from the scraper API.\n");
}

// Find the local track ID that matches the official venue ID
$stmt_track = $pdo->prepare("SELECT id FROM tracks WHERE official_venue_id = ? AND user_id = ?");
$stmt_track->execute([$venue_id_to_process, $user_id_to_process]);
$local_track_id = $stmt_track->fetchColumn();

if (!$local_track_id) {
    die("Error: No track found in your app with the Official Venue ID: " . htmlspecialchars($venue_id_to_process) . ".\n");
}
echo "Found matching local track with ID: $local_track_id\n";

// 2. Process and save the data to the database
$event_name = $data['latest_event']['event_name'];
$event_date_str = $data['latest_event']['event_date'];
$date_obj = DateTime::createFromFormat('d/m/Y', $event_date_str);
if ($date_obj === false) {
    die("Error: Invalid date format received from API: " . htmlspecialchars($event_date_str) . "\n");
}
$event_date_for_db = $date_obj->format('Y-m-d');

$pdo->beginTransaction();
try {
    // Check if this event already exists
    $stmt_check = $pdo->prepare("SELECT id FROM race_events WHERE user_id = ? AND event_name = ? AND event_date = ?");
    $stmt_check->execute([$user_id_to_process, $event_name, $event_date_for_db]);
    if ($stmt_check->fetch()) {
        echo "Event '$event_name' on $event_date_for_db already exists. Skipping.\n";
        $pdo->rollBack();
        exit;
    }

    // Insert the new Race Event
    $stmt_event = $pdo->prepare("INSERT INTO race_events (user_id, event_name, event_date, track_id) VALUES (?, ?, ?, ?)");
    $stmt_event->execute([$user_id_to_process, $event_name, $event_date_for_db, $local_track_id]);
    $new_event_id = $pdo->lastInsertId();
    echo "Created new event: '$event_name' with ID: $new_event_id\n";

    // Loop through each race and insert it into race_logs
    foreach ($data['latest_event']['races'] as $race) {
        $stmt_log = $pdo->prepare("
            INSERT INTO race_logs (user_id, event_id, track_id, setup_id, race_date, event_type, laps_completed, total_race_time, best_lap_time, best_10_avg, best_3_consecutive_avg, finishing_position)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // --- THIS IS THE FIX ---
        // We now pass NULL for the setup_id, which the database now allows.
        $stmt_log->execute([
            $user_id_to_process, $new_event_id, $local_track_id, null, 
            $event_date_for_db, $race['race_name'], $race['laps'], $race['total_time'], 
            $race['best_lap'], $race['best_10_avg'], $race['best_3_consecutive'], $race['position']
        ]);
        $new_race_log_id = $pdo->lastInsertId();
        echo "  - Added log for: " . $race['race_name'] . "\n";

        // Insert individual lap times
        if (!empty($race['lap_times'])) {
            $stmt_lap = $pdo->prepare("INSERT INTO race_lap_times (race_log_id, lap_number, lap_time) VALUES (?, ?, ?)");
            $lap_num = 1;
            foreach ($race['lap_times'] as $lap_time) {
                $stmt_lap->execute([$new_race_log_id, $lap_num, $lap_time]);
                $lap_num++;
            }
            echo "    - Saved " . count($race['lap_times']) . " lap times.\n";
        }
    }
    
    // Update championship standings
    if (!empty($data['championship'])) {
        $champ_data = $data['championship'];
        $stmt_champ = $pdo->prepare("
            INSERT INTO championship_standings (user_id, venue_id, championship_name, position, points_details)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                position = VALUES(position),
                points_details = VALUES(points_details)
        ");
        $stmt_champ->execute([$user_id_to_process, $venue_id_to_process, $event_name, $champ_data['position'], $champ_data['points']]);
        echo "Updated championship standings.\n";
    }

    $pdo->commit();
    echo "Import process completed successfully!\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Database error during import: " . $e->getMessage() . "\n");
}
?>