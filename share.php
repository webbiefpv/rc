<?php
// This page does NOT require a login, but it can check if a user is logged in.
require 'db_config.php';
require 'auth.php'; // This makes isLoggedIn() available but doesn't force a login.

// 1. Get the share token from the URL
if (!isset($_GET['token'])) {
    die('No share token provided.');
}
$share_token = $_GET['token'];

// 2. Find the setup that matches the token AND is marked as public
$stmt_setup = $pdo->prepare("
    SELECT s.*, m.name as model_name 
    FROM setups s 
    JOIN models m ON s.model_id = m.id 
    WHERE s.share_token = ? AND s.is_public = 1
");
$stmt_setup->execute([$share_token]);
$setup = $stmt_setup->fetch(PDO::FETCH_ASSOC);

if (!$setup) {
    // If no setup is found, show an error message and stop.
    $error_message = "This setup sheet was not found, or sharing has been disabled.";
} else {
    // If setup is found, fetch all its related data
    $setup_id = $setup['id'];
    $data = [];
    $tables = ['front_suspension', 'rear_suspension', 'drivetrain', 'body_chassis', 'electronics', 'esc_settings', 'comments'];
    foreach ($tables as $table) {
        $stmt_data = $pdo->prepare("SELECT * FROM $table WHERE setup_id = ?");
        $stmt_data->execute([$setup_id]);
        $data[$table] = $stmt_data->fetch(PDO::FETCH_ASSOC);
    }
    // Tires
    $stmt_tires = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = ?");
    $stmt_tires->execute([$setup_id, 'front']);
    $data['tires_front'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);
    $stmt_tires->execute([$setup_id, 'rear']);
    $data['tires_rear'] = $stmt_tires->fetch(PDO::FETCH_ASSOC);

    // Weight Distribution
    $stmt_weights = $pdo->prepare("SELECT * FROM weight_distribution WHERE setup_id = ?");
    $stmt_weights->execute([$setup_id]);
    $data['weights'] = $stmt_weights->fetch(PDO::FETCH_ASSOC);
    $data['weight_calcs'] = null;
    if ($data['weights']) {
        $lf = floatval($data['weights']['lf_weight']); $rf = floatval($data['weights']['rf_weight']);
        $lr = floatval($data['weights']['lr_weight']); $rr = floatval($data['weights']['rr_weight']);
        $total = $lf + $rf + $lr + $rr;
        if ($total > 0) {
            $data['weight_calcs'] = [
                'total' => $total,
                'front_perc' => (($lf + $rf) / $total) * 100,
                'rear_perc' => (($lr + $rr) / $total) * 100,
                'left_perc' => (($lf + $lr) / $total) * 100,
                'right_perc' => (($rf + $rr) / $total) * 100,
                'cross_lf_rr_perc' => (($lf + $rr) / $total) * 100
            ];
        }
    }
}

// Helper function to display a data point
function display_data($label, $value) {
    if (isset($value) && $value !== '') {
        echo '<dt class="col-sm-6">' . htmlspecialchars($label) . '</dt>';
        echo '<dd class="col-sm-6">' . htmlspecialchars($value) . '</dd>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shared Setup Sheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background-color: #f8f9fa; }
        .setup-section { margin-bottom: 1.5rem; }
        .setup-section h4 { border-bottom: 2px solid #dee2e6; padding-bottom: 0.5rem; margin-bottom: 1rem; }
        dt { font-weight: bold; }
        dd { margin-bottom: .5rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Pan Car Setup Viewer</a>
        <?php if (isLoggedIn()): ?>
            <a href="index.php" class="btn btn-outline-light">Back to My Pits</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-outline-light">Login or Register to Import</a>
        <?php endif; ?>
    </div>
</nav>
<div class="container mt-4">
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger text-center">
            <h4>Error</h4>
            <p><?php echo $error_message; ?></p>
        </div>
    <?php else: ?>
        <!-- Display the setup sheet if it was found -->
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><?php echo htmlspecialchars($setup['model_name']); ?></h2>
                <h4 class="text-muted"><?php echo htmlspecialchars($setup['name']); ?></h4>
            </div>
            <!-- NEW: Import Button for logged-in users -->
            <?php if (isLoggedIn()): ?>
                <form action="import_setup.php" method="POST">
                    <input type="hidden" name="share_token" value="<?php echo htmlspecialchars($share_token); ?>">
                    <button type="submit" class="btn btn-success">Import This Setup to My Account</button>
                </form>
            <?php endif; ?>
        </div>
        <hr>

        <!-- Main Data Sections -->
        <div class="row">
            <div class="col-lg-4 setup-section">
                <h4>Front Suspension</h4>
                <dl class="row">
                    <?php foreach($data['front_suspension'] as $key => $val) { if($key !== 'id' && $key !== 'setup_id') display_data(ucwords(str_replace('_', ' ', $key)), $val); } ?>
                </dl>
            </div>
            <div class="col-lg-4 setup-section">
                <h4>Rear Suspension</h4>
                <dl class="row">
                    <?php foreach($data['rear_suspension'] as $key => $val) { if($key !== 'id' && $key !== 'setup_id') display_data(ucwords(str_replace('_', ' ', $key)), $val); } ?>
                </dl>
            </div>
            <div class="col-lg-4 setup-section">
                <h4>Tires</h4>
                <h6 class="text-muted">Front</h6>
                <dl class="row">
                    <?php foreach($data['tires_front'] as $key => $val) { if($key !== 'id' && $key !== 'setup_id' && $key !== 'position') display_data(ucwords(str_replace('_', ' ', $key)), $val); } ?>
                </dl>
                <h6 class="text-muted">Rear</h6>
                <dl class="row">
                    <?php foreach($data['tires_rear'] as $key => $val) { if($key !== 'id' && $key !== 'setup_id' && $key !== 'position') display_data(ucwords(str_replace('_', ' ', $key)), $val); } ?>
                </dl>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-4 setup-section">
                <h4>Drivetrain</h4>
                <dl class="row">
                    <?php foreach($data['drivetrain'] as $key => $val) { if($key !== 'id' && $key !== 'setup_id') display_data(ucwords(str_replace('_', ' ', $key)), $val); } ?>
                </dl>
            </div>
            <div class="col-lg-8 setup-section">
                <h4>Electronics</h4>
                <dl class="row">
                    <?php foreach($data['electronics'] as $key => $val) { if($key !== 'id' && $key !== 'setup_id') display_data(ucwords(str_replace('_', ' ', $key)), $val); } ?>
                </dl>
            </div>
        </div>
        
        <!-- Weight Distribution -->
        <?php if (isset($data['weights']) && $data['weights'] && isset($data['weight_calcs'])): ?>
        <div class="row">
            <div class="col-12 setup-section">
                <h4>Weight Distribution</h4>
                <table class="table table-bordered text-center">
                    <thead>
                        <tr><th></th><th>Left</th><th>Right</th><th>Total</th><th>Percent</th></tr>
                    </thead>
                    <tbody>
                        <tr><th>Front</th><td><?php echo htmlspecialchars($data['weights']['lf_weight']); ?> g</td><td><?php echo htmlspecialchars($data['weights']['rf_weight']); ?> g</td><td><?php echo number_format(floatval($data['weights']['lf_weight']) + floatval($data['weights']['rf_weight']), 1); ?> g</td><td><?php echo number_format($data['weight_calcs']['front_perc'], 1); ?>%</td></tr>
                        <tr><th>Rear</th><td><?php echo htmlspecialchars($data['weights']['lr_weight']); ?> g</td><td><?php echo htmlspecialchars($data['weights']['rr_weight']); ?> g</td><td><?php echo number_format(floatval($data['weights']['lr_weight']) + floatval($data['weights']['rr_weight']), 1); ?> g</td><td><?php echo number_format($data['weight_calcs']['rear_perc'], 1); ?>%</td></tr>
                        <tr><th>Total</th><td><?php echo number_format(floatval($data['weights']['lf_weight']) + floatval($data['weights']['lr_weight']), 1); ?> g</td><td><?php echo number_format(floatval($data['weights']['rf_weight']) + floatval($data['weights']['rr_weight']), 1); ?> g</td><td><strong><?php echo number_format($data['weight_calcs']['total'], 1); ?> g</strong></td><td></td></tr>
                        <tr><th>Percent</th><td><?php echo number_format($data['weight_calcs']['left_perc'], 1); ?>%</td><td><?php echo number_format($data['weight_calcs']['right_perc'], 1); ?>%</td><td></td><td><strong>Cross:</strong> <?php echo number_format($data['weight_calcs']['cross_lf_rr_perc'], 1); ?>%</td></tr>
                    </tbody>
                </table>
                 <?php if(!empty($data['weights']['notes'])): ?><p class="mt-2"><strong>Notes:</strong> <?php echo htmlspecialchars($data['weights']['notes']); ?></p><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>