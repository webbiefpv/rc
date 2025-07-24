<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Check for success messages from redirects
if (isset($_GET['added'])) { $message = '<div class="alert alert-success">Track Layout added successfully!</div>'; }
if (isset($_GET['deleted'])) { $message = '<div class="alert alert-success">Track Layout deleted successfully.</div>'; }

// Handle all POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Handle ADDING a new track layout
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name']);
        $venue_id = intval($_POST['venue_id']);
        $length_meters = !empty($_POST['length_meters']) ? intval($_POST['length_meters']) : null;
        $surface_type = $_POST['surface_type'];
        $grip_level = $_POST['grip_level'];
        $layout_type = $_POST['layout_type'];
        $notes = trim($_POST['notes']);
        $rotation_week_number = !empty($_POST['rotation_week_number']) ? intval($_POST['rotation_week_number']) : null;
        $track_image_path = null;

        // --- Automatic Image Assignment Logic ---
        $normalized_name = strtolower(str_replace(' ', '', $name)); // e.g., "Week 1" -> "week1"
        $pre_assigned_image = 'track_layouts/' . $normalized_name . '.jpg';
        
        if (file_exists($pre_assigned_image)) {
            $track_image_path = $pre_assigned_image;
        }

        // --- Handle Manual File Upload (will override pre-assigned image) ---
        if (isset($_FILES['track_image']) && $_FILES['track_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['track_image']['type'], $allowed_types)) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $file_extension = pathinfo($_FILES['track_image']['name'], PATHINFO_EXTENSION);
                $unique_filename = uniqid('track_manual_', true) . '.' . $file_extension;
                $destination = $upload_dir . $unique_filename;
                
                if (move_uploaded_file($_FILES['track_image']['tmp_name'], $destination)) {
                    $track_image_path = $destination; // Override with the manually uploaded file
                }
            }
        }
        
        if (empty($name) || empty($venue_id)) {
            $message = '<div class="alert alert-danger">Track Layout Name and Venue are required.</div>';
        } else {
            $stmt = $pdo->prepare("INSERT INTO tracks (user_id, venue_id, name, length_meters, surface_type, grip_level, layout_type, notes, rotation_week_number, track_image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $venue_id, $name, $length_meters, $surface_type, $grip_level, $layout_type, $notes, $rotation_week_number, $track_image_path]);
            header("Location: tracks.php?added=1");
            exit;
        }
    }

    // Handle DELETING a track
    if ($_POST['action'] === 'delete') {
        $track_id = intval($_POST['track_id']);
        // Safety check: prevent deletion if race logs are linked to this track
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM race_logs WHERE track_id = ? AND user_id = ?");
        $stmt_check->execute([$track_id, $user_id]);
        if ($stmt_check->fetchColumn() > 0) {
            $message = '<div class="alert alert-danger">Cannot delete this layout because it has race logs associated with it. Please delete those logs first.</div>';
        } else {
            $stmt = $pdo->prepare("DELETE FROM tracks WHERE id = ? AND user_id = ?");
            $stmt->execute([$track_id, $user_id]);
            header("Location: tracks.php?deleted=1");
            exit;
        }
    }
}

// Fetch all venues for the dropdown
$stmt_venues = $pdo->prepare("SELECT id, name FROM venues WHERE user_id = ? ORDER BY name");
$stmt_venues->execute([$user_id]);
$venues_list = $stmt_venues->fetchAll(PDO::FETCH_ASSOC);

// Fetch all tracks to display
$stmt_tracks = $pdo->prepare("SELECT t.*, v.name as venue_name FROM tracks t JOIN venues v ON t.venue_id = v.id WHERE t.user_id = ? ORDER BY v.name, t.name");
$stmt_tracks->execute([$user_id]);
$tracks = $stmt_tracks->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track Layouts - Tweak Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Track Layouts</h1>
    <p>Manage the specific track layouts for each of your venues.</p>
	<?php echo $message; ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5>Add New Track Layout</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="venue_id" class="form-label">Venue</label>
                        <select class="form-select" id="venue_id" name="venue_id" required>
                            <option value="">-- Select a Venue --</option>
                            <?php foreach ($venues_list as $venue): ?>
                                <option value="<?php echo $venue['id']; ?>"><?php echo htmlspecialchars($venue['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="name" class="form-label">Track Layout Name</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Week 1" required>
                        <small class="form-text text-muted">Naming "Week 1", "Week 2", etc., will auto-load the image if it exists.</small>
                    </div>
                    <div class="col-md-4">
                        <label for="rotation_week_number" class="form-label">Rotation Week # (Optional)</label>
                        <input type="number" class="form-control" id="rotation_week_number" name="rotation_week_number" min="1" max="6">
                    </div>
                    <div class="col-md-8">
                        <label for="track_image" class="form-label">Manual Image Upload (Optional)</label>
                        <input type="file" class="form-control" id="track_image" name="track_image">
                        <small class="form-text text-muted">This will override any pre-assigned image.</small>
                    </div>
                    <div class="col-md-3">
                        <label for="length_meters" class="form-label">Length (meters)</label>
                        <input type="number" class="form-control" id="length_meters" name="length_meters">
                    </div>
                    <div class="col-md-3">
                        <label for="surface_type" class="form-label">Surface Type</label>
                        <select class="form-select" id="surface_type" name="surface_type" required>
                            <option value="carpet">Carpet</option>
                            <option value="asphalt">Asphalt</option>
                            <option value="concrete">Concrete</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="grip_level" class="form-label">Grip Level</label>
                        <select class="form-select" id="grip_level" name="grip_level" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="layout_type" class="form-label">Layout Type</label>
                        <select class="form-select" id="layout_type" name="layout_type" required>
                            <option value="tight">Tight</option>
                            <option value="mixed">Mixed</option>
                            <option value="open">Open</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Add Track Layout</button>
            </form>
        </div>
    </div>
    <h3>Saved Track Layouts</h3>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
            <tr>
                <th>Layout</th>
                <th>Layout Name</th>
                <th>Venue</th>
                <th>Week #</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
			<?php foreach ($tracks as $track): ?>
                <tr>
                    <td style="width: 150px;">
                        <?php if (!empty($track['track_image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($track['track_image_url']); ?>" alt="Track Layout" class="img-fluid rounded">
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($track['name']); ?></td>
                    <td><?php echo htmlspecialchars($track['venue_name']); ?></td>
                    <td><?php echo htmlspecialchars($track['rotation_week_number'] ?? 'N/A'); ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this layout?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="track_id" value="<?php echo $track['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
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