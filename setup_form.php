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

// --- Fetch all user-defined options for the dropdowns ---
$stmt_options = $pdo->prepare("SELECT option_category, option_value FROM user_options WHERE user_id = ? ORDER BY option_value");
$stmt_options->execute([$user_id]);
$all_options_raw = $stmt_options->fetchAll(PDO::FETCH_ASSOC);

// Group the options by category so they are easy to use in the form
$options_by_category = [];
foreach ($all_options_raw as $option) {
    $options_by_category[$option['option_category']][] = $option['option_value'];
}

// Helper function to generate a dropdown for a setup sheet field
function createOptionDropdown($name, $label, $options_list, $saved_value) {
    echo '<div class="col-md-4 mb-3">';
    echo '<label for="' . $name . '" class="form-label">' . $label . '</label>';
    echo '<select class="form-select" id="' . $name . '" name="' . $name . '">';
    
    // Add an empty default option
    echo '<option value="">-- Select --</option>';

    // Add the list of predefined options
    if (!empty($options_list)) {
        foreach ($options_list as $option) {
            $selected = ($option == $saved_value) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($option) . '</option>';
        }
    }

    // IMPORTANT: If the saved value is not in the predefined list, add it as a selected option.
    // This handles old entries or one-off custom values.
    if (!empty($saved_value) && (empty($options_list) || !in_array($saved_value, $options_list))) {
        echo '<option value="' . htmlspecialchars($saved_value) . '" selected>CUSTOM: ' . htmlspecialchars($saved_value) . '</option>';
    }

    echo '</select>';
    echo '</div>';
}

// Verify Current setup
$stmt_get_selected_id = $pdo->prepare("SELECT selected_setup_id FROM users WHERE id = ?");
$stmt_get_selected_id->execute([$user_id]);
$current_selected_id = $stmt_get_selected_id->fetchColumn();

$stmt_tags = $pdo->prepare("
    SELECT t.name 
    FROM tags t 
    JOIN setup_tags st ON t.id = st.tag_id 
    WHERE st.setup_id = ?
");

$stmt_tags->execute([$setup_id]);
$tags_array = $stmt_tags->fetchAll(PDO::FETCH_COLUMN);
$tags_string = implode(', ', $tags_array); // e.g., "high-grip, rainy"

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_setup'])) {
    // START a transaction for the entire save operation
    $pdo->beginTransaction();

    try {

        // --- HANDLE THE "CURRENT SETUP" CHECKBOX ---
        // We only act if the box was checked on submit
        if (isset($_POST['current_setup'])) {
            $stmt_pin = $pdo->prepare("UPDATE users SET selected_setup_id = ? WHERE id = ?");
            $stmt_pin->execute([$setup_id, $user_id]);
        }

        // --- HANDLE TAGS ---
        // 1. Get the raw tag string from the form and clean it up
        $tags_input_string = trim($_POST['tags'] ?? '');
        // Split the string by commas, trim whitespace from each tag, and remove any empty tags
        $tag_names = array_filter(array_map('trim', explode(',', $tags_input_string)));

        $tag_ids_for_setup = []; // We'll collect the IDs of the tags to be associated with the setup

        if (!empty($tag_names)) {
            foreach ($tag_names as $tag_name) {
                // 2. For each tag name, check if it already exists for this user
                $stmt_find_tag = $pdo->prepare("SELECT id FROM tags WHERE user_id = ? AND name = ?");
                $stmt_find_tag->execute([$user_id, $tag_name]);
                $tag_id = $stmt_find_tag->fetchColumn();

                // 3. If the tag doesn't exist, create it
                if (!$tag_id) {
                    $stmt_insert_tag = $pdo->prepare("INSERT INTO tags (user_id, name) VALUES (?, ?)");
                    $stmt_insert_tag->execute([$user_id, $tag_name]);
                    $tag_id = $pdo->lastInsertId();
                }
                $tag_ids_for_setup[] = $tag_id;
            }
        }

        // 4. Sync the setup_tags table: Delete old associations and insert the new ones.
        // This is the easiest way to handle additions, removals, and changes all at once.
        $stmt_delete_old_tags = $pdo->prepare("DELETE FROM setup_tags WHERE setup_id = ?");
        $stmt_delete_old_tags->execute([$setup_id]);

        if (!empty($tag_ids_for_setup)) {
            $stmt_insert_new_tags = $pdo->prepare("INSERT INTO setup_tags (setup_id, tag_id) VALUES (?, ?)");
            foreach ($tag_ids_for_setup as $tag_id) {
                $stmt_insert_new_tags->execute([$setup_id, $tag_id]);
            }
        }

        // --- HANDLE THE BASELINE CHECKBOX ---
        $is_baseline = isset($_POST['is_baseline']) ? 1 : 0;
        $model_id = $setup['model_id'];

        // If the user checked the box to make THIS setup the baseline...
        if ($is_baseline == 1) {
            // ...first, set all OTHER setups for this model to NOT be the baseline.
            $stmt_reset = $pdo->prepare("UPDATE setups SET is_baseline = 0 WHERE model_id = ? AND id != ?");
            $stmt_reset->execute([$model_id, $setup_id]);
        }

        // Now, update the baseline status for THIS specific setup.
        // This query runs regardless, to either set it to 1 or back to 0 if the user unchecked it.
        $stmt_update_baseline = $pdo->prepare("UPDATE setups SET is_baseline = ? WHERE id = ?");
        $stmt_update_baseline->execute([$is_baseline, $setup_id]);


        // --- YOUR EXISTING SAVE LOGIC FOR ALL OTHER TABLES NOW FOLLOWS ---

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

        // --- END OF YOUR EXISTING SAVE LOGIC ---

        // If everything was successful, COMMIT the transaction
        $pdo->commit();

        // Redirect back to the same page with a success message
        header("Location: setup_form.php?setup_id=" . $setup_id . "&success=1");
        exit;

    } catch (PDOException $e) {
        // If any query fails, ROLLBACK the entire transaction
        $pdo->rollBack();
        error_log("Setup save failed: " . $e->getMessage());
        // Redirect back with an error message
        header("Location: setup_form.php?setup_id=" . $setup_id . "&error=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Setup saved successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">There was an error saving the setup. Please try again.</div>
    <?php endif; ?>

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
            // --- Now we call our new helper function for the dropdowns ---
            createOptionDropdown('electronics[battery_brand]', 'Battery Brand', $options_by_category['battery_brand'] ?? [], $data['electronics']['battery_brand'] ?? null);
            createOptionDropdown('electronics[esc_brand]', 'ESC Brand', $options_by_category['esc_brand'] ?? [], $data['electronics']['esc_brand'] ?? null);
            createOptionDropdown('electronics[motor_brand]', 'Motor Brand', $options_by_category['motor_brand'] ?? [], $data['electronics']['motor_brand'] ?? null);
            createOptionDropdown('electronics[radio_brand]', 'Radio Brand', $options_by_category['radio_brand'] ?? [], $data['electronics']['radio_brand'] ?? null);
            createOptionDropdown('electronics[servo_brand]', 'Servo Brand', $options_by_category['servo_brand'] ?? [], $data['electronics']['servo_brand'] ?? null);

            // --- These fields will remain as text inputs ---
            $text_fields = ['battery', 'battery_c_rating', 'capacity', 'model', 'esc_model', 'motor_kv_constant', 'motor_model', 'motor_timing', 'motor_wind', 'radio_model', 'receiver_model', 'transponder_id'];
            foreach ($text_fields as $field):
            ?>
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
        <div class="mb-3">
            <label for="tags" class="form-label">Tags</label>
            <input type="text" class="form-control" id="tags" name="tags" value="<?php echo htmlspecialchars($tags_string); ?>">
            <div class="form-text">Enter tags separated by commas (e.g., high-grip, rainy, competition).</div>
        </div>
        <!-- Comments -->
        <h3>Comments</h3>
        <div class="mb-3">
            <label for="comments_comment" class="form-label">Comment</label>
            <textarea class="form-control" id="comments_comment" name="comments[comment]"><?php echo htmlspecialchars($data['comments']['comment'] ?? ''); ?></textarea>
        </div>
        <div class="form-check form-switch ms-auto">
            <input class="form-check-input" type="checkbox" role="switch" id="is_baseline_checkbox" name="is_baseline" value="1" <?php echo ($setup['is_baseline'] ?? 0) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="is_baseline_checkbox">Set as Baseline</label>
        </div>
        <div class="form-check form-switch ms-auto">
            <input class="form-check-input" type="checkbox" role="switch" id="current_setup_checkbox" name="current_setup" value="1" <?php echo ($current_selected_id == $setup_id) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="current_setup_checkbox">Set as Current Setup</label>
        </div>
        <button type="submit" name="save_setup" class="btn btn-primary">Save Setup</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>