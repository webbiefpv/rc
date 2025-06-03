<?php
require 'db_config.php';
require 'auth.php';
requireLogin();

$setup_id = $_GET['setup_id'];
$user_id = $_SESSION['user_id'];

// Verify setup belongs to user
$stmt = $pdo->prepare("SELECT s.*, m.user_id FROM setups s JOIN models m ON s.model_id = m.id WHERE s.id = ?");
$stmt->execute([$setup_id]);
$setup = $stmt->fetch();
if (!$setup || $setup['user_id'] != $user_id) {
	header('Location: index.php');
	exit;
}

// Fetch all data
$tables = [
	'front_suspension' => ['ackermann', 'arms', 'bumpsteer', 'droop_shims', 'kingpin_fluid', 'ride_height', 'arm_shims', 'springs', 'steering_blocks', 'steering_limiter', 'track_wheel_shims', 'notes'],
	'rear_suspension' => ['axle_height', 'centre_pivot_ball', 'centrecntre_pivot_fluid', 'droop', 'rear_pod_shims', 'rear_spring', 'ride_height', 'side_bands_lr', 'notes'],
	'drivetrain' => ['axle_type', 'drive_ratio', 'gear_pitch', 'rollout', 'spur'],
	'body_chassis' => ['battery_position', 'body', 'body_mounting', 'chassis', 'electronics_layout', 'motor_position', 'motor_shims', 'rear_wing', 'screw_turn_buckles', 'servo_position', 'weight_balance_fr', 'weight_total', 'weights'],
	'electronics' => ['battery', 'battery_c_rating', 'battery_brand', 'capacity', 'model', 'charging_notes', 'esc_brand', 'esc_model', 'motor_kv_constant', 'motor_brand', 'motor_model', 'motor_timing', 'motor_wind', 'radio_brand', 'radio_model', 'receiver_model', 'servo_brand', 'servo_model', 'transponder_id'],
	'esc_settings' => ['boost_activation', 'boost_rpm_end', 'boost_rpm_start', 'boost_ramp', 'boost_timing', 'brake_curve', 'brake_drag', 'brake_frequency', 'brake_initial_strength', 'race_mode', 'throttle_curve', 'throttle_frequency', 'throttle_initial_strength', 'throttle_neutral_range', 'throttle_strength', 'turbo_activation_method', 'turbo_delay', 'turbo_ramp', 'turbo_timing'],
	'comments' => ['comment']
];

// Tires (front and rear)
$fields = ['tire_brand', 'tire_compound', 'wheel_brand_type', 'tire_additive', 'tire_additive_area', 'tire_additive_time', 'tire_diameter', 'tire_side_wall_glue'];
$tires_fields = [];
foreach ($fields as $field) {
	$tires_fields[] = "front_$field";
	$tires_fields[] = "rear_$field";
}

// Collect all fields
$all_fields = ['setup_name' => $setup['name']];
foreach ($tables as $table => $fields) {
	$stmt = $pdo->prepare("SELECT * FROM $table WHERE setup_id = ?");
	$stmt->execute([$setup_id]);
	$row = $stmt->fetch();
	foreach ($fields as $field) {
		$all_fields["{$table}_{$field}"] = $row[$field] ?? '';
	}
}

// Tires
$stmt = $pdo->prepare("SELECT * FROM tires WHERE setup_id = ? AND position = ?");
$stmt->execute([$setup_id, 'front']);
$front_tires = $stmt->fetch();
$stmt->execute([$setup_id, 'rear']);
$rear_tires = $stmt->fetch();
foreach ($fields as $field) {
	$all_fields["front_tires_{$field}"] = $front_tires[$field] ?? '';
	$all_fields["rear_tires_{$field}"] = $rear_tires[$field] ?? '';
}

// Generate CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="setup_' . $setup_id . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, array_keys($all_fields));
fputcsv($output, array_values($all_fields));
fclose($output);
exit;
?>