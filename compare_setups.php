<?php
require 'db_config.php'; // Your database configuration
require 'auth.php';     // Your authentication functions
requireLogin();         // Ensure the user is logged in

$user_id = $_SESSION['user_id'];
$setup_details_1 = null; // Will hold all data for setup 1
$setup_details_2 = null; // Will hold all data for setup 2
$error_message = '';
$setup1_name_display = ''; // For displaying the name of setup 1
$setup2_name_display = ''; // For displaying the name of setup 2

// Fetch all setups for the current user for the dropdowns
$stmt_all_setups = $pdo->prepare("
    SELECT s.id, s.name as setup_name, m.name as model_name 
    FROM setups s 
    JOIN models m ON s.model_id = m.id 
    WHERE m.user_id = ? 
    ORDER BY m.name, s.name
");
$stmt_all_setups->execute([$user_id]);
$available_setups = $stmt_all_setups->fetchAll(PDO::FETCH_ASSOC);

// Helper function to get all details for a given setup_id
function getSetupDetails($pdo, $setup_id, $user_id) {
    $details = [];

    // First, verify the setup belongs to the user and get its name and model name
    $stmt_main = $pdo->prepare("
        SELECT s.name as setup_name, m.name as model_name, s.model_id 
        FROM setups s 
        JOIN models m ON s.model_id = m.id 
        WHERE s.id = ? AND m.user_id = ?
    ");
    $stmt_main->execute([$setup_id, $user_id]);
    $main_info = $stmt_main->fetch(PDO::FETCH_ASSOC);

    if (!$main_info) {
        return null; // Setup not found or doesn't belong to user
    }
    $details['setup_name'] = $main_info['setup_name'];
    $details['model_name'] = $main_info['model_name'];
    $details['model_id'] = $main_info['model_id']; // Store model_id if needed later

    // Define tables and their columns to fetch (similar to export_csv.php or setup_form.php)
    // You might want to create a more structured way to define these fields globally if used in multiple places
    $data_tables = [
        'front_suspension' => ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims', 'notes'],
        'rear_suspension'  => ['axle_height', 'centre_pivot_ball', 'centre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_bands_lr', 'notes'],
        'drivetrain'       => ['axle_type', 'drive_ratio', 'gear_pitch', 'rollout', 'spur'],
        'body_chassis'     => ['battery_position', 'body', 'body_mounting', 'chassis', 'electronics_layout', 'motor_position', 'motor_shims', 'rear_wing', 'screw_turn_buckles', 'servo_position', 'weight_balance_fr', 'weight_total', 'weights'],
        'electronics'      => ['battery', 'battery_c_rating', 'battery_brand', 'capacity', 'model', /* 'charging_notes' is a textarea, handle separately or ensure it's in this list */ 'esc_brand', 'esc_model', 'motor_kv_constant', 'motor_brand', 'motor_model', 'motor_timing', 'motor_wind', 'radio_brand', 'radio_model', 'receiver_model', 'servo_brand', 'servo_model', 'transponder_id', 'charging_notes'],
        'esc_settings'     => ['boost_activation', 'boost_rpm_end', 'boost_rpm_start', 'boost_ramp', 'boost_timing', 'brake_curve', 'brake_drag', 'brake_frequency', 'brake_initial_strength', 'race_mode', 'throttle_curve', 'throttle_frequency', 'throttle_initial_strength', 'throttle_neutral_range', 'throttle_strength', 'turbo_activation_method', 'turbo_delay', 'turbo_ramp', 'turbo_timing'],
        'comments'         => ['comment']
    ];

    foreach ($data_tables as $table_name => $fields) {
        $stmt = $pdo->prepare("SELECT * FROM $table_name WHERE setup_id = ?");
        $stmt->execute([$setup_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach ($fields as $field) {
                $details[$table_name . '_' . $field] = $row[$field] ?? '';
            }
        } else {
            foreach ($fields as $field) {
                $details[$table_name . '_' . $field] = ''; // Set empty if no record for this table
            }
        }
    }

    // Tires (front and rear)
    $tire_fields = ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue'];
    $stmt_tires_front = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = 'front'");
    $stmt_tires_front->execute([$setup_id]);
    $front_tires_data = $stmt_tires_front->fetch(PDO::FETCH_ASSOC);

    $stmt_tires_rear = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = 'rear'");
    $stmt_tires_rear->execute([$setup_id]);
    $rear_tires_data = $stmt_tires_rear->fetch(PDO::FETCH_ASSOC);

    foreach ($tire_fields as $field) {
        $details['tires_front_' . $field] = $front_tires_data[$field] ?? '';
        $details['tires_rear_' . $field] = $rear_tires_data[$field] ?? '';
    }

    return $details;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $setup_id_1 = $_POST['setup_id_1'] ?? null;
    $setup_id_2 = $_POST['setup_id_2'] ?? null;

    if (empty($setup_id_1) || empty($setup_id_2)) {
        $error_message = 'Please select two setups to compare.';
    } elseif ($setup_id_1 == $setup_id_2) {
        $error_message = 'Please select two different setups.';
    } else {
        $setup_details_1 = getSetupDetails($pdo, $setup_id_1, $user_id);
        $setup_details_2 = getSetupDetails($pdo, $setup_id_2, $user_id);

        if (!$setup_details_1 || !$setup_details_2) {
            $error_message = 'One or both selected setups could not be found or accessed.';
            // Clear details if one fails to prevent partial display
            $setup_details_1 = null;
            $setup_details_2 = null;
        } else {
            $setup1_name_display = htmlspecialchars($setup_details_1['model_name'] . ' - ' . $setup_details_1['setup_name']);
            $setup2_name_display = htmlspecialchars($setup_details_2['model_name'] . ' - ' . $setup_details_2['setup_name']);
        }
    }
}

// This is the list of all parameters we want to display in the comparison table.
// The keys should match the keys in $setup_details_1 and $setup_details_2
// The values are the human-readable labels for the table.
$comparison_fields_structure = [
    'Front Suspension' => [
        'front_suspension_ackermann' => 'Ackermann',
        'front_suspension_arms' => 'Arms',
        'front_suspension_bumpsteer' => 'Bumpsteer',
        'front_suspension_droop_shims' => 'Droop Shims',
        'front_suspension_kingpin_fluid' => 'Kingpin Fluid',
        'front_suspension_ride_height' => 'Ride Height (Front)',
        'front_suspension_arm_shims' => 'Arm Shims',
        'front_suspension_springs' => 'Springs (Front)',
        'front_suspension_steering_blocks' => 'Steering Blocks',
        'front_suspension_steering_limiter' => 'Steering Limiter',
        'front_suspension_track_wheel_shims' => 'Track/Wheel Shims',
        'front_suspension_notes' => 'Notes (Front Susp.)',
    ],
    'Rear Suspension' => [
        'rear_suspension_axle_height' => 'Axle Height',
        'rear_suspension_centre_pivot_ball' => 'Centre Pivot Ball',
        'rear_suspension_centre_pivot_fluid' => 'Centre Pivot Fluid',
        'rear_suspension_droop' => 'Droop (Rear)',
        'rear_suspension_rear_pod_shims' => 'Rear Pod Shims',
        'rear_suspension_rear_spring' => 'Rear Spring',
        'rear_suspension_ride_height' => 'Ride Height (Rear)',
        'rear_suspension_side_bands_lr' => 'Side Bands L/R',
        'rear_suspension_notes' => 'Notes (Rear Susp.)',
    ],
    'Front Tires' => [
        'tires_front_tire_brand' => 'Tire Brand',
        'tires_front_tire_compound' => 'Tire Compound',
        'tires_front_wheel_brand_type' => 'Wheel Brand/Type',
        'tires_front_tire_additive' => 'Tire Additive',
        'tires_front_tire_additive_area' => 'Additive Area',
        'tires_front_tire_additive_time' => 'Additive Time',
        'tires_front_tire_diameter' => 'Tire Diameter',
        'tires_front_tire_side_wall_glue' => 'Sidewall Glue',
    ],
    'Rear Tires' => [
        'tires_rear_tire_brand' => 'Tire Brand',
        'tires_rear_tire_compound' => 'Tire Compound',
        'tires_rear_wheel_brand_type' => 'Wheel Brand/Type',
        'tires_rear_tire_additive' => 'Tire Additive',
        'tires_rear_tire_additive_area' => 'Additive Area',
        'tires_rear_tire_additive_time' => 'Additive Time',
        'tires_rear_tire_diameter' => 'Tire Diameter',
        'tires_rear_tire_side_wall_glue' => 'Sidewall Glue',
    ],
    'Drivetrain' => [
        'drivetrain_axle_type' => 'Axle Type',
        'drivetrain_drive_ratio' => 'Drive Ratio',
        'drivetrain_gear_pitch' => 'Gear Pitch',
        'drivetrain_rollout' => 'Rollout',
        'drivetrain_spur' => 'Spur',
    ],
    'Body & Chassis' => [
        'body_chassis_battery_position' => 'Battery Position',
        'body_chassis_body' => 'Body',
        'body_chassis_body_mounting' => 'Body Mounting',
        'body_chassis_chassis' => 'Chassis',
        'body_chassis_electronics_layout' => 'Electronics Layout',
        'body_chassis_motor_position' => 'Motor Position',
        'body_chassis_motor_shims' => 'Motor Shims',
        'body_chassis_rear_wing' => 'Rear Wing',
        'body_chassis_screw_turn_buckles' => 'Screw/Turnbuckles',
        'body_chassis_servo_position' => 'Servo Position',
        'body_chassis_weight_balance_fr' => 'Weight Balance F/R',
        'body_chassis_weight_total' => 'Weight Total',
        'body_chassis_weights' => 'Weights Added',
    ],
    'Electronics' => [
        'electronics_battery' => 'Battery Type',
        'electronics_battery_c_rating' => 'Battery C Rating',
        'electronics_battery_brand' => 'Battery Brand',
        'electronics_capacity' => 'Capacity (mAh)',
        'electronics_model' => 'Battery Model', // Assuming this is battery model
        'electronics_esc_brand' => 'ESC Brand',
        'electronics_esc_model' => 'ESC Model',
        'electronics_motor_kv_constant' => 'Motor KV',
        'electronics_motor_brand' => 'Motor Brand',
        'electronics_motor_model' => 'Motor Model',
        'electronics_motor_timing' => 'Motor Timing',
        'electronics_motor_wind' => 'Motor Wind',
        'electronics_radio_brand' => 'Radio Brand',
        'electronics_radio_model' => 'Radio Model',
        'electronics_receiver_model' => 'Receiver Model',
        'electronics_servo_brand' => 'Servo Brand',
        'electronics_servo_model' => 'Servo Model',
        'electronics_transponder_id' => 'Transponder ID',
        'electronics_charging_notes' => 'Charging Notes',
    ],
    'ESC Settings' => [
        'esc_settings_boost_activation' => 'Boost Activation',
        'esc_settings_boost_rpm_end' => 'Boost RPM End',
        'esc_settings_boost_rpm_start' => 'Boost RPM Start',
        'esc_settings_boost_ramp' => 'Boost Ramp',
        'esc_settings_boost_timing' => 'Boost Timing',
        'esc_settings_brake_curve' => 'Brake Curve',
        'esc_settings_brake_drag' => 'Drag Brake',
        'esc_settings_brake_frequency' => 'Brake Frequency',
        'esc_settings_brake_initial_strength' => 'Initial Brake',
        'esc_settings_race_mode' => 'Race Mode',
        'esc_settings_throttle_curve' => 'Throttle Curve',
        'esc_settings_throttle_frequency' => 'Throttle Frequency',
        'esc_settings_throttle_initial_strength' => 'Initial Throttle',
        'esc_settings_throttle_neutral_range' => 'Neutral Range',
        'esc_settings_throttle_strength' => 'Throttle Strength',
        'esc_settings_turbo_activation_method' => 'Turbo Activation',
        'esc_settings_turbo_delay' => 'Turbo Delay',
        'esc_settings_turbo_ramp' => 'Turbo Ramp',
        'esc_settings_turbo_timing' => 'Turbo Timing',
    ],
    'Comments' => [
        'comments_comment' => 'General Comments',
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compare Setups - Pan Car Setup App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .highlight-diff {
            background-color: #fff3cd; /* A light yellow to highlight differences */
            font-weight: bold;
        }
        .comparison-table th, .comparison-table td {
            vertical-align: middle;
            font-size: 0.9rem; /* Slightly smaller font for dense table */
        }
        .sticky-header th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa; /* Match Bootstrap table header */
            z-index: 10;
        }
    </style>
</head>
<body>
<?php require 'header.php'; // Your common header ?>

<div class="container-fluid mt-3"> <h1>Compare Setups</h1>


    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="compare_setups.php" class="mb-4 p-3 bg-light border rounded">
        <div class="row align-items-end">
            <div class="col-md-5 mb-2">
                <label for="setup_id_1" class="form-label fw-bold">Select Setup 1:</label>
                <select name="setup_id_1" id="setup_id_1" class="form-select" required>
                    <option value="">-- Choose Setup 1 --</option>
                    <?php foreach ($available_setups as $setup): ?>
                        <option value="<?php echo $setup['id']; ?>" <?php echo (isset($_POST['setup_id_1']) && $_POST['setup_id_1'] == $setup['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($setup['model_name'] . ' - ' . $setup['setup_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5 mb-2">
                <label for="setup_id_2" class="form-label fw-bold">Select Setup 2:</label>
                <select name="setup_id_2" id="setup_id_2" class="form-select" required>
                    <option value="">-- Choose Setup 2 --</option>
                    <?php foreach ($available_setups as $setup): ?>
                        <option value="<?php echo $setup['id']; ?>" <?php echo (isset($_POST['setup_id_2']) && $_POST['setup_id_2'] == $setup['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($setup['model_name'] . ' - ' . $setup['setup_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <button type="submit" class="btn btn-primary w-100">Compare</button>
            </div>
        </div>
    </form>

    <?php if ($setup_details_1 && $setup_details_2): ?>
        <h2 class="mt-4">Comparison Result:</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-striped comparison-table">
                <thead class="sticky-header">
                <tr>
                    <th>Parameter Group</th>
                    <th>Parameter</th>
                    <th><?php echo $setup1_name_display; ?></th>
                    <th><?php echo $setup2_name_display; ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($comparison_fields_structure as $group_name => $fields_in_group): ?>
                    <?php $first_in_group = true; ?>
                    <?php foreach ($fields_in_group as $field_key => $field_label): ?>
                        <?php
                        $value1 = $setup_details_1[$field_key] ?? '';
                        $value2 = $setup_details_2[$field_key] ?? '';
                        $diff_class = (trim((string)$value1) !== trim((string)$value2)) ? 'highlight-diff' : '';
                        ?>
                        <tr>
                            <?php if ($first_in_group): ?>
                                <td rowspan="<?php echo count($fields_in_group); ?>" class="fw-bold align-middle">
                                    <?php echo htmlspecialchars($group_name); ?>
                                </td>
                                <?php $first_in_group = false; ?>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($field_label); ?></td>
                            <td class="<?php echo $diff_class; ?>"><?php echo nl2br(htmlspecialchars($value1)); ?></td>
                            <td class="<?php echo $diff_class; ?>"><?php echo nl2br(htmlspecialchars($value2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>