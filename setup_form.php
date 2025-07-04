<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$setup_id = $_GET['setup_id'];

// --- Get the setup and verify ownership ---
$stmt_setup = $pdo->prepare("SELECT s.*, m.user_id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ?");
$stmt_setup->execute([$setup_id]);
$setup = $stmt_setup->fetch(PDO::FETCH_ASSOC);
if (!$setup || $setup['user_id'] != $user_id) { header('Location: index.php'); exit; }

// --- Fetch all user-defined options ---
$stmt_options = $pdo->prepare("SELECT option_category, option_value FROM user_options WHERE user_id = ? ORDER BY option_value");
$stmt_options->execute([$user_id]);
$all_options_raw = $stmt_options->fetchAll(PDO::FETCH_ASSOC);
$options_by_category = [];
foreach ($all_options_raw as $option) { $options_by_category[$option['option_category']][] = $option['option_value']; }

// --- Fetch all existing data for the form ---
$data = [];
$tables = ['front_suspension', 'rear_suspension', 'drivetrain', 'body_chassis', 'electronics', 'esc_settings', 'comments'];
foreach ($tables as $table) {
    $stmt_data = $pdo->prepare("SELECT * FROM $table WHERE setup_id = ?");
    $stmt_data->execute([$setup_id]);
    $data[$table] = $stmt_data->fetch(PDO::FETCH_ASSOC);
}
$stmt_tires = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = ?");
$stmt_tires->execute([$setup_id, 'front']);
$data['tires_front'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);
$stmt_tires->execute([$setup_id, 'rear']);
$data['tires_rear'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);


// --- Handle Form SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_setup'])) {
    $pdo->beginTransaction();
    try {
        // ... (Baseline and other top-level save logic) ...

        // Correctly and explicitly save each section
        $sections_to_save = [
            'front_suspension' => ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims', 'notes'],
            // Add all your other sections here just like this
            'electronics'      => ['battery_brand', 'esc_brand', 'motor_brand', 'radio_brand', 'servo_brand', 'battery', 'battery_c_rating', 'capacity', 'model', 'esc_model', 'motor_kv_constant', 'motor_model', 'motor_timing', 'motor_wind', 'radio_model', 'receiver_model', 'servo_model', 'transponder_id', 'charging_notes']
        ];

        foreach ($sections_to_save as $table_name => $fields) {
            $post_data = $_POST[$table_name] ?? [];
            $set_clause = implode(' = ?, ', $fields) . ' = ?';
            $sql = "UPDATE $table_name SET $set_clause WHERE setup_id = ?";
            
            $params = [];
            foreach($fields as $field) { $params[] = $post_data[$field] ?? null; }
            $params[] = $setup_id;
            
            $stmt_save = $pdo->prepare($sql);
            $stmt_save->execute($params);
        }

        // ... (Add your separate logic for Tires here as it has a 'position' column) ...

        $pdo->commit();
        header("Location: setup_form.php?setup_id=" . $setup_id . "&success=1");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: setup_form.php?setup_id=" . $setup_id . "&error=1&msg=" . urlencode($e->getMessage()));
        exit;
    }
}


// Helper function to render a form field (either text or dropdown)
function render_field($field_name, $input_name, $saved_value, $options_by_category) {
    $label = ucwords(str_replace('_', ' ', $field_name));

    // If a category exists for this field, render a dropdown
    if (isset($options_by_category[$field_name])) {
        $options = $options_by_category[$field_name];
        echo '<div class="col-md-4 mb-3"><label class="form-label">' . $label . '</label><select class="form-select" name="' . $input_name . '">';
        echo '<option value="">-- Select --</option>';
        foreach ($options as $opt) {
            $selected = ($opt == $saved_value) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($opt) . '"' . $selected . '>' . htmlspecialchars($opt) . '</option>';
        }
        if (!empty($saved_value) && !in_array($saved_value, $options)) {
            echo '<option value="' . htmlspecialchars($saved_value) . '" selected>CUSTOM: ' . htmlspecialchars($saved_value) . '</option>';
        }
        echo '</select></div>';
    } else { // Otherwise, render a text input
        echo '<div class="col-md-4 mb-3"><label class="form-label">' . $label . '</label><input type="text" class="form-control" name="' . $input_name . '" value="' . htmlspecialchars($saved_value ?? '') . '"></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Setup: <?php echo htmlspecialchars($setup['name']); ?></h1>
    
    <?php if(isset($_GET['success'])) echo '<div class="alert alert-success">Setup saved successfully!</div>'; ?>
    <?php if(isset($_GET['error'])) echo '<div class="alert alert-danger">An error occurred while saving. Please check your data.</div>'; ?>
    <?php if(isset($_GET['msg'])) echo '<div class="alert alert-danger">'.htmlspecialchars($_GET['msg']).'</div>'; ?>

    <form method="POST">
        <h3>Front Suspension</h3>
        <div class="row">
        <?php
            $fields = ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims'];
            foreach ($fields as $field) {
                render_field($field, 'front_suspension[' . $field . ']', $data['front_suspension'][$field] ?? null, $options_by_category);
            }
        ?>
        </div>

        <h3>Rear Suspension</h3>
        <div class="row">
        <?php
            $fields = ['axle_height', 'centre_pivot_ball', 'centre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_bands_lr'];
            foreach ($fields as $field) {
                render_field($field, 'rear_suspension[' . $field . ']', $data['rear_suspension'][$field] ?? null, $options_by_category);
            }
        ?>
        </div>

        <h3>Front Tires</h3>
        <div class="row">
        <?php
            $fields = ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue'];
            foreach ($fields as $field) {
                render_field($field, 'tires_front[' . $field . ']', $data['tires_front'][$field] ?? null, $options_by_category);
            }
        ?>
        </div>

        <h3>Rear Tires</h3>
        <div class="row">
        <?php
            $fields = ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue'];
            foreach ($fields as $field) {
                render_field($field, 'tires_rear[' . $field . ']', $data['tires_rear'][$field] ?? null, $options_by_category);
            }
        ?>
        </div>
        
        <h3>Drivetrain</h3>
        <div class="row">
        <?php
            $fields = ['axle_type', 'drive_ratio', 'gear_pitch', 'rollout', 'spur'];
            foreach ($fields as $field) {
                render_field($field, 'drivetrain[' . $field . ']', $data['drivetrain'][$field] ?? null, $options_by_category);
            }
        ?>
        </div>

        <h3>Body and Chassis</h3>
        <div class="row">
        <?php
            $fields = ['battery_position', 'body', 'body_mounting', 'chassis', 'electronics_layout', 'motor_position', 'motor_shims', 'rear_wing', 'screw_turn_buckles', 'servo_position', 'weight_balance_fr', 'weight_total', 'weights'];
            foreach ($fields as $field) {
                render_field($field, 'body_chassis[' . $field . ']', $data['body_chassis'][$field] ?? null, $options_by_category);
            }
        ?>
        </div>

        <h3>Electronics</h3>
        <div class="row">
        <?php
            $fields = ['battery_brand', 'esc_brand', 'motor_brand', 'radio_brand', 'servo_brand', 'battery', 'battery_c_rating', 'capacity', 'model', 'esc_model', 'motor_kv_constant', 'motor_model', 'motor_timing', 'motor_wind', 'radio_model', 'receiver_model', 'servo_model', 'transponder_id'];
            foreach ($fields as $field) {
                render_field($field, 'electronics[' . $field . ']', $data['electronics'][$field] ?? null, $options_by_category);
            }
        ?>
        </div>

        <h3>ESC Settings</h3>
        <div class="row">
        <?php
            $fields = ['boost_activation', 'boost_rpm_end', 'boost_rpm_start', 'boost_ramp', 'boost_timing', 'brake_curve', 'brake_drag', 'brake_frequency', 'brake_initial_strength', 'race_mode', 'throttle_curve', 'throttle_frequency', 'throttle_initial_strength', 'throttle_neutral_range', 'throttle_strength', 'turbo_activation_method', 'turbo_delay', 'turbo_ramp', 'turbo_timing'];
            foreach ($fields as $field) {
                render_field($field, 'esc_settings[' . $field . ']', $data['esc_settings'][$field] ?? null, $options_by_category);
            }
        ?>
        </div>

        <div class="row">
            <div class="col-12 mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="front_suspension[notes]"><?php echo htmlspecialchars($data['front_suspension']['notes'] ?? ''); ?></textarea>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Comments</label>
                <textarea class="form-control" name="comments[comment]"><?php echo htmlspecialchars($data['comments']['comment'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <hr>
        <button type="submit" name="save_setup" class="btn btn-primary">Save All Changes</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>