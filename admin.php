<?php
// admin.php — Admin Panel
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$db       = getDB();
$user_id  = $_SESSION['user_id'];
$fullname = $_SESSION['full_name'];

$success = ''; $error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Delete user
    if ($action === 'delete_user') {
        $tid = intval($_POST['target_id'] ?? 0);
        if ($tid !== $user_id) { // Can't delete yourself
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $tid);
            $stmt->execute();
            $stmt->close();
            $success = 'User deleted successfully.';
        } else {
            $error = 'You cannot delete your own account.';
        }
    }

    // Change role
    if ($action === 'change_role') {
        $tid = intval($_POST['target_id'] ?? 0);
        $new_role = in_array($_POST['new_role'] ?? '', ['admin','user']) ? $_POST['new_role'] : 'user';
        if ($tid !== $user_id) {
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $tid);
            $stmt->execute();
            $stmt->close();
            $success = 'Role updated successfully.';
        } else {
            $error = 'You cannot change your own role here.';
        }
    }

    // Reset password
    if ($action === 'reset_password') {
        $tid = intval($_POST['target_id'] ?? 0);
        $new_pass = trim($_POST['new_password'] ?? '');
        if (strlen($new_pass) >= 6) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $tid);
            $stmt->execute();
            $stmt->close();
            $success = 'Password reset successfully.';
        } else {
            $error = 'Password must be at least 6 characters.';
        }
    }

    header('Location: admin.php' . ($success ? '?msg=1' : '?err=1'));
    exit;
}

if (isset($_GET['msg'])) $success = 'Action completed successfully.';
if (isset($_GET['err'])) $error = 'An error occurred.';

// Fetch all users with stats
$users = $db->query("
    SELECT u.*, 
        COUNT(r.id) as record_count,
        COALESCE(SUM(CASE WHEN r.type='expense' THEN r.amount ELSE 0 END), 0) as total_expense,
        COALESCE(SUM(CASE WHEN r.type='earning' THEN r.amount ELSE 0 END), 0) as total_earning
    FROM users u
    LEFT JOIN records r ON r.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Overall stats
$stats = $db->query("SELECT COUNT(*) as total_users FROM users")->fetch_assoc();
$rec_stats = $db->query("SELECT COUNT(*) as total_records, SUM(amount) as total_money FROM records")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FinTrack — Admin Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
    --red:#e63946; --red2:#c1121f; --green:#10b981; --dark:#f5f7fa;
    --dark2:#ffffff; --dark3:#f0f1f3; --light:#1a1a2e; --muted:#6b7280;
    --border:#e5e7eb; --gold:#f59e0b; --blue:#3b82f6;
}
* { margin:0; padding:0; box-sizing:border-box; }
@keyframes fadeInUp { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
body { background:var(--dark); font-family:'Sora',sans-serif; font-size:14px; color:var(--light); }

.navbar { background:var(--dark2); border-bottom:1px solid var(--border); padding:0 32px; height:58px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
.nav-logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
.nav-logo-icon { width:36px; height:36px; background:var(--red); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800; color:white; }
.nav-logo-text { font-size:17px; font-weight:800; color:var(--light); letter-spacing:-0.5px; }
.nav-logo-text span { color:var(--red); }
.nav-links { display:flex; align-items:center; gap:6px; }
.nav-link { padding:7px 14px; border-radius:7px; color:var(--muted); text-decoration:none; font-size:13px; font-weight:500; transition:all 0.2s; }
.nav-link:hover, .nav-link.active { color:var(--light); background:var(--dark3); }
.nav-right { display:flex; align-items:center; gap:12px; }
.nav-user { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--muted); }
.nav-avatar { width:32px; height:32px; background:var(--gold); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:var(--dark); }
.btn-logout { padding:7px 14px; background:rgba(230,57,70,0.12); border:1px solid rgba(230,57,70,0.3); color:var(--red); border-radius:7px; text-decoration:none; font-size:12px; font-weight:600; }

.page-wrap { max-width:1100px; margin:0 auto; padding:28px 24px; }
.page-header { margin-bottom:24px; }
.page-title { font-size:22px; font-weight:700; letter-spacing:-0.5px; }
.page-title-sub { font-size:13px; color:var(--muted); margin-top:4px; }

.alert { padding:12px 16px; border-radius:8px; font-size:13px; margin-bottom:20px; border-left:3px solid; }
.alert-success { background:rgba(45,198,83,0.1); border-color:var(--green); color:#5dd48a; }
.alert-error   { background:rgba(230,57,70,0.1); border-color:var(--red); color:#ff8a92; }

.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:28px; }
.stat-card { background:var(--dark2); border:1px solid var(--border); border-radius:12px; padding:18px 20px; }
.stat-label { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:1.5px; color:var(--muted); margin-bottom:8px; }
.stat-val { font-size:26px; font-weight:800; font-family:'JetBrains Mono',monospace; }
.val-gold { color:var(--gold); }
.val-blue { color:var(--blue); }
.val-green { color:var(--green); }
.val-red  { color:var(--red); }

.section-title { font-size:15px; font-weight:700; margin-bottom:16px; display:flex; align-items:center; gap:8px; }

.users-table-wrap { background:var(--dark2); border:1px solid var(--border); border-radius:12px; overflow:hidden; }

.users-table { width:100%; border-collapse:collapse; }
.users-table th { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); padding:12px 18px; text-align:left; border-bottom:1px solid var(--border); background:rgba(255,255,255,0.02); }
.users-table td { padding:14px 18px; border-bottom:1px solid rgba(46,46,46,0.4); font-size:13px; vertical-align:middle; }
.users-table tr:last-child td { border-bottom:none; }
.users-table tr:hover td { background:rgba(255,255,255,0.015); }

.user-info { display:flex; align-items:center; gap:12px; }
.user-avatar { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex-shrink:0; }
.ua-admin { background:var(--gold); color:var(--dark); }
.ua-user  { background:var(--blue); color:white; }
.user-name { font-weight:600; color:var(--light); }
.user-email { font-size:11px; color:#555; }

.role-badge { font-size:10px; font-weight:700; padding:3px 9px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px; }
.badge-admin { background:rgba(244,162,97,0.15); color:var(--gold); }
.badge-user  { background:rgba(72,149,239,0.15); color:var(--blue); }

.mono { font-family:'JetBrains Mono',monospace; }

.actions-cell { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }

.btn-sm { padding:5px 10px; border-radius:5px; font-size:11px; font-weight:600; cursor:pointer; font-family:'Sora',sans-serif; border:1px solid; transition:all 0.2s; }
.btn-danger { background:rgba(230,57,70,0.1); border-color:rgba(230,57,70,0.3); color:var(--red); }
.btn-danger:hover { background:rgba(230,57,70,0.25); }
.btn-warn { background:rgba(244,162,97,0.1); border-color:rgba(244,162,97,0.3); color:var(--gold); }
.btn-warn:hover { background:rgba(244,162,97,0.25); }
.btn-info { background:rgba(72,149,239,0.1); border-color:rgba(72,149,239,0.3); color:var(--blue); }
.btn-info:hover { background:rgba(72,149,239,0.25); }

/* Modal */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal { background:var(--dark2); border:1px solid var(--border); border-radius:14px; padding:28px; width:100%; max-width:400px; }
.modal-title { font-size:16px; font-weight:700; margin-bottom:18px; }
.modal-input { width:100%; padding:10px 12px; background:var(--dark3); border:1.5px solid var(--border); border-radius:7px; font-family:'Sora',sans-serif; font-size:13px; color:var(--light); outline:none; margin-bottom:14px; }
.modal-input:focus { border-color:var(--red); }
.modal-btns { display:flex; gap:8px; }
.btn-modal-cancel { flex:1; padding:10px; background:transparent; border:1px solid var(--border); color:var(--muted); border-radius:7px; font-family:'Sora',sans-serif; font-size:13px; cursor:pointer; }
.btn-modal-submit { flex:1; padding:10px; background:var(--red); border:none; color:white; border-radius:7px; font-family:'Sora',sans-serif; font-size:13px; font-weight:700; cursor:pointer; }

.admin-key-box { background:var(--dark3); border:1px solid var(--border); border-radius:8px; padding:14px 16px; margin-top:24px; }
.admin-key-box p { font-size:12px; color:var(--muted); margin-bottom:8px; }
.admin-key-val { font-family:'JetBrains Mono',monospace; font-size:14px; color:var(--gold); background:rgba(244,162,97,0.1); padding:8px 12px; border-radius:5px; display:inline-block; }
</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo">
        <div class="nav-logo-icon">₹</div>
        <span class="nav-logo-text">Fin<span>Track</span></span>
    </a>
    <div class="nav-links">
        <a href="index.php" class="nav-link">Dashboard</a>
        <a href="records.php" class="nav-link">All Records</a>
        <a href="admin.php" class="nav-link active">Admin Panel</a>
    </div>
    <div class="nav-right">
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($fullname, 0, 1)) ?></div>
            <span><?= htmlspecialchars($fullname) ?></span>
            <span class="role-badge badge-admin">Admin</span>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">⚙️ Admin Panel</div>
        <div class="page-title-sub">Manage users, roles, and view system stats</div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-val val-gold"><?= $stats['total_users'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Records</div>
            <div class="stat-val val-blue"><?= $rec_stats['total_records'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Transacted</div>
            <div class="stat-val val-green">₹<?= number_format($rec_stats['total_money'] ?? 0, 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Admin Accounts</div>
            <div class="stat-val val-red"><?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) ?></div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="section-title">👥 All Users</div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Records</th>
                    <th>Total Spent</th>
                    <th>Total Earned</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div class="user-info">
                        <div class="user-avatar <?= $u['role']==='admin'?'ua-admin':'ua-user' ?>"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
                        <div>
                            <div class="user-name"><?= htmlspecialchars($u['full_name']) ?></div>
                            <div class="user-email">@<?= htmlspecialchars($u['username']) ?><?= $u['email'] ? ' · '.$u['email'] : '' ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="role-badge <?= $u['role']==='admin'?'badge-admin':'badge-user' ?>"><?= ucfirst($u['role']) ?></span></td>
                <td class="mono"><?= $u['record_count'] ?></td>
                <td class="mono" style="color:var(--red);">₹<?= number_format($u['total_expense'], 0) ?></td>
                <td class="mono" style="color:var(--green);">₹<?= number_format($u['total_earning'], 0) ?></td>
                <td style="color:var(--muted); font-size:12px;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if ($u['id'] != $user_id): ?>
                    <div class="actions-cell">
                        <!-- Toggle Role -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="new_role" value="<?= $u['role']==='admin'?'user':'admin' ?>">
                            <button type="submit" class="btn-sm btn-warn" onclick="return confirm('Change role to <?= $u['role']==='admin'?'user':'admin' ?>?')">
                                <?= $u['role']==='admin'?'→ User':'→ Admin' ?>
                            </button>
                        </form>

                        <!-- Reset Password -->
                        <button type="button" class="btn-sm btn-info" onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">Reset PW</button>

                        <!-- Delete -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Delete user <?= htmlspecialchars($u['username']) ?> and all their records?')">Delete</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <span style="font-size:12px; color:#444;">That's you</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Admin Key Info -->
    <div class="admin-key-box">
        <p>🔑 Share this <strong>Admin Registration Key</strong> with people who should have admin access:</p>
        <div class="admin-key-val"><?= ADMIN_SECRET_KEY ?></div>
        <p style="margin-top:8px;">To change the key, edit <code style="color:var(--blue);">config.php</code> → <code style="color:var(--blue);">ADMIN_SECRET_KEY</code></p>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetModal">
    <div class="modal">
        <div class="modal-title">Reset Password for <span id="modalUsername" style="color:var(--blue);"></span></div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_id" id="modalTargetId">
            <input type="password" name="new_password" class="modal-input" placeholder="New password (min 6 chars)" required>
            <div class="modal-btns">
                <button type="button" class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-modal-submit">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(id, username) {
    document.getElementById('modalTargetId').value = id;
    document.getElementById('modalUsername').textContent = username;
    document.getElementById('resetModal').classList.add('open');
}
function closeModal() {
    document.getElementById('resetModal').classList.remove('open');
}
document.getElementById('resetModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
