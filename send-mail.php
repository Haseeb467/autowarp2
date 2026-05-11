<?php
declare(strict_types=1);

/*
  Hostinger SMTP setup:
  1. Create an email account in Hostinger, for example info@yourdomain.com.
  2. Put that email and password below.
  3. Upload this file to the site root, next to index.html.
*/
$recipientEmail = 'haseebdev786@gmail.com';
$subjectPrefix = 'Autoskins Quote Request';
$saveLocalSubmissions = true;

$smtp = [
    'enabled' => true,
    'host' => 'smtp.hostinger.com',
    'port' => 465,
    'encryption' => 'ssl', // ssl for port 465, tls for port 587
    'username' => 'info@yourdomain.com',
    'password' => 'REPLACE_WITH_HOSTINGER_EMAIL_PASSWORD',
    'from_email' => 'info@yourdomain.com',
    'from_name' => 'Autoskins Website',
    'timeout' => 20,
];

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return stripos($accept, 'application/json') !== false || strtolower($requestedWith) === 'fetch';
}

function respond(bool $ok, string $message, int $statusCode = 200): void
{
    http_response_code($statusCode);

    if (wants_json()) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    $title = $ok ? 'Request Sent' : 'Request Not Sent';
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo "<!doctype html><html lang=\"en\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>{$safeTitle}</title></head><body><main style=\"font-family:Arial,sans-serif;max-width:720px;margin:60px auto;padding:24px;line-height:1.5\"><h1>{$safeTitle}</h1><p>{$safeMessage}</p><p><a href=\"pages/contact.html\">Back to contact form</a></p></main></body></html>";
    exit;
}

function field(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function header_text(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function starts_with_text(string $value, string $needle): bool
{
    return substr($value, 0, strlen($needle)) === $needle;
}

function ends_with_text(string $value, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    return substr($value, -strlen($needle)) === $needle;
}

function is_local_server(): bool
{
    $serverName = strtolower($_SERVER['SERVER_NAME'] ?? '');
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $serverAddress = $_SERVER['SERVER_ADDR'] ?? '';

    return in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)
        || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || starts_with_text($host, 'localhost:')
        || starts_with_text($host, '127.0.0.1:')
        || starts_with_text($host, '192.168.')
        || ends_with_text($serverName, '.test')
        || ends_with_text($serverName, '.local')
        || in_array($serverAddress, ['127.0.0.1', '::1'], true);
}

function smtp_is_configured(array $smtp): bool
{
    $required = ['host', 'port', 'username', 'password', 'from_email'];

    foreach ($required as $key) {
        if (!isset($smtp[$key]) || trim((string)$smtp[$key]) === '') {
            return false;
        }
    }

    $joined = implode(' ', array_map('strtolower', [
        (string)$smtp['username'],
        (string)$smtp['password'],
        (string)$smtp['from_email'],
    ]));

    if (strpos($joined, 'yourdomain.com') !== false || strpos($joined, 'replace_with') !== false) {
        return false;
    }

    return filter_var($smtp['from_email'], FILTER_VALIDATE_EMAIL) !== false;
}

function smtp_read($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return trim($response);
}

function smtp_expect($socket, array $codes, string $context): string
{
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);

    if (!in_array($code, $codes, true)) {
        throw new RuntimeException("{$context} failed: {$response}");
    }

    return $response;
}

function smtp_command($socket, string $command, array $codes, string $context): string
{
    fwrite($socket, $command . "\r\n");

    return smtp_expect($socket, $codes, $context);
}

function smtp_escape_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = preg_replace('/^\./m', '..', $body);

    return str_replace("\n", "\r\n", $body ?? '');
}

function smtp_send(array $smtp, string $to, string $subject, string $body, array $headers): void
{
    if (!smtp_is_configured($smtp)) {
        throw new RuntimeException('SMTP details are not configured in send-mail.php.');
    }

    $host = (string)$smtp['host'];
    $port = (int)$smtp['port'];
    $timeout = (int)($smtp['timeout'] ?? 20);
    $encryption = strtolower((string)($smtp['encryption'] ?? 'ssl'));
    $target = $encryption === 'ssl' ? "ssl://{$host}:{$port}" : "{$host}:{$port}";

    $socket = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

    if (!$socket) {
        throw new RuntimeException("Could not connect to SMTP server: {$errstr} ({$errno}).");
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtp_expect($socket, [220], 'SMTP greeting');

        $serverName = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
        smtp_command($socket, "EHLO {$serverName}", [250], 'SMTP EHLO');

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220], 'SMTP STARTTLS');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not enable SMTP TLS encryption.');
            }

            smtp_command($socket, "EHLO {$serverName}", [250], 'SMTP EHLO after STARTTLS');
        }

        smtp_command($socket, 'AUTH LOGIN', [334], 'SMTP auth start');
        smtp_command($socket, base64_encode((string)$smtp['username']), [334], 'SMTP username');
        smtp_command($socket, base64_encode((string)$smtp['password']), [235], 'SMTP password');

        $fromEmail = header_text((string)$smtp['from_email']);
        smtp_command($socket, "MAIL FROM:<{$fromEmail}>", [250], 'SMTP from');
        smtp_command($socket, "RCPT TO:<{$to}>", [250, 251], 'SMTP recipient');
        smtp_command($socket, 'DATA', [354], 'SMTP data');

        $messageHeaders = array_merge([
            'To: ' . $to,
            'Subject: ' . header_text($subject),
            'Date: ' . date(DATE_RFC2822),
        ], $headers);

        fwrite($socket, implode("\r\n", $messageHeaders) . "\r\n\r\n" . smtp_escape_body($body) . "\r\n.\r\n");
        smtp_expect($socket, [250], 'SMTP send');
        smtp_command($socket, 'QUIT', [221], 'SMTP quit');
    } finally {
        fclose($socket);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', 405);
}

if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Contact form recipient email is invalid.', 500);
}

if (field('website') !== '') {
    respond(true, 'Thanks. Your quote request has been sent.');
}

$name = field('name');
$email = field('email');
$phone = field('phone');
$service = field('service');
$message = field('message');

if ($name === '' || $email === '' || $phone === '' || $service === '' || $message === '') {
    respond(false, 'Please complete all required fields.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', 422);
}

if (strlen($name) > 120 || strlen($email) > 160 || strlen($phone) > 60 || strlen($service) > 120 || strlen($message) > 4000) {
    respond(false, 'Please shorten your message and try again.', 422);
}

$safeName = header_text($name);
$safeEmail = header_text($email);
$subject = "{$subjectPrefix} from {$safeName}";
$fromEmail = smtp_is_configured($smtp) ? (string)$smtp['from_email'] : 'no-reply@localhost';
$fromName = header_text((string)($smtp['from_name'] ?? 'Autoskins Website'));

$body = "New quote request from the Autoskins website\n\n";
$body .= "Name: {$name}\n";
$body .= "Email: {$email}\n";
$body .= "Phone: {$phone}\n";
$body .= "Service: {$service}\n\n";
$body .= "Message:\n{$message}\n\n";
$body .= "Submitted: " . date('Y-m-d H:i:s T') . "\n";
$body .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";

$headers = [
    "From: {$fromName} <{$fromEmail}>",
    "Reply-To: {$safeName} <{$safeEmail}>",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
];

$mailError = '';

try {
    smtp_send($smtp, $recipientEmail, $subject, $body, $headers);
    respond(true, 'Thanks. Your quote request has been sent.');
} catch (Throwable $error) {
    $mailError = $error->getMessage();
}

if ($saveLocalSubmissions && is_local_server()) {
    $logEntry = "---- " . date('Y-m-d H:i:s T') . " ----\n{$body}\nMail status: {$mailError}\n\n";
    @file_put_contents(__DIR__ . '/form-submissions.log', $logEntry, FILE_APPEND | LOCK_EX);

    respond(true, 'Local test saved. Add real SMTP details in send-mail.php before uploading.');
}

respond(false, "SMTP mail failed. Check send-mail.php SMTP username, password, and from_email. {$mailError}", 500);
