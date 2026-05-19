<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'request') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id AS user_id, name AS username FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Don't reveal if email exists or not for security
        echo json_encode(['success' => true, 'message' => 'If the email exists, a reset link has been sent.']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store token in database
    $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $user['user_id'], $token, $expires);
    
    if ($stmt->execute()) {
        // Send email (using PHP mail function for simplicity)
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
        
        $subject = "Password Reset - DSpeedway";
        $message = "Hello {$user['username']},\n\n";
        $message .= "You requested a password reset for your DSpeedway account.\n\n";
        $message .= "Click the link below to reset your password:\n";
        $message .= $resetLink . "\n\n";
        $message .= "This link will expire in 1 hour.\n\n";
        $message .= "If you did not request this reset, please ignore this email.\n";
        
        $headers = "From: noreply@dspeedway.com\r\n";
        $headers .= "Reply-To: noreply@dspeedway.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        if (mail($email, $subject, $message, $headers)) {
            echo json_encode(['success' => true, 'message' => 'Password reset link has been sent to your email.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send email. Please contact support.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to generate reset token.']);
    }
    
    $stmt->close();
    exit;
}

if ($action === 'verify') {
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT prt.user_id, u.name AS username FROM password_reset_tokens prt 
                           JOIN users u ON prt.user_id = u.id 
                           WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = 0");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode(['success' => true, 'user_id' => $user['user_id'], 'username' => $user['username']]);
    exit;
}

if ($action === 'reset') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($token) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    // Verify token
    $stmt = $conn->prepare("SELECT user_id FROM password_reset_tokens 
                           WHERE token = ? AND expires_at > NOW() AND used = 0");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }
    
    $reset = $result->fetch_assoc();
    $stmt->close();
    
    // Update password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $hashedPassword, $reset['user_id']);
    
    if ($stmt->execute()) {
        // Mark token as used
        $stmt2 = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
        $stmt2->bind_param('s', $token);
        $stmt2->execute();
        $stmt2->close();
        
        echo json_encode(['success' => true, 'message' => 'Password has been reset successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
    }
    
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
$conn->close();