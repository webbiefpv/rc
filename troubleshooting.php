<?php
require 'db_config.php';
require 'auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Setup Troubleshooting - Pan Car Setup App</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="styles.css">
</head>
<body>
<?php
require 'header.php';

// Array of handling problems and potential solutions for 1/12 Minis
$troubleshooting_guide = [
    'Understeer / Push on Corner Entry' => [
        'Softer front tires (lower shore).',
        'Move battery position forward.',
        'Use thinner kingpin oil.',
        'Use softer side springs.',
        'Reduce front track width (move wheel shims inboard).',
        'Reduce caster angle.'
    ],
    'Understeer / Push on Corner Exit (On-Power)' => [
        'Softer side springs.',
        'Use more rear droop.',
        'Move battery position forward.',
        'Use softer rear tires.',
        'Ensure rear axle is spinning freely.'
    ],
    'Oversteer / Loose on Corner Entry' => [
        'Harder front tires (higher shore).',
        'Use thicker kingpin oil.',
        'Move battery position rearward.',
        'Use harder side springs.',
        'Increase front track width (move wheel shims outboard).'
    ],
    'Oversteer / Loose on Corner Exit (On-Power)' => [
        'Harder side springs or use side bands.',
        'Use less rear droop.',
        'Move battery position rearward.',
        'Apply less aggressive tire additive to rear tires.',
        'Check for rear pod tweak; ensure it is balanced.'
    ],
    'Lacks Forward Traction' => [
        'Softer rear tires (lower shore).',
        'Use more rear droop.',
        'Soften side springs.',
        'Move battery position further back.',
        'Ensure ride height is not too low, causing chassis to drag.'
    ],
    'Car feels "edgy" or nervous' => [
        'Use thicker kingpin oil.',
        'Increase caster angle.',
        'Use harder side springs.',
        'Check for chassis tweak and ensure pod is free.',
        'Widen front and/or rear track width for more stability.'
    ],
    'Car rolls too much in corners' => [
        'Use harder side springs or add side bands.',
        'Use thicker fluid in side dampers (if applicable).',
        'Lower the overall ride height.',
        'Use a transverse battery position instead of inline.'
    ],
    'Car is slow on the straights' => [
        'Check rollout calculation. You may need a larger pinion or smaller spur gear.',
        'Ensure all bearings are clean and spinning freely.',
        'Check for any binding in the drivetrain or suspension.',
        'Ensure motor timing and ESC settings are optimized.'
    ]
];
ksort($troubleshooting_guide);
?>
<div class="container mt-3">
	<h1>Setup Troubleshooting Guide</h1>
	<p>Find a handling problem you are experiencing and click on it to see a list of potential setup changes that may help fix it.</p>

    <div class="accordion" id="troubleshootingAccordion">
        <?php foreach ($troubleshooting_guide as $problem => $solutions): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $problem); ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $problem); ?>" aria-expanded="false" aria-controls="collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $problem); ?>">
                        <strong><?php echo htmlspecialchars($problem); ?></strong>
                    </button>
                </h2>
                <div id="collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $problem); ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $problem); ?>" data-bs-parent="#troubleshootingAccordion">
                    <div class="accordion-body">
                        <ul class="list-group">
                            <?php foreach ($solutions as $solution): ?>
                                <li class="list-group-item"><?php echo htmlspecialchars($solution); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>