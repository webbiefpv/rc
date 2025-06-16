<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['add_model'])) {
		$name = $_POST['name'];
		$stmt = $pdo->prepare("INSERT INTO models (user_id, name) VALUES (?, ?)");
		$stmt->execute([$user_id, $name]);
	} elseif (isset($_POST['edit_model'])) {
		$id = $_POST['id'];
		$name = $_POST['name'];
		$stmt = $pdo->prepare("UPDATE models SET name = ? WHERE id = ? AND user_id = ?");
		$stmt->execute([$name, $id, $user_id]);
	} elseif (isset($_POST['delete_model'])) {
		$id = $_POST['id'];
		$stmt = $pdo->prepare("DELETE FROM models WHERE id = ? AND user_id = ?");
		$stmt->execute([$id, $user_id]);
	}
}

// Fetch models
$stmt = $pdo->prepare("SELECT * FROM models WHERE user_id = ?");
$stmt->execute([$user_id]);
$models = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Models - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="scripts.js"></script>
</head>
<body>
<?php
require 'header.php';
?>
<div class="container mt-3">
    <h1>Manage Models</h1>
    <!-- Add Model -->
    <h3>Add Model</h3>
    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="name" class="form-label">Model Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <button type="submit" name="add_model" class="btn btn-primary">Add Model</button>
    </form>
    <!-- List Models -->
    <h3>Your Models</h3>
    <table class="table table-striped">
        <thead>
        <tr>
            <th>Name</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
		<?php foreach ($models as $model): ?>
            <tr>
                <td><a href="setups.php?model_id=<?php echo $model['id']; ?>"><?php echo htmlspecialchars($model['name']); ?></a></td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editModel(<?php echo $model['id']; ?>, '<?php echo htmlspecialchars($model['name']); ?>')">Edit</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $model['id']; ?>">
                        <button type="submit" name="delete_model" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</button>
                    </form>
                </td>
            </tr>
		<?php endforeach; ?>
        </tbody>
    </table>
    <!-- Edit Model Modal -->
    <div class="modal fade" id="editModelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Model</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_model_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Model Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="edit_model" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>