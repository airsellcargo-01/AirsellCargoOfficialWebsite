<?php
// Set JSON header and CORS headers if needed
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Only POST requests are accepted.']);
    exit();
}

// Read raw JSON input from the booking form
$rawInput = file_get_contents('php://input');
$bookingData = json_decode($rawInput, true);

// Validate basic payload structure
if (!$bookingData || !isset($bookingData['trackingId'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid payload. A valid tracking ID and booking data are required.'
    ]);
    exit();
}

$dataFile = __DIR__ . '/shipment.json';
$currentShipments = [];

// Read existing shipment records from shipment.json
if (file_exists($dataFile)) {
    $existingContent = file_get_contents($dataFile);
    if (!empty($existingContent)) {
        $decoded = json_decode($existingContent, true);
        if (is_array($decoded)) {
            $currentShipments = $decoded;
        }
    }
}

// Key the new shipment entry by its Tracking ID
$trackingId = $bookingData['trackingId'];
$currentShipments[$trackingId] = $bookingData;

// Write updated shipments list back to shipment.json safely with file locking
$jsonOutput = json_encode($currentShipments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (file_put_contents($dataFile, $jsonOutput, LOCK_EX) !== false) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Shipment booking saved successfully.',
        'trackingId' => $trackingId
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to update shipment database file.'
    ]);
}
?>