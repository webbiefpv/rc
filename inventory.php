<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Handle all form submissions for adding, updating, or deleting parts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Handle ADDING a new part
    if ($_POST['action'] === 'add_part') {
        $part_name = trim($_POST['part_name']);
        $part_category = trim($_POST['part_category']);
        $quantity = intval($_POST['quantity']);
        $notes = trim($_POST['notes']);

        if (empty($part_name) || $quantity < 0) {
            $message = '<div class="alert alert-danger">Part Name is required and Quantity cannot be negative.</div>';
        } else {
            $stmt = $pdo->prepare("INSERT INTO inventory (user_id, part_name, part_category, quantity, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $part_name, $part_category, $quantity, $notes]);
            $message = '<div class="alert alert-success">Part added to inventory.</div>';
        }
    }

    // Handle UPDATING quantity
    if ($_POST['action'] === 'update_quantity') {
        $part_id = intval($_POST['part_id']);
        $change = intval($_POST['change']); // This will be 1 or -1
        
        // Use a transaction to prevent race conditions
        $pdo->beginTransaction();
        $stmt_current = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ? AND user_id = ?");
        $stmt_current->execute([$part_id, $user_id]);
        $current_quantity = $stmt_current->fetchColumn();

        if ($current_quantity !== false) {
            $new_quantity = $current_quantity + $change;
            if ($new_quantity >= 0) {
                $stmt_update = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
                $stmt_update->execute([$new_quantity, $part_id]);
            }
        }
        $pdo->commit();
        // No message needed for quick updates to keep UI clean
        header("Location: inventory.php"); // Redirect to prevent re-submission
        exit;
    }

    // Handle DELETING a part
    if ($_POST['action'] === 'delete_part') {
        $part_id = intval($_POST['part_id']);
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ? AND user_id = ?");
        $stmt->execute([$part_id, $user_id]);
        $message = '<div class="alert alert-info">Part removed from inventory.</div>';
    }
}

// Fetch all inventory parts for the user, grouped by category
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE user_id = ? ORDER BY part_category, part_name");
$stmt->execute([$user_id]);
$all_parts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped_parts = [];
foreach ($all_parts_raw as $part) {
    $category = !empty($part['part_category']) ? $part['part_category'] : 'Uncategorized';
    $grouped_parts[$category][] = $part;
}
ksort($grouped_parts);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Spare Parts Inventory - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    <h1>Spare Parts Inventory</h1>
    <p>Keep track of the spare parts in your pit box so you always know what you have on hand.</p>
    <?php echo $message; ?>

    <!-- Add New Part Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Add a New Part</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_part">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="part_name" class="form-label">Part Name</label>
                        <input type="text" class="form-control" id="part_name" name="part_name" placeholder="e.g., Front Wishbone Set" required>
                    </div>
                    <div class="col-md-3">
                        <label for="part_category" class="form-label">Category</label>
                        <input type="text" class="form-control" id="part_category" name="part_category" placeholder="e.g., Suspension, Gears">
                    </div>
                    <div class="col-md-2">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="0" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Add to Inventory</button>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="1" placeholder="e.g., Part number, specific car model..."></textarea>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Display Existing Inventory -->
    <h3>Your Inventory</h3>
    <?php if (empty($grouped_parts)): ?>
        <p class="text-muted">No parts in your inventory yet. Add one above.</p>
    <?php else: ?>
        <div class="accordion" id="inventoryAccordion">
            <?php foreach ($grouped_parts as $category => $parts): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $category); ?>">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $category); ?>" aria-expanded="true" aria-controls="collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $category); ?>">
                            <strong><?php echo htmlspecialchars($category); ?></strong>
                        </button>
                    </h2>
                    <div id="collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $category); ?>" class="accordion-collapse collapse show" aria-labelledby="heading-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $category); ?>" data-bs-parent="#inventoryAccordion">
                        <div class="accordion-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($parts as $part): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($part['part_name']); ?></strong>
                                            <?php if (!empty($part['notes'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($part['notes']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <form method="POST" class="me-2">
                                                <input type="hidden" name="action" value="update_quantity">
                                                <input type="hidden" name="part_id" value="<?php echo $part['id']; ?>">
                                                <div class="input-group">
                                                    <button class="btn btn-outline-secondary btn-sm" type="submit" name="change" value="-1">-</button>
                                                    <span class="input-group-text"><?php echo $part['quantity']; ?></span>
                                                    <button class="btn btn-outline-secondary btn-sm" type="submit" name="change" value="1">+</button>
                                                </div>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this part?');">
                                                <input type="hidden" name="action" value="delete_part">
                                                <input type="hidden" name="part_id" value="<?php echo $part['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>