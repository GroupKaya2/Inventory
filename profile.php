<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$activePage = 'profile';

$userId = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-card { border: none; border-radius: 14px; box-shadow: 0 10px 30px rgba(2,6,23,.08); }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-radius: 14px 14px 0 0;
        }
        .kv { color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }
        .vv { font-weight: 600; color: #0f172a; }
    </style>
</head>
<body>
<?php include "sidebar.php"; ?>

<main class="app-main p-3 p-md-4">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0">Profile</h3>
        </div>

        <div class="card profile-card">
            <div class="card-body p-0">
                <div class="profile-header p-4">
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                    <div class="opacity-75"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="kv">Role</div>
                            <div class="vv"><?php echo htmlspecialchars(strtoupper($user['role'])); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="kv">User ID</div>
                            <div class="vv"><?php echo intval($user['id']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="kv">Status</div>
                            <div class="vv">Active</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <?php if ($user['role'] === 'owner'): ?>
                        <div class="d-flex flex-column flex-md-row gap-3 mb-3">
                            <a href="register.php" class="btn btn-success">
                                <i class="bi bi-person-badge"></i> Add Manager
                            </a>
                        </div>

                        <div class="card border-0 shadow-sm mb-0">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <h5 class="mb-0">Manage Users</h5>
                                    <span class="badge bg-primary">Owner only</span>
                                </div>
                                <?php
                                $stmtList = $conn->prepare("SELECT id, name, email, role FROM users ORDER BY id DESC");
                                $stmtList->execute();
                                $resultList = $stmtList->get_result();
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th style="width: 140px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($resultList->num_rows > 0): ?>
                                                <?php while ($row = $resultList->fetch_assoc()): ?>
                                                    <?php
                                                        $isSelf = ($row['id'] == $user['id']);
                                                        $isOwnerRow = ($row['role'] === 'owner');
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                        <td>
                                                            <span class="badge text-bg-<?php echo $isOwnerRow ? 'primary' : 'secondary'; ?>">
                                                                <?php echo htmlspecialchars($row['role']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($isSelf || $isOwnerRow): ?>
                                                                <span class="text-muted">—</span>
                                                            <?php else: ?>
                                                                <a class="btn btn-sm btn-outline-danger"
                                                                   href="delete_user.php?id=<?php echo intval($row['id']); ?>"
                                                                   onclick="return confirm('Are you sure you want to delete this user?')">
                                                                    Delete
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center text-muted">No users found.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php $stmtList->close(); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            This page shows your account details. Only the owner can manage users.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


