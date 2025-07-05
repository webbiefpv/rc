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
if (!$setup || $setup['user_id'] != $user_id) {
    header('Location: index.php'); exit;
}

// --- 2. FETCH ALL DATA FOR THE FORM ---
// Fetch standard field data
$data = [];
$tables = ['front_suspension', 'rear_suspension', 'drivetrain', 'body_chassis', 'electronics', 'esc_settings', 'comments'];
foreach ($tables as $table) {
    $stmt_data = $pdo->prepare("SELECT * FROM $table WHERE setup_id = ?");
    $stmt_data->execute([$setup_id]);
    $data[$table] = $stmt_data->fetch(PDO::FETCH_ASSOC);
}
// Fetch tires data
$stmt_tires = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = ?");
$stmt_tires->execute([$setup_id, 'front']);
$data['tires_front'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);
$stmt_tires->execute([$setup_id, 'rear']);
$data['tires_rear'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);

// Fetch custom field data
$stmt_custom_fields = $pdo->prepare("SELECT id, section, field_label, field_value FROM custom_setup_fields WHERE setup_id = ?");
$stmt_custom_fields->execute([$setup_id]);
$custom_fields_raw = $stmt_custom_fields->fetchAll(PDO::FETCH_ASSOC);
$custom_fields_by_section = [];
foreach ($custom_fields_raw as $field) {
    $custom_fields_by_section[$field['section']][] = $field;
}

// Fetch predefined options for dropdowns
$stmt_options = $pdo->prepare("SELECT option_category, option_value FROM user_options WHERE user_id = ? ORDER BY option_value");
$stmt_options->execute([$user_id]);
$all_options_raw = $stmt_options->fetchAll(PDO::FETCH_ASSOC);
$options_by_category = [];
foreach ($all_options_raw as $option) { $options_by_category[$option['option_category']][] = $option['option_value']; }


// --- 3. HANDLE FORM SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_setup'])) {
    $pdo->beginTransaction();
    try {
        // --- Save Standard Fields (using the simple, explicit method for reliability) ---
        // You would place the corrected save logic for each standard section here.
        // For example:
        $front_susp_data = $_POST['front_suspension'] ?? [];
        $stmt_fs = $pdo->prepare("UPDATE front_suspension SET ackermann = ?, arms = ?, bumpsteer = ? WHERE setup_id = ?"); // Abbreviated for example
        // $stmt_fs->execute([...]);
        
        // --- Save Custom Fields ---
        $stmt_delete_custom = $pdo->prepare("DELETE FROM custom_setup_fields WHERE setup_id = ?");
        $stmt_delete_custom->execute([$setup_id]);

        if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            $stmt_insert_custom = $pdo->prepare("INSERT INTO custom_setup_fields (user_id, setup_id, section, field_label, field_value) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['custom_fields'] as $section => $fields) {
                if (is_array($fields)) {
                    foreach ($fields as $field) {
                        $label = trim($field['label']);
                        $value = trim($field['value']);
                        if (!empty($label)) {
                            $stmt_insert_custom->execute([$user_id, $setup_id, $section, $label, $value]);
                        }
                    }
                }
            }
        }

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
    } else {
        echo '<div class="col-md-4 mb-3"><label class="form-label">' . $label . '</label><input type="text" class="form-control" name="' . $input_name . '" value="' . htmlspecialchars($saved_value ?? '') . '"></div>';
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
    <?php if(isset($_GET['success'])) echo '<div class="alert alert-success">Setup saved successfully!</div>'; ?>

    <form method="POST">
        <?php
        $form_structure = [
            'Front Suspension' => ['section_key' => 'front_suspension', 'fields' => ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims']],
            'Rear Suspension'  => ['section_key' => 'rear_suspension', 'fields' => ['axle_height', 'centre_pivot_ball', 'centre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_bands_lr']],
            'Front Tires'      => ['section_key' => 'tires_front', 'fields' => ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue']],
            'Rear Tires'       => ['section_key' => 'tires_rear', 'fields' => ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue']],
            'Electronics'      => ['section_key' => 'electronics', 'fields' => ['battery_brand', 'esc_brand', 'motor_brand', 'radio_brand', 'servo_brand', 'battery', 'battery_c_rating', 'capacity', 'model', 'esc_model', 'motor_kv_constant', 'motor_model', 'motor_timing', 'motor_wind', 'radio_model', 'receiver_model', 'servo_model', 'transponder_id']]
            // Add other sections here...
        ];
        
        foreach ($form_structure as $title => $section_details):
            $section_key = $section_details['section_key'];
        ?>
            <h3><?php echo $title; ?></h3>
            <div class="row">
                <?php
                foreach ($section_details['fields'] as $field) {
                    render_field($field, $section_key . '[' . $field . ']', $data[$section_key][$field] ?? null, $options_by_category);
                }
                ?>
            </div>

            <div id="custom-fields-<?php echo $section_key; ?>" class="mt-3">
                <?php if (!empty($custom_fields_by_section[$section_key])): ?>
                    <?php foreach ($custom_fields_by_section[$section_key] as $index => $custom_field): ?>
                        <div class="row custom-field-row mb-2">
                            <div class="col-md-5">
                                <input type="text" class="form-control" placeholder="Custom Field Label" name="custom_fields[<?php echo $section_key; ?>][<?php echo $custom_field['id']; ?>][label]" value="<?php echo htmlspecialchars($custom_field['field_label']); ?>">
                            </div>
                            <div class="col-md-5">
                                <input type="text" class="form-control" placeholder="Value" name="custom_fields[<?php echo $section_key; ?>][<?php echo $custom_field['id']; ?>][value]" value="<?php echo htmlspecialchars($custom_field['field_value']); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-danger w-100" onclick="this.closest('.custom-field-row').remove();">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="mt-2 mb-4">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="addCustomField('<?php echo $section_key; ?>')">
                    Add Custom Field to <?php echo $title; ?>
                </button>
            </div>
        <?php endforeach; ?>
        
        <hr>
        <button type="submit" name="save_setup" class="btn btn-primary">Save All Changes</button>
    </form>
</div>

<script>
function addCustomField(sectionKey) {
    const container = document.getElementById('custom-fields-' + sectionKey);
    if (!container) return;
    const newIndex = 'new_' + Date.now();
    const row = document.createElement('div');
    row.className = 'row custom-field-row mb-2';
    row.innerHTML = `
        <div class="col-md-5"><input type="text" class="form-control" placeholder="Custom Field Label" name="custom_fields[${sectionKey}][${newIndex}][label]"></div>
        <div class="col-md-5"><input type="text" class="form-control" placeholder="Value" name="custom_fields[${sectionKey}][${newIndex}][value]"></div>
        <div class="col-md-2"><button type="button" class="btn btn-danger w-100" onclick="this.closest('.custom-field-row').remove();">Remove</button></div>
    `;
    container.appendChild(row);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>