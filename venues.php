<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submissions for adding or deleting venues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Handle ADDING a new venue
    if ($_POST['action'] === 'add_venue') {
        $name = trim($_POST['name']);
        $official_venue_id = !empty($_POST['official_venue_id']) ? intval($_POST['official_venue_id']) : null;
        $notes = trim($_POST['notes']);

        if (empty($name)) {
            $message = '<div class="alert alert-danger">Venue Name is a required field.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO venues (user_id, name, official_venue_id, notes) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $name, $official_venue_id, $notes]);
                header("Location: venues.php?added=1");
                exit;
            } catch (PDOException $e) {
                // Handle case where official_venue_id is not unique
                if ($e->errorInfo[1] == 1062) {
                    $message = '<div class="alert alert-danger">Error: A venue with that Official Venue ID already exists.</div>';
                } else {
                    $message = '<div class="alert alert-danger">An error occurred while saving the venue.</div>';
                }
            }
        }
    }

    // Handle DELETING a venue
    if ($_POST['action'] === 'delete_venue') {
        $venue_id = intval($_POST['venue_id']);
        
        // Safety check: prevent deletion if tracks are linked to this venue
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tracks WHERE venue_id = ? AND user_id = ?");
        $stmt_check->execute([$venue_id, $user_id]);
        if ($stmt_check->fetchColumn() > 0) {
            $message = '<div class="alert alert-danger">Cannot delete this venue because it has track layouts associated with it. Please delete those track layouts first.</div>';
        } else {
            $stmt = $pdo->prepare("DELETE FROM venues WHERE id = ? AND user_id = ?");
            $stmt->execute([$venue_id, $user_id]);
            header("Location: venues.php?deleted=1");
            exit;
        }
    }
}

// Check for success messages from redirects
if (isset($_GET['added'])) { $message = '<div class="alert alert-success">Venue added successfully!</div>'; }
if (isset($_GET['deleted'])) { $message = '<div class="alert alert-success">Venue deleted successfully.</div>'; }

// Fetch all venues for the current user to display in the list
$stmt_venues = $pdo->prepare("SELECT * FROM venues WHERE user_id = ? ORDER BY name");
$stmt_venues->execute([$user_id]);
$venues_list = $stmt_venues->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Venues - Tweak Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Manage Venues</h1>
    <p>A venue is the physical location where you race (e.g., Basildon Buggy Club). Each venue can have multiple track layouts.</p>
    <?php echo $message; ?>

    <!-- Add New Venue Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Add New Venue</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_venue">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Venue Name</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Basildon Buggy Club" required>
                    </div>
                    <div class="col-md-4">
                        <label for="official_venue_id" class="form-label">Official Venue ID (Optional)</label>
                        <input type="number" class="form-control" id="official_venue_id" name="official_venue_id" placeholder="From rc-results.com">
                    </div>
                    <div class="col-md-2">
                         <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Add Venue</button>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="1" placeholder="e.g., Wednesday night club, high grip carpet..."></textarea>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Display Existing Venues -->
    <h3>Your Venues</h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Venue Name</th>
                    <th>Official ID</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($venues_list)): ?>
                    <tr><td colspan="4" class="text-center">No venues added yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($venues_list as $venue): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($venue['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($venue['official_venue_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($venue['notes']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this venue?');">
                                    <input type="hidden" name="action" value="delete_venue">
                                    <input type="hidden" name="venue_id" value="<?php echo $venue['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>