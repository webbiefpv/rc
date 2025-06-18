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

// Array of glossary terms and definitions, tailored for 1/12 Pan Cars
$glossary_terms = [
    'Ackermann' => 'The steering geometry that allows the inside front wheel to turn at a sharper angle than the outside wheel. On a pan car, this is adjusted by changing the angle of the steering arms or the position of the steering links on the servo saver. Less Ackermann (more parallel steering) provides more aggressive turn-in.',
    'Anti-Dive' => 'A geometric adjustment on the front suspension to prevent the front end from diving excessively under braking. On a pan car, this is achieved by angling the lower arm mounts so the front pivot is lower than the rear pivot.',
    'Anti-Squat' => 'A geometric adjustment on the rear pod to prevent it from squatting (compressing) heavily under acceleration. This is achieved by changing the angle of the rear lower pivot ball relative to the chassis, affecting how the pod transfers power to the wheels.',
    'Battery Position' => 'The placement of the battery in the chassis. An inline position (lengthwise) generally promotes more chassis roll. A transverse position (widthwise) reduces chassis roll. Moving the battery forward increases steering response, while moving it rearward increases rear traction.',
    'Bump Steer' => 'The change in the front wheels\' toe angle as the suspension compresses. This is adjusted by adding or removing shims under the steering link ball stud on the steering block. Ideally, you want zero bump steer so the car tracks straight over bumps.',
    'Camber' => 'The vertical angle of the front tires when viewed from the front. Negative camber means the top of the tire is tilted inwards. Typically -1 to -2 degrees is used to maximize the tire\'s contact patch when the chassis rolls during cornering.',
    'Camber Gain' => 'The rate at which the camber angle changes as the front suspension is compressed. It is adjusted by changing the length and angle of the front upper arm. More camber gain can increase mid-corner steering.',
    'Caster' => 'The angle of the steering kingpin when viewed from the side of the car. More caster (kingpin tilted further back) increases high-speed stability and steering feel into a corner. Less caster provides more aggressive initial steering response.',
    'Center Pivot' => 'The main ball-and-socket joint connecting the rear power pod to the main chassis. The dampening grease or fluid used here controls the forward and backward pitching of the pod, affecting on-power and off-power grip.',
    'Droop' => 'Refers to the amount of downward suspension travel from ride height. Front droop is adjusted with shims or grub screws on the lower arms. Rear droop is adjusted with screws on the rear pod plate. See also: Rear Droop.',
    'Kingpin Oil / Fluid' => 'The dampening oil (typically 10-50k weight silicone fluid) inside the front kingpins. Thicker oil slows down the front suspension movement, making the car smoother and less reactive, which is good for high-grip conditions. Thinner oil allows for a quicker response.',
    'Loose / Oversteer' => 'A handling condition where the rear of the car loses traction before the front during cornering. This causes the car to turn more sharply than intended, potentially spinning out.',
    'Pinion Gear' => 'The small gear attached directly to the motor shaft. A larger pinion (more teeth) results in a higher top speed and less acceleration. A smaller pinion provides more acceleration but a lower top speed.',
    'Pre-Load' => 'The initial tension on a spring. On a pan car, this is most relevant to the main center shock spring and the side springs. Adding pre-load raises the ride height (on the center shock) or increases the force needed to initiate chassis roll (on the side springs).',
    'Push / Understeer' => 'A handling condition where the front tires lose traction before the rear during cornering. This causes the car to turn less than intended and run wide in the corner.',
    'Rear Droop' => 'Less rear droop gives the car more on-power steering but can reduce forward traction. More rear droop allows the pod to articulate more freely, which can increase forward traction at the cost of some on-power steering response.',
    'Ride Height' => 'The distance between the bottom of the chassis and the ground. A lower ride height lowers the center of gravity, which is beneficial on smooth, high-grip tracks. A higher ride height is necessary for bumpy tracks to prevent the chassis from grounding.',
    'Rollout' => 'The distance the car travels for one full revolution of the motor. It is a critical calculation based on tire diameter and gear ratio, used to tune the car for different track sizes and motor types.',
    'Shock Springs' => 'On a 1/12 pan car, this typically refers to the main center shock spring. This spring primarily controls the fore-aft pitch of the rear pod under braking and acceleration. A stiffer spring will make the car react faster, while a softer spring can generate more grip.',
    'Shore' => 'A measure of the hardness of the foam or rubber used in the tires, measured with a durometer. A lower shore number (e.g., 30 shore) indicates a softer tire, which generally provides more grip but wears faster. A higher shore number (e.g., 40 shore) indicates a harder, more durable tire.',
    'Side Springs' => 'The springs on either side of the rear pod that control the chassis roll and how it returns to center. Stiffer side springs make the car react quicker to steering inputs and reduce chassis roll. Softer side springs allow for more roll, which can generate more grip.',
    'Side Tubes / Dampers' => 'Oil or grease-filled tubes that control the speed of the rear pod\'s side-to-side roll motion. Thicker fluid slows the roll speed, making the car more stable and easier to drive. Lighter fluid allows for quicker weight transfer and more aggressive steering.',
    'Spur Gear' => 'The large gear that is attached to the rear axle. A larger spur gear (more teeth) will increase acceleration. A smaller spur gear will increase top speed.',
    'Steering Lock (on car)' => 'The maximum physical angle that the front wheels can turn, usually limited by screws in the steering blocks. This is set to prevent the wheels from binding at full lock.',
    'Steering Throw (on Controller)' => 'A setting on the radio transmitter (also known as End Point Adjustment - EPA) that limits the maximum signal sent to the steering servo. This is used to adjust the car\'s steering angle electronically to match the physical steering lock.',
    'Stroke' => 'The total distance the suspension can travel. On a pan car, this is often used interchangeably with droop, as it defines the limits of the suspension movement.',
    'Tire Additive' => 'A chemical compound applied to tires to soften the rubber and increase grip. The choice of additive and application method is a critical tuning option for different track surfaces like carpet or asphalt.',
    'Tire Prep' => 'The process of preparing tires for a run, which includes cleaning, applying additive, setting the application time, and sometimes using tire warmers. This process is a crucial part of a setup.',
    'Track Width (Front & Rear)' => 'The distance between the centerlines of the tires on an axle, adjusted with shims on the kingpins (front) or axle (rear). A wider front track width is more stable; a narrower front provides more aggressive steering. A wider rear track width can free up the car in corners.',
    'Toe' => 'The angle of the wheels when viewed from above. Front wheels are often set with a slight amount of toe-out (fronts of wheels pointing away from the chassis) to improve turn-in. Rear toe is typically fixed by the pod plates and is set to toe-in to provide stability under power.',
    'Tweak' => 'An imbalance in the rear pod where the side springs apply unequal pressure, causing the car to handle differently when turning left versus right. This is checked and adjusted on a tweak board.',
    'Weight Distribution' => 'How the car\'s weight is balanced between the front, rear, left, and right. This has a major impact on handling. Moving weight forward increases steering; moving it rearward increases rear traction. Left-to-right balance is critical for consistent handling.'
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
                <h2 class="accordion-header" id="heading-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>" aria-expanded="false" aria-controls="collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>">
                        <strong><?php echo htmlspecialchars($term); ?></strong>
                    </button>
                </h2>
                <div id="collapse-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $term); ?>" data-bs-parent="#glossaryAccordion">
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