<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

$phpmailerFiles = [
    __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php',
    __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
    __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php',
];

foreach ($phpmailerFiles as $phpmailerFile) {
    if (is_file($phpmailerFile)) {
        require_once $phpmailerFile;
    }
}

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'cda_db';

const SITE_NAME = 'Centre For Domestic Abuse';
const ADMIN_EMAIL = 'mk8979494@gmail.com';
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_USER = 'mk8979494@gmail.com';
const SMTP_PASS = 'gbywrtcyjxhtcddf';
const SMTP_PORT = 465;

const MAX_UPLOAD_SIZE = 10485760;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function clean_value(string $value): string
{
    return trim(strip_tags(str_replace(["\r", "\0"], '', $value)));
}

function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    return $scheme . '://' . $host . ($path === '' ? '' : $path) . '/';
}

function render_response(bool $success, string $title, string $message): void
{
    if (wants_json_response()) {
        http_response_code($success ? 200 : 422);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $success,
            'title' => $title,
            'message' => $message,
        ]);
        exit;
    }

    $color = $success ? '#0f6b3d' : '#b42318';
    http_response_code($success ? 200 : 422);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/app.css">
    <style>
        body{margin:0;font-family:Montserrat,Arial,sans-serif;background:#f7f5f0;color:#1f2933}
        .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .panel{max-width:680px;width:100%;background:#fff;border:1px solid #e6e0d8;padding:34px;text-align:center}
        h1{font-family:"Archivo Black",Arial,sans-serif;font-size:30px;line-height:1.2;margin:0 0 14px;color:<?= $color ?>}
        p{font-size:16px;line-height:1.7;margin:0 0 24px}
        a{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:14px 24px;font-weight:700}
    </style>
</head>
<body>
    <main class="wrap">
        <section class="panel">
            <h1><?= e($title) ?></h1>
            <p><?= e($message) ?></p>
            <a href="contact-us.html">Back to Contact Us</a>
        </section>
    </main>
</body>
</html>
    <?php
    exit;
}

function fail_response(string $message): void
{
    render_response(false, 'Form not submitted', $message);
}

function wants_json_response(): bool
{
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    return strtolower($requestedWith) === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function save_uploaded_file(): string
{
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        fail_response('Please attach a file.');
    }

    $upload = $_FILES['file'];

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        fail_response('Please attach a valid file.');
    }

    if (($upload['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        fail_response('File size must be 10MB or less.');
    }

    $originalName = basename((string)($upload['name'] ?? 'attachment'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = [
        'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx',
        'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'zip',
        'mp4', 'mov', 'mpeg', 'mp3', 'wav', 'ogg', 'webm',
    ];

    if ($extension === '' || !in_array($extension, $allowed, true)) {
        fail_response('Please attach an allowed file type.');
    }

    $uploadDir = __DIR__ . '/uploads/contact-files';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        fail_response('Upload folder could not be created.');
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
    $safeBase = trim((string)$safeBase, '-_');
    $safeBase = $safeBase === '' ? 'file' : $safeBase;
    $fileName = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '-' . $safeBase . '.' . $extension;
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file((string)$upload['tmp_name'], $targetPath)) {
        fail_response('File could not be uploaded.');
    }

    return 'uploads/contact-files/' . $fileName;
}

function build_admin_body(array $contact): string
{
    $fileUrl = base_url() . $contact['file'];

    return '<h2>New contact form submission</h2>'
        . '<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse">'
        . '<tr><th align="left">Name</th><td>' . e($contact['name']) . '</td></tr>'
        . '<tr><th align="left">Email</th><td>' . e($contact['email']) . '</td></tr>'
        . '<tr><th align="left">Note</th><td>' . nl2br(e($contact['note'])) . '</td></tr>'
        . '<tr><th align="left">File</th><td><a href="' . e($fileUrl) . '">' . e($fileUrl) . '</a></td></tr>'
        . '</table>';
}

function build_user_body(string $name): string
{
    return '<p>Dear ' . e($name) . ',</p>'
        . '<p>Thank you for contacting Centre For Domestic Abuse. Your message has been received and our team will review it shortly.</p>'
        . '<p>Regards,<br>' . SITE_NAME . '</p>';
}

function send_with_phpmailer(string $to, string $subject, string $html, ?string $replyToEmail = null, ?string $replyToName = null, ?string $attachment = null): void
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(SMTP_USER, SITE_NAME);
    $mail->addAddress($to);

    if ($replyToEmail !== null) {
        $mail->addReplyTo($replyToEmail, $replyToName ?? $replyToEmail);
    }

    if ($attachment !== null && is_file(__DIR__ . '/' . $attachment)) {
        $mail->addAttachment(__DIR__ . '/' . $attachment);
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
    $mail->send();
}

function send_email(string $to, string $subject, string $html, ?string $replyToEmail = null, ?string $replyToName = null, ?string $attachment = null): void
{
    if (class_exists(PHPMailer::class)) {
        send_with_phpmailer($to, $subject, $html, $replyToEmail, $replyToName, $attachment);
        return;
    }

    send_with_smtp_socket($to, $subject, $html, $replyToEmail, $replyToName, $attachment);
}

function smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }

    return $response;
}

function email_address_header(string $email, ?string $name = null): string
{
    $name = trim((string)$name);
    return $name === '' ? '<' . $email . '>' : '"' . addcslashes($name, "\\\"") . '" <' . $email . '>';
}

function build_mime_message(string $to, string $subject, string $html, ?string $replyToEmail, ?string $replyToName, ?string $attachment): string
{
    $boundary = 'cda_' . bin2hex(random_bytes(12));
    $headers = [
        'From: ' . email_address_header(SMTP_USER, SITE_NAME),
        'To: <' . $to . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    ];

    if ($replyToEmail !== null) {
        $headers[] = 'Reply-To: ' . email_address_header($replyToEmail, $replyToName);
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n";
    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $html . "\r\n\r\n";

    if ($attachment !== null && is_file(__DIR__ . '/' . $attachment)) {
        $attachmentPath = __DIR__ . '/' . $attachment;
        $attachmentName = basename($attachmentPath);
        $message .= '--' . $boundary . "\r\n";
        $message .= 'Content-Type: application/octet-stream; name="' . addcslashes($attachmentName, "\\\"") . "\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= 'Content-Disposition: attachment; filename="' . addcslashes($attachmentName, "\\\"") . "\"\r\n\r\n";
        $message .= chunk_split(base64_encode((string)file_get_contents($attachmentPath))) . "\r\n";
    }

    $message .= '--' . $boundary . "--\r\n";

    return $message;
}

function send_with_smtp_socket(string $to, string $subject, string $html, ?string $replyToEmail = null, ?string $replyToName = null, ?string $attachment = null): void
{
    $socket = stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 20);
    if (!$socket) {
        throw new RuntimeException('SMTP connection failed: ' . $errstr);
    }

    try {
        $greeting = smtp_read($socket);
        if ((int)substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException('SMTP error: ' . trim($greeting));
        }

        smtp_command($socket, 'EHLO localhost', [250]);
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode(SMTP_USER), [334]);
        smtp_command($socket, base64_encode(SMTP_PASS), [235]);
        smtp_command($socket, 'MAIL FROM:<' . SMTP_USER . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $message = build_mime_message($to, $subject, $html, $replyToEmail, $replyToName, $attachment);
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($socket, $message . "\r\n.\r\n");
        $response = smtp_read($socket);
        if ((int)substr($response, 0, 3) !== 250) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }

        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: contact-us.html');
    exit;
}

if (!empty($_POST['_app_id'] ?? '')) {
    fail_response('Invalid form submission.');
}

$name = clean_value((string)($_POST['name'] ?? ''));
$email = clean_value((string)($_POST['email'] ?? ''));
$note = clean_value((string)($_POST['note'] ?? ''));

if ($name === '') {
    fail_response('Name is required.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail_response('Valid email is required.');
}

if ($note === '') {
    fail_response('Note is required.');
}

$file = save_uploaded_file();

$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$db) {
    fail_response('Database connection failed.');
}

mysqli_set_charset($db, 'utf8mb4');

$stmt = mysqli_prepare($db, 'INSERT INTO contacts (name, email, note, file) VALUES (?, ?, ?, ?)');
if (!$stmt) {
    fail_response('Database query could not be prepared.');
}

mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $note, $file);

if (!mysqli_stmt_execute($stmt)) {
    fail_response('Your details could not be saved in the database.');
}

$contactId = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

$contact = ['name' => $name, 'email' => $email, 'note' => $note, 'file' => $file];

if ($contactId > 0) {
    $stmt = mysqli_prepare($db, 'SELECT name, email, note, file FROM contacts WHERE id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $contactId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $savedContact = mysqli_fetch_assoc($result);
        if (is_array($savedContact)) {
            $contact = $savedContact;
        }
        mysqli_stmt_close($stmt);
    }
}

try {
    send_email(
        ADMIN_EMAIL,
        'New contact form submission - ' . SITE_NAME,
        build_admin_body($contact),
        $contact['email'],
        $contact['name'],
        $contact['file']
    );

    send_email(
        $contact['email'],
        'We received your message - ' . SITE_NAME,
        build_user_body($contact['name'])
    );

    render_response(true, 'Thank you', 'Your message has been sent successfully.');
} catch (Throwable $mailError) {
    render_response(true, 'Details saved', 'Your details have been saved in the database, but email could not be sent right now.');
}
