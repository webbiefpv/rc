<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Check for success messages from redirects
if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '<div class="alert alert-success">Track added successfully!</div>';
}
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = '<div class="alert alert-success">Track updated successfully!</div>';
}

// Handle all POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Handle ADDING a new track
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name']);
        $length_meters = !empty($_POST['length_meters']) ? intval($_POST['length_meters']) : null;
        $surface_type = $_POST['surface_type'];
        $grip_level = $_POST['grip_level'];
        $layout_type = $_POST['layout_type'];
        $notes = trim($_POST['notes']);
        $rotation_week_number = !empty($_POST['rotation_week_number']) ? intval($_POST['rotation_week_number']) : null;
        $official_venue_id = !empty($_POST['official_venue_id']) ? intval($_POST['official_venue_id']) : null; // Get new field
        $track_image_path = null;

        // Handle File Upload
        if (isset($_FILES['track_image']) && $_FILES['track_image']['error'] == 0) {
            // ... (Your existing file upload logic) ...
        }
        
        if (empty($name)) {
            $message = '<div class="alert alert-danger">Track Name is required.</div>';
        } else {
            $stmt = $pdo->prepare("INSERT INTO tracks (user_id, name, length_meters, surface_type, grip_level, layout_type, notes, rotation_week_number, track_image_url, official_venue_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $length_meters, $surface_type, $grip_level, $layout_type, $notes, $rotation_week_number, $track_image_path, $official_venue_id]);
            header("Location: tracks.php?added=1");
            exit;
        }
    }

    // Handle DELETING a track
    if ($_POST['action'] === 'delete') {
        $track_id = intval($_POST['track_id']);
        // ... (Your existing safe delete logic) ...
    }
    
    // We will add EDIT logic here in the future if needed
}

// Fetch all tracks to display
$stmt = $pdo->prepare("SELECT * FROM tracks WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$tracks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track Profiles - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Track Profiles</h1>
    <p>Add and manage track profiles. Link them to an official venue ID to enable automatic race log imports.</p>
	<?php echo $message; ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>Add New Track</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="row g-2">
                    <div class="col-md-4 mb-3">
                        <label for="name" class="form-label">Track Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="official_venue_id" class="form-label">Official Venue ID</label>
                        <input type="number" class="form-control" id="official_venue_id" name="official_venue_id" placeholder="e.g., 1053">
                        <small class="form-text text-muted">From rc-results.com for the scraper.</small>
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
                    <div class="col-md-4 mb-3">
                        <label for="rotation_week_number" class="form-label">Rotation Week # (1-6)</label>
                        <input type="number" class="form-control" id="rotation_week_number" name="rotation_week_number" min="1" max="6">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label for="track_image" class="form-label">Track Image</label>
                        <input type="file" class="form-control" id="track_image" name="track_image">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Add Track</button>
            </form>
        </div>
    </div>
    <h3>Saved Tracks</h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Layout</th>
                <th>Name</th>
                <th>Official ID</th>
                <th>Week #</th>
                <th>Surface</th>
                <th>Grip</th>
                <th>Layout</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
			<?php foreach ($tracks as $track): ?>
                <tr>
                    <td>
                        <?php if (!empty($track['track_image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($track['track_image_url']); ?>" alt="Track Layout" style="width: 100px; height: auto; border-radius: 5px;">
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($track['name']); ?></td>
                    <td><?php echo htmlspecialchars($track['official_venue_id'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($track['rotation_week_number'] ?? 'N/A'); ?></td>
                    <td><?php echo ucfirst($track['surface_type']); ?></td>
                    <td><?php echo ucfirst($track['grip_level']); ?></td>
                    <td><?php echo ucfirst($track['layout_type']); ?></td>
                    <td>
                        <!-- Edit/Delete buttons would go here -->
                    </td>
                </tr>
			<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>