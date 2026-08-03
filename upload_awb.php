<?php
header('Content-Type: application/json');

function parseCsvRecords(string $csvContent): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
    if (!$lines || count($lines) < 2) {
        return [];
    }

    $headers = array_map('trim', str_getcsv(array_shift($lines)));
    $records = [];

    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }

        $values = array_map('trim', str_getcsv($line));
        if (count($values) < count($headers)) {
            $values = array_pad($values, count($headers), '');
        }

        $record = array_combine($headers, $values);
        if (!is_array($record) || empty($record['awb'])) {
            continue;
        }

        $records[] = $record;
    }

    return $records;
}

function saveShipmentData(array $data, string $file): bool
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }

    if (!is_writable($file)) {
        return false;
    }

    file_put_contents($file, $encoded . PHP_EOL);
    return true;
}

$file = __DIR__ . '/shipment.json';

if (!is_file($file)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'shipment.json was not found.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$csvFile = $_FILES['csvFile'] ?? null;

$json = file_get_contents($file);
$data = json_decode($json, true);
if (!is_array($data)) {
    $data = [];
}

if ($method === 'PUT') {
    $payload = json_decode($rawInput, true);
    if (!is_array($payload) || empty($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No AWB data was submitted for update.']);
        exit;
    }

    $awb = trim((string) ($payload['awb'] ?? $payload['ShipmentID'] ?? $payload['tracking_id'] ?? ''));
    if ($awb === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'AWB is required for update.']);
        exit;
    }

    $store = trim((string) ($payload['store'] ?? $payload['customer'] ?? $payload['CustomerName'] ?? ''));
    $status = trim((string) ($payload['status'] ?? $payload['Status'] ?? 'Booked'));
    $origin = trim((string) ($payload['origin'] ?? $payload['Origin'] ?? ''));
    $destination = trim((string) ($payload['destination'] ?? $payload['Destination'] ?? ''));
    $timeline = !empty($payload['timeline']) && is_array($payload['timeline']) ? $payload['timeline'] : [
        ['stage' => 'Booked', 'date' => date('M d, Y - H:i') . ' EAT'],
    ];

    $data[$awb] = [
        'store' => $store !== '' ? $store : 'N/A',
        'status' => $status !== '' ? $status : 'Booked',
        'origin' => $origin !== '' ? $origin : 'N/A',
        'destination' => $destination !== '' ? $destination : 'N/A',
        'timeline' => $timeline,
    ];

    if (!saveShipmentData($data, $file)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to update shipment.json.']);
        exit;
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'AWB updated successfully.', 'awb' => $awb]);
    exit;
}

if ($method === 'DELETE') {
    $payload = json_decode($rawInput, true);
    if (!is_array($payload) || empty($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No AWB data was submitted for deletion.']);
        exit;
    }

    $awb = trim((string) ($payload['awb'] ?? $payload['ShipmentID'] ?? $payload['tracking_id'] ?? ''));
    if ($awb === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'AWB is required for deletion.']);
        exit;
    }

    if (!isset($data[$awb])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'AWB not found.']);
        exit;
    }

    unset($data[$awb]);

    if (!saveShipmentData($data, $file)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to delete AWB from shipment.json.']);
        exit;
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'AWB deleted successfully.', 'awb' => $awb]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Unsupported request method.']);
    exit;
}

$recordsToSave = [];

if (is_array($csvFile) && ($csvFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($csvFile['tmp_name'])) {
    $csvRecords = parseCsvRecords(file_get_contents($csvFile['tmp_name']));
    foreach ($csvRecords as $record) {
        $recordsToSave[] = $record;
    }
} elseif (stripos($contentType, 'application/json') !== false && $rawInput !== '') {
    $payload = json_decode($rawInput, true);
    if (is_array($payload) && !empty($payload)) {
        $recordsToSave[] = $payload;
    }
} else {
    $payload = $_POST;
    if (is_array($payload) && !empty($payload)) {
        $recordsToSave[] = $payload;
    }
}

if (empty($recordsToSave)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No AWB data was submitted.']);
    exit;
}

$savedAwbs = [];
foreach ($recordsToSave as $payload) {
    $awb = trim((string) ($payload['awb'] ?? $payload['ShipmentID'] ?? $payload['tracking_id'] ?? ''));
    $store = trim((string) ($payload['store'] ?? $payload['customer'] ?? $payload['CustomerName'] ?? ''));
    $status = trim((string) ($payload['status'] ?? $payload['Status'] ?? 'Booked'));
    $origin = trim((string) ($payload['origin'] ?? $payload['Origin'] ?? ''));
    $destination = trim((string) ($payload['destination'] ?? $payload['Destination'] ?? ''));

    if ($awb === '') {
        continue;
    }

    $timeline = [];
    if (!empty($payload['timeline']) && is_array($payload['timeline'])) {
        $timeline = $payload['timeline'];
    } else {
        $timeline = [
            ['stage' => 'Booked', 'date' => date('M d, Y - H:i') . ' EAT'],
        ];
    }

    $data[$awb] = [
        'store' => $store !== '' ? $store : 'N/A',
        'status' => $status !== '' ? $status : 'Booked',
        'origin' => $origin !== '' ? $origin : 'N/A',
        'destination' => $destination !== '' ? $destination : 'N/A',
        'timeline' => $timeline,
    ];

    $savedAwbs[] = $awb;
}

if (empty($savedAwbs)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No valid AWB entries were found in the submitted data.']);
    exit;
}

if (!saveShipmentData($data, $file)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save shipment.json.']);
    exit;
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => count($savedAwbs) === 1 ? 'AWB saved successfully.' : 'AWBs saved successfully.',
    'awbs' => $savedAwbs,
]);
