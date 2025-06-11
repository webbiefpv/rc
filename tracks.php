<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
	$name = trim($_POST['name']);
	$length_meters = !empty($_POST['length_meters']) ? intval($_POST['length_meters']) : null;
	$surface_type = $_POST['surface_type'];
	$grip_level = $_POST['grip_level'];
	$layout_type = $_POST['layout_type'];
	$notes = trim($_POST['notes']);
    $rotation_week_number = !empty($_POST['rotation_week_number']) ? intval($_POST['rotation_week_number']) : null;
    $track_image_url = trim($_POST['track_image_url']);

	if (empty($name) || !in_array($surface_type, ['carpet', 'asphalt', 'concrete', 'other']) ||
	    !in_array($grip_level, ['low', 'medium', 'high']) || !in_array($layout_type, ['tight', 'mixed', 'open'])) {
		$message = '<div class="alert alert-danger">Invalid input. Please check all fields.</div>';
	} else {
        $stmt = $pdo->prepare("INSERT INTO tracks (user_id, name, length_meters, surface_type, grip_level, layout_type, notes, rotation_week_number, track_image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $name, $length_meters, $surface_type, $grip_level, $layout_type, $notes, $rotation_week_number, $track_image_url]);
		$message = '<div class="alert alert-success">Track added successfully!</div>';
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
	$track_id = intval($_POST['track_id']);
	$stmt = $pdo->prepare("DELETE FROM tracks WHERE id = ? AND user_id = ?");
	$stmt->execute([$track_id, $user_id]);
	$message = '<div class="alert alert-success">Track deleted successfully!</div>';
}

$stmt = $pdo->prepare("SELECT * FROM tracks WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$tracks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track Profiles - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php
require 'header.php';
?>
<div class="container mt-3">
    <h1>Track Profiles</h1>
    <p>Add and manage track profiles to associate with your rollout calculations.</p>
	<?php echo $message; ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>Add New Track</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="row g-2">
                    <div class="col-md-4 mb-3">
                        <label for="name" class="form-label">Track Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="length_meters" class="form-label">Length (meters)</label>
                        <input type="number" class="form-control" id="length_meters" name="length_meters">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="surface_type" class="form-label">Surface Type</label>
                        <select class="form-select" id="surface_type" name="surface_type" required>
                            <option value="carpet">Carpet</option>
                            <option value="asphalt">Asphalt</option>
                            <option value="concrete">Concrete</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="grip_level" class="form-label">Grip Level</label>
                        <select class="form-select" id="grip_level" name="grip_level" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="layout_type" class="form-label">Layout Type</label>
                        <select class="form-select" id="layout_type" name="layout_type" required>
                            <option value="tight">Tight</option>
                            <option value="mixed">Mixed</option>
                            <option value="open">Open</option>
                        </select>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-md-4 mb-3">
                        <label for="rotation_week_number" class="form-label">Rotation Week # (1-6)</label>
                        <input type="number" class="form-control" id="rotation_week_number" name="rotation_week_number" min="1" max="6">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label for="track_image_url" class="form-label">Track Image URL</label>
                        <input type="text" class="form-control" id="track_image_url" name="track_image_url" placeholder="e.g., /images/track1.jpg">
                    </div>

                </div>
                <button type="submit" class="btn btn-primary">Add Track</button>
            </form>
        </div>
    </div>
    <h3>Saved Tracks</h3>
	<?php if (empty($tracks)): ?>
        <p>No tracks saved yet.</p>
	<?php else: ?>
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Layout</th>
                <th>Name</th>
                <th>Length (m)</th>
                <th>Surface</th>
                <th>Grip</th>
                <th>Layout Type</th>
                <th>Week #</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
			<?php foreach ($tracks as $track): ?>
                <tr>
                    <td>
                        <?php if (!empty($track['track_image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($track['track_image_url']); ?>" alt="Track Layout" style="width: 100px; height: auto;">
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($track['name']); ?></td>
                    <td><?php echo $track['length_meters'] ?: 'N/A'; ?></td>
                    <td><?php echo ucfirst($track['surface_type']); ?></td>
                    <td><?php echo ucfirst($track['grip_level']); ?></td>
                    <td><?php echo ucfirst($track['layout_type']); ?></td>
                    <td><?php echo $track['rotation_week_number'] ?: 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($track['notes'] ?: 'N/A'); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="track_id" value="<?php echo $track['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this track?');">Delete</button>
                        </form>
                    </td>
                </tr>
			<?php endforeach; ?>
            </tbody>
        </table>
	<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>