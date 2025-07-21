<?php
require 'db_config.php';

// --- Configuration ---
$user_id_to_process = 1; 
$venue_id_to_process = '1053';
$driver_name_to_process = 'Paul Webb';
$race_class_to_process = 'Mini BL';
$api_url = "http://109.155.110.165/scrape";
$api_url .= "?venueId=" . urlencode($venue_id_to_process);
$api_url .= "&driverName=" . urlencode($driver_name_to_process);
$api_url .= "&raceClass=" . urlencode($race_class_to_process);

echo "Starting import process for user ID: $user_id_to_process...\n";

// --- 1. Call the Python API using cURL ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Give the scraper up to 60 seconds to run
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
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

// --- 2. Process and save the data to the database ---
$event_name = $data['latest_event']['event_name'];
$event_date_str = $data['latest_event']['event_date'];
$event_date_for_db = DateTime::createFromFormat('d/m/Y', $event_date_str)->format('Y-m-d');

$pdo->beginTransaction();
try {
    $stmt_check = $pdo->prepare("SELECT id FROM race_events WHERE user_id = ? AND event_name = ? AND event_date = ?");
    $stmt_check->execute([$user_id_to_process, $event_name, $event_date_for_db]);
    if ($stmt_check->fetch()) {
        echo "Event '$event_name' on $event_date_for_db already exists. Skipping.\n";
        $pdo->rollBack();
        exit;
    }

    // ... (The rest of your database INSERT and UPDATE logic remains exactly the same) ...

    $pdo->commit();
    echo "Import process completed successfully!\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Database error during import: " . $e->getMessage() . "\n");
}
?>