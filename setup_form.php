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

// --- Fetch existing custom fields for this setup ---
$stmt_custom_fields = $pdo->prepare("SELECT id, section, field_label, field_value FROM custom_setup_fields WHERE setup_id = ?");
$stmt_custom_fields->execute([$setup_id]);
$custom_fields_raw = $stmt_custom_fields->fetchAll(PDO::FETCH_ASSOC);
$custom_fields_by_section = [];
foreach ($custom_fields_raw as $field) {
    $custom_fields_by_section[$field['section']][] = $field;
}

// --- Handle Form SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_setup'])) {
    $pdo->beginTransaction();
    try {
        // ... (Your existing save logic for standard fields would go here) ...
        // For simplicity, we'll focus on saving the custom fields. Assume standard fields save correctly.

        // --- Save Custom Fields ---
        // First, delete all existing custom fields for this setup to handle deletions cleanly.
        $stmt_delete_custom = $pdo->prepare("DELETE FROM custom_setup_fields WHERE setup_id = ?");
        $stmt_delete_custom->execute([$setup_id]);

        // Now, insert all the submitted custom fields as new records.
        if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            $stmt_insert_custom = $pdo->prepare("INSERT INTO custom_setup_fields (user_id, setup_id, section, field_label, field_value) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($_POST['custom_fields'] as $section => $fields) {
                if (is_array($fields)) {
                    foreach ($fields as $field) {
                        $label = trim($field['label']);
                        $value = trim($field['value']);
                        // Only save if a label has been provided
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

// (Helper function render_field and other setup logic remains unchanged)
function render_field($field_name, $input_name, $saved_value, $options_by_category) {
    // This function remains the same as before
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
        <div id="custom-fields-front_suspension" class="mt-3">
            </div>
        <div class="mt-2 mb-4">
            <button type="button" class="btn btn-sm btn-outline-success" onclick="addCustomField('front_suspension')">Add Custom Field</button>
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
        <div id="custom-fields-rear_suspension" class="mt-3"></div>
        <div class="mt-2 mb-4">
            <button type="button" class="btn btn-sm btn-outline-success" onclick="addCustomField('rear_suspension')">Add Custom Field</button>
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
        <div id="custom-fields-tires_front" class="mt-3"></div>
        <div class="mt-2 mb-4">
            <button type="button" class="btn btn-sm btn-outline-success" onclick="addCustomField('tires_front')">Add Custom Field</button>
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
        <div id="custom-fields-tires_rear" class="mt-3"></div>
        <div class="mt-2 mb-4">
            <button type="button" class="btn btn-sm btn-outline-success" onclick="addCustomField('tires_rear')">Add Custom Field</button>
        </div>

        <hr>
        <button type="submit" name="save_setup" class="btn btn-primary">Save All Changes</button>
        </form>
</div>

<script>
function addCustomField(sectionKey) {
    const container = document.getElementById('custom-fields-' + sectionKey);
    if (!container) return;
    const newIndex = 'new_' + Date.now(); // Use a unique ID for new rows
    const row = document.createElement('div');
    row.className = 'row custom-field-row mb-2';
    row.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control" placeholder="Custom Field Label" name="custom_fields[${sectionKey}][${newIndex}][label]">
        </div>
        <div class="col-md-5">
            <input type="text" class="form-control" placeholder="Value" name="custom_fields[${sectionKey}][${newIndex}][value]">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger w-100" onclick="this.closest('.custom-field-row').remove();">Remove</button>
        </div>
    `;
    container.appendChild(row);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>