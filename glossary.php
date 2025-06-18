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
	<title>On-Road Setup Glossary - Pan Car Setup App</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="styles.css">
</head>
<body>
<?php
require 'header.php';

// Array of glossary terms and definitions, tailored for 1/12 Mini/Stock Cars (Mardave, Kamtec, etc.)
$glossary_terms = [
    'Ackermann' => 'The steering geometry that allows the inside front wheel to turn more sharply than the outside wheel. On a mini, this is adjusted by changing the mounting position of the steering links on the servo saver or steering blocks. Less Ackermann (more parallel steering) provides more aggressive turn-in.',
    'Anti-Dive' => 'A geometric adjustment on the front suspension to prevent the front end from diving under braking. On a mini, this is sometimes possible by shimming the front lower wishbone mounts to angle them, but it is not a common adjustment.',
    'Anti-Squat' => 'The geometry of the rear pod pivot. On a mini, this is not typically adjustable in the same way as LMP cars. It refers to the inherent resistance of the rear pod to "squat" or compress under acceleration.',
    'Battery Position' => 'The placement of the battery in the chassis. An inline position (lengthwise) promotes more chassis roll. A transverse position (widthwise) reduces roll. Moving the battery forward increases steering, while moving it rearward increases rear traction.',
    'Bump Steer' => 'The change in the front wheels\' toe angle as the suspension moves up and down. This is adjusted by adding or removing shims under the steering link ball stud on the steering block. The goal is to have zero bump steer, so the car tracks straight over bumps.',
    'Camber' => 'The vertical angle of the front tires. Negative camber means the top of the tire is tilted inwards. As these cars have no front suspension travel in the traditional sense, camber is usually fixed or not a primary tuning option.',
    'Caster' => 'The backwards angle of the kingpin. On a Mardave or similar mini, this is adjusted by putting shims or washers under the front of the lower wishbones to tilt the kingpin back. More caster increases stability, especially on corner exit.',
    'Center Pivot' => 'The main pivot point, usually a single bolt with an O-ring or spring, connecting the rear pod to the main T-piece of the chassis. Tightening the pivot nut reduces chassis roll and sharpens steering response, while loosening it allows more roll and can increase side bite.',
    'Droop' => 'The amount the rear pod is allowed to drop below the chassis plate. This is adjusted via screws on the rear pod plate that contact the main chassis. More droop can help on bumpy tracks, while less droop provides a quicker steering response.',
    'Kingpin Oil / Fluid' => 'The thick oil or grease used to dampen the front suspension. The kingpin is the main vertical pin the front steering block pivots on. Thicker oil slows down suspension movement, making the car feel smoother, which is ideal for high-grip carpet tracks.',
    'Loose / Oversteer' => 'A handling condition where the rear of the car loses traction and slides out during cornering.',
    'Pinion Gear' => 'The small gear attached directly to the motor shaft. A larger pinion (more teeth) gives a higher top speed. A smaller pinion gives more acceleration.',
    'Pre-Load' => 'The initial tension on the side springs, adjusted by tightening or loosening the nuts that hold them. More pre-load increases the force needed to make the chassis roll, making the car feel more responsive but potentially edgy.',
    'Push / Understeer' => 'A handling condition where the front tires lose traction, causing the car to run wide in a corner.',
    'Rear Droop' => 'Less rear droop results in more on-power steering but can reduce forward traction. More rear droop allows the pod to rotate more freely on its pivot, which can provide more forward traction at the cost of some on-power steering.',
    'Ride Height' => 'The distance between the bottom of the chassis and the ground. This is a critical adjustment, typically set very low (3-4mm) for flat carpet tracks. It\'s adjusted by changing tire diameter or using shims on the front and rear axles.',
    'Rollout' => 'The distance the car travels for one full revolution of the motor. It is calculated from tire diameter and gear ratio, and is used to tune gearing for different track sizes.',
    'Shock Springs' => 'On Mardave/Kamtec style minis, this term almost always refers to the Side Springs. These cars do not typically have traditional oil-filled shock absorbers. Their "dampening" comes from the center pivot, side springs, and kingpin fluid.',
    'Shore' => 'A measure of tire hardness (durometer). For this class, common compounds are in the 30-47 shore range. A lower number is a softer tire, providing more grip but wearing faster.',
    'Side Springs' => 'The two main springs that control the side-to-side roll of the rear pod. These are the primary tool for adjusting the car\'s balance. Stiffer springs make the car more responsive and corner flatter. Softer springs allow more roll and can generate more grip.',
    'Spur Gear' => 'The large plastic gear that is attached to the rear axle. A larger spur gear (more teeth) provides more acceleration. A smaller spur gear provides a higher top speed.',
    'Steering Lock (on car)' => 'The maximum physical angle that the front wheels can turn. This is often limited by the chassis cut-out or by adding stops to prevent the wheels from touching the chassis at full lock.',
    'Steering Throw (on Controller)' => 'A setting on the radio transmitter (also known as End Point Adjustment - EPA) that limits the signal sent to the steering servo. This is used to adjust the car\'s steering angle electronically.',
    'Tire Additive' => 'A chemical compound applied to tires to soften the rubber and increase grip. The choice of additive and how long it is applied for is a critical tuning option for carpet racing.',
    'Tire Prep' => 'The process of cleaning the tires and applying additive before a run. The specific process (how much additive, how long it soaks, whether it is wiped off or not) greatly affects grip levels.',
    'Track Width (Front & Rear)' => 'The distance between the outside of the tires on an axle. This is adjusted by adding or removing shims/spacers on the front kingpins or rear axle. A wider track width generally increases stability but can reduce grip.',
    'Toe' => 'The angle of the front wheels when viewed from above. Usually set to a slight amount of toe-out (fronts of wheels pointing away from the chassis) to provide responsive steering. Rear toe is not adjustable on most mini chassis.',
    'Tweak' => 'An imbalance in the rear pod where the side springs apply unequal pressure, causing the car to handle differently when turning left versus right. This is checked by seeing if the pod lifts evenly on both sides and is adjusted with the side spring nuts.',
    'Weight Distribution' => 'How the car\'s weight is balanced. This is mainly adjusted by the Battery Position. Moving weight affects how the car transfers weight, influencing front versus rear grip.'
];
// Sort the array alphabetically by the term (the key)
ksort($glossary_terms);
?>
<div class="container mt-3">
	<h1>On-Road Setup Glossary</h1>
	<p>A quick reference for common 1/12 Mini setup terms. Click any term to see its definition.</p>

    <div class="accordion" id="glossaryAccordion">
        <?php foreach ($glossary_terms as $term => $definition): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>" aria-expanded="false" aria-controls="collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>">
                        <strong><?php echo htmlspecialchars($term); ?></strong>
                    </button>
                </h2>
                <div id="collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>" data-bs-parent="#glossaryAccordion">
                    <div class="accordion-body">
                        <?php echo nl2br(htmlspecialchars($definition)); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>