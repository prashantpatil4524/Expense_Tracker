<?php
// login.php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // login | register

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $db = getDB();

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $db->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }

    } elseif ($action === 'register') {
        $username  = trim($_POST['username'] ?? '');
        $password  = trim($_POST['password'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $reg_role  = 'user';
        $admin_key = trim($_POST['admin_key'] ?? '');

        // Check if admin key was entered
        if ($admin_key !== '') {
            if ($admin_key === ADMIN_SECRET_KEY) {
                $reg_role = 'admin';
            } else {
                $error = 'Invalid admin key. Leave blank to register as regular user.';
            }
        }

        if ($error === '') {
            if ($username === '' || $password === '' || $full_name === '') {
                $error = 'Username, full name and password are required.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                // Check duplicate
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $error = 'Username already taken.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt2 = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->bind_param("sssss", $username, $hashed, $full_name, $email, $reg_role);
                    if ($stmt2->execute()) {
                        $success = ($reg_role === 'admin')
                            ? 'Admin account created! You can now log in.'
                            : 'Account created successfully! You can now log in.';
                        $mode = 'login';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                    $stmt2->close();
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense Tracker — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
    --red:    #e63946;
    --red2:   #c1121f;
    --dark:   #0f0f0f;
    --dark2:  #1a1a1a;
    --dark3:  #242424;
    --dark4:  #2e2e2e;
    --light:  #f8f5f0;
    --muted:  #888;
    --border: #333;
    --gold:   #f4a261;
}

* { margin:0; padding:0; box-sizing:border-box; }

body {
    background: var(--dark);
    font-family: 'Sora', sans-serif;
    min-height: 100vh;
    display: flex;
    overflow: hidden;
}

/* Left decorative panel */
.left-panel {
    width: 46%;
    background: var(--red2);
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    padding: 60px 56px;
    overflow: hidden;
}

.left-panel::before {
    content: '';
    position: absolute;
    top: -120px; right: -120px;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
}
.left-panel::after {
    content: '';
    position: absolute;
    bottom: -80px; left: -80px;
    width: 300px; height: 300px;
    background: rgba(0,0,0,0.1);
    border-radius: 50%;
}

.logo-area {
    position: relative;
    z-index: 2;
    margin-bottom: 48px;
}

.logo-icon {
    width: 72px;
    height: 72px;
    background: white;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

.logo-icon svg {
    width: 40px;
    height: 40px;
}

.logo-title {
    font-size: 32px;
    font-weight: 800;
    color: white;
    letter-spacing: -1px;
    line-height: 1;
}

.logo-sub {
    color: rgba(255,255,255,0.65);
    font-size: 13px;
    margin-top: 6px;
    font-weight: 400;
    letter-spacing: 0.5px;
}

.features {
    position: relative;
    z-index: 2;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 20px;
    color: rgba(255,255,255,0.85);
    font-size: 13.5px;
}

.feature-dot {
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    flex-shrink: 0;
    opacity: 0.8;
}

.deco-numbers {
    position: absolute;
    bottom: 40px;
    right: 40px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: rgba(255,255,255,0.25);
    text-align: right;
    z-index: 2;
}

/* Right form panel */
.right-panel {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    background: var(--dark);
}

.form-box {
    width: 100%;
    max-width: 400px;
}

.form-heading {
    font-size: 26px;
    font-weight: 700;
    color: var(--light);
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}

.form-subheading {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 32px;
}

.tab-row {
    display: flex;
    gap: 4px;
    background: var(--dark2);
    padding: 4px;
    border-radius: 10px;
    margin-bottom: 28px;
}

.tab-btn {
    flex: 1;
    padding: 9px;
    border: none;
    border-radius: 7px;
    font-family: 'Sora', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    background: transparent;
    color: var(--muted);
    text-decoration: none;
    text-align: center;
}

.tab-btn.active {
    background: var(--red);
    color: white;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 7px;
}

.form-input {
    width: 100%;
    padding: 12px 14px;
    background: var(--dark2);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'Sora', sans-serif;
    font-size: 14px;
    color: var(--light);
    outline: none;
    transition: border-color 0.2s, background 0.2s;
}

.form-input:focus {
    border-color: var(--red);
    background: var(--dark3);
}

.form-input::placeholder { color: #555; }

.form-hint {
    font-size: 11px;
    color: #555;
    margin-top: 5px;
}

.form-hint span { color: var(--gold); }

.btn-submit {
    width: 100%;
    padding: 13px;
    background: var(--red);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: 'Sora', sans-serif;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 8px;
    transition: background 0.2s, transform 0.1s;
    letter-spacing: 0.2px;
}

.btn-submit:hover { background: var(--red2); transform: translateY(-1px); }
.btn-submit:active { transform: translateY(0); }

.alert {
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 18px;
    border-left: 3px solid;
}

.alert-error {
    background: rgba(230,57,70,0.12);
    border-color: var(--red);
    color: #ff8a92;
}

.alert-success {
    background: rgba(39,174,96,0.12);
    border-color: #27ae60;
    color: #5dd48a;
}

.divider {
    text-align: center;
    position: relative;
    margin: 20px 0 16px;
}

.divider::before {
    content: '';
    position: absolute;
    top: 50%; left: 0;
    width: 100%;
    height: 1px;
    background: var(--border);
}

.divider span {
    position: relative;
    background: var(--dark);
    padding: 0 12px;
    font-size: 11px;
    color: #555;
}

.demo-info {
    background: var(--dark2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 12px 14px;
}

.demo-info p {
    font-size: 11.5px;
    color: #666;
    margin-bottom: 4px;
}

.demo-info code {
    font-family: 'JetBrains Mono', monospace;
    color: var(--gold);
    background: rgba(244,162,97,0.1);
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 11px;
}

@media (max-width: 768px) {
    .left-panel { display: none; }
    .right-panel { padding: 24px 20px; }
}
</style>
</head>
<body>

<!-- Left Decorative Panel -->
<div class="left-panel">
    <div class="logo-area">
        <div class="logo-icon">
            <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="40" height="40" rx="10" fill="#e63946"/>
                <path d="M20 8C13.37 8 8 13.37 8 20C8 26.63 13.37 32 20 32C26.63 32 32 26.63 32 20C32 13.37 26.63 8 20 8ZM21.5 26.5H18.5V24H21.5V26.5ZM21.5 21.5H18.5L18 13.5H22L21.5 21.5Z" fill="white" opacity="0"/>
                <path d="M12 20H28M20 12V28" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                <circle cx="20" cy="20" r="9" stroke="white" stroke-width="2"/>
                <path d="M16 16L24 24M24 16L16 24" stroke="#e63946" stroke-width="1.5" stroke-linecap="round" opacity="0"/>
                <text x="14" y="24" font-family="Arial" font-size="12" font-weight="bold" fill="white">₹</text>
            </svg>
        </div>
        <div class="logo-title">FinTrack</div>
        <div class="logo-sub">Smart Expense Management</div>
    </div>

    <div class="features">
        <div class="feature-item"><span class="feature-dot"></span>Track income &amp; expenses in real-time</div>
        <div class="feature-item"><span class="feature-dot"></span>Visual breakdowns by category</div>
        <div class="feature-item"><span class="feature-dot"></span>Monthly summaries &amp; reports</div>
        <div class="feature-item"><span class="feature-dot"></span>Multi-user with admin controls</div>
        <div class="feature-item"><span class="feature-dot"></span>Secure MySQL-backed storage</div>
    </div>

    <div class="deco-numbers">
        ₹ 0.00<br>
        BALANCE
    </div>
</div>

<!-- Right Form Panel -->
<div class="right-panel">
    <div class="form-box">
        <div class="form-heading"><?= $mode === 'register' ? 'Create Account' : 'Welcome Back' ?></div>
        <div class="form-subheading"><?= $mode === 'register' ? 'Join FinTrack and start managing your money' : 'Sign in to your FinTrack account' ?></div>

        <!-- Tabs -->
        <div class="tab-row">
            <a href="?mode=login" class="tab-btn <?= $mode === 'login' ? 'active' : '' ?>">Sign In</a>
            <a href="?mode=register" class="tab-btn <?= $mode === 'register' ? 'active' : '' ?>">Register</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($mode === 'login'): ?>
        <!-- LOGIN FORM -->
        <form method="POST">
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" placeholder="Enter username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Enter password" autocomplete="current-password">
            </div>

            <button type="submit" class="btn-submit">Sign In →</button>
        </form>

        <div class="divider"><span>DEMO CREDENTIALS</span></div>
        <div class="demo-info">
            <p>Admin: <code>admin</code> / <code>admin123</code></p>
            <p style="margin-top:6px; font-size:11px; color:#555;">First run db_setup.sql then update admin password</p>
        </div>

        <?php else: ?>
        <!-- REGISTER FORM -->
        <form method="POST">
            <input type="hidden" name="action" value="register">

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-input" placeholder="Your full name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" placeholder="Choose a username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Email <span style="color:#555; font-size:10px;">(optional)</span></label>
                <input type="email" name="email" class="form-input" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Min. 6 characters">
            </div>

            <div class="form-group">
                <label class="form-label">Admin Key <span style="color:#555; font-size:10px;">(optional)</span></label>
                <input type="password" name="admin_key" class="form-input" placeholder="Leave blank for regular account">
                <p class="form-hint">Have an <span>admin key</span>? Enter it to register as admin.</p>
            </div>

            <button type="submit" class="btn-submit">Create Account →</button>
        </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
