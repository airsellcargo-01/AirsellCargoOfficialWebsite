<?php
header('Content-Type: application/json; charset=UTF-8');

function cleanText(?string $value): string
{
    return trim(strip_tags((string) $value));
}

function outputJson(bool $success, int $statusCode, string $message, array $errors = []): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'errors' => $errors,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function getAppSetting(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }

    if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
        return (string) $_ENV[$name];
    }

    if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return (string) $_SERVER[$name];
    }

    $dotenvFile = __DIR__ . '/.env';
    if (is_file($dotenvFile)) {
        $lines = @file($dotenvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                if ($trimmedLine === '' || strpos($trimmedLine, '#') === 0) {
                    continue;
                }

                $parsed = explode('=', $trimmedLine, 2);
                if (count($parsed) !== 2) {
                    continue;
                }

                if (trim($parsed[0]) === $name) {
                    return trim($parsed[1], " \t\n\r\0\x0B\"'");
                }
            }
        }
    }

    return $default;
}

function sendEmailViaSmtp(string $to, string $from, string $replyTo, string $subject, string $body, array $headers): bool
{
    $smtpHost = getAppSetting('AIRSELL_SMTP_HOST') ?: '';
    $smtpPort = (int) (getAppSetting('AIRSELL_SMTP_PORT', '587') ?: 587);
    $smtpUser = getAppSetting('AIRSELL_SMTP_USER') ?: '';
    $smtpPass = getAppSetting('AIRSELL_SMTP_PASS') ?: '';

    if ($smtpHost === '') {
        return false;
    }

    $socket = @fsockopen('tcp://' . $smtpHost, $smtpPort, $errno, $errstr, 10);
    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 10);
    $response = fgets($socket, 515);
    if (strpos($response, '220') !== 0) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "EHLO localhost\r\n");
    while (true) {
        $line = fgets($socket, 515);
        if ($line === false || trim($line) === '' || strpos($line, '-') === false && strpos($line, '250') === 0) {
            break;
        }
        if (strpos($line, '250') === 0 && strpos($line, '250-') !== 0) {
            break;
        }
    }

    if ($smtpUser !== '' && $smtpPass !== '') {
        fwrite($socket, "AUTH LOGIN\r\n");
        fgets($socket, 515);
        fwrite($socket, base64_encode($smtpUser) . "\r\n");
        fgets($socket, 515);
        fwrite($socket, base64_encode($smtpPass) . "\r\n");
        $authResponse = fgets($socket, 515);
        if (strpos($authResponse, '235') !== 0) {
            fclose($socket);
            return false;
        }
    }

    fwrite($socket, "MAIL FROM:<{$from}>\r\n");
    if (strpos(fgets($socket, 515), '250') !== 0) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "RCPT TO:<{$to}>\r\n");
    if (strpos(fgets($socket, 515), '250') !== 0 && strpos(fgets($socket, 515), '251') !== 0) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "DATA\r\n");
    if (strpos(fgets($socket, 515), '354') !== 0) {
        fclose($socket);
        return false;
    }

    $smtpBody = "Subject: {$subject}\r\n";
    foreach ($headers as $header) {
        $smtpBody .= $header . "\r\n";
    }
    $smtpBody .= "\r\n" . $body . "\r\n." . "\r\n";
    fwrite($socket, $smtpBody);
    $dataResponse = fgets($socket, 515);
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return strpos($dataResponse, '250') === 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outputJson(false, 405, 'Only POST requests are allowed.');
}

$to = 'info@airsellcargo.com';
$from = 'info@airsellcargo.com';

$honeypot = cleanText($_POST['website'] ?? '');
if ($honeypot !== '') {
    outputJson(false, 400, 'Invalid request.');
}

$name = cleanText($_POST['name'] ?? $_POST['Full Name'] ?? '');
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);
$phone = cleanText($_POST['phone'] ?? $_POST['Phone Number'] ?? '');
$service = cleanText($_POST['service_type'] ?? $_POST['Service Required'] ?? '');
$subject = cleanText($_POST['subject'] ?? $_POST['Subject'] ?? 'Airsell Cargo Contact Request');
$details = cleanText($_POST['details'] ?? $_POST['Message'] ?? '');

$errors = [];
if (mb_strlen($name) < 2) {
    $errors[] = 'Name is required.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email is required.';
}
if (mb_strlen($phone) < 5) {
    $errors[] = 'A valid phone number is required.';
}
if (mb_strlen($service) < 2) {
    $errors[] = 'Service type is required.';
}
if (mb_strlen($details) < 10) {
    $errors[] = 'Cargo details are required.';
}

if (!empty($errors)) {
    outputJson(false, 422, 'Please fix the form errors.', $errors);
}

$emailSubject = 'Airsell Portal Inquiry: ' . ($subject !== '' ? $subject : 'General Inquiry');
$headers = [
    'From: ' . $from,
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
];

$body = "New message from Airsell Cargo Website Form:\n\n";
$body .= "Customer Name: $name\n";
$body .= "Customer Email: $email\n";
$body .= "Phone Number: $phone\n";
$body .= "Service Required: $service\n";
$body .= "Subject: $subject\n\n";
$body .= "Cargo Details:\n$details\n\n";
$body .= "--- End of Message ---";

$emailSent = sendEmailViaSmtp($to, $from, $email, $emailSubject, $body, $headers);
if (!$emailSent) {
    $emailSent = mail($to, $emailSubject, $body, implode("\r\n", $headers));
}

if ($emailSent) {
    $logFile = __DIR__ . '/contact_submissions.log';
    $logLine = date('c') . ' | ' . $name . ' | ' . $email . ' | ' . $service . PHP_EOL;
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

    outputJson(true, 200, 'Thank you! Your inquiry has been sent to the Airsell Cargo team.');
}

error_log('Contact form mail failed.');
outputJson(false, 500, 'Unable to send your inquiry right now. Please email us directly at sales@airsellcargo.com');

