<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$model_id = isset($_GET['model_id']) ? intval($_GET['model_id']) : 0;

if ($model_id > 0) {
    // Fetch setups for the given model ID, ensuring it belongs to the user
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.is_baseline 
        FROM setups s
        JOIN models m ON s.model_id = m.id
        WHERE s.model_id = ? AND m.user_id = ?
        ORDER BY s.is_baseline DESC, s.name ASC
    ");
    $stmt->execute([$model_id, $user_id]);
    $setups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set the content type to JSON and output the data
    header('Content-Type: application/json');
    echo json_encode($setups);
} else {
    // If no model ID is provided, return an empty array
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>