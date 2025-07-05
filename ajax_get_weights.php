<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$setup_id = isset($_GET['setup_id']) ? intval($_GET['setup_id']) : 0;

$response = []; // Default to an empty response

if ($setup_id > 0) {
    // Fetch the saved weights for the given setup ID, ensuring it belongs to the user
    $stmt = $pdo->prepare("
        SELECT lf_weight, rf_weight, lr_weight, rr_weight, notes 
        FROM weight_distribution 
        WHERE setup_id = ? AND user_id = ?
    ");
    $stmt->execute([$setup_id, $user_id]);
    $weights = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($weights) {
        $response = $weights;
    }
}

// Set the content type to JSON and output the data
header('Content-Type: application/json');
echo json_encode($response);
?>