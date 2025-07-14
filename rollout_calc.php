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
    $tire_diameter = isset($_POST['tires_rear']['tire_diameter']) ? floatval($_POST['tires_rear']['tire_diameter']) : '';
    $spur_teeth = isset($_POST['drivetrain']['spur']) ? intval($_POST['drivetrain']['spur']) : '';
    $pinion_teeth = isset($_POST['drivetrain']['pinion']) ? intval($_POST['drivetrain']['pinion']) : '';
    $message = '<div class="alert alert-info">Gearing data loaded from setup sheet.</div>';
}
// Handle all other POST requests originating from this page
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

    // --- NEW: Handle APPLYING gearing to a setup ---
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
                $message = '<div class="alert alert-success">Gearing has been successfully applied to the selected setup!</div>';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = '<div class="alert alert-danger">An error occurred while applying the gearing.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">You do not have permission to modify this setup.</div>';
        }
    }

    // --- ORIGINAL: Handle pre-fill from setup ---
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

    // --- ORIGINAL: Handle calculation and save ---
    if (isset($_POST['action']) && $_POST['action'] === 'calculate') {
        if ($tire_diameter <= 0 || $spur_teeth <= 0 || $pinion_teeth <= 0 || $internal_ratio <= 0) {
            $message = '<div class="alert alert-danger">Please enter valid positive numbers for tire diameter, spur teeth, pinion teeth, and internal ratio.</div>';
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
            
            $display_diameter = $tire_diameter;
            $display_rollout = $rollout;
            if ($unit === 'inches') {
                $display_diameter /= 25.4;
                $display_rollout /= 25.4;
            }
            $message = sprintf(
                '<div class="alert alert-success">Rollout: %.2f %s per motor revolution<br>'.
                'Tire Diameter: %.2f %s<br>'.
                'Final Drive Ratio: %.2f<br>'.
                'Top Speed: %.2f km/h (estimated)<br>'.
                'Calculation saved!</div>',
                $display_rollout, $unit === 'mm' ? 'mm' : 'inches',
                $display_diameter, $unit === 'mm' ? 'mm' : 'inches',
                $fdr, $top_speed
            );
        }
    }
    
    // --- ORIGINAL: Handle comparison selection ---
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
$setups = [];
if (!empty($model_id)) {
    $stmt_setups = $pdo->prepare("SELECT id, name, is_baseline FROM setups WHERE model_id = ? ORDER BY is_baseline DESC, name ASC");
    $stmt_setups->execute([$model_id]);
    $setups = $stmt_setups->fetchAll();
}
$stmt_tracks = $pdo->prepare("SELECT id, name, surface_type, grip_level, layout_type FROM tracks WHERE user_id = ?");
$stmt_tracks->execute([$user_id]);
$tracks = $stmt_tracks->fetchAll();

// --- ORIGINAL: Fetch saved calculations for the table ---
$stmt_saved = $pdo->prepare("
    SELECT rc.*, m.name AS model_name, s.name AS setup_name, t.name AS track_name,
           GROUP_CONCAT(tags.name ORDER BY tags.name SEPARATOR ', ') as setup_tags
    FROM rollout_calculations rc
    LEFT JOIN models m ON rc.model_id = m.id
    LEFT JOIN setups s ON rc.setup_id = s.id
    LEFT JOIN tracks t ON rc.track_id = t.id
    LEFT JOIN setup_tags st ON s.id = st.setup_id
    LEFT JOIN tags ON st.tag_id = tags.id
    WHERE rc.user_id = ?
    GROUP BY rc.id
    ORDER BY rc.created_at DESC
");
$stmt_saved->execute([$user_id]);
$saved_calculations = $stmt_saved->fetchAll();

// --- ORIGINAL: Prepare chart data ---
$chart_data = ['labels' => [], 'datasets' => []];
$colors = [
    'rgba(75, 192, 192, 1)', 'rgba(255, 99, 132, 1)', 'rgba(54, 162, 235, 1)',
    'rgba(255, 206, 86, 1)', 'rgba(153, 102, 255, 1)'
];
$base_pinion = $pinion_teeth ?: null;
if (!$base_pinion && !empty($selected_calc_ids)) {
    $stmt_base_pinion = $pdo->prepare("SELECT pinion_teeth FROM rollout_calculations WHERE id = ? AND user_id = ?");
    $stmt_base_pinion->execute([$selected_calc_ids[0], $user_id]);
    $base_pinion = $stmt_base_pinion->fetchColumn();
}
$base_pinion = $base_pinion ?: 16;
$min_pinion = max(10, $base_pinion - 5);
$max_pinion = $base_pinion + 5;
$chart_data['labels'] = range($min_pinion, $max_pinion);

if ($tire_diameter > 0 && $spur_teeth > 0 && $internal_ratio > 0) {
    $rollouts = [];
    foreach ($chart_data['labels'] as $pinion) {
        $fdr = ($spur_teeth / $pinion) * $internal_ratio;
        $rollout = ($pi * $tire_diameter) / $fdr;
        if ($unit === 'inches') { $rollout /= 25.4; }
        $rollouts[] = round($rollout, 2);
    }
    $chart_data['datasets'][] = [
        'label' => 'Current Calculation', 'data' => $rollouts,
        'borderColor' => $colors[0], 'backgroundColor' => str_replace('1)', '0.2)', $colors[0]), 'fill' => false
    ];
}

if (!empty($selected_calc_ids)) {
    $placeholders = implode(',', array_fill(0, count($selected_calc_ids), '?'));
    $stmt_selected_calcs = $pdo->prepare("SELECT rc.*, m.name AS model_name, s.name AS setup_name, t.name AS track_name FROM rollout_calculations rc LEFT JOIN models m ON rc.model_id = m.id LEFT JOIN setups s ON rc.setup_id = s.id LEFT JOIN tracks t ON rc.track_id = t.id WHERE rc.id IN ($placeholders) AND rc.user_id = ?");
    $stmt_selected_calcs->execute([...$selected_calc_ids, $user_id]);
    $selected_calcs = $stmt_selected_calcs->fetchAll();
    foreach ($selected_calcs as $index => $calc) {
        $calc_tire_diameter = floatval($calc['tire_diameter']);
        $calc_spur_teeth = intval($calc['spur_teeth']);
        $calc_internal_ratio = floatval($calc['internal_ratio']);
        $rollouts = [];
        foreach ($chart_data['labels'] as $pinion) {
            $fdr = ($calc_spur_teeth / $pinion) * $calc_internal_ratio;
            $rollout = ($pi * $calc_tire_diameter) / $fdr;
            if ($unit === 'inches') { $rollout /= 25.4; }
            $rollouts[] = round($rollout, 2);
        }
        $color_index = ($index + 1) % count($colors);
        $track_label = $calc['track_name'] ? " - {$calc['track_name']}" : '';
        $chart_data['datasets'][] = [
            'label' => 'Saved: ' . ($calc['model_name'] ?? 'N/A') . ' - ' . ($calc['setup_name'] ?? 'N/A') . $track_label . ' (' . $calc['created_at'] . ')',
            'data' => $rollouts, 'borderColor' => $colors[$color_index],
            'backgroundColor' => str_replace('1)', '0.2)', $colors[$color_index]), 'fill' => false
        ];
    }
}
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

    <div class="card mb-4">
        <div class="card-header"><h5>Practical Tips for Rollout</h5></div>
        <div class="card-body">
            <ul>
                <li><strong>Typical Values</strong>: For 1/12 pan cars, rollout typically ranges from 30–50 mm (1.2–2 inches).</li>
                <li><strong>Measuring Tire Diameter</strong>: Use a caliper to measure the rear tire diameter accurately.</li>
                <li><strong>Internal Ratio</strong>: Most 1/12 pan cars are direct drive (1:1).</li>
                <li><strong>Adjusting Rollout</strong>: Increase pinion or decrease spur for higher rollout (more speed). Decrease pinion or increase spur for lower rollout (more torque).</li>
            </ul>
        </div>
    </div>

    <?php if($message) echo $message; else if(isset($result) && $result) echo $result; ?>
    <?php if ($recommended_rollout): ?>
        <div class="alert alert-info">Recommended rollout for selected track: <?php echo $recommended_rollout; ?></div>
    <?php endif; ?>
    <form method="POST" id="calcForm">
        <div class="row g-2">
            <div class="col-md-3 mb-3">
                <label for="model_id" class="form-label">Model (Optional)</label>
                <select class="form-select" id="model_id" name="model_id" onchange="this.form.submit()">
                    <option value="">Select a model</option>
                    <?php foreach ($models as $model): ?>
                        <option value="<?php echo $model['id']; ?>" <?php echo $model_id == $model['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($model['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="setup_id" class="form-label">Setup (Optional)</label>
                <select class="form-select" id="setup_id" name="setup_id">
                    <option value="">Select a setup</option>
                    <?php foreach ($setups as $setup): ?>
                        <option value="<?php echo $setup['id']; ?>" <?php echo $setup_id == $setup['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($setup['name']); ?>
                            <?php echo $setup['is_baseline'] ? ' ⭐' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="track_id" class="form-label">Track (Optional)</label>
                <select class="form-select" id="track_id" name="track_id">
                    <option value="">Select a track</option>
                    <?php foreach ($tracks as $track): ?>
                        <option value="<?php echo $track['id']; ?>" <?php echo $track_id == $track['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($track['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">&nbsp;</label>
                <button type="submit" name="action" value="prefill" class="btn btn-secondary w-100">Pre-fill from Setup</button>
            </div>
            <div class="col-md-3 mb-3">
                <label for="tire_diameter" class="form-label">Tire Diameter</label>
                <input type="number" step="0.1" class="form-control" id="tire_diameter" name="tire_diameter" value="<?php echo htmlspecialchars($tire_diameter); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label for="unit" class="form-label">Unit</label>
                <select class="form-select" id="unit" name="unit">
                    <option value="mm" <?php echo $unit === 'mm' ? 'selected' : ''; ?>>Millimeters</option>
                    <option value="inches" <?php echo $unit === 'inches' ? 'selected' : ''; ?>>Inches</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="spur_teeth" class="form-label">Spur Gear Teeth</label>
                <input type="number" class="form-control" id="spur_teeth" name="spur_teeth" value="<?php echo htmlspecialchars($spur_teeth); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label for="pinion_teeth" class="form-label">Pinion Gear Teeth</label>
                <input type="number" class="form-control" id="pinion_teeth" name="pinion_teeth" value="<?php echo htmlspecialchars($pinion_teeth); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label for="internal_ratio" class="form-label">Internal Ratio</label>
                <input type="number" step="0.01" class="form-control" id="internal_ratio" name="internal_ratio" value="<?php echo htmlspecialchars($internal_ratio); ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label for="motor_kv" class="form-label">Motor KV</label>
                <input type="number" class="form-control" id="motor_kv" name="motor_kv" value="<?php echo htmlspecialchars($motor_kv); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label for="battery_voltage" class="form-label">Battery Voltage</label>
                <input type="number" step="0.1" class="form-control" id="battery_voltage" name="battery_voltage" value="<?php echo htmlspecialchars($battery_voltage); ?>">
            </div>
        </div>
        <button type="submit" name="action" value="calculate" class="btn btn-primary">Calculate & Save</button>
    </form>

    <!-- NEW: Section to apply results to a setup -->
    <div class="card mt-4">
        <div class="card-header"><h5>Apply Current Gearing to a Setup Sheet</h5></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="apply_to_setup">
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

    <!-- Rollout Chart -->
    <?php if (!empty($chart_data['datasets'])): ?>
        <div class="mt-4">
            <h3>Rollout vs. Pinion Size</h3>
            <canvas id="rolloutChart" style="max-height: 400px;"></canvas>
        </div>
        <script>
            const ctx = document.getElementById('rolloutChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_data['labels']); ?>,
                    datasets: <?php echo json_encode($chart_data['datasets']); ?>
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            title: { display: true, text: 'Pinion Teeth' }
                        },
                        y: {
                            title: { display: true, text: 'Rollout (<?php echo $unit; ?>)' }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>

    <!-- Saved Calculations Table -->
    <div class="mt-4">
        <h3>Saved Calculations</h3>
        <?php if (empty($saved_calculations)): ?>
            <p>No calculations saved yet.</p>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="compare">
                <!-- ... hidden inputs for compare form ... -->
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Select</th><th>Date</th><th>Model</th><th>Setup</th><th>Track</th><th>Tags</th><th>Tire Diameter</th><th>Spur/Pinion</th><th>Rollout</th><th>Top Speed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saved_calculations as $calc): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_calcs[]" value="<?php echo $calc['id']; ?>" <?php echo in_array($calc['id'], $selected_calc_ids) ? 'checked' : ''; ?>></td>
                                <td><?php echo $calc['created_at']; ?></td>
                                <td><?php echo htmlspecialchars($calc['model_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($calc['setup_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($calc['track_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($calc['setup_tags'])): ?>
                                        <?php foreach (explode(', ', $calc['setup_tags']) as $tag): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($tag); ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo round($calc['unit'] === 'mm' ? $calc['tire_diameter'] : $calc['tire_diameter'] / 25.4, 2); ?> <?php echo $calc['unit']; ?></td>
                                <td><?php echo $calc['spur_teeth']; ?>/<?php echo $calc['pinion_teeth']; ?></td>
                                <td><?php echo round($calc['unit'] === 'mm' ? $calc['rollout'] : $calc['rollout'] / 25.4, 2); ?> <?php echo $calc['unit']; ?></td>
                                <td><?php echo round($calc['top_speed'], 2); ?> km/h</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn btn-primary">Compare Selected</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
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