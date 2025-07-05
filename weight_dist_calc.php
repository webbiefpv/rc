<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// --- Handle form submission to save weights ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_weights') {
    $setup_id = intval($_POST['setup_id']);
    $lf_weight = !empty($_POST['lf_weight']) ? floatval($_POST['lf_weight']) : null;
    $rf_weight = !empty($_POST['rf_weight']) ? floatval($_POST['rf_weight']) : null;
    $lr_weight = !empty($_POST['lr_weight']) ? floatval($_POST['lr_weight']) : null;
    $rr_weight = !empty($_POST['rr_weight']) ? floatval($_POST['rr_weight']) : null;
    $notes = trim($_POST['notes']);

    // Security check: ensure the setup belongs to the user
    $stmt_check = $pdo->prepare("SELECT m.user_id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ?");
    $stmt_check->execute([$setup_id]);
    $owner = $stmt_check->fetch();

    if (!$owner || $owner['user_id'] != $user_id) {
        $message = '<div class="alert alert-danger">Error: You do not have permission to save to this setup.</div>';
    } elseif (empty($setup_id)) {
        $message = '<div class="alert alert-danger">Error: Please select a setup to save the weights to.</div>';
    } else {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to either create or update the record
        // This relies on the UNIQUE KEY we placed on the `setup_id` column
        $sql = "
            INSERT INTO weight_distribution (setup_id, user_id, lf_weight, rf_weight, lr_weight, rr_weight, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                lf_weight = VALUES(lf_weight),
                rf_weight = VALUES(rf_weight),
                lr_weight = VALUES(lr_weight),
                rr_weight = VALUES(rr_weight),
                notes = VALUES(notes)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$setup_id, $user_id, $lf_weight, $rf_weight, $lr_weight, $rr_weight, $notes]);
        
        $message = '<div class="alert alert-success">Weight distribution saved successfully to the selected setup!</div>';
    }
}


// Fetch user's models for the dropdown
$stmt_models = $pdo->prepare("SELECT id, name FROM models WHERE user_id = ? ORDER BY name");
$stmt_models->execute([$user_id]);
$models_list = $stmt_models->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weight Distribution Calculator - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css"> <!-- Your main stylesheet -->
    <style>
        /* Styles to match your app's aesthetic */
        .calculator-container { max-width: 480px; margin: auto; background-color: #ffffff; padding: 20px; border-radius: 15px; border: 1px solid #dee2e6; color: #212529; }
        .car-chassis-svg { fill: none; stroke: #adb5bd; stroke-width: 2; }
        .weight-input { background-color: #f8f9fa; color: #212529; border: 1px solid #ced4da; text-align: center; font-size: 1.2rem; width: 100px; padding: 5px; border-radius: 5px; }
        .weight-display { color: #212529; font-weight: bold; font-size: 1.5rem; }
        .percentage-display { color: #6c757d; font-size: 1rem; }
        .label-text { color: #495057; font-size: 0.9rem; }
    </style>
</head>
<body>
<?php require 'header.php'; ?>

<div class="container mt-3">
    <form method="POST">
        <input type="hidden" name="action" value="save_weights">
        <div class="calculator-container">
            <h4 class="text-center mb-4">CORNER WEIGHT</h4>
            <?php echo $message; // Display success/error messages here ?>

            <!-- Main Grid Layout -->
            <div class="grid-container" style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: center;">
                
                <div style="grid-column: 2; text-align: center;">
                    <div class="label-text">Front Weight</div>
                    <div id="front-weight" class="weight-display">0.0g</div>
                    <div id="front-percentage" class="percentage-display">0%</div>
                </div>

                <div class="text-center">
                    <div class="label-text">Left Front</div>
                    <input type="number" step="0.1" id="lf-input" name="lf_weight" class="weight-input" placeholder="LF" oninput="calculateWeights()">
                </div>
                <div class="mx-3">
                    <svg width="100" height="200" viewBox="0 0 100 200" class="car-chassis-svg">
                        <path d="M20,10 L80,10 L80,190 L20,190 L20,10 Z M10,20 L20,20 M10,40 L20,40 M80,20 L90,20 M80,40 L90,40 M10,160 L20,160 M10,180 L20,180 M80,160 L90,160 M80,180 L90,180"/>
                    </svg>
                </div>
                <div class="text-center">
                    <div class="label-text">Right Front</div>
                    <input type="number" step="0.1" id="rf-input" name="rf_weight" class="weight-input" placeholder="RF" oninput="calculateWeights()">
                </div>

                <div class="text-center">
                    <div class="label-text">Left Weight</div>
                    <div id="left-weight" class="weight-display">0.0g</div>
                    <div id="left-percentage" class="percentage-display">0%</div>
                </div>
                <div></div>
                <div class="text-center">
                    <div class="label-text">Right Weight</div>
                    <div id="right-weight" class="weight-display">0.0g</div>
                    <div id="right-percentage" class="percentage-display">0%</div>
                </div>

                <div class="text-center">
                    <div class="label-text">Left Rear</div>
                    <input type="number" step="0.1" id="lr-input" name="lr_weight" class="weight-input" placeholder="LR" oninput="calculateWeights()">
                </div>
                <div></div>
                <div class="text-center">
                    <div class="label-text">Right Rear</div>
                    <input type="number" step="0.1" id="rr-input" name="rr_weight" class="weight-input" placeholder="RR" oninput="calculateWeights()">
                </div>

                <div style="grid-column: 2; text-align: center;">
                    <div class="label-text">Rear Weight</div>
                    <div id="rear-weight" class="weight-display">0.0g</div>
                    <div id="rear-percentage" class="percentage-display">0%</div>
                </div>

                <div style="grid-column: 1 / 4; text-align: center; margin-top: 15px;">
                    <div class="label-text">Total Weight</div>
                    <div id="total-weight" class="weight-display" style="font-size: 2rem;">0.0g</div>
                </div>

                <div class="text-center mt-3">
                    <div id="cross-weight-rf-lr" class="weight-display">0.0g</div>
                    <div id="cross-percentage-rf-lr" class="percentage-display">0%</div>
                    <div class="label-text">RF+LR</div>
                </div>
                <div class="mt-3 text-center label-text">Cross Weight</div>