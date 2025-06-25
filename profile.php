<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// --- Fetch User's Name ---
$stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$username = $stmt_user->fetchColumn();

// --- Fetch User's Stats (reusing queries from index.php) ---
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
    <title>User Profile - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>User Profile</h1>
    <p class="lead">Welcome, <?php echo htmlspecialchars($username); ?>!</p>
    <hr>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Your Statistics</h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Car Models
                        <span class="badge bg-primary rounded-pill"><?php echo $models_count; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Setups
                        <span class="badge bg-primary rounded-pill"><?php echo $setups_count; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Race Events
                        <span class="badge bg-primary rounded-pill"><?php echo $events_count; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Race Logs
                        <span class="badge bg-primary rounded-pill"><?php echo $logs_count; ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Account Management</h5>
                </div>
                <div class="card-body">
                    <p>Manage your account settings and password.</p>
                    <a href="change_password.php" class="btn btn-primary">Change Password</a>
                </div>
            </div>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>