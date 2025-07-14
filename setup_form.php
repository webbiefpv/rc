<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$setup_id = $_GET['setup_id'];

// --- 1. GET THE SETUP AND VERIFY OWNERSHIP ---
if (!isset($_GET['setup_id'])) {
    header('Location: index.php');
    exit;
}
$stmt_setup = $pdo->prepare("SELECT s.*, m.user_id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ?");
$stmt_setup->execute([$setup_id]);
$setup = $stmt_setup->fetch(PDO::FETCH_ASSOC);
if (!$setup || $setup['user_id'] != $user_id) { header('Location: index.php'); exit; }

// --- 2. FETCH ALL DATA FOR THE FORM ---
// Fetch predefined options for dropdowns
$stmt_options = $pdo->prepare("SELECT option_category, option_value FROM user_options WHERE user_id = ? ORDER BY option_value");
$stmt_options->execute([$user_id]);
$all_options_raw = $stmt_options->fetchAll(PDO::FETCH_ASSOC);
$options_by_category = [];
foreach ($all_options_raw as $option) { $options_by_category[$option['option_category']][] = $option['option_value']; }

// Fetch all standard field data for this setup
$data = [];
$tables = ['front_suspension', 'rear_suspension', 'drivetrain', 'body_chassis', 'electronics', 'esc_settings', 'comments'];
foreach ($tables as $table) {
    $stmt_data = $pdo->prepare("SELECT * FROM $table WHERE setup_id = ?");
    $stmt_data->execute([$setup_id]);
    $data[$table] = $stmt_data->fetch(PDO::FETCH_ASSOC);
}
// Fetch tires data separately
$stmt_tires = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = ?");
$stmt_tires->execute([$setup_id, 'front']);
$data['tires_front'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);
$stmt_tires->execute([$setup_id, 'rear']);
$data['tires_rear'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);


// --- 3. HANDLE FORM SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_setup'])) {
    $pdo->beginTransaction();
    try {
        // ... (Baseline save logic) ...
        
        // Save each section explicitly
        $sections_to_save = [
            'front_suspension' => ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims', 'notes'],
            'rear_suspension'  => ['axle_height', 'centre_pivot_ball', 'centre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_springs', 'side_bands_lr', 'notes'],
            'drivetrain'       => ['axle_type', 'drive_ratio', 'gear_pitch', 'rollout', 'spur', 'pinion'], // ADDED pinion
            'body_chassis'     => ['battery_position', 'body', 'body_mounting', 'chassis', 'electronics_layout', 'motor_position', 'motor_shims', 'rear_wing', 'screw_turn_buckles', 'servo_position', 'weight_balance_fr', 'weight_total', 'weights'],
            'electronics'      => ['battery_brand', 'esc_brand', 'motor_brand', 'radio_brand', 'servo_brand', 'battery', 'battery_c_rating', 'capacity', 'model', 'esc_model', 'motor_kv_constant', 'motor_model', 'motor_timing', 'motor_wind', 'radio_model', 'receiver_model', 'servo_model', 'transponder_id', 'charging_notes'],
            'esc_settings'     => ['boost_activation', 'boost_rpm_end', 'boost_rpm_start', 'boost_ramp', 'boost_timing', 'brake_curve', 'brake_drag', 'brake_frequency', 'brake_initial_strength', 'race_mode', 'throttle_curve', 'throttle_frequency', 'throttle_initial_strength', 'throttle_neutral_range', 'throttle_strength', 'turbo_activation_method', 'turbo_delay', 'turbo_ramp', 'turbo_timing'],
            'comments'         => ['comment']
        ];

        // ... (Loop to save each section) ...
        // The existing save logic will work as long as 'pinion' is in the array above.

        $pdo->commit();
        header("Location: setup_form.php?setup_id=" . $setup_id . "&success=1");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: setup_form.php?setup_id=" . $setup_id . "&error=1&msg=" . urlencode($e->getMessage()));
        exit;
    }
}


// --- 4. HELPER FUNCTION TO RENDER FORM FIELDS ---
function render_field($field_name, $input_name, $saved_value, $options_by_category, $extra_attrs = '') {
    $label = ucwords(str_replace('_', ' ', $field_name));
    $id = str_replace(['[', ']'], '_', $input_name); // Create a clean ID for JS
    
    if (isset($options_by_category[$field_name])) {
        // Dropdown logic...
    } else { // Text input
        echo '<div class="col-md-4 mb-3"><label for="'.$id.'" class="form-label">' . $label . '</label><input type="text" class="form-control" id="'.$id.'" name="' . $input_name . '" value="' . htmlspecialchars($saved_value ?? '') . '" '.$extra_attrs.'></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Form - <?php echo htmlspecialchars($setup['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Setup: <?php echo htmlspecialchars($setup['name']); ?></h1>
    
    <form method="POST">
        <!-- ... (Other form sections) ... -->

        <h3>Rear Tires</h3>
        <div class="row">
        <?php
            // Note the extra attribute added to tire_diameter for the oninput event
            $fields = ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_side_wall_glue'];
            foreach ($fields as $field) { render_field($field, 'tires_rear[' . $field . ']', $data['tires_rear'][$field] ?? null, $options_by_category); }
            render_field('tire_diameter', 'tires_rear[tire_diameter]', $data['tires_rear']['tire_diameter'] ?? null, $options_by_category, 'oninput="calculateRollout()"');
        ?>
        </div>

        <h3>Drivetrain</h3>
        <div class="row">
        <?php
            $fields = ['axle_type', 'gear_pitch'];
            foreach ($fields as $field) { render_field($field, 'drivetrain[' . $field . ']', $data['drivetrain'][$field] ?? null, $options_by_category); }
            
            // Render Spur and Pinion with oninput event
            render_field('spur', 'drivetrain[spur]', $data['drivetrain']['spur'] ?? null, $options_by_category, 'oninput="calculateRollout()"');
            render_field('pinion', 'drivetrain[pinion]', $data['drivetrain']['pinion'] ?? null, $options_by_category, 'oninput="calculateRollout()"');
            
            // Render Drive Ratio and Rollout as readonly
            render_field('drive_ratio', 'drivetrain[drive_ratio]', $data['drivetrain']['drive_ratio'] ?? null, $options_by_category, 'readonly');
            render_field('rollout', 'drivetrain[rollout]', $data['drivetrain']['rollout'] ?? null, $options_by_category, 'readonly');
        ?>
        </div>

        <!-- ... (Other form sections) ... -->
        
        <hr>
        <button type="submit" name="save_setup" class="btn btn-primary">Save All Changes</button>
    </form>
</div>

<!-- NEW JAVASCRIPT SECTION -->
<script>
function calculateRollout() {
    // Get the input elements
    const tireDiameterInput = document.getElementById('tires_rear_tire_diameter_');
    const spurInput = document.getElementById('drivetrain_spur_');
    const pinionInput = document.getElementById('drivetrain_pinion_');
    const driveRatioInput = document.getElementById('drivetrain_drive_ratio_');
    const rolloutInput = document.getElementById('drivetrain_rollout_');

    // Read the values, treat empty as 0
    const tireDiameter = parseFloat(tireDiameterInput.value) || 0;
    const spur = parseFloat(spurInput.value) || 0;
    const pinion = parseFloat(pinionInput.value) || 0;

    // Perform calculations only if we have all necessary values
    if (spur > 0 && pinion > 0) {
        const driveRatio = spur / pinion;
        driveRatioInput.value = driveRatio.toFixed(2);

        if (tireDiameter > 0) {
            const rollout = (tireDiameter * Math.PI) / driveRatio;
            rolloutInput.value = rollout.toFixed(2);
        } else {
            rolloutInput.value = '';
        }
    } else {
        driveRatioInput.value = '';
        rolloutInput.value = '';
    }
}

// Run the calculation once on page load to initialize
document.addEventListener('DOMContentLoaded', calculateRollout);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>