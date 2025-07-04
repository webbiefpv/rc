<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Handle ADDING a new option
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_option') {
    $category = trim($_POST['option_category']);
    $value = trim($_POST['option_value']);

    if (!empty($category) && !empty($value)) {
        try {
            // Note: We are no longer using the 'section' column
            $stmt = $pdo->prepare("INSERT INTO user_options (user_id, option_category, option_value) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $category, $value]);
            $message = '<div class="alert alert-success">Option added successfully.</div>';
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $message = '<div class="alert alert-danger">Error: This option already exists in this category.</div>';
            } else {
                $message = '<div class="alert alert-danger">An error occurred.</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">Please provide both a category and a value.</div>';
    }
}

// Handle DELETING an option
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_option') {
    $option_id = intval($_POST['option_id']);
    $stmt = $pdo->prepare("DELETE FROM user_options WHERE id = ? AND user_id = ?");
    $stmt->execute([$option_id, $user_id]);
    $message = '<div class="alert alert-info">Option deleted.</div>';
}

// Fetch all existing options for the user, grouped by category
$stmt = $pdo->prepare("SELECT id, option_category, option_value FROM user_options WHERE user_id = ? ORDER BY option_category, option_value");
$stmt->execute([$user_id]);
$all_options_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manage Predefined Options</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Manage Predefined Options</h1>
    <p>Add values for categories that will appear as dropdowns in your setup sheets. The 'Category Name' must exactly match a field name from the setup sheet (e.g., 'tire_brand', 'motor_brand').</p>
    <?php echo $message; ?>

    <div class="card mb-4">
        <div class="card-header"><h5>Add a New Option</h5></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_option">
                <div class="row align-items-end">
                    <div class="col-md-5">
                        <label for="option_category" class="form-label">Category Name</label>
                        <input type="text" class="form-control" name="option_category" placeholder="e.g., tire_brand" required>
                    </div>
                    <div class="col-md-5">
                        <label for="option_value" class="form-label">Option Value</label>
                        <input type="text" class="form-control" name="option_value" placeholder="e.g., Contact RC" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <h3>Your Current Options</h3>
    <div class="row">
        <?php if (empty($grouped_options)): ?>
            <p class="text-muted">No options added yet.</p>
        <?php else: ?>
            <?php foreach ($grouped_options as $category => $options): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header"><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $category))); ?></strong></div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($options as $option): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($option['option_value']); ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete_option">
                                        <input type="hidden" name="option_id" value="<?php echo $option['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>