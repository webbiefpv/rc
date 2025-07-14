<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// --- 1. GET THE SETUP AND VERIFY OWNERSHIP ---
if (!isset($_GET['setup_id'])) {
    header('Location: index.php');
    exit;
}
$setup_id = intval($_GET['setup_id']);
$stmt_setup = $pdo->prepare("SELECT s.*, m.user_id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ?");
$stmt_setup->execute([$setup_id]);
$setup = $stmt_setup->fetch(PDO::FETCH_ASSOC);
if (!$setup || $setup['user_id'] != $user_id) { header('Location: index.php'); exit; }

// --- 2. HANDLE FORM SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_setup'])) {
    $pdo->beginTransaction();
    try {
        // --- Handle Sharing Status ---
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        $share_token = $setup['share_token'];
        if ($is_public && empty($share_token)) {
            $share_token = bin2hex(random_bytes(16));
        } elseif (!$is_public) {
            $share_token = null;
        }
        $stmt_share = $pdo->prepare("UPDATE setups SET is_public = ?, share_token = ? WHERE id = ?");
        $stmt_share->execute([$is_public, $share_token, $setup_id]);
        
        // Refresh the local $setup variable to show changes immediately
        $setup['is_public'] = $is_public;
        $setup['share_token'] = $share_token;

        // --- Handle Baseline Setting ---
        $is_baseline = isset($_POST['is_baseline']) ? 1 : 0;
        $stmt_update_baseline = $pdo->prepare("UPDATE setups SET is_baseline = ? WHERE id = ?");
        $stmt_update_baseline->execute([$is_baseline, $setup_id]);
        if ($is_baseline) {
            $stmt_reset = $pdo->prepare("UPDATE setups SET is_baseline = 0 WHERE model_id = ? AND id != ?");
            $stmt_reset->execute([$setup['model_id'], $setup_id]);
        }
        
        // --- Save all other standard fields ---
        $sections_to_save = [
            'front_suspension' => ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims', 'notes'],
            'rear_suspension'  => ['axle_height', 'centre_pivot_ball', 'centre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_springs', 'side_bands_lr', 'notes'],
            'drivetrain'       => ['axle_type', 'drive_ratio', 'gear_pitch', 'rollout', 'spur', 'pinion'],
            'body_chassis'     => ['battery_position', 'body', 'body_mounting', 'chassis', 'electronics_layout', 'motor_position', 'motor_shims', 'rear_wing', 'screw_turn_buckles', 'servo_position', 'weight_balance_fr', 'weight_total', 'weights'],
            'electronics'      => ['battery_brand', 'esc_brand', 'motor_brand', 'radio_brand', 'servo_brand', 'battery', 'battery_c_rating', 'capacity', 'model', 'esc_model', 'motor_kv_constant', 'motor_model', 'motor_timing', 'motor_wind', 'radio_model', 'receiver_model', 'servo_model', 'transponder_id', 'charging_notes'],
            'esc_settings'     => ['boost_activation', 'boost_rpm_end', 'boost_rpm_start', 'boost_ramp', 'boost_timing', 'brake_curve', 'brake_drag', 'brake_frequency', 'brake_initial_strength', 'race_mode', 'throttle_curve', 'throttle_frequency', 'throttle_initial_strength', 'throttle_neutral_range', 'throttle_strength', 'turbo_activation_method', 'turbo_delay', 'turbo_ramp', 'turbo_timing'],
            'comments'         => ['comment']
        ];

        foreach ($sections_to_save as $table_name => $fields) {
            $post_data = $_POST[$table_name] ?? [];
            $params = [];
            foreach($fields as $field) { $params[$field] = $post_data[$field] ?? null; }

            $stmt_check_exists = $pdo->prepare("SELECT setup_id FROM $table_name WHERE setup_id = ?");
            $stmt_check_exists->execute([$setup_id]);
            
            if ($stmt_check_exists->fetch()) { // UPDATE
                $set_clause = implode(' = ?, ', $fields) . ' = ?';
                $sql = "UPDATE $table_name SET $set_clause WHERE setup_id = ?";
                $execute_params = array_values($params);
                $execute_params[] = $setup_id;
            } else { // INSERT
                $cols = implode(', ', $fields);
                $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                $sql = "INSERT INTO $table_name (setup_id, $cols) VALUES (?, $placeholders)";
                $execute_params = array_merge([$setup_id], array_values($params));
            }
            $stmt_save = $pdo->prepare($sql);
            $stmt_save->execute($execute_params);
        }

        // Special handling for TIRES
        $tire_fields = ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue'];
        
        $front_tires_post = $_POST['tires_front'] ?? [];
        $front_params = [];
        foreach($tire_fields as $field) { $front_params[] = $front_tires_post[$field] ?? null; }
        $stmt_check_front_tires = $pdo->prepare("SELECT setup_id FROM tires WHERE setup_id = ? AND position = 'front'");
        $stmt_check_front_tires->execute([$setup_id]);
        if ($stmt_check_front_tires->fetch()) {
            $stmt_front = $pdo->prepare("UPDATE tires SET tire_brand=?, tire_compound=?, wheel_brand_type=?, tire_additive=?, tire_additive_area=?, tire_additive_time=?, tire_diameter=?, tire_side_wall_glue=? WHERE setup_id = ? AND position = 'front'");
            $front_params[] = $setup_id;
            $stmt_front->execute($front_params);
        } else {
            $stmt_front = $pdo->prepare("INSERT INTO tires (setup_id, position, tire_brand, tire_compound, wheel_brand_type, tire_additive, tire_additive_area, tire_additive_time, tire_diameter, tire_side_wall_glue) VALUES (?, 'front', ?, ?, ?, ?, ?, ?, ?, ?)");
            array_unshift($front_params, $setup_id);
            $stmt_front->execute($front_params);
        }
        
        $rear_tires_post = $_POST['tires_rear'] ?? [];
        $rear_params = [];
        foreach($tire_fields as $field) { $rear_params[] = $rear_tires_post[$field] ?? null; }
        $stmt_check_rear_tires = $pdo->prepare("SELECT setup_id FROM tires WHERE setup_id = ? AND position = 'rear'");
        $stmt_check_rear_tires->execute([$setup_id]);
        if ($stmt_check_rear_tires->fetch()) {
            $stmt_rear = $pdo->prepare("UPDATE tires SET tire_brand=?, tire_compound=?, wheel_brand_type=?, tire_additive=?, tire_additive_area=?, tire_additive_time=?, tire_diameter=?, tire_side_wall_glue=? WHERE setup_id = ? AND position = 'rear'");
            $rear_params[] = $setup_id;
            $stmt_rear->execute($rear_params);
        } else {
            $stmt_rear = $pdo->prepare("INSERT INTO tires (setup_id, position, tire_brand, tire_compound, wheel_brand_type, tire_additive, tire_additive_area, tire_additive_time, tire_diameter, tire_side_wall_glue) VALUES (?, 'rear', ?, ?, ?, ?, ?, ?, ?, ?)");
            array_unshift($rear_params, $setup_id);
            $stmt_rear->execute($rear_params);
        }

        $pdo->commit();
        $message = '<div class="alert alert-success">Setup saved successfully!</div>';

    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">An error occurred while saving: ' . $e->getMessage() . '</div>';
    }
}

// --- 3. FETCH ALL DATA FOR THE FORM (after potential save) ---
$stmt_options = $pdo->prepare("SELECT option_category, option_value FROM user_options WHERE user_id = ? ORDER BY option_value");
$stmt_options->execute([$user_id]);
$all_options_raw = $stmt_options->fetchAll(PDO::FETCH_ASSOC);
$options_by_category = [];
foreach ($all_options_raw as $option) { $options_by_category[$option['option_category']][] = $option['option_value']; }

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

// --- 4. HELPER FUNCTION TO RENDER FORM FIELDS ---
function render_field($field_name, $input_name, $saved_value, $options_by_category, $extra_attrs = '') {
    $label = ucwords(str_replace('_', ' ', $field_name));
    $id = str_replace(['[', ']'], ['_', ''], $input_name);
    
    if (isset($options_by_category[$field_name])) {
        $options = $options_by_category[$field_name];
        echo '<div class="col-md-4 mb-3"><label for="'.$id.'" class="form-label">' . $label . '</label><select class="form-select" id="'.$id.'" name="' . $input_name . '" '.$extra_attrs.'>';
        echo '<option value="">-- Select --</option>';
        foreach ($options as $opt) {
            $selected = ($opt == $saved_value) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($opt) . '"' . $selected . '>' . htmlspecialchars($opt) . '</option>';
        }
        if (!empty($saved_value) && !in_array($saved_value, $options)) {
            echo '<option value="' . htmlspecialchars($saved_value) . '" selected>CUSTOM: ' . htmlspecialchars($saved_value) . '</option>';
        }
        echo '</select></div>';
    } else {
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
    <?php echo $message; ?>
    
    <form method="POST">
        <div class="accordion" id="setupFormAccordion">
            <?php
            $form_structure = [
                'Front Suspension' => ['section_key' => 'front_suspension', 'fields' => ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims', 'notes']],
                'Rear Suspension'  => ['section_key' => 'rear_suspension', 'fields' => ['axle_height', 'centre_pivot_ball', 'centre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_springs', 'side_bands_lr', 'notes']],
                'Front Tires'      => ['section_key' => 'tires_front', 'fields' => ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue']],
                'Rear Tires'       => ['section_key' => 'tires_rear', 'fields' => ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue']],
                'Drivetrain'       => ['section_key' => 'drivetrain', 'fields' => ['axle_type', 'drive_ratio', 'gear_pitch', 'rollout', 'spur', 'pinion']],
                'Body and Chassis' => ['section_key' => 'body_chassis', 'fields' => ['battery_position', 'body', 'body_mounting', 'chassis', 'electronics_layout', 'motor_position', 'motor_shims', 'rear_wing', 'screw_turn_buckles', 'servo_position', 'weight_balance_fr', 'weight_total', 'weights']],
                'Electronics'      => ['section_key' => 'electronics', 'fields' => ['battery_brand', 'esc_brand', 'motor_brand', 'radio_brand', 'servo_brand', 'battery', 'battery_c_rating', 'capacity', 'model', 'esc_model', 'motor_kv_constant', 'motor_model', 'motor_timing', 'motor_wind', 'radio_model', 'receiver_model', 'servo_model', 'transponder_id', 'charging_notes']],
                'ESC Settings'     => ['section_key' => 'esc_settings', 'fields' => ['boost_activation', 'boost_rpm_end', 'boost_rpm_start', 'boost_ramp', 'boost_timing', 'brake_curve', 'brake_drag', 'brake_frequency', 'brake_initial_strength', 'race_mode', 'throttle_curve', 'throttle_frequency', 'throttle_initial_strength', 'throttle_neutral_range', 'throttle_strength', 'turbo_activation_method', 'turbo_delay', 'turbo_ramp', 'turbo_timing']],
                'Comments'         => ['section_key' => 'comments', 'fields' => ['comment']]
            ];
            
            foreach ($form_structure as $title => $section_details):
                $section_key = $section_details['section_key'];
                $section_id = preg_replace('/[^a-zA-Z0-9]/', '', $section_key);
            ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-<?php echo $section_id; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $section_id; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $section_id; ?>">
                            <strong><?php echo $title; ?></strong>
                        </button>
                    </h2>
                    <div id="collapse-<?php echo $section_id; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $section_id; ?>" data-bs-parent="#setupFormAccordion">
                        <div class="accordion-body">
                            <div class="row">
                            <?php
                            foreach ($section_details['fields'] as $field) {
                                if ($field === 'notes' || $field === 'comment' || $field === 'charging_notes') {
                                    echo '<div class="col-12 mb-3"><label class="form-label">' . ucwords(str_replace('_', ' ', $field)) . '</label><textarea class="form-control" name="' . $section_key . '[' . $field . ']">' . htmlspecialchars($data[$section_key][$field] ?? '') . '</textarea></div>';
                                } else {
                                    $extra_attrs = '';
                                    if ($field === 'rollout' || $field === 'drive_ratio') $extra_attrs = 'readonly';
                                    if (in_array($field, ['tire_diameter', 'spur', 'pinion'])) $extra_attrs = 'oninput="calculateRollout()"';
                                    render_field($field, $section_key . '[' . $field . ']', $data[$section_key][$field] ?? null, $options_by_category, $extra_attrs);
                                }
                            }
                            ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr>
        
        <div class="card mb-4">
            <div class="card-header"><h5>Sharing & Status</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_public_checkbox" name="is_public" value="1" <?php echo ($setup['is_public'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_public_checkbox">Make this setup sheet public</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_baseline_checkbox" name="is_baseline" value="1" <?php echo ($setup['is_baseline'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_baseline_checkbox">Set as Baseline Setup</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?php if ($setup['is_public'] && !empty($setup['share_token'])): ?>
                            <label for="share_link" class="form-label">Your public share link:</label>
                            <div class="input-group">
                                <?php
                                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                                    $host = $_SERVER['HTTP_HOST'];
                                    $path = dirname($_SERVER['PHP_SELF']);
                                    $share_url = rtrim($protocol . $host . $path, '/') . '/share.php?token=' . $setup['share_token'];
                                ?>
                                <input type="text" id="share_link" class="form-control" value="<?php echo htmlspecialchars($share_url); ?>" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyShareLink()">Copy</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" name="save_setup" class="btn btn-primary">Save All Changes</button>
    </form>
</div>

<script>
function copyShareLink() {
    const linkInput = document.getElementById('share_link');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    document.execCommand('copy');
    alert('Share link copied to clipboard!');
}

function calculateRollout() {
    const tireDiameterInput = document.getElementById('tires_rear_tire_diameter');
    const spurInput = document.getElementById('drivetrain_spur');
    const pinionInput = document.getElementById('drivetrain_pinion');
    const driveRatioInput = document.getElementById('drivetrain_drive_ratio');
    const rolloutInput = document.getElementById('drivetrain_rollout');
    if (!tireDiameterInput || !spurInput || !pinionInput || !driveRatioInput || !rolloutInput) return;
    const tireDiameter = parseFloat(tireDiameterInput.value) || 0;
    const spur = parseFloat(spurInput.value) || 0;
    const pinion = parseFloat(pinionInput.value) || 0;
    if (spur > 0 && pinion > 0) {
        const driveRatio = spur / pinion;
        driveRatioInput.value = driveRatio.toFixed(2);
        if (tireDiameter > 0) {
            const rollout = (tireDiameter * Math.PI) / driveRatio;
            rolloutInput.value = rollout.toFixed(2);
        } else { rolloutInput.value = ''; }
    } else {
        driveRatioInput.value = '';
        rolloutInput.value = '';
    }
}
document.addEventListener('DOMContentLoaded', calculateRollout);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>