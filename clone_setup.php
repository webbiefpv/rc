<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clone_setup'], $_POST['original_setup_id'])) {
    $original_setup_id = (int)$_POST['original_setup_id'];
    $model_id_for_redirect = isset($_POST['model_id']) ? (int)$_POST['model_id'] : null; // For potential redirect back to setups list

    // 1. Verify the original setup belongs to the user and get its data
    $stmt_orig_setup = $pdo->prepare("SELECT s.* FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ? AND m.user_id = ?");
    $stmt_orig_setup->execute([$original_setup_id, $user_id]);
    $original_setup_data = $stmt_orig_setup->fetch(PDO::FETCH_ASSOC);

    if (!$original_setup_data) {
        // Setup not found or doesn't belong to the user
        // Redirect with an error message or to a generic error page
        header('Location: models.php?error=clonefailed'); // Adjust as needed
        exit;
    }

    // Start a transaction
    $pdo->beginTransaction();

    try {
        // 2. Create the new setup entry in the 'setups' table
        $new_setup_name = "Copy of " . $original_setup_data['name'];
        // Ensure name isn't too long if you have a length limit
        if (strlen($new_setup_name) > 255) { // Assuming a common VARCHAR(255) limit
            $new_setup_name = substr($new_setup_name, 0, 250) . "...";
        }

        $stmt_new_setup = $pdo->prepare("INSERT INTO setups (model_id, name, created_at) VALUES (?, ?, NOW())");
        $stmt_new_setup->execute([$original_setup_data['model_id'], $new_setup_name]);
        $new_setup_id = $pdo->lastInsertId();

        // 3. Define tables and their columns to clone
        // Exclude 'id' (auto-increment) and 'setup_id' (will be the new one)
        // The fields listed here must exist in your tables.
        $tables_to_clone = [
            // Table name => [array of columns to copy]
            'front_suspension' => ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims', 'notes'],
            'rear_suspension'  => ['axle_height', 'centre_pivot_ball', 'centre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_bands_lr', 'notes'],
            'drivetrain'       => ['axle_type', 'drive_ratio', 'gear_pitch', 'rollout', 'spur'],
            'body_chassis'     => ['battery_position', 'body', 'body_mounting', 'chassis', 'electronics_layout', 'motor_position', 'motor_shims', 'rear_wing', 'screw_turn_buckles', 'servo_position', 'weight_balance_fr', 'weight_total', 'weights'],
            'electronics'      => ['battery', 'battery_c_rating', 'battery_brand', 'capacity', 'model', 'charging_notes', 'esc_brand', 'esc_model', 'motor_kv_constant', 'motor_brand', 'motor_model', 'motor_timing', 'motor_wind', 'radio_brand', 'radio_model', 'receiver_model', 'servo_brand', 'servo_model', 'transponder_id'],
            'esc_settings'     => ['boost_activation', 'boost_rpm_end', 'boost_rpm_start', 'boost_ramp', 'boost_timing', 'brake_curve', 'brake_drag', 'brake_frequency', 'brake_initial_strength', 'race_mode', 'throttle_curve', 'throttle_frequency', 'throttle_initial_strength', 'throttle_neutral_range', 'throttle_strength', 'turbo_activation_method', 'turbo_delay', 'turbo_ramp', 'turbo_timing'],
            'comments'         => ['comment']
            // Note: 'tires' table needs special handling due to 'position'
        ];

        foreach ($tables_to_clone as $table_name => $columns) {
            $stmt_select_orig = $pdo->prepare("SELECT * FROM $table_name WHERE setup_id = ?");
            $stmt_select_orig->execute([$original_setup_id]);
            $original_rows = $stmt_select_orig->fetchAll(PDO::FETCH_ASSOC);

            if ($original_rows) {
                $cols_string = implode(', ', $columns);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));

                $stmt_insert_clone = $pdo->prepare("INSERT INTO $table_name (setup_id, $cols_string) VALUES (?, $placeholders)");

                foreach ($original_rows as $original_row) {
                    $values_to_insert = [];
                    foreach ($columns as $column) {
                        $values_to_insert[] = $original_row[$column];
                    }
                    $stmt_insert_clone->execute([$new_setup_id, ...$values_to_insert]);
                }
            }
        }

        // 4. Special handling for 'tires' table (assuming it might have multiple rows per setup_id: front and rear)
        $tire_columns = ['position', 'tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue'];
        $stmt_select_orig_tires = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ?");
        $stmt_select_orig_tires->execute([$original_setup_id]);
        $original_tires = $stmt_select_orig_tires->fetchAll(PDO::FETCH_ASSOC);

        if ($original_tires) {
            $tire_cols_string = implode(', ', $tire_columns);
            $tire_placeholders = implode(', ', array_fill(0, count($tire_columns), '?'));
            $stmt_insert_clone_tires = $pdo->prepare("INSERT INTO tires (setup_id, $tire_cols_string) VALUES (?, $tire_placeholders)");

            foreach ($original_tires as $original_tire_row) {
                $tire_values_to_insert = [];
                foreach ($tire_columns as $column) {
                    $tire_values_to_insert[] = $original_tire_row[$column];
                }
                $stmt_insert_clone_tires->execute([$new_setup_id, ...$tire_values_to_insert]);
            }
        }

        // 5. Commit the transaction
        $pdo->commit();

        // 6. Redirect to the edit page for the new clone
        header("Location: setup_form.php?setup_id=" . $new_setup_id . "&cloned=true");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        // Handle error - log it, display a generic error message, redirect
        // For debugging, you can echo $e->getMessage();
        // In production, redirect to an error page or back with an error flag
        error_log("Cloning failed: " . $e->getMessage());
        if ($model_id_for_redirect) {
            header("Location: setups.php?model_id=" . $model_id_for_redirect . "&error=clonefailed_exception");
        } else {
            header('Location: models.php?error=clonefailed_exception');
        }
        exit;
    }
} else {
    // Not a POST request or missing parameters, redirect
    header('Location: models.php');
    exit;
}
?>