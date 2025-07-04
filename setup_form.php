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
if (!$setup || $setup['user_id'] != $user_id) {
    header('Location: index.php'); exit;
}

// --- Dynamic Form & Save Logic ---
$dynamic_form_sections = [
    'Front Suspension' => 'front_suspension', 'Rear Suspension' => 'rear_suspension',
    'Front Tires' => 'tires_front', 'Rear Tires' => 'tires_rear',
    'Drivetrain' => 'drivetrain', 'Body and Chassis' => 'body_chassis',
    'Electronics' => 'electronics', 'ESC Settings' => 'esc_settings', 'Comments' => 'comments'
];
$all_db_fields = [];
$all_data = [];

// Get all column names for each table to build the form
foreach ($dynamic_form_sections as $title => $table_name) {
    // The tires table is special
    if (strpos($table_name, 'tires') === false) {
        $q = $pdo->query("DESCRIBE $table_name");
        $all_db_fields[$table_name] = array_column($q->fetchAll(PDO::FETCH_ASSOC), 'Field');
    } else {
        $q = $pdo->query("DESCRIBE tires");
        $all_db_fields[$table_name] = array_column($q->fetchAll(PDO::FETCH_ASSOC), 'Field');
    }
}

// Fetch all existing data for this setup
foreach ($dynamic_form_sections as $title => $table_name) {
    if (strpos($table_name, 'tires') === false) {
        $stmt_data = $pdo->prepare("SELECT * FROM $table_name WHERE setup_id = ?");
        $stmt_data->execute([$setup_id]);
        $all_data[$table_name] = $stmt_data->fetch(PDO::FETCH_ASSOC);
    } else {
        $position = (strpos($table_name, '_front') !== false) ? 'front' : 'rear';
        $stmt_data = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = ?");
        $stmt_data->execute([$setup_id, $position]);
        $all_data[$table_name] = $stmt_data->fetch(PDO::FETCH_ASSOC);
    }
}

// Fetch user-defined options and group them
$stmt_options = $pdo->prepare("SELECT section, option_category, option_value FROM user_options WHERE user_id = ? ORDER BY option_value");
$stmt_options->execute([$user_id]);
$all_options_raw = $stmt_options->fetchAll(PDO::FETCH_ASSOC);
$options_by_category = [];
foreach($all_options_raw as $opt) { $options_by_category[$opt['section']][$opt['option_category']][] = $opt['option_value']; }

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_setup'])) {
    $pdo->beginTransaction();
    try {
        // ... (Your baseline save logic remains the same here) ...

        // Loop through all submitted data and save it dynamically
        foreach ($_POST as $section_key => $fields_data) {
            if (is_array($fields_data)) {
                $table_name = $section_key;
                $columns = array_keys($fields_data);
                $set_clause = implode(' = ?, ', $columns) . ' = ?';

                if (strpos($table_name, 'tires') !== false) {
                    $position = (strpos($table_name, '_front') !== false) ? 'front' : 'rear';
                    $sql = "UPDATE tires SET $set_clause WHERE setup_id = ? AND position = ?";
                    $params = array_values($fields_data);
                    $params[] = $setup_id;
                    $params[] = $position;
                } else {
                    $sql = "UPDATE $table_name SET $set_clause WHERE setup_id = ?";
                    $params = array_values($fields_data);
                    $params[] = $setup_id;
                }
                $stmt_save = $pdo->prepare($sql);
                $stmt_save->execute($params);
            }
        }
        $pdo->commit();
        header("Location: setup_form.php?setup_id=" . $setup_id . "&success=1");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        // ... (Error handling) ...
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup: <?php echo htmlspecialchars($setup['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Setup: <?php echo htmlspecialchars($setup['name']); ?></h1>
    <form method="POST">
        <?php foreach ($dynamic_form_sections as $section_title => $section_key): ?>
            <h3><?php echo $section_title; ?></h3>
            <div class="row">
                <?php
                $fields_in_section = $all_db_fields[$section_key];
                foreach ($fields_in_section as $field):
                    if (in_array($field, ['id', 'setup_id', 'position'])) continue; // Skip internal fields
                    
                    $input_name = $section_key . '[' . $field . ']';
                    $label = ucwords(str_replace('_', ' ', $field));
                    $saved_value = $all_data[$section_key][$field] ?? null;

                    // Check if this field should be a dropdown
                    if (isset($options_by_category[$section_key][$field])) {
                        $options_list = $options_by_category[$section_key][$field];
                        // Dropdown HTML creation logic here
                        echo '<div class="col-md-4 mb-3"><label class="form-label">' . $label . '</label><select class="form-select" name="' . $input_name . '"><option value="">-- Select --</option>';
                        foreach($options_list as $opt) {
                            $selected_attr = ($opt == $saved_value) ? ' selected' : '';
                            echo '<option value="'.htmlspecialchars($opt).'"'.$selected_attr.'>'.htmlspecialchars($opt).'</option>';
                        }
                        if (!empty($saved_value) && !in_array($saved_value, $options_list)) {
                            echo '<option value="'.htmlspecialchars($saved_value).'" selected>CUSTOM: '.htmlspecialchars($saved_value).'</option>';
                        }
                        echo '</select></div>';
                    } else { // It's a text input or textarea
                        if ($field === 'notes' || $field === 'comment' || $field === 'charging_notes') {
                            echo '<div class="col-12 mb-3"><label class="form-label">' . $label . '</label><textarea class="form-control" name="' . $input_name . '">' . htmlspecialchars($saved_value ?? '') . '</textarea></div>';
                        } else {
                            echo '<div class="col-md-4 mb-3"><label class="form-label">' . $label . '</label><input type="text" class="form-control" name="' . $input_name . '" value="' . htmlspecialchars($saved_value ?? '') . '"></div>';
                        }
                    }
                endforeach;
                ?>
            </div>
        <?php endforeach; ?>
        
        <hr>
        <button type="submit" name="save_setup" class="btn btn-primary">Save All Changes</button>
        </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>