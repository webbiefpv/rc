<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$setup_id = $_GET['setup_id'];
$user_id = $_SESSION['user_id'];

// Verify setup belongs to user
$stmt = $pdo->prepare("SELECT s.*, m.user_id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ?");
$stmt->execute([$setup_id]);
$setup = $stmt->fetch();
if (!$setup || $setup['user_id'] != $user_id) {
	header('Location: index.php');
	exit;
}

// Fetch existing data
$tables = ['front_suspension', 'rear_suspension', 'drivetrain', 'body_chassis', 'electronics', 'esc_settings', 'comments'];
$data = [];
foreach ($tables as $table) {
	$stmt = $pdo->prepare("SELECT * FROM $table WHERE setup_id = ?");
	$stmt->execute([$setup_id]);
	$data[$table] = $stmt->fetch();
}

// Tires (front and rear)
$stmt = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = ?");
$stmt->execute([$setup_id, 'front']);
$data['tires_front'] = $stmt->fetch();
$stmt->execute([$setup_id, 'rear']);
$data['tires_rear'] = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Front Suspension
	$front_suspension = [
		'ackermann' => $_POST['front_suspension']['ackermann'] ?? '',
		'arms' => $_POST['front_suspension']['arms'] ?? '',
		'bumpsteer' => $_POST['front_suspension']['bumpsteer'] ?? '',
		'droop_shims' => $_POST['front_suspension']['droop_shims'] ?? '',
		'kingpin_fluid' => $_POST['front_suspension']['kingpin_fluid'] ?? '',
		'ride_height' => $_POST['front_suspension']['ride_height'] ?? '',
		'arm_shims' => $_POST['front_suspension']['arm_shims'] ?? '',
		'springs' => $_POST['front_suspension']['springs'] ?? '',
		'steering_blocks' => $_POST['front_suspension']['steering_blocks'] ?? '',
		'steering_limiter' => $_POST['front_suspension']['steering_limiter'] ?? '',
		'track_wheel_shims' => $_POST['front_suspension']['track_wheel_shims'] ?? '',
		'notes' => $_POST['front_suspension']['notes'] ?? ''
	];
	if ($data['front_suspension']) {
		$stmt = $pdo->prepare("UPDATE front_suspension SET ackermann = ?, arms = ?, bumpsteer = ?, droop_shims = ?, kingpin_fluid = ?, ride_height = ?, arm_shims = ?, springs = ?, steering_blocks = ?, steering_limiter = ?, track_wheel_shims = ?, notes = ? WHERE setup_id = ?");
		$stmt->execute([...array_values($front_suspension), $setup_id]);
	} else {
		$stmt = $pdo->prepare("INSERT INTO front_suspension (setup_id, ackermann, arms, bumpsteer, droop_shims, kingpin_fluid, ride_height, arm_shims, springs, steering_blocks, steering_limiter, track_wheel_shims, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$setup_id, ...array_values($front_suspension)]);
	}

	// Rear Suspension
	$rear_suspension = [
		'axle_height' => $_POST['rear_suspension']['axle_height'] ?? '',
		'centre_pivot_ball' => $_POST['rear_suspension']['centre_pivot_ball'] ?? '',
		'centre_pivot_fluid' => $_POST['rear_suspension']['centre_pivot_fluid'] ?? '',
		'droop' => $_POST['rear_suspension']['droop'] ?? '',
		'rear_pod_shims' => $_POST['rear_suspension']['rear_pod_shims'] ?? '',
		'rear_spring' => $_POST['rear_suspension']['rear_spring'] ?? '',
		'ride_height' => $_POST['rear_suspension']['ride_height'] ?? '',
		'side_bands_lr' => $_POST['rear_suspension']['side_bands_lr'] ?? '',
		'notes' => $_POST['rear_suspension']['notes'] ?? ''
	];
	if ($data['rear_suspension']) {
		$stmt = $pdo->prepare("UPDATE rear_suspension SET axle_height = ?, centre_pivot_ball = ?, centre_pivot_fluid = ?, droop = ?, rear_pod_shims = ?, rear_spring = ?, ride_height = ?, side_bands_lr = ?, notes = ? WHERE setup_id = ?");
		$stmt->execute([...array_values($rear_suspension), $setup_id]);
	} else {
		$stmt = $pdo->prepare("INSERT INTO rear_suspension (setup_id, axle_height, centre_pivot_ball, centre_pivot_fluid, droop, rear_pod_shims, rear_spring, ride_height, side_bands_lr, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$setup_id, ...array_values($rear_suspension)]);
	}

	// Tires (Front)
	$tires_front = [
		'tire_brand' => $_POST['tires_front']['tire_brand'] ?? '',
		'tire_compound' => $_POST['tires_front']['tire_compound'] ?? '',
		'wheel_brand_type' => $_POST['tires_front']['wheel_brand_type'] ?? '',
		'tire_additive' => $_POST['tires_front']['tire_additive'] ?? '',
		'tire_additive_area' => $_POST['tires_front']['tire_additive_area'] ?? '',
		'tire_additive_time' => $_POST['tires_front']['tire_additive_time'] ?? '',
		'tire_diameter' => $_POST['tires_front']['tire_diameter'] ?? '',
		'tire_side_wall_glue' => $_POST['tires_front']['tire_side_wall_glue'] ?? ''
	];
	if ($data['tires_front']) {
		$stmt = $pdo->prepare("UPDATE tires SET tire_brand = ?, tire_compound = ?, wheel_brand_type = ?, tire_additive = ?, tire_additive_area = ?, tire_additive_time = ?, tire_diameter = ?, tire_side_wall_glue = ? WHERE setup_id = ? AND position = 'front'");
		$stmt->execute([...array_values($tires_front), $setup_id]);
	} else {
		$stmt = $pdo->prepare("INSERT INTO tires (setup_id, position, tire_brand, tire_compound, wheel_brand_type, tire_additive, tire_additive_area, tire_additive_time, tire_diameter, tire_side_wall_glue) VALUES (?, 'front', ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$setup_id, ...array_values($tires_front)]);
	}

	// Tires (Rear)
	$tires_rear = [
		'tire_brand' => $_POST['tires_rear']['tire_brand'] ?? '',
		'tire_compound' => $_POST['tires_rear']['tire_compound'] ?? '',
		'wheel_brand_type' => $_POST['tires_rear']['wheel_brand_type'] ?? '',
		'tire_additive' => $_POST['tires_rear']['tire_additive'] ?? '',
		'tire_additive_area' => $_POST['tires_rear']['tire_additive_area'] ?? '',
		'tire_additive_time' => $_POST['tires_rear']['tire_additive_time'] ?? '',
		'tire_diameter' => $_POST['tires_rear']['tire_diameter'] ?? '',
		'tire_side_wall_glue' => $_POST['tires_rear']['tire_side_wall_glue'] ?? ''
	];
	if ($data['tires_rear']) {
		$stmt = $pdo->prepare("UPDATE tires SET tire_brand = ?, tire_compound = ?, wheel_brand_type = ?, tire_additive = ?, tire_additive_area = ?, tire_additive_time = ?, tire_diameter = ?, tire_side_wall_glue = ? WHERE setup_id = ? AND position = 'rear'");
		$stmt->execute([...array_values($tires_rear), $setup_id]);
	} else {
		$stmt = $pdo->prepare("INSERT INTO tires (setup_id, position, tire_brand, tire_compound, wheel_brand_type, tire_additive, tire_additive_area, tire_additive_time, tire_diameter, tire_side_wall_glue) VALUES (?, 'rear', ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$setup_id, ...array_values($tires_rear)]);
	}

	// Drivetrain
	$drivetrain = [
		'axle_type' => $_POST['drivetrain']['axle_type'] ?? '',
		'drive_ratio' => $_POST['drivetrain']['drive_ratio'] ?? '',
		'gear_pitch' => $_POST['drivetrain']['gear_pitch'] ?? '',
		'rollout' => $_POST['drivetrain']['rollout'] ?? '',
		'spur' => $_POST['drivetrain']['spur'] ?? ''
	];
	if ($data['drivetrain']) {
		$stmt = $pdo->prepare("UPDATE drivetrain SET axle_type = ?, drive_ratio = ?, gear_pitch = ?, rollout = ?, spur = ? WHERE setup_id = ?");
		$stmt->execute([...array_values($drivetrain), $setup_id]);
	} else {
		$stmt = $pdo->prepare("INSERT INTO drivetrain (setup_id, axle_type, drive_ratio, gear_pitch, rollout, spur) VALUES (?, ?, ?, ?, ?, ?)");
		$stmt->execute([$setup_id, ...array_values($drivetrain)]);
	}

	// Body and Chassis
	$body_chassis = [
		'battery_position' => $_POST['body_chassis']['battery_position'] ?? '',
		'body' => $_POST['body_chassis']['body'] ?? '',
		'body_mounting' => $_POST['body_chassis']['body_mounting'] ?? '',
		'chassis' => $_POST['body_chassis']['chassis'] ?? '',
		'electronics_layout' => $_POST['body_chassis']['electronics_layout'] ?? '',
		'motor_position' => $_POST['body_chassis']['motor_position'] ?? '',
		'motor_shims' => $_POST['body_chassis']['motor_shims'] ?? '',
		'rear_wing' => $_POST['body_chassis']['rear_wing'] ?? '',
		'screw_turn_buckles' => $_POST['body_chassis']['screw_turn_buckles'] ?? '',
		'servo_position' => $_POST['body_chassis']['servo_position'] ?? '',
		'weight_balance_fr' => $_POST['body_chassis']['weight_balance_fr'] ?? '',
		'weight_total' => $_POST['body_chassis']['weight_total'] ?? '',
		'weights' => $_POST['body_chassis']['weights'] ?? ''
	];
	if ($data['body_chassis']) {
		$stmt = $pdo->prepare("UPDATE body_chassis SET battery_position = ?, body = ?, body_mounting = ?, chassis = ?, electronics_layout = ?, motor_position = ?, motor_shims = ?, rear_wing = ?, screw_turn_buckles = ?, servo_position = ?, weight_balance_fr = ?, weight_total = ?, weights = ? WHERE setup_id = ?");
		$stmt->execute([...array_values($body_chassis), $setup_id]);
	} else {
		$stmt = $pdo->prepare("INSERT INTO body_chassis (setup_id, battery_position, body, body_mounting, chassis, electronics_layout, motor_position, motor_shims, rear_wing, screw_turn_buckles, servo_position, weight_balance_fr, weight_total, weights) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$setup_id, ...array_values($body_chassis)]);
	}

	// Electronics
	$electronics = [
		'battery' => $_POST['electronics']['battery'] ?? '',
		'battery_c_rating' => $_POST['electronics']['battery_c_rating'] ?? '',
		'battery_brand' => $_POST['electronics']['battery_brand'] ?? '',
		'capacity' => $_POST['electronics']['capacity'] ?? '',
		'model' => $_POST['electronics']['model'] ?? '',
		'charging_notes' => $_POST['electronics']['charging_notes'] ?? '',
		'esc_brand' => $_POST['electronics']['esc_brand'] ?? '',
		'esc_model' => $_POST['electronics']['esc_model'] ?? '',
		'motor_kv_constant' => $_POST['electronics']['motor_kv_constant'] ?? '',
		'motor_brand' => $_POST['electronics']['motor_brand'] ?? '',
		'motor_model' => $_POST['electronics']['motor_model'] ?? '',
		'motor_timing' => $_POST['electronics']['motor_timing'] ?? '',
		'motor_wind' => $_POST['electronics']['motor_wind'] ?? '',
		'radio_brand' => $_POST['electronics']['radio_brand'] ?? '',
		'radio_model' => $_POST['electronics']['radio_model'] ?? '',
		'receiver_model' => $_POST['electronics']['receiver_model'] ?? '',
		'servo_brand' => $_POST['electronics']['servo_brand'] ?? '',
		'servo_model' => $_POST['electronics']['servo_model'] ?? '',
		'transponder_id' => $_POST['electronics']['transponder_id'] ?? ''
	];
	if ($data['electronics']) {
		$stmt = $pdo->prepare("UPDATE electronics SET battery = ?, battery_c_rating = ?, battery_brand = ?, capacity = ?, model = ?, charging_notes = ?, esc_brand = ?, esc_model = ?, motor_kv_constant = ?, motor_brand = ?, motor_model = ?, motor_timing = ?, motor_wind = ?, radio_brand = ?, radio_model = ?, receiver_model = ?, servo_brand = ?, servo_model = ?, transponder_id = ? WHERE setup_id = ?");
		$stmt->execute([...array_values($electronics), $setup_id]);
	} else {
		$stmt = $pdo->prepare("INSERT INTO electronics (setup_id, battery, battery_c_rating, battery_brand, capacity, model, charging_notes, esc_brand, esc_model, motor_kv_constant, motor_brand, motor_model, motor_timing, motor_wind, radio_brand, radio_model, receiver_model, servo_brand, servo_model, transponder_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$setup_id, ...array_values($electronics)]);
	}

	// ESC Settings
	$esc_settings = [
		'boost_activation' => $_POST['esc_settings']['boost_activation'] ?? '',
		'boost_rpm_end' => $_POST['esc_settings']['boost_rpm_end'] ?? '',
		'boost_rpm_start' => $_POST['esc_settings']['boost_rpm_start'] ?? '',
		'boost_ramp' => $_POST['esc_settings']['boost_ramp'] ?? '',
		'boost_timing' => $_POST['esc_settings']['boost_timing'] ?? '',
		'brake_curve' => $_POST['esc_settings']['brake_curve'] ?? '',
		'brake_drag' => $_POST['esc_settings']['brake_drag'] ?? '',
		'brake_frequency' => $_POST['esc_settings']['brake_frequency'] ?? '',
		'brake_initial_strength' => $_POST['esc_settings']['brake_initial_strength'] ?? '',
		'race_mode' => $_POST['esc_settings']['race_mode'] ?? '',
		'throttle_curve' => $_POST['esc_settings']['throttle_curve'] ?? '',
		'throttle_frequency' => $_POST['esc_settings']['throttle_frequency'] ?? '',
		'throttle_initial_strength' => $_POST['esc_settings']['throttle_initial_strength'] ?? '',
		'throttle_neutral_range' => $_POST['esc_settings']['throttle_neutral_range'] ?? '',
		'throttle_strength' => $_POST['esc_settings']['throttle_strength'] ?? '',
		'turbo_activation_method' => $_POST['esc_settings']['turbo_activation_method'] ?? '',
		'turbo_delay' => $_POST['esc_settings']['turbo_delay'] ?? '',
		'turbo_ramp' => $_POST['esc_settings']['turbo_ramp'] ?? '',
		'turbo_timing' => $_POST['esc_settings']['turbo_timing'] ?? ''
	];
	if ($data['esc_settings']) {
		$stmt = $pdo->prepare("UPDATE esc_settings SET boost_activation = ?, boost_rpm_end = ?, boost_rpm_start = ?, boost_ramp = ?, boost_timing = ?, brake_curve = ?, brake_drag = ?, brake_frequency = ?, brake_initial_strength = ?, race_mode = ?, throttle_curve = ?, throttle_frequency = ?, throttle_initial_strength = ?, throttle_neutral_range = ?, throttle_strength = ?, turbo_activation_method = ?, turbo_delay = ?, turbo_ramp = ?, turbo_timing = ? WHERE setup_id = ?");
		$stmt->execute([...array_values($esc_settings), $setup_id]);
	} else {
		$stmt = $pdo->prepare("INSERT INTO esc_settings (setup_id, boost_activation, boost_rpm_end, boost_rpm_start, boost_ramp, boost_timing, brake_curve, brake_drag, brake_frequency, brake_initial_strength, race_mode, throttle_curve, throttle_frequency, throttle_initial_strength, throttle_neutral_range, throttle_strength, turbo_activation_method, turbo_delay, turbo_ramp, turbo_timing) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$setup_id, ...array_values($esc_settings)]);
	}

	// Comments
	$comment = $_POST['comments']['comment'] ?? '';
	if ($data['comments']) {
		$stmt = $pdo->prepare("UPDATE comments SET comment = ? WHERE setup_id = ?");
		$stmt->execute([$comment, $setup_id]);
	} else {
		$stmt = $pdo->prepare("INSERT INTO comments (setup_id, comment) VALUES (?, ?)");
		$stmt->execute([$setup_id, $comment]);
	}

	header("Location: setups.php?model_id={$setup['model_id']}");
	exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_baseline'])) {
    // Logic to mark this setup as the baseline for its model

    // The user ID and setup ID are already available at the top of your script
    // We also have the $setup variable which should contain the model_id

    $model_id = $setup['model_id'];

    // Use a transaction to ensure data integrity
    $pdo->beginTransaction();
    try {
        // 1. First, set all other setups for this model to NOT be the baseline
        $stmt_reset = $pdo->prepare("UPDATE setups SET is_baseline = 0 WHERE model_id = ? AND user_id = (SELECT user_id FROM models WHERE id = ?)");
        // Note: The subquery for user_id is an extra security check, assuming setups table doesn't have user_id directly.
        // A simpler way if your security model allows, since you already verified ownership of the setup:
        // $stmt_reset = $pdo->prepare("UPDATE setups SET is_baseline = 0 WHERE model_id = ?");
        $stmt_reset->execute([$model_id]);

        // 2. Then, set the current setup to be the baseline
        $stmt_set = $pdo->prepare("UPDATE setups SET is_baseline = 1 WHERE id = ?");
        $stmt_set->execute([$setup_id]);

        $pdo->commit();

        // Optionally, set a success message to display
        $_SESSION['success_message'] = "Setup marked as baseline successfully!";

    } catch (PDOException $e) {
        $pdo->rollBack();
        // Set an error message
        $_SESSION['error_message'] = "Failed to mark setup as baseline.";
        error_log("Baseline marking failed: " . $e->getMessage());
    }

    // Redirect back to the same page to prevent form re-submission on refresh
    header("Location: setup_form.php?setup_id=" . $setup_id);
    exit;
}

// Check for and display messages
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup: <?php echo htmlspecialchars($setup['name']); ?> - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Pan Car Setup</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Pits</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rollout_calc.php">Roll Out</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="race_log.php">Race Log</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="glossary.php">On-Road Setup Glossary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="troubleshooting.php">Troubleshooting</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="change_password.php">Change Password</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-3">
    <h1>Setup: <?php echo htmlspecialchars($setup['name']); ?></h1>
    <form method="POST">
        <!-- Front Suspension -->
        <h3>Front Suspension</h3>
        <div class="row">
			<?php
			$fields = ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims'];
			foreach ($fields as $field): ?>
                <div class="col-md-4 mb-3">
                    <label for="front_suspension_<?php echo $field; ?>" class="form-label"><?php echo ucwords(str_replace('_', ' ', $field)); ?></label>
                    <input type="text" class="form-control" id="front_suspension_<?php echo $field; ?>" name="front_suspension[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($data['front_suspension'][$field] ?? ''); ?>">
                </div>
			<?php endforeach; ?>
            <div class="col-12 mb-3">
                <label for="front_suspension_notes" class="form-label">Notes</label>
                <textarea class="form-control" id="front_suspension_notes" name="front_suspension[notes]"><?php echo htmlspecialchars($data['front_suspension']['notes'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Rear Suspension -->
        <h3>Rear Suspension</h3>
        <div class="row">
			<?php
			$fields = ['axle_height', 'centre_pivot_ball', 'centre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_bands_lr'];
			foreach ($fields as $field): ?>
                <div class="col-md-4 mb-3">
                    <label for="rear_suspension_<?php echo $field; ?>" class="form-label"><?php echo ucwords(str_replace('_', ' ', $field)); ?></label>
                    <input type="text" class="form-control" id="rear_suspension_<?php echo $field; ?>" name="rear_suspension[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($data['rear_suspension'][$field] ?? ''); ?>">
                </div>
			<?php endforeach; ?>
            <div class="col-12 mb-3">
                <label for="rear_suspension_notes" class="form-label">Notes</label>
                <textarea class="form-control" id="rear_suspension_notes" name="rear_suspension[notes]"><?php echo htmlspecialchars($data['rear_suspension']['notes'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Tires (Front) -->
        <h3>Front Tires</h3>
        <div class="row">
			<?php
			$fields = ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue'];
			foreach ($fields as $field): ?>
                <div class="col-md-4 mb-3">
                    <label for="tires_front_<?php echo $field; ?>" class="form-label"><?php echo ucwords(str_replace('_', ' ', $field)); ?></label>
                    <input type="text" class="form-control" id="tires_front_<?php echo $field; ?>" name="tires_front[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($data['tires_front'][$field] ?? ''); ?>">
                </div>
			<?php endforeach; ?>
        </div>

        <!-- Tires (Rear) -->
        <h3>Rear Tires</h3>
        <div class="row">
			<?php foreach ($fields as $field): ?>
                <div class="col-md-4 mb-3">
                    <label for="tires_rear_<?php echo $field; ?>" class="form-label"><?php echo ucwords(str_replace('_', ' ', $field)); ?></label>
                    <input type="text" class="form-control" id="tires_rear_<?php echo $field; ?>" name="tires_rear[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($data['tires_rear'][$field] ?? ''); ?>">
                </div>
			<?php endforeach; ?>
        </div>

        <!-- Drivetrain -->
        <h3>Drivetrain</h3>
        <div class="row">
			<?php
			$fields = ['axle_type', 'drive_ratio', 'gear_pitch', 'rollout', 'spur'];
			foreach ($fields as $field): ?>
                <div class="col-md-4 mb-3">
                    <label for="drivetrain_<?php echo $field; ?>" class="form-label"><?php echo ucwords(str_replace('_', ' ', $field)); ?></label>
                    <input type="text" class="form-control" id="drivetrain_<?php echo $field; ?>" name="drivetrain[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($data['drivetrain'][$field] ?? ''); ?>">
                </div>
			<?php endforeach; ?>
        </div>

        <!-- Body and Chassis -->
        <h3>Body and Chassis</h3>
        <div class="row">
			<?php
			$fields = ['battery_position', 'body', 'body_mounting', 'chassis', 'electronics_layout', 'motor_position', 'motor_shims', 'rear_wing', 'screw_turn_buckles', 'servo_position', 'weight_balance_fr', 'weight_total', 'weights'];
			foreach ($fields as $field): ?>
                <div class="col-md-4 mb-3">
                    <label for="body_chassis_<?php echo $field; ?>" class="form-label"><?php echo ucwords(str_replace('_', ' ', $field)); ?></label>
                    <input type="text" class="form-control" id="body_chassis_<?php echo $field; ?>" name="body_chassis[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($data['body_chassis'][$field] ?? ''); ?>">
                </div>
			<?php endforeach; ?>
        </div>

        <!-- Electronics -->
        <h3>Electronics</h3>
        <div class="row">
			<?php
			$fields = ['battery', 'battery_c_rating', 'battery_brand', 'capacity', 'model', 'esc_brand', 'esc_model', 'motor_kv_constant', 'motor_brand', 'motor_model', 'motor_timing', 'motor_wind', 'radio_brand', 'radio_model', 'receiver_model', 'servo_brand', 'servo_model', 'transponder_id'];
			foreach ($fields as $field): ?>
                <div class="col-md-4 mb-3">
                    <label for="electronics_<?php echo $field; ?>" class="form-label"><?php echo ucwords(str_replace('_', ' ', $field)); ?></label>
                    <input type="text" class="form-control" id="electronics_<?php echo $field; ?>" name="electronics[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($data['electronics'][$field] ?? ''); ?>">
                </div>
			<?php endforeach; ?>
            <div class="col-12 mb-3">
                <label for="electronics_charging_notes" class="form-label">Charging Notes</label>
                <textarea class="form-control" id="electronics_charging_notes" name="electronics[charging_notes]"><?php echo htmlspecialchars($data['electronics']['charging_notes'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- ESC Settings -->
        <h3>ESC Settings</h3>
        <div class="row">
			<?php
			$fields = ['boost_activation', 'boost_rpm_end', 'boost_rpm_start', 'boost_ramp', 'boost_timing', 'brake_curve', 'brake_drag', 'brake_frequency', 'brake_initial_strength', 'race_mode', 'throttle_curve', 'throttle_frequency', 'throttle_initial_strength', 'throttle_neutral_range', 'throttle_strength', 'turbo_activation_method', 'turbo_delay', 'turbo_ramp', 'turbo_timing'];
			foreach ($fields as $field): ?>
                <div class="col-md-4 mb-3">
                    <label for="esc_settings_<?php echo $field; ?>" class="form-label"><?php echo ucwords(str_replace('_', ' ', $field)); ?></label>
                    <input type="text" class="form-control" id="esc_settings_<?php echo $field; ?>" name="esc_settings[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($data['esc_settings'][$field] ?? ''); ?>">
                </div>
			<?php endforeach; ?>
        </div>
        <div class="mb-3">
            <a href="export_csv.php?setup_id=<?php echo $setup_id; ?>" class="btn btn-info">Download CSV</a>
            <button type="button" class="btn btn-warning" onclick="shareSetup()">Share</button>
        </div>

        <!-- Comments -->
        <h3>Comments</h3>
        <div class="mb-3">
            <label for="comments_comment" class="form-label">Comment</label>
            <textarea class="form-control" id="comments_comment" name="comments[comment]"><?php echo htmlspecialchars($data['comments']['comment'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save Setup</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>