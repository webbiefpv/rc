<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$selected_model_id = null;
$maintenance_logs = [];
$selected_model_name = '';

// Check for a success message from a redirect
if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '<div class="alert alert-success">Maintenance log added successfully!</div>';
}

// Determine which model is selected (from GET or POST)
if (isset($_REQUEST['model_id']) && !empty($_REQUEST['model_id'])) {
    $selected_model_id = intval($_REQUEST['model_id']);
}

// Handle Add New Maintenance Log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_log') {
    $model_id_for_log = intval($_POST['model_id']);
    $log_date = trim($_POST['log_date']);
    $activity_description = trim($_POST['activity_description']);
    $notes = trim($_POST['notes']);

    // Security check: ensure model belongs to user
    $stmt_check = $pdo->prepare("SELECT id FROM models WHERE id = ? AND user_id = ?");
    $stmt_check->execute([$model_id_for_log, $user_id]);

    if (empty($log_date) || empty($activity_description)) {
        $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    } elseif (!$stmt_check->fetch()) {
        $message = '<div class="alert alert-danger">Invalid model selected.</div>';
    } else {
        $stmt = $pdo->prepare("INSERT INTO maintenance_logs (user_id, model_id, log_date, activity_description, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $model_id_for_log, $log_date, $activity_description, $notes]);
        
        // Redirect to prevent form resubmission
        header("Location: maintenance.php?model_id=" . $model_id_for_log . "&added=1");
        exit;
    }
}

// Fetch all of the user's models for the selection dropdown
$stmt_models = $pdo->prepare("SELECT id, name FROM models WHERE user_id = ? ORDER BY name");
$stmt_models->execute([$user_id]);
$models_list = $stmt_models->fetchAll(PDO::FETCH_ASSOC);

// If a model has been selected, fetch its maintenance logs
if ($selected_model_id) {
    // Get the selected model's name for display
    $stmt_model_name = $pdo->prepare("SELECT name FROM models WHERE id = ? AND user_id = ?");
    $stmt_model_name->execute([$selected_model_id, $user_id]);
    $selected_model_name = $stmt_model_name->fetchColumn();
    
    // Get the logs for that model
    if($selected_model_name) {
        $stmt_logs = $pdo->prepare("SELECT * FROM maintenance_logs WHERE model_id = ? ORDER BY log_date DESC");
        $stmt_logs->execute([$selected_model_id]);
        $maintenance_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // If model name not found, it means invalid model_id for user
        $selected_model_id = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Log - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Maintenance Log</h1>
    <p>Select a car model to view or add to its maintenance history.</p>
    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET">
                <div class="row align-items-end">
                    <div class="col-md-6">
                        <label for="model_id" class="form-label">Select Car Model:</label>
                        <select class="form-select" id="model_id" name="model_id" required>
                            <option value="">-- Choose a model --</option>
                            <?php foreach ($models_list as $model): ?>
                                <option value="<?php echo $model['id']; ?>" <?php echo ($model['id'] == $selected_model_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">View Logs</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_model_id): ?>
    <hr>
    <h2 class="mt-4">History for: <span class="text-primary"><?php echo htmlspecialchars($selected_model_name); ?></span></h2>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Add New Maintenance Entry</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_log">
                <input type="hidden" name="model_id" value="<?php echo $selected_model_id; ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="log_date" class="form-label">Date of Maintenance</label>
                        <input type="date" class="form-control" id="log_date" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label for="activity_description" class="form-label">Activity / Part Replaced</label>
                        <input type="text" class="form-control" id="activity_description" name="activity_description" placeholder="e.g., Rebuilt motor, replaced side springs" required>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="e.g., Brushes were worn, springs had lost tension..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Save Log Entry</button>
            </form>
        </div>
    </div>

    <h3>Logged Activities</h3>
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Date</th>
                <th>Activity</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($maintenance_logs)): ?>
                <tr><td colspan="4" class="text-center">No maintenance logged for this model yet.</td></tr>
            <?php else: ?>
                <?php foreach ($maintenance_logs as $log): ?>
                    <tr>
                        <td><?php echo date("M j, Y", strtotime($log['log_date'])); ?></td>
                        <td><?php echo htmlspecialchars($log['activity_description']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($log['notes'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" disabled>Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>