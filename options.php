<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Define the sections of the setup form where dynamic options can be placed.
$form_sections = [
    'tires_front' => 'Front Tires',
    'tires_rear' => 'Rear Tires',
    'electronics' => 'Electronics',
    'drivetrain' => 'Drivetrain'
    // Add other sections here if you want them to support dynamic dropdowns
];

// Define the categories for the dropdown.
$option_categories = [
    'tire_brand' => 'Tire Brand',
    'tire_compound' => 'Tire Compound',
    'motor_brand' => 'Motor Brand',
    'esc_brand' => 'ESC Brand',
    'battery_brand' => 'Battery Brand',
    'servo_brand' => 'Servo Brand',
    'radio_brand' => 'Radio Brand'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_option') {
        $section = $_POST['section'];
        $category = $_POST['option_category'];
        $value = trim($_POST['option_value']);

        if (!empty($value) && array_key_exists($section, $form_sections) && array_key_exists($category, $option_categories)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO user_options (user_id, section, option_category, option_value) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $section, $category, $value]);
                $message = '<div class="alert alert-success">Option added successfully.</div>';
            } catch (PDOException $e) {
                $message = '<div class="alert alert-danger">Error: This option may already exist.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Please select a valid section, category, and enter a value.</div>';
        }
    }
    // ... (your existing delete logic is fine and does not need to change)
}

// Fetch all existing options for the user
$stmt = $pdo->prepare("SELECT id, section, option_category, option_value FROM user_options WHERE user_id = ? ORDER BY section, option_category, option_value");
$stmt->execute([$user_id]);
$all_options_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped_options = [];
foreach ($all_options_raw as $option) {
    $grouped_options[$option['section']][$option['option_category']][] = $option;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Predefined Options</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Manage Predefined Options</h1>
    <?php echo $message; ?>
    <div class="card mb-4">
        <div class="card-header"><h5>Add a New Option</h5></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_option">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label for="section" class="form-label">Form Section</label>
                        <select class="form-select" name="section" required>
                            <option value="">-- Select Section --</option>
                            <?php foreach ($form_sections as $key => $display_name): ?>
                                <option value="<?php echo $key; ?>"><?php echo $display_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="option_category" class="form-label">Category</label>
                        <select class="form-select" name="option_category" required>
                             <option value="">-- Select Category --</option>
                            <?php foreach ($option_categories as $key => $display_name): ?>
                                <option value="<?php echo $key; ?>"><?php echo $display_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="option_value" class="form-label">Option Value</label>
                        <input type="text" class="form-control" name="option_value" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <h3>Your Current Options</h3>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>