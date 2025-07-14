<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = ''; // Use a single message variable for all feedback
$unit = 'mm';
$tire_diameter = '';
$spur_teeth = '';
$pinion_teeth = '';
$internal_ratio = 1.0;
$motor_kv = '';
$battery_voltage = '';
$model_id = '';
$setup_id = '';
$track_id = '';
$selected_calc_ids = [];
$pi = 3.14159265359;

// --- NEW: Handle incoming data from setup_form.php ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drivetrain'])) {
    // This block runs when the "Analyze" button is clicked on the setup form
    $tire_diameter = isset($_POST['tires_rear']['tire_diameter']) ? floatval($_POST['tires_rear']['tire_diameter']) : '';
    $spur_teeth = isset($_POST['drivetrain']['spur']) ? intval($_POST['drivetrain']['spur']) : '';
    $pinion_teeth = isset($_POST['drivetrain']['pinion']) ? intval($_POST['drivetrain']['pinion']) : '';
    $message = '<div class="alert alert-info">Gearing data loaded from setup sheet.</div>';
}
// --- Handle all other POST requests from this page ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Preserve form inputs from POST
    $tire_diameter = isset($_POST['tire_diameter']) ? floatval($_POST['tire_diameter']) : '';
    $spur_teeth = isset($_POST['spur_teeth']) ? intval($_POST['spur_teeth']) : '';
    $pinion_teeth = isset($_POST['pinion_teeth']) ? intval($_POST['pinion_teeth']) : '';
    $internal_ratio = isset($_POST['internal_ratio']) ? floatval($_POST['internal_ratio']) : 1.0;
    $motor_kv = isset($_POST['motor_kv']) ? intval($_POST['motor_kv']) : '';
    $battery_voltage = isset($_POST['battery_voltage']) ? floatval($_POST['battery_voltage']) : '';
    $unit = isset($_POST['unit']) ? $_POST['unit'] : 'mm';
    $model_id = isset($_POST['model_id']) ? $_POST['model_id'] : '';
    $setup_id = isset($_POST['setup_id']) ? $_POST['setup_id'] : '';
    $track_id = isset($_POST['track_id']) ? $_POST['track_id'] : '';

    // Handle APPLYING gearing to a setup
    if (isset($_POST['action']) && $_POST['action'] === 'apply_to_setup') {
        $setup_id_to_update = intval($_POST['setup_id_to_apply']);
        
        $tire_dia_to_save = !empty($_POST['tire_diameter']) ? floatval($_POST['tire_diameter']) : null;
        $spur_to_save = !empty($_POST['spur_teeth']) ? intval($_POST['spur_teeth']) : null;
        $pinion_to_save = !empty($_POST['pinion_teeth']) ? intval($_POST['pinion_teeth']) : null;
        $drive_ratio_to_save = ($spur_to_save > 0 && $pinion_to_save > 0) ? round($spur_to_save / $pinion_to_save, 2) : null;
        $rollout_to_save = ($drive_ratio_to_save > 0 && $tire_dia_to_save > 0) ? round(($tire_dia_to_save * $pi) / $drive_ratio_to_save, 2) : null;
        
        $stmt_check = $pdo->prepare("SELECT m.user_id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ?");
        $stmt_check->execute([$setup_id_to_update]);
        $owner = $stmt_check->fetch();

        if ($owner && $owner['user_id'] == $user_id) {
            $pdo->beginTransaction();
            try {
                $stmt_dt = $pdo->prepare("UPDATE drivetrain SET spur = ?, pinion = ?, drive_ratio = ?, rollout = ? WHERE setup_id = ?");
                $stmt_dt->execute([$spur_to_save, $pinion_to_save, $drive_ratio_to_save, $rollout_to_save, $setup_id_to_update]);
                $stmt_tires = $pdo->prepare("UPDATE tires SET tire_diameter = ? WHERE setup_id = ? AND position = 'rear'");
                $stmt_tires->execute([$tire_dia_to_save, $setup_id_to_update]);
                $pdo->commit();
                $message = '<div class="alert alert-success">Gearing has been successfully applied to the selected setup! You can view it on the Setup Form page.</div>';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger">An error occurred while applying the gearing.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">You do not have permission to modify this setup.</div>';
        }
    }

    // Handle pre-fill from setup
    if (isset($_POST['action']) && $_POST['action'] === 'prefill' && !empty($_POST['setup_id'])) {
        $setup_id_prefill = $_POST['setup_id'];
        $stmt = $pdo->prepare("SELECT t.tire_diameter, d.spur, d.pinion FROM tires t JOIN drivetrain d ON t.setup_id = d.setup_id WHERE t.setup_id = ? AND t.position = 'rear'");
        $stmt->execute([$setup_id_prefill]);
        $data = $stmt->fetch();
        if ($data) {
            $tire_diameter = floatval($data['tire_diameter']);
            $spur_teeth = intval($data['spur']);
            $pinion_teeth = intval($data['pinion']);
        }
    }

    // Handle calculation and save
    if (isset($_POST['action']) && $_POST['action'] === 'calculate') {
        if ($tire_diameter <= 0 || $spur_teeth <= 0 || $pinion_teeth <= 0 || $internal_ratio <= 0) {
            $message = '<div class="alert alert-danger">Please enter valid positive numbers for all gearing fields.</div>';
        } else {
            $circumference = $pi * $tire_diameter;
            $fdr = ($spur_teeth / $pinion_teeth) * $internal_ratio;
            $rollout = $circumference / $fdr;
            $top_speed = 0;
            if ($motor_kv > 0 && $battery_voltage > 0) {
                $rpm = $motor_kv * $battery_voltage;
                $wheel_rpm = $rpm / $fdr;
                $wheel_circum_m = $unit === 'mm' ? $circumference / 1000 : $circumference * 0.0254;
                $speed_m_per_min = $wheel_rpm * $wheel_circum_m;
                $top_speed = ($speed_m_per_min * 60) / 1000;
            }
            $stmt = $pdo->prepare("INSERT INTO rollout_calculations (user_id, model_id, setup_id, track_id, tire_diameter, spur_teeth, pinion_teeth, internal_ratio, motor_kv, battery_voltage, rollout, top_speed, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $model_id ?: null, $setup_id ?: null, $track_id ?: null, $tire_diameter, $spur_teeth, $pinion_teeth, $internal_ratio, $motor_kv, $battery_voltage, $rollout, $top_speed, $unit]);
            $message = '<div class="alert alert-success">Calculation saved!</div>';
        }
    }
    
    // Handle comparison selection
    if (isset($_POST['action']) && $_POST['action'] === 'compare') {
        $selected_calc_ids = isset($_POST['selected_calcs']) ? array_map('intval', $_POST['selected_calcs']) : [];
        if (empty($selected_calc_ids) && empty($tire_diameter)) {
            $message = '<div class="alert alert-warning">Please select at least one saved calculation or enter values to compare.</div>';
        }
    }
}

// Fetch user's models, setups, and tracks for dropdowns
$stmt_models = $pdo->prepare("SELECT id, name FROM models WHERE user_id = ?");
$stmt_models->execute([$user_id]);
$models = $stmt_models->fetchAll();
if (!empty($model_id)) {
    $stmt_setups = $pdo->prepare("SELECT id, name, is_baseline FROM setups WHERE model_id = ? ORDER BY is_baseline DESC, name ASC");
    $stmt_setups->execute([$model_id]);
    $setups = $stmt_setups->fetchAll();
}
$stmt_tracks = $pdo->prepare("SELECT id, name, surface_type, grip_level, layout_type FROM tracks WHERE user_id = ?");
$stmt_tracks->execute([$user_id]);
$tracks = $stmt_tracks->fetchAll();

// All other original PHP logic for chart data and fetching saved calculations remains the same...
// ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Roll Out Calculator - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Roll Out Calculator</h1>
    <p>Calculate the rollout (distance per motor revolution) and estimated top speed for your RC car.</p>
    <?php echo $message; ?>

    <!-- Main Calculation Form -->
    <form method="POST" id="calcForm">
        <input type="hidden" name="action" value="calculate">
        <div class="row g-2">
            <!-- All your original inputs for model, setup, track, tire diameter, spur, pinion, etc. go here -->
            <div class="col-md-3 mb-3">
                <label for="tire_diameter" class="form-label">Tire Diameter</label>
                <input type="number" step="0.1" class="form-control" id="tire_diameter" name="tire_diameter" value="<?php echo htmlspecialchars($tire_diameter); ?>" required>
            </div>
            <!-- ... other inputs ... -->
        </div>
        <button type="submit" class="btn btn-primary">Calculate & Save</button>
    </form>

    <!-- NEW: Section to apply results to a setup -->
    <div class="card mt-4">
        <div class="card-header"><h5>Apply Current Gearing to a Setup Sheet</h5></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="apply_to_setup">
                <!-- Hidden fields to carry over current calculator values -->
                <input type="hidden" name="tire_diameter" value="<?php echo htmlspecialchars($tire_diameter); ?>">
                <input type="hidden" name="spur_teeth" value="<?php echo htmlspecialchars($spur_teeth); ?>">
                <input type="hidden" name="pinion_teeth" value="<?php echo htmlspecialchars($pinion_teeth); ?>">

                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label for="model_id_apply" class="form-label">1. Select Model</label>
                        <select class="form-select" id="model_id_apply" onchange="fetchSetupsForApply(this.value)">
                            <option value="">-- Choose Model --</option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo $model['id']; ?>"><?php echo htmlspecialchars($model['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="setup_id_to_apply" class="form-label">2. Select Setup</label>
                        <select class="form-select" id="setup_id_to_apply" name="setup_id_to_apply" required>
                            <option value="">-- Select model first --</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100">Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Your existing chart and saved calculations table HTML go here -->
    <!-- ... -->
</div>

<script>
    // You will need a new, separate function for the "Apply" section's dropdown
    function fetchSetupsForApply(modelId) {
        const setupSelect = document.getElementById('setup_id_to_apply');
        setupSelect.innerHTML = '<option value="">Loading...</option>';
        if (!modelId) {
            setupSelect.innerHTML = '<option value="">-- Select model first --</option>';
            return;
        }
        fetch(`ajax_get_setups.php?model_id=${modelId}`)
            .then(response => response.json())
            .then(data => {
                setupSelect.innerHTML = '<option value="">-- Select a setup --</option>';
                data.forEach(setup => {
                    const option = document.createElement('option');
                    option.value = setup.id;
                    option.textContent = setup.name + (setup.is_baseline ? ' ⭐' : '');
                    setupSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching setups:', error));
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>