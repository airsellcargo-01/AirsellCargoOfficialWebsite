<?php
$endpoint = $argv[1] ?? 'http://localhost/contact_process.php';

$payload = [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'phone' => '1234567890',
    'service_type' => 'Freight Inquiry',
    'subject' => 'SMTP Test',
    'details' => 'This is a test submission to verify the contact form endpoint.',
    'website' => '',
];

$options = [
    'http' => [
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($payload),
        'ignore_errors' => true,
    ],
];

$context = stream_context_create($options);
$response = @file_get_contents($endpoint, false, $context);

if ($response === false) {
    fwrite(STDERR, "Unable to reach endpoint: {$endpoint}\n");
    exit(1);
}

echo $response . PHP_EOL;
