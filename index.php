<?php

require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// --- Dashboard Stats ---
// Count Models
$stmt_models_count = $pdo->prepare("SELECT COUNT(*) FROM models WHERE user_id = ?");
$stmt_models_count->execute([$user_id]);
$models_count = $stmt_models_count->fetchColumn();

// Count Setups
$stmt_setups_count = $pdo->prepare("SELECT COUNT(*) FROM setups s JOIN models m ON s.model_id = m.id WHERE m.user_id = ?");
$stmt_setups_count->execute([$user_id]);
$setups_count = $stmt_setups_count->fetchColumn();

// Count Tracks
$stmt_tracks_count = $pdo->prepare("SELECT COUNT(*) FROM tracks WHERE user_id = ?");
$stmt_tracks_count->execute([$user_id]);
$tracks_count = $stmt_tracks_count->fetchColumn();

// --- Latest Setup (as a proxy for "Selected Setup") ---
$latest_setup = null;

// --- LATEST SETUP ---
// --- Fetch Current Selected Setup ---
$current_setup = null;
// First, find the user's selected_setup_id from the 'users' table
$stmt_get_selected_id = $pdo->prepare("SELECT selected_setup_id FROM users WHERE id = ?");
$stmt_get_selected_id->execute([$user_id]);
$selected_id = $stmt_get_selected_id->fetchColumn();

// If an ID is selected, fetch that specific setup's details
if ($selected_id) {
    $stmt_current_setup = $pdo->prepare("
        SELECT s.id, s.name as setup_name, s.created_at, s.is_baseline, m.name as model_name,
               GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') as tags
        FROM setups s
        JOIN models m ON s.model_id = m.id
        LEFT JOIN setup_tags st ON s.id = st.setup_id
        LEFT JOIN tags t ON st.tag_id = t.id
        WHERE s.id = ? AND m.user_id = ?
        GROUP BY s.id
    ");
    $stmt_current_setup->execute([$selected_id, $user_id]);
    $current_setup = $stmt_current_setup->fetch(PDO::FETCH_ASSOC);
}

// FALLBACK: If no setup is selected OR the selected setup was somehow invalid, show the latest setup instead.
if (!$current_setup) {
    $stmt_latest_setup = $pdo->prepare("
        SELECT s.id, s.name as setup_name, s.created_at, s.is_baseline, m.name as model_name,
               GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') as tags
        FROM setups s
        JOIN models m ON s.model_id = m.id
        LEFT JOIN setup_tags st ON s.id = st.setup_id
        LEFT JOIN tags t ON st.tag_id = t.id
        WHERE m.user_id = ?
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt_latest_setup->execute([$user_id]);
    $current_setup = $stmt_latest_setup->fetch(PDO::FETCH_ASSOC);
}

// --- Recent Models (e.g., last 3) ---
$recent_models = [];
$stmt_recent_models = $pdo->prepare("SELECT id, name, created_at FROM models WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt_recent_models->execute([$user_id]);
$recent_models = $stmt_recent_models->fetchAll(PDO::FETCH_ASSOC);

// --- Recent Setups (e.g., last 3, excluding the one already shown as "latest" if necessary, or just top N) ---
// For simplicity, let's fetch the top 3-4 most recent setups overall.
// If $latest_setup is one of them, it might appear again here or you can filter it out in the loop.


$recent_setups = [];

// --- RECENT SETUPS ---
$stmt_recent_setups_sql = "
    SELECT s.id, s.name as setup_name, s.created_at, s.is_baseline, m.name as model_name,
           GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') as tags
    FROM setups s
    JOIN models m ON s.model_id = m.id
    LEFT JOIN setup_tags st ON s.id = st.setup_id
    LEFT JOIN tags t ON st.tag_id = t.id
    WHERE m.user_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 4";
$stmt_recent_setups = $pdo->prepare($stmt_recent_setups_sql);
$stmt_recent_setups->execute([$user_id]);
$recent_setups = $stmt_recent_setups->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pits - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php
require 'header.php'; // Your common header
?>
<div class="container mt-3">
    <h1 class="mb-4">Pits Dashboard</h1>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Models</h5>
                    <p class="card-text fs-3"><?php echo $models_count; ?></p>
                    <a href="models.php" class="btn btn-sm btn-outline-primary">Manage Models</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Setups</h5>
                    <p class="card-text fs-3"><?php echo $setups_count; ?></p>
                    <a href="models.php" class="btn btn-sm btn-outline-primary">View Setups</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Tracks</h5>
                    <p class="card-text fs-3"><?php echo $tracks_count; ?></p>
                    <a href="tracks.php" class="btn btn-sm btn-outline-primary">Manage Tracks</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Quick Links</h5>
        </div>
        <div class="card-body text-center">
            <a href="models.php" class="btn btn-primary m-1">Manage Models & Setups</a>
            <a href="tracks.php" class="btn btn-info m-1">Manage Tracks</a>
            <a href="rollout_calc.php" class="btn btn-success m-1">Roll Out Calculator</a>
            <a href="compare_setups.php" class="btn btn-warning m-1">Compare Setups</a>
            <a href="race_log.php" class="btn btn-secondary m-1">Race Log</a>
            <a href="glossary.php" class="btn btn-light m-1 border">Setup Glossary</a>
            <a href="troubleshooting.php" class="btn btn-light m-1 border">Troubleshooting</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5>⭐ Latest Setup</h5>
                </div>
                <div class="card-body">
                    <?php if ($latest_setup): ?>
                        <h5 class="card-title">
                            <?php echo htmlspecialchars($latest_setup['setup_name']); ?>
                            <?php if ($latest_setup['is_baseline']): ?>
                                <span class="badge bg-warning text-dark ms-1">Baseline ⭐</span>
                            <?php endif; ?>
                        </h5>
                        <?php if (!empty($latest_setup['tags'])): ?>
                            <div class="mt-2">
                                <?php foreach (explode(', ', $latest_setup['tags']) as $tag): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <h6 class="card-subtitle mb-2 text-muted">
                            <?php echo htmlspecialchars($latest_setup['model_name']); ?>
                        </h6>
                        <p class="card-text">
                            <small>
                                Created: <?php echo date("D, M j Y, g:i a", strtotime($latest_setup['created_at'])); ?>
                            </small>
                        </p>
                        <a href="setup_form.php?setup_id=<?php echo $latest_setup['id']; ?>" class="btn btn-sm btn-outline-success">View/Edit Setup</a>
                    <?php else: ?>
                        <p class="card-text">No setups found yet. Go add one!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5>Recent Models</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_models)): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recent_models as $model): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="setups.php?model_id=<?php echo $model['id']; ?>"><?php echo htmlspecialchars($model['name']); ?></a>
                                    <small class="text-muted"><?php echo date("M j, Y", strtotime($model['created_at'])); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No models added yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Other Recent Setups</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_setups)): ?>
                        <div class="list-group">
                            <?php foreach ($recent_setups as $setup): ?>
                                <?php // Optionally skip if it's the same as $latest_setup and you only want unique entries here
                                // if ($latest_setup && $setup['id'] == $latest_setup['id']) continue;
                                ?>
                                <a href="setup_form.php?setup_id=<?php echo $setup['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($setup['setup_name']); ?>
                                            <?php if ($setup['is_baseline']): ?>
                                                <span class="badge bg-warning text-dark ms-1">Baseline ⭐</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small><?php echo date("M j, Y", strtotime($setup['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($setup['model_name']); ?></p>
                                    <?php if (!empty($setup['tags'])): ?>
                                        <div class="mt-1">
                                            <?php foreach (explode(', ', $setup['tags']) as $tag): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No setups found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>