<?php
// --- CONFIGURATION ---
$api_url = "http://109.155.110.165/scrape"; 

echo "<h1>Connection Test (using cURL)</h1>";
echo "<p>Attempting to connect to: <strong>" . htmlspecialchars($api_url) . "</strong></p>";

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set a 10-second timeout
// --- THIS IS THE CRITICAL LINE ---
// Set a user-agent to make it look like a real browser
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

// Execute the request
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo '<div style="color: red; font-weight: bold; border: 2px solid red; padding: 10px;">';
    echo "TEST FAILED: cURL Error.<br>";
    echo "This means there is still a network or firewall issue.";
    echo "<p><strong>Error details:</strong> " . htmlspecialchars(curl_error($ch)) . "</p>";
    echo '</div>';
} else {
    // Check the HTTP status code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code == 200) {
        echo '<div style="color: green; font-weight: bold; border: 2px solid green; padding: 10px;">';
        echo "TEST SUCCESSFUL: The connection was made and received a 200 OK response!";
        echo '</div>';
        echo "<h4>Response from API:</h4>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo '<div style="color: orange; font-weight: bold; border: 2px solid orange; padding: 10px;">';
        echo "TEST FAILED: Connected, but received a non-200 HTTP status code: " . $http_code;
        echo "<p>This means the API server responded with an error.</p>";
        echo "<h4>Response from API:</h4>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        echo '</div>';
    }
}

// Close cURL
curl_close($ch);
?>