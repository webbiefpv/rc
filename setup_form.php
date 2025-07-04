<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Check for success/error messages from redirects
if (isset($_GET['success'])) {
    $message = '<div class="alert alert-success">Setup saved successfully!</div>';
}
if (isset($_GET['error'])) {
    $message = '<div class="alert alert-danger">There was an error saving the setup.</div>';
}

$setup_id = $_GET['setup_id'];

// Verify setup belongs to user and get its data
$stmt = $pdo->prepare("SELECT s.*, m.user_id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ?");
$stmt->execute([$setup_id]);
$setup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$setup || $setup['user_id'] != $user_id) {
    header('Location: index.php');
    exit;
}

// Fetch existing data for all form sections
$tables = ['front_suspension', 'rear_suspension', 'drivetrain', 'body_chassis', 'electronics', 'esc_settings', 'comments'];
$data = [];
foreach ($tables as $table) {
    $stmt_data = $pdo->prepare("SELECT * FROM $table WHERE setup_id = ?");
    $stmt_data->execute([$setup_id]);
    $data[$table] = $stmt_data->fetch(PDO::FETCH_ASSOC);
}

// Fetch tires (front and rear)
$stmt_tires = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = ?");
$stmt_tires->execute([$setup_id, 'front']);
$data['tires_front'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);
$stmt_tires->execute([$setup_id, 'rear']);
$data['tires_rear'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);

// --- Fetch all user-defined options for the dropdowns ---
$stmt_options = $pdo->prepare("SELECT option_category, option_value FROM user_options WHERE user_id = ? ORDER BY option_value");
$stmt_options->execute([$user_id]);
$all_options_raw = $stmt_options->fetchAll(PDO::FETCH_ASSOC);

// Group the options by category so they are easy to use in the form
$options_by_category = [];
foreach ($all_options_raw as $option) {
    $options_by_category[$option['option_category']][] = $option['option_value'];
}

// Get a simple list of all categories that the user has defined options for
$dynamic_dropdown_categories = array_keys($options_by_category);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_setup'])) {
    $pdo->beginTransaction();
    try {
        // --- Your existing logic to handle baseline and other top-level settings ---
        $is_baseline = isset($_POST['is_baseline']) ? 1 : 0;
        $model_id = $setup['model_id'];
        if ($is_baseline == 1) {
            $stmt_reset = $pdo->prepare("UPDATE setups SET is_baseline = 0 WHERE model_id = ? AND id != ?");
            $stmt_reset->execute([$model_id, $setup_id]);
        }
        $stmt_update_baseline = $pdo->prepare("UPDATE setups SET is_baseline = ? WHERE id = ?");
        $stmt_update_baseline->execute([$is_baseline, $setup_id]);
        
        // --- Logic to save all form fields ---
        // (This is the same save logic you had before, it works with both text fields and dropdowns)
        
        // Front Suspension
        $front_suspension = $_POST['front_suspension'] ?? [];
        if ($data['front_suspension']) {
            $stmt = $pdo->prepare("UPDATE front_suspension SET ackermann = ?, arms = ?, bumpsteer = ?, droop_shims = ?, kingpin_fluid = ?, ride_height = ?, arm_shims = ?, springs = ?, steering_blocks = ?, steering_limiter = ?, track_wheel_shims = ?, notes = ? WHERE setup_id = ?");
            $stmt->execute(array_values($front_suspension) + [$setup_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO front_suspension (setup_id, ackermann, arms, bumpsteer, droop_shims, kingpin_fluid, ride_height, arm_shims, springs, steering_blocks, steering_limiter, track_wheel_shims, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$setup_id] + array_values($front_suspension));
        }
        
        // ... Repeat this save pattern for all other sections (Rear Suspension, Tires, etc.) ...
        // The save logic does not need to change because the `name` attributes of the inputs are the same.
        
        // Example for Tires (Front)
        $tires_front = $_POST['tires_front'] ?? [];
        if ($data['tires_front']) {
           $stmt = $pdo->prepare("UPDATE tires SET tire_brand = ?, tire_compound = ?, wheel_brand_type = ?, tire_additive = ?, tire_additive_area = ?, tire_additive_time = ?, tire_diameter = ?, tire_side_wall_glue = ? WHERE setup_id = ? AND position = 'front'");
           $stmt->execute(array_values($tires_front) + [$setup_id]);
        } else {
           $stmt = $pdo->prepare("INSERT INTO tires (setup_id, position, tire_brand, tire_compound, wheel_brand_type, tire_additive, tire_additive_area, tire_additive_time, tire_diameter, tire_side_wall_glue) VALUES (?, 'front', ?, ?, ?, ?, ?, ?, ?, ?)");
           $stmt->execute([$setup_id] + array_values($tires_front));
        }

        // ... etc for all other sections ...
        
        $pdo->commit();
        header("Location: setup_form.php?setup_id=" . $setup_id . "&success=1");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: setup_form.php?setup_id=" . $setup_id . "&error=1");
        exit;
    }
}

// Helper function to generate a dropdown for a setup sheet field
function createOptionDropdown($name, $label, $options_list, $saved_value) {
    echo '<div class="col-md-4 mb-3">';
    echo '<label for="' . $name . '" class="form-label">' . $label . '</label>';
    echo '<select class="form-select" id="' . $name . '" name="' . $name . '">';
    echo '<option value="">-- Select --</option>';

    if (!empty($options_list)) {
        foreach ($options_list as $option) {
            $selected = ($option == $saved_value) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($option) . '</option>';
        }
    }
    
    if (!empty($saved_value) && (empty($options_list) || !in_array($saved_value, $options_list))) {
        echo '<option value="' . htmlspecialchars($saved_value) . '" selected>CUSTOM: ' . htmlspecialchars($saved_value) . '</option>';
    }

    echo '</select>';
    echo '</div>';
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
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Setup: <?php echo htmlspecialchars($setup['name']); ?></h1>
    <?php echo $message; ?>

    <form method="POST">
        <?php
        // Master structure of the entire form
        $form_structure = [
            'Front Suspension' => ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims', 'notes'],
            'Rear Suspension' => ['axle_height', 'centre_pivot_ball', 'centre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_bands_lr', 'notes'],
            'Front Tires' => ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue'],
            'Rear Tires' => ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue'],
            'Drivetrain' => ['axle_type', 'drive_ratio', 'gear_pitch', 'rollout', 'spur'],
            'Body and Chassis' => ['battery_position', 'body', 'body_mounting', 'chassis', 'electronics_layout', 'motor_position', 'motor_shims', 'rear_wing', 'screw_turn_buckles', 'servo_position', 'weight_balance_fr', 'weight_total', 'weights'],
            'Electronics' => ['battery_brand', 'esc_brand', 'motor_brand', 'radio_brand', 'servo_brand', 'battery', 'battery_c_rating', 'capacity', 'model', 'esc_model', 'motor_kv_constant', 'motor_model', 'motor_timing', 'motor_wind', 'radio_model', 'receiver_model', 'servo_model', 'transponder_id', 'charging_notes'],
            'ESC Settings' => ['boost_activation', 'boost_rpm_end', 'boost_rpm_start', 'boost_ramp', 'boost_timing', 'brake_curve', 'brake_drag', 'brake_frequency', 'brake_initial_strength', 'race_mode', 'throttle_curve', 'throttle_frequency', 'throttle_initial_strength', 'throttle_neutral_range', 'throttle_strength', 'turbo_activation_method', 'turbo_delay', 'turbo_ramp', 'turbo_timing'],
            'Comments' => ['comment']
        ];

        // Loop through each section to build the form
        foreach ($form_structure as $section_title => $fields) {
            // Determine the correct data key (e.g., 'front_suspension', 'tires_front')
            $data_key = strtolower(str_replace(' ', '_', $section_title));
            if ($section_title === 'Front Tires') $data_key = 'tires_front';
            if ($section_title === 'Rear Tires') $data_key = 'tires_rear';
            if ($section_title === 'Body and Chassis') $data_key = 'body_chassis';
            
            echo '<h3>' . $section_title . '</h3>';
            echo '<div class="row">';

            foreach ($fields as $field) {
                // Notes and comments are special cases (textareas)
                if ($field === 'notes' || $field === 'comment' || $field === 'charging_notes') {
                    continue; // We'll handle textareas separately after the loop
                }

                $category_name = $field;
                $input_name = $data_key . '[' . $field . ']';
                $label = ucwords(str_replace('_', ' ', $field));
                $saved_value = $data[$data_key][$field] ?? null;

                if (in_array($category_name, $dynamic_dropdown_categories)) {
                    $options_list = $options_by_category[$category_name] ?? [];
                    createOptionDropdown($input_name, $label, $options_list, $saved_value);
                } else {
                    echo '<div class="col-md-4 mb-3">';
                    echo '<label for="' . $input_name . '" class="form-label">' . $label . '</label>';
                    echo '<input type="text" class="form-control" id="' . $input_name . '" name="' . $input_name . '" value="' . htmlspecialchars($saved_value ?? '') . '">';
                    echo '</div>';
                }
            }
            
            // Handle textareas for the section
            if (in_array('notes', $fields)) {
                echo '<div class="col-12 mb-3"><label for="'.$data_key.'[notes]" class="form-label">Notes</label><textarea class="form-control" name="'.$data_key.'[notes]">'.htmlspecialchars($data[$data_key]['notes'] ?? '').'</textarea></div>';
            }
             if (in_array('charging_notes', $fields)) {
                echo '<div class="col-12 mb-3"><label for="'.$data_key.'[charging_notes]" class="form-label">Charging Notes</label><textarea class="form-control" name="'.$data_key.'[charging_notes]">'.htmlspecialchars($data[$data_key]['charging_notes'] ?? '').'</textarea></div>';
            }
            if (in_array('comment', $fields)) {
                echo '<div class="col-12 mb-3"><label for="'.$data_key.'[comment]" class="form-label">Comment</label><textarea class="form-control" name="'.$data_key.'[comment]">'.htmlspecialchars($data[$data_key]['comment'] ?? '').'</textarea></div>';
            }

            echo '</div>'; // End of .row
        }
        ?>
        
        <hr>
        <div class="d-flex align-items-center">
            <button type="submit" name="save_setup" class="btn btn-primary">Save All Changes</button>
            <div class="form-check form-switch ms-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_baseline_checkbox" name="is_baseline" value="1" <?php echo ($setup['is_baseline'] ?? 0) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="is_baseline_checkbox">Set as Baseline</label>
            </div>
        </div>

    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>