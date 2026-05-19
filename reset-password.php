<?php
session_start();
require_once 'backend/db.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit();
}

// Verify token
$stmt = $conn->prepare("SELECT prt.user_id, u.name AS username FROM password_reset_tokens prt 
                       JOIN users u ON prt.user_id = u.id 
                       WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = 0");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = "Invalid or expired reset link. Please request a new password reset.";
    $stmt->close();
} else {
    $user = $result->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - DSpeedway</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reset-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        .reset-card h2 {
            color: #e8175d;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .reset-card p {
            color: #94a3b8;
            margin-bottom: 30px;
        }
        .form-label {
            color: #e2e8f0;
            font-weight: 500;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #e8175d;
            color: #fff;
        }
        .btn-reset {
            background: linear-gradient(135deg, #e8175d, #c31432);
            border: none;
            color: #fff;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            width: 100%;
        }
        .btn-reset:hover {
            background: linear-gradient(135deg, #c31432, #a01228);
            color: #fff;
        }
        .alert {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #fca5a5;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <a href="forgot-password.php" class="btn btn-reset">Request New Reset Link</a>
        <?php else: ?>
            <h2>Reset Password</h2>
            <p>Enter your new password for <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
            
            <form id="resetForm">
                <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" id="userId" value="<?php echo $user['user_id']; ?>">
                
                <div class="mb-3">
                    <label class="form-label">New Password *</label>
                    <input type="password" class="form-control" id="password" required minlength="6" placeholder="At least 6 characters">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" id="confirmPassword" required minlength="6" placeholder="Re-enter password">
                </div>
                
                <button type="submit" class="btn btn-reset" id="submitBtn">
                    <i class="bi bi-shield-lock me-2"></i>Reset Password
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="login.php" style="color: #94a3b8; text-decoration: none;">
                    <i class="bi bi-arrow-left me-1"></i>Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('resetForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const token = document.getElementById('token').value;
            
            if (password !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Passwords do not match',
                    text: 'Please make sure both passwords are the same.'
                });
                return;
            }
            
            if (password.length < 6) {
                Swal.fire({
                    icon: 'error',
                    title: 'Password too short',
                    text: 'Password must be at least 6 characters.'
                });
                return;
            }
            
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-spinner-border me-2"></i>Resetting...';
            
            try {
                const fd = new FormData();
                fd.append('action', 'reset');
                fd.append('token', token);
                fd.append('password', password);
                fd.append('confirm_password', confirmPassword);
                
                const res = await fetch('backend/forgot-password.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Password Reset!',
                        text: 'Your password has been reset successfully. You can now login with your new password.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    window.location.href = 'login.php';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: err.message
                });
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-shield-lock me-2"></i>Reset Password';
            }
        });
    </script>
</body>
</html>