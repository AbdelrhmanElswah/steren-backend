<?php

/**
 * StandingTech API Direct Test Script
 * 
 * This script tests the StandingTech API directly without going through Laravel
 * to help diagnose connection and authentication issues.
 * 
 * Usage: php test-standingtech-api.php
 */

echo "===========================================\n";
echo "StandingTech API Direct Test\n";
echo "===========================================\n\n";

// Configuration
$apiUrl = 'https://gateway.standingtech.com/api/v4/sms/send';
$apiToken = '534|ya4bex01Ef8UykzsFG9NKfeYzZaORJ5tVe8Miq6x751bd377';
$testRecipient = '9647502171212';
$testMessage = 'Test message from Steren App';

echo "Configuration:\n";
echo "- API URL: {$apiUrl}\n";
echo "- API Token: " . substr($apiToken, 0, 20) . "...\n";
echo "- Test Recipient: {$testRecipient}\n";
echo "- Test Message: {$testMessage}\n\n";

// Prepare request data
$data = [
    'recipient' => $testRecipient,
    'sender_id' => 'SterenApp',
    'type' => 'sms',
    'message' => $testMessage,
    'lang' => 'en'
];

echo "Request Data:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// Initialize cURL
$ch = curl_init($apiUrl);

// Set options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiToken,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_HEADER, true);

echo "Sending request...\n\n";

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$error = curl_error($ch);

// Parse response
$header = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

// Display results
echo "===========================================\n";
echo "RESPONSE\n";
echo "===========================================\n\n";

if ($error) {
    echo "❌ ERROR: {$error}\n\n";
    echo "This usually means:\n";
    echo "- Network connectivity issues\n";
    echo "- Firewall blocking the request\n";
    echo "- SSL certificate issues\n";
    echo "- DNS resolution problems\n";
    exit(1);
}

echo "HTTP Status Code: {$httpCode}\n\n";

echo "Response Headers:\n";
echo str_repeat('-', 43) . "\n";
echo $header . "\n";

echo "Response Body:\n";
echo str_repeat('-', 43) . "\n";
echo $body . "\n\n";

// Try to parse JSON
$jsonResponse = json_decode($body, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "Parsed JSON Response:\n";
    echo str_repeat('-', 43) . "\n";
    echo json_encode($jsonResponse, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "⚠️ Response is not valid JSON\n";
    echo "JSON Error: " . json_last_error_msg() . "\n\n";
}

// Interpret results
echo "===========================================\n";
echo "DIAGNOSIS\n";
echo "===========================================\n\n";

if ($httpCode >= 200 && $httpCode < 300) {
    echo "✅ SUCCESS! Message sent successfully.\n\n";
    echo "The API is working correctly. If your Laravel app is still having issues:\n";
    echo "1. Check that .env has STANDINGTECH_API_TOKEN set\n";
    echo "2. Run: php artisan config:clear\n";
    echo "3. Run: php artisan cache:clear\n";
    echo "4. Restart your server\n";
} elseif ($httpCode == 401) {
    echo "❌ AUTHENTICATION FAILED (401 Unauthorized)\n\n";
    echo "Possible causes:\n";
    echo "- API token is invalid or expired\n";
    echo "- Token format is incorrect\n";
    echo "- Token has been revoked\n\n";
    echo "Solution:\n";
    echo "- Contact StandingTech support (akam@standingtech.com)\n";
    echo "- Request a new API token\n";
} elseif ($httpCode == 400) {
    echo "❌ BAD REQUEST (400)\n\n";
    echo "Possible causes:\n";
    echo "- Invalid phone number format\n";
    echo "- Missing required fields\n";
    echo "- Invalid message content\n\n";
    if ($jsonResponse) {
        echo "API Error Message:\n";
        echo json_encode($jsonResponse, JSON_PRETTY_PRINT) . "\n";
    }
} elseif ($httpCode == 402) {
    echo "❌ PAYMENT REQUIRED (402)\n\n";
    echo "Your account has insufficient credits.\n";
    echo "Contact StandingTech to top up: akam@standingtech.com\n";
} elseif ($httpCode == 429) {
    echo "❌ RATE LIMIT EXCEEDED (429)\n\n";
    echo "You're sending too many requests.\n";
    echo "Wait a few minutes and try again.\n";
} elseif ($httpCode == 500 || $httpCode == 503) {
    echo "❌ SERVER ERROR ({$httpCode})\n\n";
    echo "The StandingTech API is having issues.\n";
    echo "This is not your fault. Wait and try again later.\n";
    echo "If it persists, contact support.\n";
} elseif ($httpCode == 0) {
    echo "❌ CONNECTION FAILED\n\n";
    echo "Could not connect to the API.\n";
    echo "Check:\n";
    echo "- Internet connection\n";
    echo "- Firewall settings\n";
    echo "- Proxy configuration\n";
} else {
    echo "❌ UNKNOWN ERROR (HTTP {$httpCode})\n\n";
    echo "Unexpected status code.\n";
    if ($jsonResponse) {
        echo "Response:\n";
        echo json_encode($jsonResponse, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n===========================================\n";
echo "For more help, see TROUBLESHOOTING.md\n";
echo "===========================================\n";

