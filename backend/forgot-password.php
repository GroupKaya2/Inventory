<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['success' => false, 'message' => 'PHPMailer not installed.']);
    exit;
}
require_once $autoload;


$envFile = __DIR__ . '/../.env';
$env = file_exists($envFile) ? parse_ini_file($envFile, false, INI_SCANNER_RAW) : [];

$smtpHost = $env['SMTP_HOST'] ?? 'smtp.gmail.com';
$smtpPort = (int) ($env['SMTP_PORT'] ?? 587);
$smtpUser = $env['SMTP_USER'] ?? '';
$smtpPass = $env['SMTP_PASS'] ?? '';
$smtpFrom = $env['SMTP_FROM'] ?? $smtpUser;
$appName = 'DSpeedway';

function sendOtpEmail(string $toEmail, string $toName, string $otp, string $smtpHost, int $smtpPort, string $smtpUser, string $smtpPass, string $smtpFrom, string $appName): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->setFrom($smtpFrom, $appName);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code — ' . $appName;
        $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family:Arial,sans-serif;background:#0d1117;margin:0;padding:0;">
    <div style="max-width:480px;margin:40px auto;background:#161b27;border:1px solid rgba(74,222,128,.18);border-radius:14px;overflow:hidden;">
        <div style="background:linear-gradient(135deg,#0f1f15,#111827);padding:28px 32px;text-align:center;border-bottom:1px solid rgba(74,222,128,.15);">
        <h1 style="color:#4ade80;font-size:1.4rem;margin:0 0 4px;">&#x1F510; ' . htmlspecialchars($appName) . '</h1>
        <p style="color:#64748b;font-size:.8rem;margin:0;">Password Reset OTP</p>
        </div>
        <div style="padding:32px;text-align:center;">
        <p style="color:#94a3b8;margin:0 0 20px;">Hi <strong style="color:#e2e8f0;">' . htmlspecialchars($toName) . '</strong>, use the OTP below to reset your password.</p>
        <div style="background:rgba(74,222,128,.08);border:2px dashed rgba(74,222,128,.3);border-radius:12px;padding:24px;margin:20px 0;">
            <div style="font-size:2.8rem;font-weight:900;letter-spacing:14px;color:#4ade80;font-family:monospace;">' . $otp . '</div>
            <p style="color:#64748b;font-size:.75rem;margin:8px 0 0;">This OTP is valid for <strong style="color:#e2e8f0;">10 minutes</strong></p>
        </div>
        <p style="color:#475569;font-size:.8rem;margin-top:20px;">If you did not request this, please ignore this email.</p>
        </div>
        <div style="padding:16px 32px;border-top:1px solid rgba(255,255,255,.06);text-align:center;font-size:.72rem;color:#475569;">
        &copy; ' . date('Y') . ' ' . htmlspecialchars($appName) . ' &mdash; D Speedway Car Care Services
        </div>
    </div>
    </body>
    </html>';
        $mail->AltBody = 'Your OTP code is: ' . $otp . '. Valid for 10 minutes.';
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer OTP error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo);
        return false;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── SEND OTP ─────────────────────────────────────────────
if ($action === 'send_otp') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();


    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'If that email is registered, an OTP has been sent.']);
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $conn->query("CREATE TABLE IF NOT EXISTS otp_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            otp VARCHAR(6) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $del = $conn->prepare("DELETE FROM otp_codes WHERE user_id = ?");
    $del->bind_param('i', $user['id']);
    $del->execute();
    $del->close();

    $ins = $conn->prepare("INSERT INTO otp_codes (user_id, otp, expires_at) VALUES (?, ?, ?)");
    $ins->bind_param('iss', $user['id'], $otp, $expires);

    if (!$ins->execute()) {
        $ins->close();
        echo json_encode(['success' => false, 'message' => 'Could not generate OTP. Please try again.']);
        exit;
    }
    $ins->close();

    $sent = sendOtpEmail($email, $user['name'], $otp, $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $appName);

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'OTP sent to your email.']);
    } else {
        $conn->query("DELETE FROM otp_codes WHERE user_id = " . (int) $user['id']);
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP email. Please check SMTP settings.']);
    }
    exit;
}

// ── VERIFY OTP ───────────────────────────────────────────
if ($action === 'verify_otp') {
    $email = trim($_POST['email'] ?? '');
    $otp = trim($_POST['otp'] ?? '');

    if (empty($email) || empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Email and OTP are required.']);
        exit;
    }

    $stmt = $conn->prepare("
            SELECT oc.id, oc.user_id
            FROM otp_codes oc
            JOIN users u ON oc.user_id = u.id
            WHERE u.email = ? AND oc.otp = ? AND oc.expires_at > NOW() AND oc.used = 0
            LIMIT 1
        ");
    $stmt->bind_param('ss', $email, $otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Please try again.']);
        exit;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    $mark = $conn->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?");
    $mark->bind_param('i', $row['id']);
    $mark->execute();
    $mark->close();

    $_SESSION['otp_verified_email'] = $email;
    $_SESSION['otp_verified_user'] = $row['user_id'];

    echo json_encode(['success' => true, 'message' => 'OTP verified successfully.']);
    exit;
}

// ── RESET PASSWORD ───────────────────────────────────────
if ($action === 'reset_password') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($pass) || empty($confirm)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if ($pass !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    if (strlen($pass) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    if (
        !isset($_SESSION['otp_verified_email']) ||
        !isset($_SESSION['otp_verified_user']) ||
        $_SESSION['otp_verified_email'] !== $email
    ) {
        echo json_encode(['success' => false, 'message' => 'OTP verification required. Please start over.']);
        exit;
    }

    $userId = (int) $_SESSION['otp_verified_user'];
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

    $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->bind_param('si', $hash, $userId);

    if ($upd->execute()) {
        $upd->close();
        unset($_SESSION['otp_verified_email'], $_SESSION['otp_verified_user']);
        echo json_encode(['success' => true, 'message' => 'Password reset successfully!']);
    } else {
        $upd->close();
        echo json_encode(['success' => false, 'message' => 'Failed to reset password. Please try again.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
$conn->close();