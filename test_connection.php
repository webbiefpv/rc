<?php
// --- CONFIGURATION ---
// Make sure this is the correct public IP address of your home network where the Python server is.
$api_url = "http://109.155.110.165/scrape"; 

echo "<h1>Connection Test</h1>";
echo "<p>Attempting to connect to: <strong>" . htmlspecialchars($api_url) . "</strong></p>";

// Use file_get_contents with a timeout to test the connection
$context = stream_context_create(['http' => ['timeout' => 5]]); // Set a 5-second timeout
$response = @file_get_contents($api_url, false, $context);

if ($response === false) {
    echo '<div style="color: red; font-weight: bold; border: 2px solid red; padding: 10px;">';
    echo "TEST FAILED: Could not connect to the API server.<br>";
    echo "This confirms there is a network or firewall issue preventing your web server from reaching your Python server.";
    echo '</div>';
    
    $error = error_get_last();
    if ($error) {
        echo "<p><strong>Error details:</strong> " . htmlspecialchars($error['message']) . "</p>";
    }

} else {
    echo '<div style="color: green; font-weight: bold; border: 2px solid green; padding: 10px;">';
    echo "TEST SUCCESSFUL: The connection was made successfully!";
    echo '</div>';
    echo "<h4>Response from API:</h4>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
?>