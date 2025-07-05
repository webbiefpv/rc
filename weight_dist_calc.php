<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// --- Handle form submission to SAVE weights ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_weights') {
    $setup_id = intval($_POST['setup_id']);
    $lf_weight = !empty($_POST['lf_weight']) ? floatval($_POST['lf_weight']) : null;
    $rf_weight = !empty($_POST['rf_weight']) ? floatval($_POST['rf_weight']) : null;
    $lr_weight = !empty($_POST['lr_weight']) ? floatval($_POST['lr_weight']) : null;
    $rr_weight = !empty($_POST['rr_weight']) ? floatval($_POST['rr_weight']) : null;
    $notes = trim($_POST['notes']);

    // Security check: ensure the setup belongs to the user
    $stmt_check = $pdo->prepare("SELECT m.user_id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ?");
    $stmt_check->execute([$setup_id]);
    $owner = $stmt_check->fetch();

    if (!$owner || $owner['user_id'] != $user_id) {
        $message = '<div class="alert alert-danger">Error: You do not have permission to save to this setup.</div>';
    } elseif (empty($setup_id)) {
        $message = '<div class="alert alert-danger">Error: Please select a setup to save the weights to.</div>';
    } else {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to either create or update the record
        $sql = "
            INSERT INTO weight_distribution (setup_id, user_id, lf_weight, rf_weight, lr_weight, rr_weight, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                lf_weight = VALUES(lf_weight), rf_weight = VALUES(rf_weight),
                lr_weight = VALUES(lr_weight), rr_weight = VALUES(rr_weight), notes = VALUES(notes)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$setup_id, $user_id, $lf_weight, $rf_weight, $lr_weight, $rr_weight, $notes]);
        
        $message = '<div class="alert alert-success">Weight distribution saved successfully to the selected setup!</div>';
    }
}

// Fetch user's models for the dropdown
$stmt_models = $pdo->prepare("SELECT id, name FROM models WHERE user_id = ? ORDER BY name");
$stmt_models->execute([$user_id]);
$models_list = $stmt_models->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weight Distribution Calculator - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php require 'header.php'; ?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Weight Distribution</h1>
        <button class="btn btn-sm btn-outline-secondary" onclick="resetCalculator()">Reset All</button>
    </div>
    
    <?php if ($message) echo $message; ?>

    <div class="row g-4">
        <!-- Input Column -->
        <div class="col-lg-5">
            <h4>Corner Weights (g)</h4>
            <div class="row row-cols-2 g-3">
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <label for="lf-input" class="form-label fw-bold">Left Front</label>
                            <input type="number" step="0.1" id="lf-input" class="form-control form-control-lg text-center" oninput="calculateWeights()">
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <label for="rf-input" class="form-label fw-bold">Right Front</label>
                            <input type="number" step="0.1" id="rf-input" class="form-control form-control-lg text-center" oninput="calculateWeights()">
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <label for="lr-input" class="form-label fw-bold">Left Rear</label>
                            <input type="number" step="0.1" id="lr-input" class="form-control form-control-lg text-center" oninput="calculateWeights()">
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <label for="rr-input" class="form-label fw-bold">Right Rear</label>
                            <input type="number" step="0.1" id="rr-input" class="form-control form-control-lg text-center" oninput="calculateWeights()">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Column -->
        <div class="col-lg-7">
            <h4>Live Results</h4>
            <div class="card">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <span class="text-muted">Total Weight</span>
                        <h3 id="total-weight" class="fw-bold">0.0 g</h3>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Front: <strong id="front-weight">0.0 g</strong></span>
                            <span>Rear: <strong id="rear-weight">0.0 g</strong></span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div id="front-percentage" class="progress-bar" role="progressbar" style="width: 50%">50%</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Left: <strong id="left-weight">0.0 g</strong></span>
                            <span>Right: <strong id="right-weight">0.0 g</strong></span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div id="left-percentage" class="progress-bar bg-success" role="progressbar" style="width: 50%">50%</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Cross (LF+RR): <strong id="cross-weight-lf-rr">0.0 g</strong></span>
                            <span>Cross (RF+LR): <strong id="cross-weight-rf-lr">0.0 g</strong></span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div id="cross-percentage-lf-rr" class="progress-bar bg-info" role="progressbar" style="width: 50%">50%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h5>Load / Save Weight Distribution</h5>
        </div>
        <div class="card-body">
            <form id="save-form" method="POST">
                <input type="hidden" name="action" value="save_weights">
                <!-- Hidden inputs to be populated by JS before submitting -->
                <input type="hidden" id="lf_weight_hidden" name="lf_weight">
                <input type="hidden" id="rf_weight_hidden" name="rf_weight">
                <input type="hidden" id="lr_weight_hidden" name="lr_weight">
                <input type="hidden" id="rr_weight_hidden" name="rr_weight">

                <div class="row g-2">
                    <div class="col-md-6">
                        <label for="model_id" class="form-label">1. Select Model</label>
                         <select class="form-select" id="model_id" name="model_id" onchange="fetchSetups(this.value)">
                            <option value="">-- Choose a Model --</option>
                            <?php foreach($models_list as $model): ?>
                                <option value="<?php echo $model['id']; ?>"><?php echo htmlspecialchars($model['name']); ?></option>
                            <?php endforeach; ?>
                         </select>
                    </div>
                    <div class="col-md-6">
                        <label for="setup_id" class="form-label">2. Select Setup to Load or Save to</label>
                        <select class="form-select" id="setup_id" name="setup_id" onchange="loadWeights(this.value)" required>
                            <option value="">-- Select a model first --</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="e.g., With shorty LiPo, brass front bulkhead..."></textarea>
                    </div>
                    <div class="col-12">
                        <button type="button" onclick="prepareAndSubmit()" class="btn btn-primary w-100">Save to Selected Setup</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function calculateWeights() {
        const lf = parseFloat(document.getElementById('lf-input').value) || 0;
        const rf = parseFloat(document.getElementById('rf-input').value) || 0;
        const lr = parseFloat(document.getElementById('lr-input').value) || 0;
        const rr = parseFloat(document.getElementById('rr-input').value) || 0;

        const totalWeight = lf + rf + lr + rr;
        const frontWeight = lf + rf;
        const rearWeight = lr + rr;
        const leftWeight = lf + lr;
        const rightWeight = rf + rr;
        const crossWeight_LF_RR = lf + rr;
        const crossWeight_RF_LR = rf + lr;

        document.getElementById('total-weight').textContent = totalWeight.toFixed(1) + ' g';
        document.getElementById('front-weight').textContent = frontWeight.toFixed(1) + ' g';
        document.getElementById('rear-weight').textContent = rearWeight.toFixed(1) + ' g';
        document.getElementById('left-weight').textContent = leftWeight.toFixed(1) + ' g';
        document.getElementById('right-weight').textContent = rightWeight.toFixed(1) + ' g';
        document.getElementById('cross-weight-lf-rr').textContent = crossWeight_LF_RR.toFixed(1) + ' g';
        document.getElementById('cross-weight-rf-lr').textContent = crossWeight_RF_LR.toFixed(1) + ' g';

        if (totalWeight > 0) {
            const frontPerc = (frontWeight / totalWeight) * 100;
            const leftPerc = (leftWeight / totalWeight) * 100;
            const crossPerc = (crossWeight_LF_RR / totalWeight) * 100;
            
            document.getElementById('front-percentage').style.width = frontPerc.toFixed(1) + '%';
            document.getElementById('front-percentage').textContent = frontPerc.toFixed(0) + '%';
            
            document.getElementById('left-percentage').style.width = leftPerc.toFixed(1) + '%';
            document.getElementById('left-percentage').textContent = leftPerc.toFixed(0) + '%';

            document.getElementById('cross-percentage-lf-rr').style.width = crossPerc.toFixed(1) + '%';
            document.getElementById('cross-percentage-lf-rr').textContent = crossPerc.toFixed(0) + '%';
        } else {
            document.getElementById('front-percentage').style.width = '50%';
            document.getElementById('front-percentage').textContent = '50%';
            document.getElementById('left-percentage').style.width = '50%';
            document.getElementById('left-percentage').textContent = '50%';
            document.getElementById('cross-percentage-lf-rr').style.width = '50%';
            document.getElementById('cross-percentage-lf-rr').textContent = '50%';
        }
    }

    function fetchSetups(modelId) {
        const setupSelect = document.getElementById('setup_id');
        setupSelect.innerHTML = '<option value="">Loading...</option>';
        if (!modelId) {
            setupSelect.innerHTML = '<option value="">-- Select a model first --</option>';
            return;
        }
        fetch(`ajax_get_setups.php?model_id=${modelId}`)
            .then(response => response.json())
            .then(data => {
                setupSelect.innerHTML = '<option value="">-- Select a setup --</option>';
                data.forEach(setup => {
                    const option = document.createElement('option');
                    option.value = setup.id;
                    option.textContent = setup.name + (setup.is_baseline ? ' ⭐' : '');
                    setupSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching setups:', error));
    }

    function loadWeights(setupId) {
        if (!setupId) {
            resetCalculator();
            return;
        }
        // This would require another AJAX helper file to fetch saved weights
        // For now, this function can be a placeholder or built out later.
        console.log("Loading weights for setup ID: " + setupId);
    }
    
    function prepareAndSubmit() {
        // Copy values from visible inputs to hidden inputs before submitting
        document.getElementById('lf_weight_hidden').value = document.getElementById('lf-input').value;
        document.getElementById('rf_weight_hidden').value = document.getElementById('rf-input').value;
        document.getElementById('lr_weight_hidden').value = document.getElementById('lr-input').value;
        document.getElementById('rr_weight_hidden').value = document.getElementById('rr-input').value;
        document.getElementById('save-form').submit();
    }

    function resetCalculator() {
        document.getElementById('lf-input').value = '';
        document.getElementById('rf-input').value = '';
        document.getElementById('lr-input').value = '';
        document.getElementById('rr-input').value = '';
        document.getElementById('notes').value = '';
        document.getElementById('setup_id').selectedIndex = 0;
        calculateWeights();
    }

    document.addEventListener('DOMContentLoaded', calculateWeights);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>