<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Handle Deleting a setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_setup') {
    $setup_id_to_delete = intval($_POST['setup_id']);

    // --- THIS IS THE FIX ---
    // The query now explicitly says "SELECT s.id" instead of just "SELECT id"
    $stmt_check = $pdo->prepare("SELECT s.id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ? AND m.user_id = ?");
    $stmt_check->execute([$setup_id_to_delete, $user_id]);

    if ($stmt_check->fetch()) {
        // The ON DELETE CASCADE constraint on the database will handle deleting all related data
        // from other tables (race_logs, weight_distribution, etc.)
        $stmt_delete = $pdo->prepare("DELETE FROM setups WHERE id = ?");
        $stmt_delete->execute([$setup_id_to_delete]);
        $message = '<div class="alert alert-success">Setup and all its associated data have been permanently deleted.</div>';
    } else {
        $message = '<div class="alert alert-danger">Could not delete setup. It may not exist or you may not have permission.</div>';
    }
}

// Fetch all setups for the user, along with their best lap time from race logs
$stmt = $pdo->prepare("
    SELECT 
        s.id, 
        s.name as setup_name, 
        s.is_baseline,
        m.name as model_name,
        MIN(rl.best_lap_time) as best_ever_lap
    FROM setups s
    JOIN models m ON s.model_id = m.id
    LEFT JOIN race_logs rl ON s.id = rl.setup_id AND rl.best_lap_time > 0
    WHERE m.user_id = ?
    GROUP BY s.id
    ORDER BY m.name, s.name
");
$stmt->execute([$user_id]);
$setups_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Sheets - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Setup Sheets</h1>
    <p>A complete list of all your saved setups. The best lap time achieved with each setup is shown for quick comparison.</p>
    <?php echo $message; ?>

    <div class="row">
        <?php if (empty($setups_list)): ?>
            <p class="text-muted">No setups found. Go create one from the "Setups" page.</p>
        <?php else: ?>
            <?php foreach ($setups_list as $setup): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php echo htmlspecialchars($setup['setup_name']); ?>
                                <?php if ($setup['is_baseline']): ?>
                                    <span class="badge bg-warning text-dark">Baseline</span>
                                <?php endif; ?>
                            </h5>
                            <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($setup['model_name']); ?></h6>
                            <p class="card-text">
                                Best Lap Achieved: 
                                <strong class="text-primary"><?php echo $setup['best_ever_lap'] ? htmlspecialchars($setup['best_ever_lap']) : 'N/A'; ?></strong>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <a href="view_setup_sheet.php?setup_id=<?php echo $setup['id']; ?>" class="btn btn-primary">View Full Sheet</a>
                            <form method="POST" style="display:inline-block; margin-left: 10px;" onsubmit="return confirm('WARNING: This will permanently delete this setup and all associated race logs and data. This cannot be undone. Are you sure?');">
                                <input type="hidden" name="action" value="delete_setup">
                                <input type="hidden" name="setup_id" value="<?php echo $setup['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>