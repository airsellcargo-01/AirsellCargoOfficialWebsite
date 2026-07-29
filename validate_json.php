<?php
// Enable error reporting during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Path to your JSON file (adjust as needed)
$file = __DIR__ . "/shipment.json";

// Read file content
$jsonContent = file_get_contents($file);

// Validate JSON
function validateJSON($jsonString) {
    json_decode($jsonString);
    if (json_last_error() === JSON_ERROR_NONE) {
        return true;
    } else {
        return json_last_error_msg();
    }
}

// Run validation
$result = validateJSON($jsonContent);

if ($result === true) {
    echo "✅ JSON is valid!";
} else {
    echo "❌ JSON error: " . $result;
}
?>
