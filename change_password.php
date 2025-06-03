<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$current_password = $_POST['current_password'];
	$new_password = $_POST['new_password'];
	$confirm_password = $_POST['confirm_password'];

	// Validate inputs
	if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
		$error = "All fields are required.";
	} elseif ($new_password !== $confirm_password) {
		$error = "New password and confirmation do not match.";
	} elseif (strlen($new_password) < 8) {
		$error = "New password must be at least 8 characters long.";
	} else {
		// Fetch current password hash
		$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
		$stmt->execute([$user_id]);
		$user = $stmt->fetch();

		// Verify current password
		if ($user && password_verify($current_password, $user['password'])) {
			// Hash new password and update
			$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
			$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
			$stmt->execute([$new_password_hash, $user_id]);
			$success = "Password updated successfully.";
		} else {
			$error = "Current password is incorrect.";
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Pan Car Setup</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Pits</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rollout_calc.php">Roll Out</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="race_log.php">Race Log</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="glossary.php">On-Road Setup Glossary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="troubleshooting.php">Troubleshooting</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active" href="change_password.php">Change Password</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-5">
    <h2>Change Password</h2>
	<?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
	<?php endif; ?>
	<?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
	<?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required>
        </div>
        <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required>
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>