<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Define the categories for the dropdown. This makes it easy to add more later.
$option_categories = [
    'tire_brand' => 'Tire Brand',
    'motor_brand' => 'Motor Brand',
    'esc_brand' => 'ESC Brand',
    'battery_brand' => 'Battery Brand',
    'servo_brand' => 'Servo Brand',
    'radio_brand' => 'Radio Brand'
];

// Handle form submissions (add or delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle ADDING a new option
    if (isset($_POST['action']) && $_POST['action'] === 'add_option') {
        $category = $_POST['option_category'];
        $value = trim($_POST['option_value']);

        if (!empty($value) && array_key_exists($category, $option_categories)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO user_options (user_id, option_category, option_value) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $category, $value]);
                $message = '<div class="alert alert-success">Option added successfully.</div>';
            } catch (PDOException $e) {
                // Catch the unique constraint violation
                if ($e->errorInfo[1] == 1062) {
                    $message = '<div class="alert alert-danger">Error: This option already exists in this category.</div>';
                } else {
                    $message = '<div class="alert alert-danger">An error occurred.</div>';
                }
            }
        } else {
            $message = '<div class="alert alert-danger">Please select a valid category and enter a value.</div>';
        }
    }

    // Handle DELETING an option
    if (isset($_POST['action']) && $_POST['action'] === 'delete_option') {
        $option_id = intval($_POST['option_id']);
        // Security check: ensure the option belongs to the logged-in user before deleting
        $stmt = $pdo->prepare("DELETE FROM user_options WHERE id = ? AND user_id = ?");
        $stmt->execute([$option_id, $user_id]);
        $message = '<div class="alert alert-info">Option deleted.</div>';
    }
}


// Fetch all existing options for the user, grouped by category
$stmt = $pdo->prepare("SELECT id, option_category, option_value FROM user_options WHERE user_id = ? ORDER BY option_category, option_value");
$stmt->execute([$user_id]);
$all_options_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reorganize the data into a nested array for easy display
$grouped_options = [];
foreach ($all_options_raw as $option) {
    $grouped_options[$option['option_category']][] = $option;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Predefined Options - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Manage Predefined Options</h1>
    <p>Add or remove options that will appear in dropdown menus on your setup sheets, like tire or motor brands.</p>
    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Add a New Option</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_option">
                <div class="row align-items-end">
                    <div class="col-md-5">
                        <label for="option_category" class="form-label">Category</label>
                        <select class="form-select" id="option_category" name="option_category" required>
                            <option value="">-- Select a Category --</option>
                            <?php foreach ($option_categories as $key => $display_name): ?>
                                <option value="<?php echo $key; ?>"><?php echo $display_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="option_value" class="form-label">Option Value</label>
                        <input type="text" class="form-control" id="option_value" name="option_value" placeholder="e.g., Contact RC, Hobbywing" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add Option</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <h3>Your Current Options</h3>
    <div class="row">
        <?php foreach ($option_categories as $key => $display_name): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><?php echo $display_name; ?></strong>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php if (!empty($grouped_options[$key])): ?>
                            <?php foreach ($grouped_options[$key] as $option): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($option['option_value']); ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this option?');">
                                        <input type="hidden" name="action" value="delete_option">
                                        <input type="hidden" name="option_id" value="<?php echo $option['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-muted">No options added yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>