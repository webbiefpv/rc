<?php
require 'db_config.php'; // Your database configuration
require 'auth.php';     // Your authentication functions
requireLogin();         // Ensure the user is logged in

$user_id = $_SESSION['user_id'];
$setups_data_1 = null;
$setups_data_2 = null;
$error_message = '';
$setup1_name = '';
$setup2_name = '';

// Fetch all setups for the current user, possibly grouped by model for easier selection
// We will likely want to include model names in the dropdown for clarity if setups from different models can be compared.
$stmt_all_setups = $pdo->prepare("
    SELECT s.id, s.name as setup_name, m.name as model_name 
    FROM setups s 
    JOIN models m ON s.model_id = m.id 
    WHERE m.user_id = ? 
    ORDER BY m.name, s.name
");
$stmt_all_setups->execute([$user_id]);
$available_setups = $stmt_all_setups->fetchAll(PDO::FETCH_ASSOC);

// --- Backend logic for fetching and comparing will go here later ---
// For now, we are focusing on the selection form and display structure.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compare Setups - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .highlight-diff {
            background-color: #fff3cd; /* A light yellow to highlight differences */
        }
        .comparison-table th, .comparison-table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
<?php require 'header.php'; // Your common header ?>

<div class="container mt-3">
    <h1>Compare Setups</h1>
    <p>Select two setups to see their parameters side-by-side.</p>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="compare_setups.php" class="mb-4">
        <div class="row">
            <div class="col-md-5">
                <label for="setup_id_1" class="form-label">Select Setup 1:</label>
                <select name="setup_id_1" id="setup_id_1" class="form-select" required>
                    <option value="">-- Choose Setup 1 --</option>
                    <?php foreach ($available_setups as $setup): ?>
                        <option value="<?php echo $setup['id']; ?>">
                            <?php echo htmlspecialchars($setup['model_name'] . ' - ' . $setup['setup_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label for="setup_id_2" class="form-label">Select Setup 2:</label>
                <select name="setup_id_2" id="setup_id_2" class="form-select" required>
                    <option value="">-- Choose Setup 2 --</option>
                    <?php foreach ($available_setups as $setup): ?>
                        <option value="<?php echo $setup['id']; ?>">
                            <?php echo htmlspecialchars($setup['model_name'] . ' - ' . $setup['setup_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Compare</button>
            </div>
        </div>
    </form>

    <?php // --- Display area for the comparison table will go here later --- ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>