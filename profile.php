<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submission to update profile settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $default_venue_id = !empty($_POST['default_venue_id']) ? intval($_POST['default_venue_id']) : null;
    $default_driver_name = trim($_POST['default_driver_name']);
    $default_race_class = trim($_POST['default_race_class']);

    $stmt = $pdo->prepare("UPDATE users SET default_venue_id = ?, default_driver_name = ?, default_race_class = ? WHERE id = ?");
    $stmt->execute([$default_venue_id, $default_driver_name, $default_race_class, $user_id]);
    
    $message = '<div class="alert alert-success">Your settings have been updated successfully.</div>';
}

// Fetch all user data, including new settings
$stmt_user = $pdo->prepare("SELECT username, default_venue_id, default_driver_name, default_race_class FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

// --- Fetch User's Stats (from original file) ---
// Count Models
$stmt_models_count = $pdo->prepare("SELECT COUNT(*) FROM models WHERE user_id = ?");
$stmt_models_count->execute([$user_id]);
$models_count = $stmt_models_count->fetchColumn();

// Count Setups
$stmt_setups_count = $pdo->prepare("SELECT COUNT(*) FROM setups s JOIN models m ON s.model_id = m.id WHERE m.user_id = ?");
$stmt_setups_count->execute([$user_id]);
$setups_count = $stmt_setups_count->fetchColumn();

// Count Race Events
$stmt_events_count = $pdo->prepare("SELECT COUNT(*) FROM race_events WHERE user_id = ?");
$stmt_events_count->execute([$user_id]);
$events_count = $stmt_events_count->fetchColumn();

// Count Race Logs
$stmt_logs_count = $pdo->prepare("SELECT COUNT(*) FROM race_logs WHERE user_id = ?");
$stmt_logs_count->execute([$user_id]);
$logs_count = $stmt_logs_count->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Profile - Tweak Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>User Profile</h1>
    <p class="lead">Welcome, <?php echo htmlspecialchars($user_data['username']); ?>!</p>
    <?php echo $message; ?>
    <hr>

    <div class="row">
        <!-- Importer Settings Column -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5>Importer Settings</h5></div>
                <div class="card-body">
                    <p>Set your default values for the Race Event importer. These will be pre-filled on the Events page.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        <div class="mb-3">
                            <label for="default_venue_id" class="form-label">Default Venue ID</label>
                            <input type="number" class="form-control" id="default_venue_id" name="default_venue_id" value="<?php echo htmlspecialchars($user_data['default_venue_id'] ?? ''); ?>" placeholder="e.g., 1053">
                        </div>
                        <div class="mb-3">
                            <label for="default_driver_name" class="form-label">Default Driver Name</label>
                            <input type="text" class="form-control" id="default_driver_name" name="default_driver_name" value="<?php echo htmlspecialchars($user_data['default_driver_name'] ?? ''); ?>" placeholder="e.g., Paul Webb">
                        </div>
                        <div class="mb-3">
                            <label for="default_race_class" class="form-label">Default Race Class</label>
                            <input type="text" class="form-control" id="default_race_class" name="default_race_class" value="<?php echo htmlspecialchars($user_data['default_race_class'] ?? ''); ?>" placeholder="e.g., Mini BL">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Management & Stats Column -->
        <div class="col-lg-6 mb-4">
            <div class="card mb-4">
                <div class="card-header"><h5>Account Management</h5></div>
                <div class="card-body