<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$activePage = 'sms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8fafc;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }
        .stat-card.failed {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        .stat-card.pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .badge-success {
            background-color: #22c55e;
        }
        .badge-failed {
            background-color: #ef4444;
        }
        .badge-pending {
            background-color: #f59e0b;
        }
        .recipient-tag {
            display: inline-block;
            background: #e0e7ff;
            color: #4338ca;
            padding: 4px 12px;
            border-radius: 20px;
            margin: 4px;
            font-size: 0.85rem;
        }
        .recipient-tag .remove-btn {
            margin-left: 8px;
            cursor: pointer;
            color: #6366f1;
        }
        .recipient-tag .remove-btn:hover {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>

    <main class="app-main p-3 p-md-4">
        <div class="container-fluid">
            <div class="page-header">
                <h1 class="mb-0"><i class="bi bi-chat-dots"></i> SMS Management</h1>
                <p class="mb-0 mt-2">Manage SMS notifications, view history, and configure recipients</p>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Total SMS Sent</div>
                        <div class="stat-value" id="statTotal">0</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success">
                        <div class="stat-label">Successful</div>
                        <div class="stat-value" id="statSuccess">0</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card failed">
                        <div class="stat-label">Failed</div>
                        <div class="stat-value" id="statFailed">0</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card pending">
                        <div class="stat-label">Pending</div>
                        <div class="stat-value" id="statPending">0</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- SMS Settings -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-gear"></i> SMS Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Recipients</label>
                                <div id="recipientsList" class="mb-2"></div>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="newRecipient" placeholder="+639123456789">
                                    <button class="btn btn-primary" onclick="addRecipient()">
                                        <i class="bi bi-plus"></i> Add
                                    </button>
                                </div>
                                <small class="text-muted">Format: +country code + number (e.g., +639123456789)</small>
                            </div>
                            <button class="btn btn-success w-100" onclick="saveSettings()">
                                <i class="bi bi-save"></i> Save Settings
                            </button>
                            <button class="btn btn-warning w-100 mt-2" onclick="testSMS()">
                                <i class="bi bi-send"></i> Send Test SMS
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SMS History -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> SMS History</h5>
                            <div>
                                <select class="form-select form-select-sm" id="statusFilter" onchange="loadHistory()">
                                    <option value="">All Status</option>
                                    <option value="success">Success</option>
                                    <option value="failed">Failed</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Recipient</th>
                                            <th>Status</th>
                                            <th>Message</th>
                                            <th>Provider</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Message Preview Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">SMS Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="messageContent" style="white-space: pre-wrap; font-family: inherit;"></pre>
                    <div id="errorContent" class="alert alert-danger mt-2" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let recipients = [];

        // Load initial data
        document.addEventListener('DOMContentLoaded', function() {
            loadSettings();
            loadStatistics();
            loadHistory();
        });

        function loadSettings() {
            fetch('fetch_sms_settings.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        recipients = data.recipients || [];
                        renderRecipients();
                    }
                })
                .catch(error => {
                    console.error('Error loading settings:', error);
                });
        }

        function renderRecipients() {
            const container = document.getElementById('recipientsList');
            if (recipients.length === 0) {
                container.innerHTML = '<p class="text-muted small">No recipients added</p>';
                return;
            }
            container.innerHTML = recipients.map((rec, index) => 
                `<span class="recipient-tag">${rec} <span class="remove-btn" onclick="removeRecipient(${index})">×</span></span>`
            ).join('');
        }

        function addRecipient() {
            const input = document.getElementById('newRecipient');
            const phone = input.value.trim();
            
            if (!phone) {
                Swal.fire('Error', 'Please enter a phone number', 'error');
                return;
            }

            // Validate format
            if (!phone.match(/^\+?[1-9]\d{9,14}$/)) {
                Swal.fire('Error', 'Invalid phone number format. Use: +countrycode+number (e.g., +639123456789)', 'error');
                return;
            }

            // Ensure starts with +
            const formattedPhone = phone.startsWith('+') ? phone : '+' + phone;

            if (recipients.includes(formattedPhone)) {
                Swal.fire('Error', 'Recipient already exists', 'error');
                return;
            }

            recipients.push(formattedPhone);
            input.value = '';
            renderRecipients();
        }

        function removeRecipient(index) {
            recipients.splice(index, 1);
            renderRecipients();
        }

        function saveSettings() {
            fetch('save_sms_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ recipients: recipients })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', 'Settings saved successfully', 'success');
                } else {
                    Swal.fire('Error', data.message || 'Failed to save settings', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Failed to save settings', 'error');
            });
        }

        function testSMS() {
            if (recipients.length === 0) {
                Swal.fire('Error', 'Please add at least one recipient', 'error');
                return;
            }

            Swal.fire({
                title: 'Send Test SMS?',
                text: `This will send a test message to ${recipients.length} recipient(s)`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Send',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('send_test_sms.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ recipients: recipients })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Success', `Test SMS sent to ${data.sent_count} recipient(s)`, 'success');
                            loadHistory();
                            loadStatistics();
                        } else {
                            Swal.fire('Error', data.message || 'Failed to send test SMS', 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Failed to send test SMS', 'error');
                    });
                }
            });
        }

        function loadStatistics() {
            fetch('fetch_sms_statistics.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('statTotal').textContent = data.stats.total || 0;
                        document.getElementById('statSuccess').textContent = data.stats.success || 0;
                        document.getElementById('statFailed').textContent = data.stats.failed || 0;
                        document.getElementById('statPending').textContent = data.stats.pending || 0;
                    }
                })
                .catch(error => {
                    console.error('Error loading statistics:', error);
                });
        }

        function loadHistory() {
            const status = document.getElementById('statusFilter').value;
            const url = 'fetch_sms_history.php' + (status ? '?status=' + status : '');
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('historyTableBody');
                    if (data.success && data.history.length > 0) {
                        tbody.innerHTML = data.history.map(item => {
                            const date = new Date(item.sent_at);
                            const statusBadge = `<span class="badge badge-${item.status}">${item.status}</span>`;
                            const messagePreview = item.message.length > 50 
                                ? item.message.substring(0, 50) + '...' 
                                : item.message;
                            return `
                                <tr>
                                    <td>${date.toLocaleString()}</td>
                                    <td>${item.recipient}</td>
                                    <td>${statusBadge}</td>
                                    <td>
                                        <a href="#" onclick="showMessage('${item.message.replace(/'/g, "\\'")}', '${item.error_message || ''}'); return false;">
                                            ${messagePreview}
                                        </a>
                                    </td>
                                    <td>${item.provider || 'N/A'}</td>
                                </tr>
                            `;
                        }).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No SMS history found</td></tr>';
                    }
                })
                .catch(error => {
                    document.getElementById('historyTableBody').innerHTML = 
                        '<tr><td colspan="5" class="text-center text-danger">Error loading history</td></tr>';
                });
        }

        function showMessage(message, error) {
            document.getElementById('messageContent').textContent = message;
            const errorDiv = document.getElementById('errorContent');
            if (error) {
                errorDiv.textContent = 'Error: ' + error;
                errorDiv.style.display = 'block';
            } else {
                errorDiv.style.display = 'none';
            }
            new bootstrap.Modal(document.getElementById('messageModal')).show();
        }
    </script>
</body>
</html>
