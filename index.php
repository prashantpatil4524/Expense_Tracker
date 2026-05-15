<?php
// index.php — Dashboard
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db       = getDB();
$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$fullname = $_SESSION['full_name'];
$role     = $_SESSION['role'];

// Handle Add / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $type     = in_array($_POST['type'] ?? '', ['expense','earning']) ? $_POST['type'] : 'expense';
        $item     = htmlspecialchars(trim($_POST['item'] ?? ''));
        $amount   = floatval($_POST['amount'] ?? 0);
        $category = htmlspecialchars(trim($_POST['category'] ?? 'Other'));
        $note     = htmlspecialchars(trim($_POST['note'] ?? ''));
        $date     = $_POST['date'] ?? date('Y-m-d');

        if ($item !== '' && $amount > 0) {
            $id = uniqid('', true);
            $stmt = $db->prepare("INSERT INTO records (id, user_id, type, item, amount, category, note, date) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("sississs", $id, $user_id, $type, $item, $amount, $category, $note, $date);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'delete') {
        $rec_id = $_POST['rec_id'] ?? '';
        $stmt = $db->prepare("DELETE FROM records WHERE id = ? AND user_id = ?");
        $stmt->bind_param("si", $rec_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: index.php');
    exit;
}

// Fetch records for current user, current month by default
$month = $_GET['month'] ?? date('Y-m');
list($y, $m) = explode('-', $month . '-01');

$stmt = $db->prepare("SELECT * FROM records WHERE user_id = ? AND DATE_FORMAT(date,'%Y-%m') = ? ORDER BY date DESC, created_at DESC");
$stmt->bind_param("is", $user_id, $month);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Totals
$total_earning = 0; $total_expense = 0;
foreach ($records as $r) {
    if ($r['type'] === 'earning') $total_earning += $r['amount'];
    else $total_expense += $r['amount'];
}
$balance = $total_earning - $total_expense;

// Category summary
$cat_summary = [];
foreach ($records as $r) {
    if ($r['type'] === 'expense') {
        $cat_summary[$r['category']] = ($cat_summary[$r['category']] ?? 0) + $r['amount'];
    }
}
arsort($cat_summary);

// Monthly trend (last 6 months)
$trend_stmt = $db->prepare("SELECT DATE_FORMAT(date,'%Y-%m') as mon, type, SUM(amount) as total FROM records WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY mon, type ORDER BY mon");
$trend_stmt->bind_param("i", $user_id);
$trend_stmt->execute();
$trend_rows = $trend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$trend_stmt->close();

$trend = [];
foreach ($trend_rows as $t) {
    $trend[$t['mon']][$t['type']] = $t['total'];
}

// All time totals for overview
$all_stmt = $db->prepare("SELECT type, SUM(amount) as total FROM records WHERE user_id = ? GROUP BY type");
$all_stmt->bind_param("i", $user_id);
$all_stmt->execute();
$all_rows = $all_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$all_stmt->close();
$all_totals = [];
foreach ($all_rows as $r) $all_totals[$r['type']] = $r['total'];

$cats = ['Food','Transport','Shopping','Bills','Health','Entertainment','Salary','Freelance','Investment','Other'];
$prev_month = date('Y-m', strtotime($month . '-01 -1 month'));
$next_month = date('Y-m', strtotime($month . '-01 +1 month'));
$can_go_next = $next_month <= date('Y-m');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FinTrack — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root {
    --red:    #e63946;
    --red2:   #c1121f;
    --green:  #10b981;
    --green2: #059669;
    --dark:   #f5f7fa;
    --dark2:  #ffffff;
    --dark3:  #f0f1f3;
    --dark4:  #e8e9ec;
    --light:  #1a1a2e;
    --muted:  #6b7280;
    --border: #e5e7eb;
    --gold:   #f59e0b;
    --blue:   #3b82f6;
}

* { margin:0; padding:0; box-sizing:border-box; }

@keyframes fadeInUp { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

body {
    background: var(--dark);
    font-family: 'Sora', sans-serif;
    font-size: 14px;
    color: var(--light);
    min-height: 100vh;
}

/* ─── NAVBAR ─────────────────────────────────── */
.navbar {
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 0 32px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 8px rgba(0,0,0,0.05);
}

.nav-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.nav-logo-icon {
    width: 36px;
    height: 36px;
    background: var(--red);
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 800;
    color: white;
}

.nav-logo-text {
    font-size: 17px;
    font-weight: 800;
    color: var(--light);
    letter-spacing: -0.5px;
}

.nav-logo-text span { color: var(--red); }

.nav-links {
    display: flex;
    align-items: center;
    gap: 6px;
}

.nav-link {
    padding: 7px 14px;
    border-radius: 7px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

.nav-link:hover { color: var(--light); background: var(--dark3); }
.nav-link.active { color: var(--light); background: var(--dark3); }

.nav-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nav-user {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--muted);
}

.nav-avatar {
    width: 32px;
    height: 32px;
    background: var(--red);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: white;
}

.nav-role-badge {
    font-size: 10px;
    padding: 2px 7px;
    border-radius: 20px;
    font-weight: 600;
}

.badge-admin { background: rgba(244,162,97,0.15); color: var(--gold); }
.badge-user  { background: rgba(72,149,239,0.15); color: var(--blue); }

.btn-logout {
    padding: 7px 14px;
    background: rgba(230,57,70,0.12);
    border: 1px solid rgba(230,57,70,0.3);
    color: var(--red);
    border-radius: 7px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-logout:hover { background: rgba(230,57,70,0.2); }

/* ─── LAYOUT ──────────────────────────────────── */
.page-wrap { max-width: 1100px; margin: 0 auto; padding: 28px 24px; }

/* ─── PAGE HEADER ─────────────────────────────── */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.page-title { font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }
.page-title span { color: var(--muted); font-weight: 400; font-size: 16px; }

.month-nav {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--dark2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 6px 12px;
}

.month-nav a {
    color: var(--muted);
    text-decoration: none;
    font-size: 18px;
    line-height: 1;
    padding: 0 4px;
    transition: color 0.2s;
}

.month-nav a:hover { color: var(--light); }
.month-nav a.disabled { opacity: 0.2; pointer-events: none; }

.month-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--light);
    min-width: 110px;
    text-align: center;
}

/* ─── SUMMARY CARDS ──────────────────────────── */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}

.summary-card {
    background: var(--dark2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px 22px;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    animation: fadeInUp 0.5s ease both;
}

.summary-card:nth-child(2) { animation-delay: 0.08s; }
.summary-card:nth-child(3) { animation-delay: 0.16s; }
.summary-card:nth-child(4) { animation-delay: 0.24s; }

.summary-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }

.summary-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}

.card-earned::before  { background: var(--green); }
.card-spent::before   { background: var(--red); }
.card-balance::before { background: var(--blue); }
.card-records::before { background: var(--gold); }

.card-icon {
    font-size: 22px;
    margin-bottom: 12px;
    display: block;
}

.card-label {
    font-size: 10.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--muted);
    margin-bottom: 6px;
}

.card-value {
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -1px;
    font-family: 'JetBrains Mono', monospace;
}

.card-value.green { color: var(--green); }
.card-value.red   { color: var(--red); }
.card-value.blue  { color: var(--blue); }
.card-value.gold  { color: var(--gold); }

.card-sub {
    font-size: 11px;
    color: var(--muted);
    margin-top: 5px;
}

/* ─── TWO COLUMN ─────────────────────────────── */
.two-col { display: grid; grid-template-columns: 1fr 360px; gap: 16px; margin-bottom: 20px; }

/* ─── SECTION BOX ────────────────────────────── */
.box {
    background: var(--dark2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 22px 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    animation: fadeInUp 0.5s ease 0.2s both;
    transition: box-shadow 0.3s;
}

.box:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.07); }

.box-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--light);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
}

.box-title-icon {
    width: 26px;
    height: 26px;
    background: rgba(230,57,70,0.15);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
}

/* ─── ADD FORM ────────────────────────────────── */
.form-type-toggle {
    display: flex;
    gap: 4px;
    background: var(--dark3);
    padding: 4px;
    border-radius: 8px;
    margin-bottom: 16px;
}

.toggle-btn {
    flex: 1;
    padding: 9px;
    border: none;
    border-radius: 6px;
    font-family: 'Sora', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    background: transparent;
    transition: all 0.2s;
}

.toggle-expense { color: #888; }
.toggle-expense.active { background: var(--red); color: white; }
.toggle-earning { color: #888; }
.toggle-earning.active { background: var(--green); color: white; }

.form-row { display: grid; gap: 12px; margin-bottom: 14px; }
.form-row-2 { grid-template-columns: 1fr 1fr; }
.form-row-3 { grid-template-columns: 1fr 1fr 1fr; }

.form-group { }

.form-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 6px;
}

.form-input, .form-select {
    width: 100%;
    padding: 10px 12px;
    background: var(--dark3);
    border: 1.5px solid var(--border);
    border-radius: 7px;
    font-family: 'Sora', sans-serif;
    font-size: 13px;
    color: var(--light);
    outline: none;
    transition: border-color 0.2s;
}

.form-input:focus, .form-select:focus { border-color: var(--red); }
.form-input::placeholder { color: #444; }
.form-select option { background: var(--dark2); }

.btn-add {
    width: 100%;
    padding: 11px;
    background: var(--red);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: 'Sora', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    margin-top: 4px;
}

.btn-add:hover { background: var(--red2); transform: translateY(-1px); }

/* ─── CATEGORY BARS ──────────────────────────── */
.cat-item {
    margin-bottom: 14px;
}

.cat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.cat-name { font-size: 13px; font-weight: 600; color: var(--light); }
.cat-amt { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.cat-pct { font-size: 11px; color: #555; margin-left: 6px; }

.bar-bg {
    height: 6px;
    background: var(--dark3);
    border-radius: 3px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    border-radius: 3px;
    background: var(--red);
    transition: width 0.6s ease;
}

/* ─── RECORDS TABLE ──────────────────────────── */
.records-table { width: 100%; border-collapse: collapse; }
.records-table th {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--muted);
    padding: 10px 14px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

.records-table td {
    padding: 12px 14px;
    border-bottom: 1px solid rgba(46,46,46,0.5);
    font-size: 13px;
    vertical-align: middle;
}

.records-table tr:last-child td { border-bottom: none; }
.records-table tr:hover td { background: rgba(0,0,0,0.02); }

.type-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.type-expense { background: rgba(230,57,70,0.15); color: var(--red); }
.type-earning { background: rgba(45,198,83,0.15); color: var(--green); }

.cat-pill {
    font-size: 11px;
    padding: 3px 9px;
    background: var(--dark3);
    border: 1px solid var(--border);
    border-radius: 20px;
    color: var(--muted);
}

.amount-cell {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 600;
    font-size: 13px;
}

.amount-expense { color: var(--red); }
.amount-earning { color: var(--green); }

.item-name { font-weight: 600; color: var(--light); }
.item-note { font-size: 11px; color: #555; margin-top: 2px; }
.date-cell { color: var(--muted); font-size: 12px; }

.btn-del {
    background: rgba(230,57,70,0.1);
    border: 1px solid rgba(230,57,70,0.2);
    color: var(--red);
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Sora', sans-serif;
    transition: all 0.2s;
}

.btn-del:hover { background: rgba(230,57,70,0.25); }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #555;
}

.empty-state p { font-size: 13px; margin-top: 8px; }

/* ─── VIEW ALL LINK ──────────────────────────── */
.view-all-link {
    display: block;
    text-align: center;
    margin-top: 14px;
    color: var(--red);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    padding: 10px;
    border: 1px dashed rgba(230,57,70,0.3);
    border-radius: 8px;
    transition: all 0.2s;
}

.view-all-link:hover { background: rgba(230,57,70,0.08); border-style: solid; }

/* ─── TREND ──────────────────────────────────── */
.trend-chart { display: flex; align-items: flex-end; gap: 10px; height: 80px; padding: 0 4px; }
.trend-group { flex: 1; display: flex; align-items: flex-end; gap: 3px; }
.trend-bar {
    flex: 1;
    border-radius: 3px 3px 0 0;
    min-height: 4px;
    position: relative;
}

.trend-bar-earn { background: rgba(45,198,83,0.6); }
.trend-bar-exp  { background: rgba(230,57,70,0.6); }

.trend-labels {
    display: flex;
    gap: 10px;
    margin-top: 8px;
    padding: 0 4px;
}

.trend-label {
    flex: 1;
    text-align: center;
    font-size: 10px;
    color: #555;
}

/* ─── RESPONSIVE ─────────────────────────────── */
@media (max-width: 900px) {
    .summary-grid { grid-template-columns: repeat(2, 1fr); }
    .two-col { grid-template-columns: 1fr; }
}

@media (max-width: 600px) {
    .navbar { padding: 0 16px; }
    .page-wrap { padding: 16px; }
    .summary-grid { grid-template-columns: 1fr 1fr; }
    .form-row-3 { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <a href="index.php" class="nav-logo">
        <div class="nav-logo-icon">₹</div>
        <span class="nav-logo-text">Fin<span>Track</span></span>
    </a>

    <div class="nav-links">
        <a href="index.php" class="nav-link active">Dashboard</a>
        <a href="records.php" class="nav-link">All Records</a>
        <?php if ($role === 'admin'): ?>
        <a href="admin.php" class="nav-link">Admin Panel</a>
        <?php endif; ?>
    </div>

    <div class="nav-right">
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($fullname, 0, 1)) ?></div>
            <span><?= htmlspecialchars($fullname) ?></span>
            <span class="nav-role-badge <?= $role === 'admin' ? 'badge-admin' : 'badge-user' ?>"><?= ucfirst($role) ?></span>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="page-wrap">

    <!-- Page Header with Month Nav -->
    <div class="page-header">
        <div>
            <div class="page-title">Dashboard <span>/ <?= date('F Y', strtotime($month . '-01')) ?></span></div>
        </div>
        <div class="month-nav">
            <a href="?month=<?= $prev_month ?>">‹</a>
            <span class="month-label"><?= date('M Y', strtotime($month . '-01')) ?></span>
            <a href="?month=<?= $next_month ?>" class="<?= !$can_go_next ? 'disabled' : '' ?>">›</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card card-earned">
            <span class="card-icon">💰</span>
            <div class="card-label">Earned This Month</div>
            <div class="card-value green">₹<?= number_format($total_earning, 0) ?></div>
            <div class="card-sub">All time: ₹<?= number_format($all_totals['earning'] ?? 0, 0) ?></div>
        </div>
        <div class="summary-card card-spent">
            <span class="card-icon">💸</span>
            <div class="card-label">Spent This Month</div>
            <div class="card-value red">₹<?= number_format($total_expense, 0) ?></div>
            <div class="card-sub">All time: ₹<?= number_format($all_totals['expense'] ?? 0, 0) ?></div>
        </div>
        <div class="summary-card card-balance">
            <span class="card-icon">⚖️</span>
            <div class="card-label">Net Balance</div>
            <div class="card-value <?= $balance >= 0 ? 'green' : 'red' ?>"><?= $balance >= 0 ? '+' : '' ?>₹<?= number_format(abs($balance), 0) ?></div>
            <div class="card-sub"><?= $balance >= 0 ? 'You\'re in the green!' : 'Overspent this month' ?></div>
        </div>
        <div class="summary-card card-records">
            <span class="card-icon">📋</span>
            <div class="card-label">Transactions</div>
            <div class="card-value gold"><?= count($records) ?></div>
            <div class="card-sub">This month's entries</div>
        </div>
    </div>

    <!-- Main Two-Column -->
    <div class="two-col">

        <!-- Left: Add Form + Recent Transactions -->
        <div>
            <!-- Add Entry -->
            <div class="box" style="margin-bottom:16px;">
                <div class="box-title">
                    <div class="box-title-icon">+</div>
                    Add Entry
                </div>

                <form method="POST" id="addForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="type" id="typeField" value="expense">

                    <div class="form-type-toggle">
                        <button type="button" class="toggle-btn toggle-expense active" onclick="setType('expense', this)">▼ Expense</button>
                        <button type="button" class="toggle-btn toggle-earning" onclick="setType('earning', this)">▲ Income</button>
                    </div>

                    <div class="form-row form-row-3">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Item / Description</label>
                            <input type="text" name="item" class="form-input" placeholder="e.g. Lunch, Salary, Rent..." required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" name="amount" class="form-input" placeholder="0.00" min="1" step="0.01" required>
                        </div>
                    </div>

                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <?php foreach ($cats as $c): ?>
                                    <option><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-input" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Note (opt.)</label>
                            <input type="text" name="note" class="form-input" placeholder="Optional note">
                        </div>
                    </div>

                    <button type="submit" class="btn-add" id="addBtn">+ Add Expense</button>
                </form>
            </div>

            <!-- Recent Transactions -->
            <div class="box">
                <div class="box-title">
                    <div class="box-title-icon">📄</div>
                    Recent Transactions
                    <span style="margin-left:auto; font-size:11px; color:var(--muted); font-weight:400;"><?= date('F Y', strtotime($month.'-01')) ?></span>
                </div>

                <?php
                $recent = array_slice($records, 0, 8);
                if (empty($recent)):
                ?>
                <div class="empty-state">
                    <div style="font-size:36px;">📭</div>
                    <p>No records for this month yet.</p>
                </div>
                <?php else: ?>
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th style="text-align:right;">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td>
                                <div class="item-name"><?= htmlspecialchars($r['item']) ?></div>
                                <?php if ($r['note']): ?><div class="item-note"><?= htmlspecialchars($r['note']) ?></div><?php endif; ?>
                            </td>
                            <td><span class="cat-pill"><?= htmlspecialchars($r['category']) ?></span></td>
                            <td class="date-cell"><?= date('d M', strtotime($r['date'])) ?></td>
                            <td class="amount-cell <?= $r['type'] === 'expense' ? 'amount-expense' : 'amount-earning' ?>" style="text-align:right;">
                                <?= $r['type'] === 'earning' ? '+' : '-' ?>₹<?= number_format($r['amount'], 0) ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="rec_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn-del" onclick="return confirm('Delete this record?')">✕</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (count($records) > 8): ?>
                <a href="records.php?month=<?= $month ?>" class="view-all-link">View all <?= count($records) ?> records →</a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div>
            <!-- Category Breakdown Donut Chart -->
            <div class="box" style="margin-bottom:16px;">
                <div class="box-title">
                    <div class="box-title-icon">🥧</div>
                    Expense Breakdown
                </div>

                <?php if (empty($cat_summary)): ?>
                <div class="empty-state"><p>No expenses this month.</p></div>
                <?php else: ?>
                <div style="position:relative; width:100%; max-width:280px; margin:0 auto;">
                    <canvas id="catDonut"></canvas>
                </div>
                <div style="margin-top:16px;">
                <?php foreach ($cat_summary as $cat => $amt):
                    $pct = $total_expense > 0 ? round(($amt / $total_expense) * 100) : 0;
                ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; font-size:13px;">
                    <span style="font-weight:600;"><?= htmlspecialchars($cat) ?></span>
                    <span style="color:var(--muted); font-family:'JetBrains Mono',monospace; font-size:12px;">₹<?= number_format($amt, 0) ?> <span style="color:#aaa;">(<?= $pct ?>%)</span></span>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- 6-Month Trend -->
            <div class="box">
                <div class="box-title">
                    <div class="box-title-icon">📈</div>
                    6-Month Trend
                </div>

                <?php
                $months_sorted = array_keys($trend);
                sort($months_sorted);
                $max_val = 1;
                foreach ($trend as $td) {
                    $max_val = max($max_val, $td['earning'] ?? 0, $td['expense'] ?? 0);
                }
                ?>

                <div class="trend-chart">
                <?php foreach ($months_sorted as $mon):
                    $e_h = isset($trend[$mon]['expense']) ? round(($trend[$mon]['expense'] / $max_val) * 76) : 2;
                    $i_h = isset($trend[$mon]['earning']) ? round(($trend[$mon]['earning'] / $max_val) * 76) : 2;
                ?>
                    <div class="trend-group">
                        <div class="trend-bar trend-bar-earn" style="height:<?= max(4,$i_h) ?>px" title="Income: ₹<?= number_format($trend[$mon]['earning'] ?? 0, 0) ?>"></div>
                        <div class="trend-bar trend-bar-exp" style="height:<?= max(4,$e_h) ?>px" title="Expense: ₹<?= number_format($trend[$mon]['expense'] ?? 0, 0) ?>"></div>
                    </div>
                <?php endforeach;
                if (empty($months_sorted)): ?>
                    <div style="color:#444; font-size:12px; padding:20px;">No data yet</div>
                <?php endif; ?>
                </div>

                <div class="trend-labels">
                <?php foreach ($months_sorted as $mon): ?>
                    <div class="trend-label"><?= date('M', strtotime($mon . '-01')) ?></div>
                <?php endforeach; ?>
                </div>

                <div style="display:flex; gap:16px; margin-top:12px; padding-top:12px; border-top:1px solid var(--border);">
                    <span style="font-size:11px; color:var(--green);">▮ Income</span>
                    <span style="font-size:11px; color:var(--red);">▮ Expense</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setType(type, btn) {
    document.getElementById('typeField').value = type;
    document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const addBtn = document.getElementById('addBtn');
    if (type === 'expense') {
        addBtn.textContent = '+ Add Expense';
        addBtn.style.background = 'var(--red)';
    } else {
        addBtn.textContent = '+ Add Income';
        addBtn.style.background = 'var(--green2)';
    }
}

// Donut Chart
<?php if (!empty($cat_summary)): ?>
const catCtx = document.getElementById('catDonut');
if (catCtx) {
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php echo implode(',', array_map(fn($c) => "'".addslashes($c)."'", array_keys($cat_summary))); ?>],
            datasets: [{
                data: [<?php echo implode(',', array_values($cat_summary)); ?>],
                backgroundColor: ['#e63946','#3b82f6','#10b981','#f59e0b','#8b5cf6','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16'],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.label + ': ₹' + ctx.raw.toLocaleString();
                        }
                    }
                }
            },
            animation: { animateRotate: true, duration: 1000 }
        }
    });
}
<?php endif; ?>
</script>
</body>
</html>
