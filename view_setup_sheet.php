<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// 1. Get Setup ID and verify ownership
if (!isset($_GET['setup_id'])) {
    header('Location: setup_sheets_list.php');
    exit;
}
$setup_id = intval($_GET['setup_id']);

$stmt_setup = $pdo->prepare("
    SELECT s.*, m.name as model_name 
    FROM setups s 
    JOIN models m ON s.model_id = m.id 
    WHERE s.id = ? AND m.user_id = ?
");
$stmt_setup->execute([$setup_id, $user_id]);
$setup = $stmt_setup->fetch(PDO::FETCH_ASSOC);

if (!$setup) {
    header('Location: setup_sheets_list.php?error=notfound');
    exit;
}

// 2. Fetch all related data for this setup
$data = [];

// Standard setup tables
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

// Tags
$stmt_tags = $pdo->prepare("SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') as tags_list FROM tags t JOIN setup_tags st ON t.id = st.tag_id WHERE st.setup_id = ?");
$stmt_tags->execute([$setup_id]);
$data['tags'] = $stmt_tags->fetchColumn();

// Weight Distribution
$stmt_weights = $pdo->prepare("SELECT * FROM weight_distribution WHERE setup_id = ?");
$stmt_weights->execute([$setup_id]);
$data['weights'] = $stmt_weights->fetch(PDO::FETCH_ASSOC);

// --- NEW: Calculate weight percentages if data exists ---
$data['weight_calcs'] = null;
if ($data['weights']) {
    $lf = floatval($data['weights']['lf_weight']);
    $rf = floatval($data['weights']['rf_weight']);
    $lr = floatval($data['weights']['lr_weight']);
    $rr = floatval($data['weights']['rr_weight']);
    
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


// Find the best performance achieved with this setup
$stmt_best_performance = $pdo->prepare("
    SELECT rl.best_lap_time, t.name as track_name, t.track_image_url, e.event_name
    FROM race_logs rl
    JOIN tracks t ON rl.track_id = t.id
    JOIN race_events e ON rl.event_id = e.id
    WHERE rl.setup_id = ? AND rl.best_lap_time > 0
    ORDER BY rl.best_lap_time ASC
    LIMIT 1
");
$stmt_best_performance->execute([$setup_id]);
$data['best_performance'] = $stmt_best_performance->fetch(PDO::FETCH_ASSOC);


// Helper function to display a data point if it exists
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
    <title>Setup Sheet: <?php echo htmlspecialchars($setup['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .setup-section { margin-bottom: 1.5rem; }
        .setup-section h4 { border-bottom: 2px solid #dee2e6; padding-bottom: 0.5rem; margin-bottom: 1rem; }
        dt { font-weight: bold; }
        dd { margin-bottom: .5rem; }
    </style>
</head>
<body>
<?php require 'header.php'; ?>
<div class="container mt-3">
    
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2><?php echo htmlspecialchars($setup['model_name']); ?></h2>
            <h4 class="text-muted"><?php echo htmlspecialchars($setup['name']); ?></h4>
        </div>
        <a href="setup_form.php?setup_id=<?php echo $setup['id']; ?>" class="btn btn-primary">Edit This Setup</a>
    </div>

    <div class="mb-3">
        <?php if ($setup['is_baseline']): ?>
            <span class="badge bg-warning text-dark">Baseline Setup</span>
        <?php endif; ?>
        <?php if (!empty($data['tags'])): ?>
            <?php foreach (explode(', ', $data['tags']) as $tag): ?>
                <span class="badge bg-secondary"><?php echo htmlspecialchars($tag); ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <hr>

    <!-- Peak Performance Section -->
    <?php if ($data['best_performance']): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <?php if (!empty($data['best_performance']['track_image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($data['best_performance']['track_image_url']); ?>" class="img-fluid rounded" alt="Track Layout">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h5 class="card-title">Peak Performance</h5>
                            <p class="mb-1">The fastest lap time achieved with this setup was a <strong><?php echo htmlspecialchars($data['best_performance']['best_lap_time']); ?></strong>.</p>
                            <p class="mb-0 text-muted">Set at <strong><?php echo htmlspecialchars($data['best_performance']['track_name']); ?></strong> during the event "<?php echo htmlspecialchars($data['best_performance']['event_name']); ?>".</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


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
            <h4>Drivetrain</h4>
            <dl class="row">
                <?php foreach($data['drivetrain'] as $key => $val) { if($key !== 'id' && $key !== 'setup_id') display_data(ucwords(str_replace('_', ' ', $key)), $val); } ?>
            </dl>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 setup-section">
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
        <div class="col-lg-6 setup-section">
            <h4>Electronics</h4>
            <dl class="row">
                <?php foreach($data['electronics'] as $key => $val) { if($key !== 'id' && $key !== 'setup_id') display_data(ucwords(str_replace('_', ' ', $key)), $val); } ?>
            </dl>
        </div>
    </div>

    <!-- Weight Distribution -->
    <?php if ($data['weights'] && $data['weight_calcs']): ?>
    <div class="row">
        <div class="col-12 setup-section">
            <h4>Weight Distribution</h4>
            <table class="table table-bordered text-center">
                <thead>
                    <tr>
                        <th></th>
                        <th>Left</th>
                        <th>Right</th>
                        <th>Total</th>
                        <th>Percent</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th>Front</th>
                        <td><?php echo htmlspecialchars($data['weights']['lf_weight']); ?> g</td>
                        <td><?php echo htmlspecialchars($data['weights']['rf_weight']); ?> g</td>
                        <td><?php echo number_format(floatval($data['weights']['lf_weight']) + floatval($data['weights']['rf_weight']), 1); ?> g</td>
                        <td><?php echo number_format($data['weight_calcs']['front_perc'], 1); ?>%</td>
                    </tr>
                    <tr>
                        <th>Rear</th>
                        <td><?php echo htmlspecialchars($data['weights']['lr_weight']); ?> g</td>
                        <td><?php echo htmlspecialchars($data['weights']['rr_weight']); ?> g</td>
                        <td><?php echo number_format(floatval($data['weights']['lr_weight']) + floatval($data['weights']['rr_weight']), 1); ?> g</td>
                        <td><?php echo number_format($data['weight_calcs']['rear_perc'], 1); ?>%</td>
                    </tr>
                    <tr>
                        <th>Total</th>
                        <td><?php echo number_format(floatval($data['weights']['lf_weight']) + floatval($data['weights']['lr_weight']), 1); ?> g</td>
                        <td><?php echo number_format(floatval($data['weights']['rf_weight']) + floatval($data['weights']['rr_weight']), 1); ?> g</td>
                        <td><strong><?php echo number_format($data['weight_calcs']['total'], 1); ?> g</strong></td>
                        <td></td>
                    </tr>
                     <tr>
                        <th>Percent</th>
                        <td><?php echo number_format($data['weight_calcs']['left_perc'], 1); ?>%</td>
                        <td><?php echo number_format($data['weight_calcs']['right_perc'], 1); ?>%</td>
                        <td></td>
                        <td><strong>Cross:</strong> <?php echo number_format($data['weight_calcs']['cross_lf_rr_perc'], 1); ?>%</td>
                    </tr>
                </tbody>
            </table>
            <?php if(!empty($data['weights']['notes'])): ?>
            <p class="mt-2"><strong>Notes:</strong> <?php echo htmlspecialchars($data['weights']['notes']); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>