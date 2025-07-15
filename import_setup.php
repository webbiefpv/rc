<?php
require 'db_config.php';
require 'auth.php';
requireLogin(); // A user MUST be logged in to import a setup.

$user_id = $_SESSION['user_id']; // The user who is importing
$message = '';
$original_setup_data = null;
$original_setup_id = null;

// --- 1. Get the original setup data using the share token OR from the confirmation form ---
$share_token = $_POST['share_token'] ?? null;
$original_setup_id_from_form = $_POST['original_setup_id'] ?? null;

if ($share_token) {
    // This block runs when you first click "Import" from the share.php page
    $stmt_orig = $pdo->prepare("SELECT s.*, m.name as model_name FROM setups s JOIN models m ON s.model_id = m.id WHERE s.share_token = ? AND s.is_public = 1");
    $stmt_orig->execute([$share_token]);
    $original_setup_data = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if ($original_setup_data) {
        $original_setup_id = $original_setup_data['id'];
    }
} elseif ($original_setup_id_from_form) {
    // This block runs when you submit the confirmation form on this page
    $original_setup_id = $original_setup_id_from_form;
    $stmt_orig = $pdo->prepare("SELECT s.*, m.name as model_name FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ? AND s.is_public = 1");
    $stmt_orig->execute([$original_setup_id]);
    $original_setup_data = $stmt_orig->fetch(PDO::FETCH_ASSOC);
}

if (!$original_setup_data) {
    die('The setup you are trying to import could not be found, is not public, or the token is invalid.');
}

// --- 2. Handle the final import confirmation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    $new_model_id = intval($_POST['model_id']);

    // Security check: ensure the target model belongs to the current user
    $stmt_check_model = $pdo->prepare("SELECT id FROM models WHERE id = ? AND user_id = ?");
    $stmt_check_model->execute([$new_model_id, $user_id]);
    if (!$stmt_check_model->fetch()) {
        die('Invalid target model selected.');
    }

    $pdo->beginTransaction();
    try {
        // Create the new setup record for the current user
        $new_setup_name = "Imported: " . $original_setup_data['name'];
        $stmt_new_setup = $pdo->prepare("INSERT INTO setups (model_id, name) VALUES (?, ?)");
        $stmt_new_setup->execute([$new_model_id, $new_setup_name]);
        $new_setup_id = $pdo->lastInsertId();

        // Helper function to copy a table's data
        function copyTableData($pdo, $table_name, $original_setup_id, $new_setup_id) {
            $stmt_select = $pdo->prepare("SELECT * FROM $table_name WHERE setup_id = ?");
            $stmt_select->execute([$original_setup_id]);
            $original_rows = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

            if ($original_rows) {
                foreach ($original_rows as $row) {
                    unset($row['id']); // Remove original primary key
                    $row['setup_id'] = $new_setup_id; // Set the new setup_id
                    
                    $columns = array_keys($row);
                    $cols_string = implode(', ', $columns);
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    
                    $stmt_insert = $pdo->prepare("INSERT INTO $table_name ($cols_string) VALUES ($placeholders)");
                    $stmt_insert->execute(array_values($row));
                }
            }
        }
        
        // Copy data for all standard tables
        copyTableData($pdo, 'front_suspension', $original_setup_id, $new_setup_id);
        copyTableData($pdo, 'rear_suspension', $original_setup_id, $new_setup_id);
        copyTableData($pdo, 'drivetrain', $original_setup_id, $new_setup_id);
        copyTableData($pdo, 'body_chassis', $original_setup_id, $new_setup_id);
        copyTableData($pdo, 'electronics', $original_setup_id, $new_setup_id);
        copyTableData($pdo, 'esc_settings', $original_setup_id, $new_setup_id);
        copyTableData($pdo, 'comments', $original_setup_id, $new_setup_id);
        copyTableData($pdo, 'tires', $original_setup_id, $new_setup_id);
        
        // Special handling for weight distribution (it has a user_id)
        $stmt_select_weight = $pdo->prepare("SELECT * FROM weight_distribution WHERE setup_id = ?");
        $stmt_select_weight->execute([$original_setup_id]);
        $weight_row = $stmt_select_weight->fetch(PDO::FETCH_ASSOC);
        if ($weight_row) {
            unset($weight_row['id']);
            $weight_row['setup_id'] = $new_setup_id;
            $weight_row['user_id'] = $user_id; // Assign to the current importing user
            
            $columns = array_keys($weight_row);
            $cols_string = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            
            $stmt_insert_weight = $pdo->prepare("INSERT INTO weight_distribution ($cols_string) VALUES ($placeholders)");
            $stmt_insert_weight->execute(array_values($weight_row));
        }

        $pdo->commit();
        header("Location: setup_form.php?setup_id=" . $new_setup_id . "&imported=1");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("An error occurred during the import process. Error: " . $e->getMessage());
    }
}

// --- 3. Fetch data for the confirmation page display ---
$stmt_my_models = $pdo->prepare("SELECT id, name FROM models WHERE user_id = ? ORDER BY name");
$stmt_my_models->execute([$user_id]);
$my_models = $stmt_my_models->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import Setup Sheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Import Setup Sheet</h1>
    <p>You are about to import the following setup into your account. Please select which of your car models you would like to save it to.</p>

    <div class="card bg-light mb-4">
        <div class="card-body">
            <h5 class="card-title"><?php echo htmlspecialchars($original_setup_data['name']); ?></h5>
            <h6 class="card-subtitle mb-2 text-muted">From Model: <?php echo htmlspecialchars($original_setup_data['model_name']); ?></h6>
        </div>
    </div>

    <?php if (empty($my_models)): ?>
        <div class="alert alert-warning">
            <strong>You have no car models in your account.</strong> You must create a model before you can import a setup.
            <a href="models.php" class="btn btn-primary mt-2">Create a Model Now</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="confirm_import">
            <input type="hidden" name="original_setup_id" value="<?php echo $original_setup_id; ?>">
            
            <div class="mb-3">
                <label for="model_id" class="form-label"><strong>Select Your Target Model:</strong></label>
                <select class="form-select" id="model_id" name="model_id" required>
                    <option value="">-- Choose one of your models --</option>
                    <?php foreach ($my_models as $model): ?>
                        <option value="<?php echo $model['id']; ?>"><?php echo htmlspecialchars($model['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Confirm Import</button>
            <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>