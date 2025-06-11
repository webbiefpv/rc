<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$model_id = $_GET['model_id'];
$user_id = $_SESSION['user_id'];

// Verify model belongs to user
$stmt = $pdo->prepare("SELECT * FROM models WHERE id = ? AND user_id = ?");
$stmt->execute([$model_id, $user_id]);
$model = $stmt->fetch();
if (!$model) {
	header('Location: index.php');
	exit;
}

// Handle add/edit/delete setups
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['add_setup'])) {
		$name = $_POST['name'];
		$stmt = $pdo->prepare("INSERT INTO setups (model_id, name) VALUES (?, ?)");
		$stmt->execute([$model_id, $name]);
	} elseif (isset($_POST['edit_setup'])) {
		$id = $_POST['id'];
		$name = $_POST['name'];
		$stmt = $pdo->prepare("UPDATE setups SET name = ? WHERE id = ? AND model_id = ?");
		$stmt->execute([$name, $id, $model_id]);
	} elseif (isset($_POST['delete_setup'])) {
		$id = $_POST['id'];
		$stmt = $pdo->prepare("DELETE FROM setups WHERE id = ? AND model_id = ?");
		$stmt->execute([$id, $model_id]);
	}
}

// Fetch setups
$stmt = $pdo->prepare("
    SELECT s.id, s.name, s.created_at, s.is_baseline, 
           GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') as tags
    FROM setups s
    LEFT JOIN setup_tags st ON s.id = st.setup_id
    LEFT JOIN tags t ON st.tag_id = t.id
    WHERE s.model_id = ?
    GROUP BY s.id
    ORDER BY s.is_baseline DESC, s.created_at DESC
");
$stmt->execute([$model_id]);
$setups = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setups for <?php echo htmlspecialchars($model['name']); ?> - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="scripts.js"></script>
</head>
<body>
<?php
require 'header.php';
?>
<div class="container mt-3">
    <h1>Setups for <?php echo htmlspecialchars($model['name']); ?></h1>
    <!-- Add Setup -->
    <h3>Add Setup</h3>
    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="name" class="form-label">Setup Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <button type="submit" name="add_setup" class="btn btn-primary">Add Setup</button>
    </form>
    <!-- List Setups -->
    <h3>Your Setups</h3>
    <table class="table table-striped">
        <thead>
        <tr>
            <th>Name</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
		<?php foreach ($setups as $setup): ?>
            <tr>
                <td>
                    <a href="setup_form.php?setup_id=<?php echo $setup['id']; ?>">
                        <?php echo htmlspecialchars($setup['name']); ?>
                    </a>

                </td>
                <td>
                    <?php if ($setup['is_baseline']): ?>
                        <span class="badge bg-warning text-dark ms-2">Baseline ⭐</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($setup['tags'])): ?>
                        <div class="mt-2">
                            <?php foreach (explode(', ', $setup['tags']) as $tag): ?>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($tag); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php echo $setup['created_at']; ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editSetup(<?php echo $setup['id']; ?>, '<?php echo htmlspecialchars(addslashes($setup['name'])); ?>')">Edit</button>

                    <form method="POST" action="clone_setup.php" style="display:inline; margin-left: 5px;">
                        <input type="hidden" name="original_setup_id" value="<?php echo $setup['id']; ?>">
                        <input type="hidden" name="model_id" value="<?php echo $model_id; // Pass model_id for redirection if needed ?>">
                        <button type="submit" name="clone_setup" class="btn btn-sm btn-info" onclick="return confirm('Are you sure you want to clone this setup?');">Clone</button>
                    </form>

                    <form method="POST" style="display:inline; margin-left: 5px;">
                        <input type="hidden" name="id" value="<?php echo $setup['id']; ?>">
                        <button type="submit" name="delete_setup" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</button>
                    </form>
                </td>
            </tr>
		<?php endforeach; ?>
        </tbody>
    </table>
    <!-- Edit Setup Modal -->
    <div class="modal fade" id="editSetupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Setup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_setup_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Setup Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="edit_setup" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>