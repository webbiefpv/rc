<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$result = '';
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

// Preserve form inputs from POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}

// Fetch user's models
$stmt = $pdo->prepare("SELECT id, name FROM models WHERE user_id = ?");
$stmt->execute([$user_id]);
$models = $stmt->fetchAll();

// Fetch setups if a model is selected
$setups = [];
if (!empty($model_id)) {
	$stmt = $pdo->prepare("SELECT id, name FROM setups WHERE model_id = ?");
	$stmt->execute([$model_id]);
	$setups = $stmt->fetchAll();
}

// Fetch tracks
$stmt = $pdo->prepare("SELECT id, name, surface_type, grip_level, layout_type FROM tracks WHERE user_id = ?");
$stmt->execute([$user_id]);
$tracks = $stmt->fetchAll();

// Generate recommended rollout range for selected track
$recommended_rollout = '';
if (!empty($track_id)) {
	$stmt = $pdo->prepare("SELECT surface_type, grip_level, layout_type FROM tracks WHERE id = ? AND user_id = ?");
	$stmt->execute([$track_id, $user_id]);
	$track = $stmt->fetch();
	if ($track) {
		if ($track['surface_type'] === 'carpet' && $track['grip_level'] === 'high' && $track['layout_type'] === 'tight') {
			$recommended_rollout = '30–35 mm';
		} elseif ($track['surface_type'] === 'asphalt' && $track['layout_type'] === 'open') {
			$recommended_rollout = '40–50 mm';
		} else {
			$recommended_rollout = '35–40 mm';
		}
		if ($unit === 'inches') {
			$recommended_rollout = sprintf('%.2f–%.2f inches', floatval(explode('–', $recommended_rollout)[0]) / 25.4, floatval(explode('–', $recommended_rollout)[1]) / 25.4);
		}
	}
}

// Handle pre-fill from setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'prefill' && !empty($_POST['setup_id'])) {
	$setup_id = $_POST['setup_id'];
	$stmt = $pdo->prepare("SELECT t.tire_diameter, d.spur FROM tires t JOIN drivetrain d ON t.setup_id = d.setup_id WHERE t.setup_id = ? AND t.position = 'rear'");
	$stmt->execute([$setup_id]);
	$data = $stmt->fetch();
	if ($data) {
		$tire_diameter = floatval($data['tire_diameter']);
		$spur_teeth = intval($data['spur']);
	}
}

// Handle calculation and save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'calculate') {
	// Validate inputs
	if ($tire_diameter <= 0 || $spur_teeth <= 0 || $pinion_teeth <= 0 || $internal_ratio <= 0) {
		$result = '<div class="alert alert-danger">Please enter valid positive numbers for tire diameter, spur teeth, pinion teeth, and internal ratio.</div>';
	} else {
		// Calculate tire circumference
		$circumference = $pi * $tire_diameter;

		// Calculate final drive ratio
		$fdr = ($spur_teeth / $pinion_teeth) * $internal_ratio;

		// Calculate rollout
		$rollout = $circumference / $fdr;

		// Calculate top speed (km/h)
		$top_speed = 0;
		if ($motor_kv > 0 && $battery_voltage > 0) {
			$rpm = $motor_kv * $battery_voltage;
			$wheel_rpm = $rpm / $fdr;
			$wheel_circum_m = $unit === 'mm' ? $circumference / 1000 : $circumference * 0.0254;
			$speed_m_per_min = $wheel_rpm * $wheel_circum_m;
			$top_speed = ($speed_m_per_min * 60) / 1000;
		}

		// Convert to inches if requested
		$display_diameter = $tire_diameter;
		$display_rollout = $rollout;
		if ($unit === 'inches') {
			$display_diameter /= 25.4;
			$display_rollout /= 25.4;
		}

		// Save to database
		$stmt = $pdo->prepare("INSERT INTO rollout_calculations (user_id, model_id, setup_id, track_id, tire_diameter, spur_teeth, pinion_teeth, internal_ratio, motor_kv, battery_voltage, rollout, top_speed, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$user_id, $model_id ?: null, $setup_id ?: null, $track_id ?: null, $tire_diameter, $spur_teeth, $pinion_teeth, $internal_ratio, $motor_kv, $battery_voltage, $rollout, $top_speed, $unit]);

		// Format result
		$result = sprintf(
			'<div class="alert alert-success">Rollout: %.2f %s per motor revolution<br>'.
			'Tire Diameter: %.2f %s<br>'.
			'Final Drive Ratio: %.2f<br>'.
			'Top Speed: %.2f km/h (estimated)<br>'.
			'Calculation saved!</div>',
			$display_rollout,
			$unit === 'mm' ? 'mm' : 'inches',
			$display_diameter,
			$unit === 'mm' ? 'mm' : 'inches',
			$fdr,
			$top_speed
		);
	}
}

// Handle comparison selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'compare') {
	$selected_calc_ids = isset($_POST['selected_calcs']) ? array_map('intval', $_POST['selected_calcs']) : [];
	if (empty($selected_calc_ids) && empty($tire_diameter)) {
		$result = '<div class="alert alert-warning">Please select at least one saved calculation or enter values to compare.</div>';
	}
}

// Fetch saved calculations
$stmt = $pdo->prepare("SELECT rc.*, m.name AS model_name, s.name AS setup_name, t.name AS track_name FROM rollout_calculations rc LEFT JOIN models m ON rc.model_id = m.id LEFT JOIN setups s ON rc.setup_id = s.id LEFT JOIN tracks t ON rc.track_id = t.id WHERE rc.user_id = ? ORDER BY rc.created_at DESC");
$stmt->execute([$user_id]);
$saved_calculations = $stmt->fetchAll();

// Prepare chart data
$chart_data = ['labels' => [], 'datasets' => []];
$colors = [
	'rgba(75, 192, 192, 1)', // Teal
	'rgba(255, 99, 132, 1)', // Red
	'rgba(54, 162, 235, 1)', // Blue
	'rgba(255, 206, 86, 1)', // Yellow
	'rgba(153, 102, 255, 1)' // Purple
];

// Determine pinion range (use current pinion or first selected calculation)
$base_pinion = $pinion_teeth ?: null;
if (!$base_pinion && !empty($selected_calc_ids)) {
	$stmt = $pdo->prepare("SELECT pinion_teeth FROM rollout_calculations WHERE id = ? AND user_id = ?");
	$stmt->execute([$selected_calc_ids[0], $user_id]);
	$base_pinion = $stmt->fetchColumn();
}
$base_pinion = $base_pinion ?: 16; // Default to 16 if no pinion available
$min_pinion = max(10, $base_pinion - 5);
$max_pinion = $base_pinion + 5;
$chart_data['labels'] = range($min_pinion, $max_pinion);

// Current calculation dataset
if ($tire_diameter > 0 && $spur_teeth > 0 && $internal_ratio > 0) {
	$rollouts = [];
	foreach ($chart_data['labels'] as $pinion) {
		$fdr = ($spur_teeth / $pinion) * $internal_ratio;
		$rollout = ($pi * $tire_diameter) / $fdr;
		if ($unit === 'inches') {
			$rollout /= 25.4;
		}
		$rollouts[] = round($rollout, 2);
	}
	$chart_data['datasets'][] = [
		'label' => 'Current Calculation',
		'data' => $rollouts,
		'borderColor' => $colors[0],
		'backgroundColor' => str_replace('1)', '0.2)', $colors[0]),
		'fill' => false
	];
}

// Add datasets for selected saved calculations
if (!empty($selected_calc_ids)) {
	$stmt = $pdo->prepare("SELECT rc.*, t.name AS track_name FROM rollout_calculations rc LEFT JOIN tracks t ON rc.track_id = t.id WHERE rc.id IN (" . implode(',', array_fill(0, count($selected_calc_ids), '?')) . ") AND rc.user_id = ?");
	$stmt->execute([...$selected_calc_ids, $user_id]);
	$selected_calcs = $stmt->fetchAll();

	foreach ($selected_calcs as $index => $calc) {
		$calc_tire_diameter = floatval($calc['tire_diameter']);
		$calc_spur_teeth = intval($calc['spur_teeth']);
		$calc_internal_ratio = floatval($calc['internal_ratio']);

		$rollouts = [];
		foreach ($chart_data['labels'] as $pinion) {
			$fdr = ($calc_spur_teeth / $pinion) * $calc_internal_ratio;
			$rollout = ($pi * $calc_tire_diameter) / $fdr;
			if ($unit === 'inches') {
				$rollout /= 25.4;
			}
			$rollouts[] = round($rollout, 2);
		}

		$color_index = ($index + 1) % count($colors);
		$track_label = $calc['track_name'] ? " - {$calc['track_name']}" : '';
		$chart_data['datasets'][] = [
			'label' => 'Saved: ' . ($calc['model_name'] ?? 'N/A') . ' - ' . ($calc['setup_name'] ?? 'N/A') . $track_label . ' (' . $calc['created_at'] . ')',
			'data' => $rollouts,
			'borderColor' => $colors[$color_index],
			'backgroundColor' => str_replace('1)', '0.2)', $colors[$color_index]),
			'fill' => false
		];
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roll Out Calculator - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php
require 'header.php';
?>
<div class="container mt-3">
    <h1>Roll Out Calculator</h1>
    <p>Calculate the rollout (distance per motor revolution) and estimated top speed for your RC car based on tire diameter, gear ratios, motor KV, and battery voltage.</p>

    <!-- Practical Tips -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Practical Tips for Rollout</h5>
        </div>
        <div class="card-body">
            <ul>
                <li><strong>Typical Values</strong>: For 1/12 pan cars, rollout typically ranges from 30–50 mm (1.2–2 inches) depending on the track and motor. Smaller tracks or lower-powered motors need lower rollout (more torque).</li>
                <li><strong>Measuring Tire Diameter</strong>: Use a caliper to measure the rear tire diameter accurately. Account for wear, as used tires have a smaller diameter.</li>
                <li><strong>Internal Ratio</strong>: Most 1/12 pan cars are direct drive (1:1). Check your car’s manual if it has a gearbox or belt system.</li>
                <li><strong>Adjusting Rollout</strong>:
                    <ul>
                        <li>Increase pinion teeth or decrease spur teeth for higher rollout (more speed).</li>
                        <li>Decrease pinion teeth or increase spur teeth for lower rollout (more torque).</li>
                    </ul>
                </li>
                <li><strong>Track Conditions</strong>:
                    <ul>
                        <li>High-grip tracks (e.g., carpet) may require lower rollout for better acceleration.</li>
                        <li>Low-grip tracks (e.g., asphalt) may need higher rollout for top speed.</li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>

    <!-- Calculation Form -->
	<?php echo $result; ?>
	<?php if ($recommended_rollout): ?>
        <div class="alert alert-info">Recommended rollout for selected track: <?php echo $recommended_rollout; ?></div>
	<?php endif; ?>
    <form method="POST" id="calcForm">
        <input type="hidden" name="action" value="calculate">
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
                <label for="prefill" class="form-label">&nbsp;</label>
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
                <label for="internal_ratio" class="form-label">Internal Transmission Ratio</label>
                <input type="number" step="0.01" class="form-control" id="internal_ratio" name="internal_ratio" value="<?php echo htmlspecialchars($internal_ratio); ?>" required>
                <small class="form-text text-muted">Enter 1.0 for direct drive (most 1/12 pan cars).</small>
            </div>
            <div class="col-md-3 mb-3">
                <label for="motor_kv" class="form-label">Motor KV</label>
                <input type="number" class="form-control" id="motor_kv" name="motor_kv" value="<?php echo htmlspecialchars($motor_kv); ?>">
                <small class="form-text text-muted">Optional, e.g., 3000 for 3000 KV.</small>
            </div>
            <div class="col-md-3 mb-3">
                <label for="battery_voltage" class="form-label">Battery Voltage</label>
                <input type="number" step="0.1" class="form-control" id="battery_voltage" name="battery_voltage" value="<?php echo htmlspecialchars($battery_voltage); ?>">
                <small class="form-text text-muted">Optional, e.g., 7.4 for 2S LiPo.</small>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Calculate & Save</button>
    </form>

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

    <!-- Saved Calculations -->
    <div class="mt-4">
        <h3>Saved Calculations</h3>
		<?php if (empty($saved_calculations)): ?>
            <p>No calculations saved yet.</p>
		<?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="compare">
                <input type="hidden" name="tire_diameter" value="<?php echo htmlspecialchars($tire_diameter); ?>">
                <input type="hidden" name="spur_teeth" value="<?php echo htmlspecialchars($spur_teeth); ?>">
                <input type="hidden" name="pinion_teeth" value="<?php echo htmlspecialchars($pinion_teeth); ?>">
                <input type="hidden" name="internal_ratio" value="<?php echo htmlspecialchars($internal_ratio); ?>">
                <input type="hidden" name="motor_kv" value="<?php echo htmlspecialchars($motor_kv); ?>">
                <input type="hidden" name="battery_voltage" value="<?php echo htmlspecialchars($battery_voltage); ?>">
                <input type="hidden" name="unit" value="<?php echo htmlspecialchars($unit); ?>">
                <input type="hidden" name="model_id" value="<?php echo htmlspecialchars($model_id); ?>">
                <input type="hidden" name="setup_id" value="<?php echo htmlspecialchars($setup_id); ?>">
                <input type="hidden" name="track_id" value="<?php echo htmlspecialchars($track_id); ?>">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Select</th>
                        <th>Date</th>
                        <th>Model</th>
                        <th>Setup</th>
                        <th>Track</th>
                        <th>Tire Diameter</th>
                        <th>Spur/Pinion</th>
                        <th>Rollout</th>
                        <th>Top Speed</th>
                    </tr>
                    </thead>
                    <tbody>
					<?php foreach ($saved_calculations as $calc): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_calcs[]" value="<?php echo $calc['id']; ?>" <?php echo in_array($calc['id'], $selected_calc_ids) ? 'checked' : ''; ?>>
                            </td>
                            <td><?php echo $calc['created_at']; ?></td>
                            <td><?php echo htmlspecialchars($calc['model_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($calc['setup_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($calc['track_name'] ?? 'N/A'); ?></td>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>