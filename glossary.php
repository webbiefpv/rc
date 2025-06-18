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

// Array of glossary terms and definitions
$glossary_terms = [
    'Ackermann' => 'The effect of the inside front wheel turning at a sharper angle than the outside front wheel. More Ackermann (less difference between the wheels) generally gives smoother steering but less initial turn-in. Less Ackermann provides more aggressive initial steering.',
    'Anti-Dive' => 'The angle of the front inner suspension pins when viewed from the side. Angling the pins downward at the front (front mount lower than the rear) creates anti-dive, which helps prevent the front of the car from diving excessively under braking. This can increase corner-entry steering but may reduce front grip over bumps while braking.',
    'Anti-Squat' => 'The angle of the rear inner suspension pins when viewed from the side. Angling the pins upward at the front (front mount higher than the rear) creates anti-squat, which reduces how much the rear of the car squats (compresses) under acceleration. More anti-squat provides better forward traction on high-grip surfaces but can reduce rear grip on bumpy sections.',
    'Battery Position' => 'The placement of the battery in the chassis. An inline position (lengthwise) generally promotes more chassis roll. A transverse position (widthwise) reduces chassis roll. Moving the battery forward increases steering response, while moving it rearward increases rear traction.',
    'Bump Steer' => 'The change in the front wheels\' toe angle as the suspension compresses and extends. Ideally, you want minimal bump steer. Bump-in (toeing in on compression) can make the car more stable, while bump-out (toeing out on compression) can make it feel twitchy over bumps.',
    'Camber' => 'The angle of the wheels in relation to the ground when viewed from the front. Negative camber means the top of the tire is tilted inwards. A small amount of negative camber (-1 to -2 degrees) is usually used to maximize the tire\'s contact patch during cornering.',
    'Camber Gain' => 'The rate at which the camber angle changes as the suspension is compressed. More camber gain means the wheel gains more negative camber as it moves upward, which can increase mid-corner grip. It is adjusted by changing the length and angle of the upper camber link.',
    'Caster' => 'The angle of the steering kingpin when viewed from the side of the car. More caster (kingpin tilted further back) increases steering stability, especially at high speed, but can make the steering feel less responsive. Less caster improves initial turn-in.',
    'Center Pivot' => 'The main pivot point connecting the rear pod to the main chassis. The dampening fluid or grease used here controls how quickly the rear pod can pivot, affecting rear grip on-power and off-power.',
    'Droop' => 'The amount of downward suspension travel. In pan cars, this is often controlled by shims under the front arms or screws in the rear pod. More droop allows for more weight transfer, which can increase grip on bumpy or lower-traction surfaces. Less droop provides a quicker steering response on high-grip, smooth surfaces.',
    'Kingpin Oil / Fluid' => 'The oil or grease used to dampen the front suspension on a pan car. The kingpin is the main vertical pin in the front steering block. Thicker oil slows down the front suspension movement, making the car smoother and less reactive, which is good for high-grip conditions. Thinner oil allows for a quicker response, which can be better on lower-grip tracks.',
    'Loose / Oversteer' => 'A handling condition where the rear of the car loses traction before the front during cornering. This causes the car to turn more sharply than intended, potentially spinning out. Often described as the rear end feeling "loose".',
    'Pinion Gear' => 'The small gear that is attached directly to the motor shaft. A larger pinion gear (more teeth) will increase your rollout for more top speed. A smaller pinion gear will decrease your rollout for more acceleration.',
    'Pre-Load' => 'The initial tension applied to a spring when the suspension is at its maximum extension. Adding pre-load (usually by turning a shock collar or adding clips) raises the ride height and slightly stiffens the initial part of the suspension travel without changing the overall spring rate.',
    'Push / Understeer' => 'A handling condition where the front tires lose traction before the rear during cornering. This causes the car to turn less than intended and travel wide of the desired cornering arc. Often described as a "push".',
    'Rear Droop' => 'Explain that Less rear droop More on-power steering and less forward traction and More allows the pod to rotate rather than tires. Less on power steering more forward traction',
    'Ride Height' => 'The distance between the bottom of the chassis and the ground. A lower ride height generally provides more grip and stability by lowering the center of gravity, but can cause the chassis to bottom out on bumps. A higher ride height can be better for bumpy tracks.',
    'Rollout' => 'The distance the car travels for one full revolution of the motor. It is calculated based on tire diameter and gear ratio. A lower rollout provides more acceleration (good for tight tracks), while a higher rollout provides more top speed (good for large, open tracks).',
    'Shock Springs' => 'The main coil-over springs used on an oil-filled shock absorber. A stiffer spring provides a faster response and less chassis roll, suited for high-grip, smooth tracks. A softer spring allows for more chassis roll and grip generation, suited for lower-grip or bumpy tracks.',
    'Shore' => 'A measure of the hardness of a material, typically used for RC car tires. It is measured with a durometer. A lower shore number (e.g., 30 shore) indicates a softer tire, which generally provides more grip but wears faster. A higher shore number (e.g., 40 shore) indicates a harder, more durable tire with less grip.',
    'Side Springs' => 'The springs on either side of the rear pod that control the chassis roll. Stiffer side springs make the car react quicker to steering inputs and reduce chassis roll. Softer side springs allow for more roll, which can generate more grip, especially on lower-traction surfaces.',
    'Side Tubes / Dampers' => 'Dampers that control the side-to-side roll of the rear pod. Thicker fluid or stiffer springs in the side dampers will slow the roll speed, making the car more stable but less responsive. Lighter fluid allows for quicker weight transfer and more aggressive steering.',
    'Spur Gear' => 'The large gear that is attached to the rear axle. A larger spur gear (more teeth) will decrease your rollout, increasing acceleration. A smaller spur gear will increase your rollout for more top speed.',
    'Steering Lock (on car)' => 'The maximum physical angle that the front wheels can turn. This is usually limited by screws in the steering blocks or hubs. Increasing the steering lock can help in very tight hairpins but may cause the car to scrub speed or become unstable if over-used.',
    'Steering Throw (on Controller)' => 'A setting on the radio transmitter (also known as End Point Adjustment - EPA, or Adjustable Travel Volume - ATV) that limits the maximum signal sent to the steering servo. This is used to adjust the car\'s maximum steering angle electronically, often set to match the physical steering lock of the car.',
    'Stroke' => 'The total distance the suspension can travel from full extension to full compression. More stroke (or down-travel) is generally referred to as droop. Adjusting stroke can limit suspension movement to prevent the chassis from touching the ground or to change weight transfer characteristics.',
    'Tire Additive' => 'A chemical compound applied to tires to soften the rubber and increase grip. Different additives are used for different track surfaces (e.g., carpet vs. asphalt) and may require different application times.',
    'Tire Prep' => 'The process of preparing tires for a run, which can include cleaning, applying additive, and using tire warmers. The specific process is a critical part of a car\'s setup.',
    'Track Width (Front & Rear)' => 'The distance between the centerlines of the tires on an axle. A wider track width generally increases stability but reduces weight transfer, which can decrease grip. A narrower track width can provide more aggressive steering (at the front) or more rear grip (at the rear) by promoting more chassis roll.',
    'Toe' => 'The angle of the wheels when viewed from above. Toe-in means the front of the wheels point inward toward the chassis. Toe-out means they point outward. Front toe-out provides more aggressive turn-in. Rear toe-in increases stability, especially under acceleration.',
    'Tweak' => 'An imbalance in the chassis where the downforce on the rear wheels is unequal. A tweaked car will often turn much better in one direction than the other. It can be adjusted by placing the car on a tweak board and adjusting the side springs or shock collars.',
    'Weight Distribution' => 'How the total weight of the car is balanced between the front, rear, left, and right sides. This has a major impact on handling. Moving weight forward increases steering, while moving it rearward increases rear traction. Left-to-right balance is also critical for consistent handling in both directions.'
];
// Sort the array alphabetically by the term (the key)
ksort($glossary_terms);
?>
<div class="container mt-3">
	<h1>On-Road Setup Glossary</h1>
	<p>A quick reference for common on-road RC car setup terms. Click any term to see its definition.</p>

    <div class="accordion" id="glossaryAccordion">
        <?php foreach ($glossary_terms as $term => $definition): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-<?php echo str_replace([' ', '/'], '', $term); ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo str_replace([' ', '/'], '', $term); ?>" aria-expanded="false" aria-controls="collapse-<?php echo str_replace([' ', '/'], '', $term); ?>">
                        <strong><?php echo htmlspecialchars($term); ?></strong>
                    </button>
                </h2>
                <div id="collapse-<?php echo str_replace([' ', '/'], '', $term); ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo str_replace([' ', '/'], '', $term); ?>" data-bs-parent="#glossaryAccordion">
                    <div class="accordion-body">
                        <?php echo htmlspecialchars($definition); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>