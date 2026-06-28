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
            max-width: 420px;
            width: 100%;
        }

        .forgot-card h2 {
            color: #4ade80;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .forgot-card p {
            color: #94a3b8;
            margin-bottom: 24px;
            font-size: .9rem;
        }

        .form-label {
            color: #e2e8f0;
            font-weight: 500;
        }

        .form-control,
        .form-control:focus {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }

        .form-control::placeholder {
            color: #475569;
        }

        .btn-submit {
            background: linear-gradient(135deg, #16a34a, #052e16);
            border: 1px solid rgba(74, 222, 128, 0.35);
            color: #4ade80;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 700;
            width: 100%;
            font-size: .9rem;
            transition: all .2s;
        }

        .btn-submit:hover {
            box-shadow: 0 0 20px rgba(74, 222, 128, 0.25);
            color: #4ade80;
        }

        .back-link {
            color: #94a3b8;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 18px;
            font-size: .85rem;
        }

        .back-link:hover {
            color: #e2e8f0;
        }

        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 10px 0;
        }

        .otp-inputs input {
            width: 52px;
            height: 58px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            color: #fff;
            outline: none;
            transition: border-color .2s;
        }

        .otp-inputs input:focus {
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
            background: rgba(255, 255, 255, 0.08);
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 28px;
        }

        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            transition: background .3s;
        }

        .step-dot.active {
            background: #4ade80;
        }

        .step-dot.done {
            background: #16a34a;
        }

        .resend-btn {
            background: none;
            border: none;
            color: #4ade80;
            font-size: .82rem;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
        }

        .resend-btn:disabled {
            color: #475569;
            text-decoration: none;
            cursor: default;
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #64748b;
        }
    </style>
</head>

<body>
    <div class="forgot-card">

        <!-- Step indicators -->
        <div class="step-indicator">
            <div class="step-dot active" id="dot1"></div>
            <div class="step-dot" id="dot2"></div>
            <div class="step-dot" id="dot3"></div>
        </div>

        <!-- STEP 1: Enter Email -->
        <div class="step active" id="step1">
            <h2>Forgot Password</h2>
            <p>Enter your registered email address and we'll send you a 6-digit OTP code.</p>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <input type="email" class="form-control" id="emailInput" placeholder="Enter your email">
                </div>
            </div>
            <button class="btn btn-submit" id="sendOtpBtn">
                <i class="bi bi-send me-2"></i>Send OTP
            </button>
            <a href="login.php" class="back-link"></i>Back to Login</a>
        </div>

        <!-- STEP 2: Enter OTP -->
        <div class="step" id="step2">
            <h2></i>Enter OTP</h2>
            <p id="otpSubtitle">We sent a 6-digit code to your email. Enter it below.</p>
            <div class="otp-inputs" id="otpInputs">
                <input type="text" maxlength="1" class="otp-box" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-box" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-box" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-box" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-box" inputmode="numeric">
                <input type="text" maxlength="1" class="otp-box" inputmode="numeric">
            </div>
            <div style="text-align:center;margin-top:8px;font-size:.8rem;color:#64748b;">
                Didn't receive it? <button class="resend-btn" id="resendBtn" disabled>Resend (<span
                        id="resendTimer">60</span>s)</button>
            </div>
            <br>
            <button class="btn btn-submit" id="verifyOtpBtn">Verify OTP
            </button>
            <a href="#" class="back-link" onclick="goStep(1)">Change Email</a>
        </div>

        <!-- STEP 3: New Password -->
        <div class="step" id="step3">
            <h2>New Password</h2>
            <p>Enter your new password below.</p>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="newPass" placeholder="At least 6 characters">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirmPass" placeholder="Re-enter password">
                </div>
            </div>
            <button class="btn btn-submit" id="resetPassBtn">Reset Password
            </button>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        'use strict';

        var currentEmail = '';
        var resendInterval = null;

        function goStep(n) {
            document.querySelectorAll('.step').forEach(function (s) { s.classList.remove('active'); });
            document.getElementById('step' + n).classList.add('active');
            for (var i = 1; i <= 3; i++) {
                var dot = document.getElementById('dot' + i);
                dot.classList.remove('active', 'done');
                if (i < n) dot.classList.add('done');
                else if (i === n) dot.classList.add('active');
            }
        }

        function setBtn(id, disabled, html) {
            var btn = document.getElementById(id);
            btn.disabled = disabled;
            btn.innerHTML = html;
        }

        function startResendTimer() {
            var sec = 60;
            document.getElementById('resendTimer').textContent = sec;
            document.getElementById('resendBtn').disabled = true;
            clearInterval(resendInterval);
            resendInterval = setInterval(function () {
                sec--;
                document.getElementById('resendTimer').textContent = sec;
                if (sec <= 0) {
                    clearInterval(resendInterval);
                    var btn = document.getElementById('resendBtn');
                    btn.disabled = false;
                    btn.innerHTML = 'Resend OTP';
                }
            }, 1000);
        }

        function getOtp() {
            return Array.from(document.querySelectorAll('.otp-box')).map(function (i) { return i.value; }).join('');
        }

        function clearOtp() {
            document.querySelectorAll('.otp-box').forEach(function (i) { i.value = ''; });
            document.querySelectorAll('.otp-box')[0].focus();
        }

        // OTP box auto-advance
        document.querySelectorAll('.otp-box').forEach(function (box, idx, boxes) {
            box.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value && idx < boxes.length - 1) boxes[idx + 1].focus();
            });
            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !this.value && idx > 0) boxes[idx - 1].focus();
            });
            box.addEventListener('paste', function (e) {
                e.preventDefault();
                var paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                boxes.forEach(function (b, i) { b.value = paste[i] || ''; });
                if (paste.length > 0) boxes[Math.min(paste.length, 5)].focus();
            });
        });

        // STEP 1: Send OTP
        document.getElementById('sendOtpBtn').addEventListener('click', async function () {
            var email = document.getElementById('emailInput').value.trim();
            if (!email) { Swal.fire({ icon: 'warning', title: 'Email Required', text: 'Please enter your email address.' }); return; }

            setBtn('sendOtpBtn', true, '<i class="bi bi-hourglass-split me-2"></i>Sending...');
            try {
                var fd = new FormData();
                fd.append('action', 'send_otp');
                fd.append('email', email);
                var res = await fetch('backend/forgot-password.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.success) {
                    currentEmail = email;
                    document.getElementById('otpSubtitle').textContent = 'We sent a 6-digit code to ' + email + '. Enter it below.';
                    goStep(2);
                    clearOtp();
                    startResendTimer();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
            } finally {
                setBtn('sendOtpBtn', false, '<i class="bi bi-send me-2"></i>Send OTP Code');
            }
        });

        // Resend OTP
        document.getElementById('resendBtn').addEventListener('click', async function () {
            setBtn('resendBtn', true, 'Sending...');
            try {
                var fd = new FormData();
                fd.append('action', 'send_otp');
                fd.append('email', currentEmail);
                var res = await fetch('backend/forgot-password.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.success) {
                    clearOtp();
                    startResendTimer();
                    Swal.fire({ icon: 'success', title: 'OTP Resent!', text: 'A new OTP has been sent to your email.', timer: 2000, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    document.getElementById('resendBtn').disabled = false;
                    document.getElementById('resendBtn').innerHTML = 'Resend OTP';
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
            }
        });

        // STEP 2: Verify OTP
        document.getElementById('verifyOtpBtn').addEventListener('click', async function () {
            var otp = getOtp();
            if (otp.length < 6) { Swal.fire({ icon: 'warning', title: 'Incomplete OTP', text: 'Please enter all 6 digits.' }); return; }

            setBtn('verifyOtpBtn', true, '<i class="bi bi-hourglass-split me-2"></i>Verifying...');
            try {
                var fd = new FormData();
                fd.append('action', 'verify_otp');
                fd.append('email', currentEmail);
                fd.append('otp', otp);
                var res = await fetch('backend/forgot-password.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.success) {
                    clearInterval(resendInterval);
                    goStep(3);
                } else {
                    Swal.fire({ icon: 'error', title: 'Invalid OTP', text: data.message });
                    clearOtp();
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
            } finally {
                setBtn('verifyOtpBtn', false, '<i class="bi bi-check-circle me-2"></i>Verify OTP');
            }
        });

        // STEP 3: Reset Password
        document.getElementById('resetPassBtn').addEventListener('click', async function () {
            var newPass = document.getElementById('newPass').value;
            var confirmPass = document.getElementById('confirmPass').value;

            if (!newPass || !confirmPass) { Swal.fire({ icon: 'warning', title: 'Required', text: 'Please fill in both password fields.' }); return; }
            if (newPass.length < 6) { Swal.fire({ icon: 'error', title: 'Too Short', text: 'Password must be at least 6 characters.' }); return; }
            if (newPass !== confirmPass) { Swal.fire({ icon: 'error', title: 'Mismatch', text: 'Passwords do not match.' }); return; }

            setBtn('resetPassBtn', true, '<i class="bi bi-hourglass-split me-2"></i>Resetting...');
            try {
                var fd = new FormData();
                fd.append('action', 'reset_password');
                fd.append('email', currentEmail);
                fd.append('password', newPass);
                fd.append('confirm_password', confirmPass);
                var res = await fetch('backend/forgot-password.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.success) {
                    await Swal.fire({ icon: 'success', title: 'Password Reset!', text: 'Your password has been changed. You can now login.', timer: 2500, showConfirmButton: false });
                    window.location.href = 'login.php';
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
            } finally {
                setBtn('resetPassBtn', false, '<i class="bi bi-shield-check me-2"></i>Reset Password');
            }
        });
    </script>
</body>

</html>