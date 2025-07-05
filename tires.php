<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Handle all form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Handle ADDING a new tire set
    if ($_POST['action'] === 'add_set') {
        $set_name = trim($_POST['set_name']);
        $brand = trim($_POST['brand']);
        $compound = trim($_POST['compound']);
        $purchase_date = !empty($_POST['purchase_date']) ? trim($_POST['purchase_date']) : null;
        $notes = trim($_POST['notes']);

        if (empty($set_name)) {
            $message = '<div class="alert alert-danger">The "Set Name" is a required field.</div>';
        } else {
            $stmt = $pdo->prepare("INSERT INTO tire_inventory (user_id, set_name, brand, compound, purchase_date, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $set_name, $brand, $compound, $purchase_date, $notes]);
            $message = '<div class="alert alert-success">New tire set added successfully!</div>';
        }
    }

    // Handle RETIRING a tire set
    if ($_POST['action'] === 'retire_set') {
        $tire_id = intval($_POST['tire_id']);
        $stmt = $pdo->prepare("UPDATE tire_inventory SET is_retired = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$tire_id, $user_id]);
        $message = '<div class="alert alert-info">Tire set has been retired.</div>';
    }

    // Handle REACTIVATING a tire set
    if ($_POST['action'] === 'reactivate_set') {
        $tire_id = intval($_POST['tire_id']);
        $stmt = $pdo->prepare("UPDATE tire_inventory SET is_retired = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$tire_id, $user_id]);
        $message = '<div class="alert alert-success">Tire set has been reactivated.</div>';
    }

    // Handle PERMANENTLY DELETING a tire set
    if ($_POST['action'] === 'delete_set') {
        $tire_id = intval($_POST['tire_id']);
        $run_count = intval($_POST['run_count']);
        
        if ($run_count == 0) {
            $stmt = $pdo->prepare("DELETE FROM tire_inventory WHERE id = ? AND user_id = ?");
            $stmt->execute([$tire_id, $user_id]);
            $message = '<div class="alert alert-success">Tire set permanently deleted.</div>';
        } else {
            $message = '<div class="alert alert-danger">Cannot permanently delete a tire set that has been used. Please retire it instead.</div>';
        }
    }
}

// Fetch all tire sets for the user, including run count
$stmt = $pdo->prepare("
    SELECT ti.*, COUNT(tl.id) as run_count
    FROM tire_inventory ti
    LEFT JOIN tire_log tl ON ti.id = tl.tire_set_id
    WHERE ti.user_id = ?
    GROUP BY ti.id
    ORDER BY ti.is_retired ASC, ti.created_at DESC
");
$stmt->execute([$user_id]);
$tire_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tire Inventory - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Tire Inventory</h1>
    <p>Manage your sets of tires. Logging their usage will help you track wear and performance over time.</p>
    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Add New Tire Set to Inventory</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_set">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="set_name" class="form-label">Set Name (e.g., "New JFT Yellows")</label>
                        <input type="text" class="form-control" id="set_name" name="set_name" required>
                    </div>
                    <div class="col-md-3">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" class="form-control" id="brand" name="brand" placeholder="e.g., Contact RC">
                    </div>
                    <div class="col-md-3">
                        <label for="compound" class="form-label">Compound / Shore</label>
                        <input type="text" class="form-control" id="compound" name="compound" placeholder="e.g., 37sh">
                    </div>
                    <div class="col-md-3">
                        <label for="purchase_date" class="form-label">Purchase Date</label>
                        <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                    </div>
                    <div class="col-md-9">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="1" placeholder="e.g., For high-grip carpet only"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Add Set</button>
            </form>
        </div>
    </div>

    <h3>Your Tire Inventory</h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Set Name</th>
                    <th>Brand</th>
                    <th>Compound</th>
                    <th>Total Runs</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tire_sets)): ?>
                    <tr><td colspan="6" class="text-center">No tire sets in inventory. Add one above.</td></tr>
                <?php else: ?>
                    <?php foreach ($tire_sets as $set): ?>
                        <tr class="<?php echo $set['is_retired'] ? 'table-secondary text-muted' : ''; ?>">
                            <td><strong><?php echo htmlspecialchars($set['set_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($set['brand']); ?></td>
                            <td><?php echo htmlspecialchars($set['compound']); ?></td>
                            <td><span class="badge bg-primary rounded-pill"><?php echo $set['run_count']; ?></span></td>
                            <td>
                                <?php if ($set['is_retired']): ?>
                                    <span class="badge bg-secondary">Retired</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($set['is_retired']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reactivate_set">
                                        <input type="hidden" name="tire_id" value="<?php echo $set['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="retire_set">
                                        <input type="hidden" name="tire_id" value="<?php echo $set['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Retire</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($set['run_count'] == 0): ?>
                                    <form method="POST" style="display:inline; margin-left: 5px;" onsubmit="return confirm('Are you sure you want to permanently delete this tire set? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_set">
                                        <input type="hidden" name="tire_id" value="<?php echo $set['id']; ?>">
                                        <input type="hidden" name="run_count" value="<?php echo $set['run_count']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
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