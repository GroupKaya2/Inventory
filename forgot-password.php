<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - DSpeedway</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .forgot-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        .forgot-card h2 {
            color: #e8175d;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .forgot-card p {
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
        .btn-submit {
            background: linear-gradient(135deg, #e8175d, #c31432);
            border: none;
            color: #fff;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            width: 100%;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #c31432, #a01228);
            color: #fff;
        }
        .back-link {
            color: #94a3b8;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 20px;
        }
        .back-link:hover {
            color: #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="forgot-card">
        <h2><i class="bi bi-key me-2"></i>Forgot Password</h2>
        <p>Enter your email address and we'll send you a link to reset your password.</p>
        
        <form id="forgotForm">
            <div class="mb-3">
                <label class="form-label">Email Address *</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="email" required placeholder="Enter your email">
                </div>
            </div>
            
            <button type="submit" class="btn btn-submit" id="submitBtn">
                <i class="bi bi-send me-2"></i>Send Reset Link
            </button>
        </form>
        
        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left me-1"></i>Back to Login
        </a>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('forgotForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            
            if (!email) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Email Required',
                    text: 'Please enter your email address.'
                });
                return;
            }
            
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-spinner-border me-2"></i>Sending...';
            
            try {
                const fd = new FormData();
                fd.append('action', 'request');
                fd.append('email', email);
                
                const res = await fetch('backend/forgot-password.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Email Sent!',
                        text: data.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    document.getElementById('email').value = '';
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
                btn.innerHTML = '<i class="bi bi-send me-2"></i>Send Reset Link';
            }
        });
    </script>
</body>
</html>
