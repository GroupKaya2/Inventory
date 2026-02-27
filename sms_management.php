<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
$activePage = 'sms_management';

// Get current recipients
$recipients = [];
$chk = @$conn->query("SHOW TABLES LIKE 'sms_settings'");
if ($chk && $chk->num_rows > 0) {
    $row = $conn->query("SELECT setting_value FROM sms_settings WHERE setting_key='recipients' LIMIT 1")->fetch_assoc();
    if ($row) $recipients = json_decode($row['setting_value'], true) ?: [];
}

// Get SMS history
$history = [];
$chkH = @$conn->query("SHOW TABLES LIKE 'sms_history'");
if ($chkH && $chkH->num_rows > 0) {
    $r = $conn->query("SELECT * FROM sms_history ORDER BY sent_at DESC LIMIT 50");
    if ($r) while ($row = $r->fetch_assoc()) $history[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SMS Management – Dispeedway</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body{background:#f0f2f8;}
.page-header{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:24px 28px;border-radius:14px;margin-bottom:24px;}
.card{border:none;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.08);}
.table thead th{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:.78rem;font-weight:600;border:none;}
.badge-success-sms{background:#dcfce7;color:#166534;}
.badge-fail-sms{background:#fee2e2;color:#991b1b;}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>
<main class="app-main p-3 p-md-4">
<div class="container-fluid">
<div class="page-header">
    <h4 class="mb-0"><i class="bi bi-chat-dots me-2"></i>SMS Alert Management</h4>
    <p class="mb-0 mt-1 opacity-75">Configure recipients and view SMS notification history</p>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white"><i class="bi bi-person-lines-fill me-1"></i>SMS Recipients</div>
            <div class="card-body">
                <p class="text-muted small">Enter phone numbers with country code (e.g. +63917xxxxxxx for Philippines)</p>
                <div id="recipientList">
                    <?php foreach ($recipients as $r): ?>
                    <div class="input-group mb-2 recipient-row">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input type="text" class="form-control recipient-input" value="<?= htmlspecialchars($r) ?>">
                        <button class="btn btn-outline-danger" onclick="removeRecipient(this)"><i class="bi bi-x"></i></button>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($recipients)): ?>
                    <div class="input-group mb-2 recipient-row">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input type="text" class="form-control recipient-input" placeholder="+63917xxxxxxx">
                        <button class="btn btn-outline-danger" onclick="removeRecipient(this)"><i class="bi bi-x"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
                <button class="btn btn-outline-secondary btn-sm mb-3" onclick="addRecipient()"><i class="bi bi-plus"></i> Add Recipient</button>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="saveRecipients()"><i class="bi bi-save"></i> Save Settings</button>
                    <button class="btn btn-outline-success" onclick="testSms()"><i class="bi bi-send"></i> Test SMS</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-info text-white"><i class="bi bi-gear me-1"></i>SMS Provider Info</div>
            <div class="card-body small text-muted">
                <p>Edit <code>sms_service.php</code> to configure:</p>
                <ul>
                    <li><strong>Semaphore (PH):</strong> Set <code>$this->enabled = true</code> and add your API key</li>
                    <li><strong>Twilio (Global):</strong> Set provider to 'twilio' and add credentials</li>
                </ul>
                <p>Get Semaphore API: <a href="https://semaphore.co" target="_blank">semaphore.co</a></p>
                <p>Get Twilio API: <a href="https://twilio.com" target="_blank">twilio.com</a></p>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-secondary text-white"><i class="bi bi-clock-history me-1"></i>SMS History (Last 50)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Date/Time</th><th>Recipient</th><th>Status</th><th>Message Preview</th></tr></thead>
                        <tbody>
                        <?php if (empty($history)): ?>
                        <tr><td colspan="4" class="text-center py-3 text-muted">No SMS history yet.</td></tr>
                        <?php else: foreach ($history as $h): ?>
                        <tr>
                            <td style="font-size:.78rem"><?= date('M d H:i', strtotime($h['sent_at'])) ?></td>
                            <td style="font-size:.8rem"><?= htmlspecialchars($h['recipient']) ?></td>
                            <td>
                                <span class="badge <?= $h['status']==='success'?'badge-success-sms':'badge-fail-sms' ?>">
                                    <?= $h['status'] ?>
                                </span>
                            </td>
                            <td style="font-size:.78rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($h['message']) ?>">
                                <?= htmlspecialchars(substr($h['message'],0,60)) ?>…
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function addRecipient() {
    const div = document.createElement('div');
    div.className = 'input-group mb-2 recipient-row';
    div.innerHTML = `<span class="input-group-text"><i class="bi bi-phone"></i></span><input type="text" class="form-control recipient-input" placeholder="+63917xxxxxxx"><button class="btn btn-outline-danger" onclick="removeRecipient(this)"><i class="bi bi-x"></i></button>`;
    document.getElementById('recipientList').appendChild(div);
}
function removeRecipient(btn) {
    const row = btn.closest('.recipient-row');
    if (document.querySelectorAll('.recipient-row').length > 1) row.remove();
    else row.querySelector('input').value = '';
}
async function saveRecipients() {
    const recipients = [...document.querySelectorAll('.recipient-input')].map(i => i.value.trim()).filter(Boolean);
    const resp = await fetch('save_sms_settings.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({recipients})});
    const data = await resp.json();
    Swal.fire({icon: data.success?'success':'error', title: data.success?'Saved!':'Error', text: data.message, timer: 1800, showConfirmButton: false});
}
async function testSms() {
    const first = document.querySelector('.recipient-input')?.value.trim();
    if (!first) { Swal.fire({icon:'warning',title:'No recipient',text:'Add a recipient first.'}); return; }
    const resp = await fetch('test_sms.php?phone=' + encodeURIComponent(first));
    const data = await resp.json();
    Swal.fire({icon: data.success?'success':'warning', title: data.success?'Sent!':'Note', text: data.message});
}
</script>
</body>
</html>