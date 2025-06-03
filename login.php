<?php
require 'db_config.php';
require 'auth.php';

if (isLoggedIn()) {
	header('Location: index.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = $_POST['username'];
	$password = $_POST['password'];

	$stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
	$stmt->execute([$username]);
	$user = $stmt->fetch();

	if ($user && password_verify($password, $user['password'])) {
		$_SESSION['user_id'] = $user['id'];
		header('Location: index.php');
		exit;
	} else {
		$error = "Invalid username or password";
	}

	// Simple registration for demo purposes
	if (isset($_POST['register'])) {
		$hashed_password = password_hash($password, PASSWORD_DEFAULT);
		try {
			$stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
			$stmt->execute([$username, $hashed_password]);
			$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
			$stmt->execute([$username]);
			$_SESSION['user_id'] = $stmt->fetch()['id'];
			header('Location: index.php');
			exit;
		} catch (PDOException $e) {
			$error = "Registration failed: " . $e->getMessage();
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Login - Pan Car Setup</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container mt-5">
	<h2>Login / Register</h2>
	<?php if (isset($error)): ?>
		<div class="alert alert-danger"><?php echo $error; ?></div>
	<?php endif; ?>
	<form method="POST">
		<div class="mb-3">
			<label for="username" class="form-label">Username</label>
			<input type="text" class="form-control" id="username" name="username" required>
		</div>
		<div class="mb-3">
			<label for="password" class="form-label">Password</label>
			<input type="password" class="form-control" id="password" name="password" required>
		</div>
		<button type="submit" class="btn btn-primary">Login</button>
		<button type="submit" name="register" class="btn btn-secondary">Register</button>
	</form>
</div>
</body>
</html>