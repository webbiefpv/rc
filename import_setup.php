<?php
require 'db_config.php';
require 'auth.php';
requireLogin(); // A user MUST be logged in to import a setup.

$user_id = $_SESSION['user_id']; // The user who is importing
$message = '';

// --- 1. Get the original setup data using the share token ---
if (!isset($_POST['share_token']) && !isset($_POST['original_setup_id'])) {
    die('No setup specified for import.');
}
$share_token = $_POST['share_token'] ?? null;
$original_setup_id = null;
$original_setup_data = null;

if ($share_token) {
    $stmt_orig = $pdo->prepare("SELECT s.*, m.name as model_name FROM setups s JOIN models m ON s.model_id = m.id WHERE s.share_token = ? AND s.is_public = 1");
    $stmt_orig->execute([$share_token]);
    $original_setup_data = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if ($original_setup_data) {
        $original_setup_id = $original_setup_data['id'];
    }
}

if (!$original_setup_data) {
    die('The setup you are trying to import could not be found or is not public.');
}

// --- 2. Handle the final import confirmation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    $new_model_id = intval($_POST['model_id']);
    $original_setup_id = intval($_POST['original_setup_id']); // Get original ID from hidden field

    // Security check: ensure the target model belongs to the current user
    $stmt_check_model = $pdo->prepare("SELECT id FROM models WHERE id = ? AND user_id = ?");
    $stmt_check_model->execute([$new_model_id, $user_id]);
    if (!$stmt_check_model->fetch()) {
        die('Invalid target model selected.');
    }

    // Begin transaction for the copy process
    $pdo->beginTransaction();
    try {
        // Create the new setup record for the current user
        $new_setup_name = "Imported: " . $original_setup_data['name'];
        $stmt_new_setup = $pdo->prepare("INSERT INTO setups (model_id, name) VALUES (?, ?)");
        $stmt_new_setup->execute([$new_model_id, $new_setup_name]);
        $new_setup_id = $pdo->lastInsertId();

        // List of tables to copy data from
        $tables_to_copy = ['front_suspension', 'rear_suspension', 'drivetrain', 'body_chassis', 'electronics', 'esc_settings', 'comments', 'tires', 'weight_distribution'];

        foreach ($tables_to_copy as $table) {
            // Get all columns for the table, excluding the primary key 'id'
            $q = $pdo->query("DESCRIBE $table");
            $columns = array_column($q->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $columns = array_filter($columns, function($c) { return $c !== 'id'; });
            
            // Fetch all rows from the original setup for this table
            $stmt_select_orig = $pdo->prepare("SELECT * FROM $table WHERE setup_id = ?");
            $stmt_select_orig->execute([$original_setup_id]);
            $original_rows = $stmt_select_orig->fetchAll(PDO::FETCH_ASSOC);

            if ($original_rows) {
                $cols_string = implode(', ', $columns);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $stmt_insert_clone = $pdo->prepare("INSERT INTO $table ($cols_string) VALUES ($placeholders)");

                foreach ($original_rows as $row) {
                    $row['setup_id'] = $new_setup_id; // Set the new setup_id
                    // Ensure the order of values matches the order of columns
                    $values_to_insert = [];
                    foreach ($columns as $col) {
                        $values_to_insert[] = $row[$col];
                    }
                    $stmt_insert_clone->execute($values_to_insert);
                }
            }
        }
        
        // Commit the transaction
        $pdo->commit();
        header("Location: setup_form.php?setup_id=" . $new_setup_id . "&imported=1");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("An error occurred during the import process. Error: " . $e->getMessage());
    }
}


// --- 3. Fetch data for the confirmation page display ---
// Fetch the current user's models for the dropdown
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
            <h6 class="card-subtitle mb-2 text-mut<?php
require 'db_config.php';
require 'auth.php';
requireLogin(); // A user MUST be logged in to import a setup.

$user_id = $_SESSION['user_id'];
$message = '';

// --- DEBUGGING: Check what is being sent to this page ---
var_dump($_POST);

// --- 1. Get the original setup data using the share token ---
if (!isset($_POST['share_token']) && !isset($_POST['original_setup_id'])) {
    die('No setup specified for import.');
}
$share_token = $_POST['share_token'] ?? null;
$original_setup_id = null;
$original_setup_data = null;

if ($share_token) {
    $stmt_orig = $pdo->prepare("SELECT s.*, m.name as model_name FROM setups s JOIN models m ON s.model_id = m.id WHERE s.share_token = ? AND s.is_public = 1");
    $stmt_orig->execute([$share_token]);
    $original_setup_data = $stmt_orig->fetch(PDO::FETCH_ASSOC);
    if ($original_setup_data) {
        $original_setup_id = $original_setup_data['id'];
    }
}

if (!$original_setup_data) {
    die('The setup you are trying to import could not be found or is not public.');
}

// --- 2. Handle the final import confirmation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    // ... (The rest of the file remains the same) ...
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>ed">From Model: <?php echo htmlspecialchars($original_setup_data['model_name']); ?></h6>
        </div>
    </div>

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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>