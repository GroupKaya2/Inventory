<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['success' => false, 'message' => 'PHPMailer not installed. Run: composer require phpmailer/phpmailer']);
    exit;
}
require_once $autoload;

// Load SMTP credentials from .env
$envFile = __DIR__ . '/../.env';
$env = file_exists($envFile) ? parse_ini_file($envFile, false, INI_SCANNER_RAW) : [];

$smtpHost = $env['SMTP_HOST'] ?? 'smtp.gmail.com';
$smtpPort = (int)($env['SMTP_PORT'] ?? 587);
$smtpUser = $env['SMTP_USER'] ?? '';
$smtpPass = $env['SMTP_PASS'] ?? '';
$smtpFrom = $env['SMTP_FROM'] ?? $smtpUser;
$appName  = 'DSpeedway';

// Guard: block requests if SMTP is still unconfigured
if (
    empty($smtpUser) ||
    str_contains($smtpUser, 'your-gmail') ||
    empty($smtpPass) ||
    str_contains($smtpPass, 'xxxx')
) {
    // Only block on actual send — verify/reset actions don't need SMTP
    $needsSmtp = in_array($_GET['action'] ?? $_POST['action'] ?? '', ['request']);
    if ($needsSmtp) {
        echo json_encode([
            'success' => false,
            'message' => 'Email is not configured. Please set SMTP_USER and SMTP_PASS in your .env file.'
        ]);
        exit;
    }
}

function sendMail(
    string $toEmail, string $toName, string $subject, string $body,
    string $smtpHost, int $smtpPort, string $smtpUser, string $smtpPass,
    string $smtpFrom, string $appName
): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;

        $mail->setFrom($smtpFrom, $appName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

function buildResetEmailHtml(string $username, string $resetLink, string $appName): string
{
    return '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background:#0d1117; color:#e2e8f0; margin:0; padding:0; }
    .wrap { max-width:520px; margin:40px auto; background:#161b27;
            border:1px solid rgba(74,222,128,.18); border-radius:14px; overflow:hidden; }
    .header { background:linear-gradient(135deg,#0f1f15,#111827);
              padding:28px 32px; text-align:center; border-bottom:1px solid rgba(74,222,128,.15); }
    .header h1 { color:#4ade80; font-size:1.4rem; margin:0 0 4px; }
    .header p  { color:#64748b; font-size:.8rem; margin:0; }
    .body { padding:32px; }
    .body p { color:#94a3b8; line-height:1.7; margin:0 0 16px; font-size:.9rem; }
    .body strong { color:#e2e8f0; }
    .btn-wrap { text-align:center; margin:28px 0; }
    .btn { display:inline-block; padding:13px 32px;
           background:linear-gradient(135deg,#22c55e,#16a34a);
           color:#fff !important; font-weight:700; font-size:.95rem;
           border-radius:9px; text-decoration:none;
           box-shadow:0 4px 18px rgba(34,197,94,.3); }
    .link-box { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
                border-radius:8px; padding:10px 14px; word-break:break-all;
                font-size:.75rem; color:#64748b; margin-top:6px; }
    .footer { padding:18px 32px; border-top:1px solid rgba(255,255,255,.06);
              text-align:center; font-size:.72rem; color:#475569; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="header">
      <h1>&#x1F510; ' . htmlspecialchars($appName) . '</h1>
      <p>Password Reset Request</p>
    </div>
    <div class="body">
      <p>Hi <strong>' . htmlspecialchars($username) . '</strong>,</p>
      <p>We received a request to reset your password. Click the button below to create a new password. This link is valid for <strong>1 hour</strong>.</p>
      <div class="btn-wrap">
        <a href="' . htmlspecialchars($resetLink) . '" class="btn">Reset My Password</a>
      </div>
      <p style="font-size:.8rem;color:#64748b;">If the button doesn\'t work, copy and paste this link into your browser:</p>
      <div class="link-box">' . htmlspecialchars($resetLink) . '</div>
      <p style="margin-top:20px;font-size:.8rem;color:#475569;">
        If you did not request a password reset, you can safely ignore this email.
        Your password will not change.
      </p>
    </div>
    <div class="footer">&copy; ' . date('Y') . ' ' . htmlspecialchars($appName) . ' &mdash; D Speedway Car Care Services</div>
  </div>
</body>
</html>';
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── ACTION: request ──────────────────────────────────────
if ($action === 'request') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id AS user_id, name AS username FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Always return the same message — don't reveal if email exists
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'If that email is registered, a reset link has been sent.']);
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Invalidate any previous unused tokens
    $del = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND used = 0");
    $del->bind_param('i', $user['user_id']);
    $del->execute();
    $del->close();

    // Generate secure token
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime('+8 hours', time() - (int)date('Z'))));

    $ins = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $ins->bind_param('iss', $user['user_id'], $token, $expires);

    if (!$ins->execute()) {
        $ins->close();
        echo json_encode(['success' => false, 'message' => 'Could not generate reset token. Please try again.']);
        exit;
    }
    $ins->close();

    // Build reset URL
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'];
    $basePath  = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/');
    $resetLink = $protocol . '://' . $host . $basePath . '/reset-password.php?token=' . urlencode($token);

    $subject = 'Password Reset — ' . $appName;
    $body    = buildResetEmailHtml($user['username'], $resetLink, $appName);

    $sent = sendMail(
        $email, $user['username'], $subject, $body,
        $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $appName
    );

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'A password reset link has been sent to your email.']);
    } else {
        // Clean up the token so the user can retry — use prepared statement
        $cleanup = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $cleanup->bind_param('s', $token);
        $cleanup->execute();
        $cleanup->close();

        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email. Please check SMTP settings in .env or contact support.'
        ]);
    }
    exit;
}

// ── ACTION: verify ───────────────────────────────────────
if ($action === 'verify') {
    $token = trim($_GET['token'] ?? '');

    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid token.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT prt.user_id, u.name AS username
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.id
        WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = 0
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'This link is invalid or has expired.']);
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    echo json_encode(['success' => true, 'user_id' => $user['user_id'], 'username' => $user['username']]);
    exit;
}

// ── ACTION: reset ────────────────────────────────────────
if ($action === 'reset') {
    $token           = trim($_POST['token'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($token) || empty($password) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT user_id FROM password_reset_tokens
        WHERE token = ? AND expires_at > NOW() AND used = 0
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'This reset link is invalid or has expired.']);
        exit;
    }

    $reset = $result->fetch_assoc();
    $stmt->close();

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $upd  = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->bind_param('si', $hash, $reset['user_id']);

    if ($upd->execute()) {
        $upd->close();

        $mark = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
        $mark->bind_param('s', $token);
        $mark->execute();
        $mark->close();

        echo json_encode(['success' => true, 'message' => 'Password changed successfully! You can now log in.']);
    } else {
        $upd->close();
        echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
$conn->close();