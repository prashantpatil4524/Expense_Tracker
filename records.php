<?php
// records.php — All Records with Filters + Heatmap
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db       = getDB();
$user_id  = $_SESSION['user_id'];
$fullname = $_SESSION['full_name'];
$role     = $_SESSION['role'];

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $rec_id = $_POST['rec_id'] ?? '';
    $stmt = $db->prepare("DELETE FROM records WHERE id = ? AND user_id = ?");
    $stmt->bind_param("si", $rec_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Filters
$filter_type = $_GET['type'] ?? 'all';
$filter_cat  = $_GET['cat'] ?? 'all';
$filter_month = $_GET['month'] ?? '';
$search = trim($_GET['q'] ?? '');

// Build query
$where = ["user_id = ?"];
$params = [$user_id];
$types = "i";

if ($filter_type !== 'all') {
    $where[] = "type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_cat !== 'all') {
    $where[] = "category = ?";
    $params[] = $filter_cat;
    $types .= "s";
}

if ($filter_month !== '') {
    $where[] = "DATE_FORMAT(date,'%Y-%m') = ?";
    $params[] = $filter_month;
    $types .= "s";
}

if ($search !== '') {
    $where[] = "(item LIKE ? OR note LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

$sql = "SELECT * FROM records WHERE " . implode(" AND ", $where) . " ORDER BY date DESC, created_at DESC";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Totals for filtered
$total_earning = 0; $total_expense = 0;
foreach ($records as $r) {
    if ($r['type'] === 'earning') $total_earning += $r['amount'];
    else $total_expense += $r['amount'];
}

// Get all categories for filter dropdown
$cats_stmt = $db->prepare("SELECT DISTINCT category FROM records WHERE user_id = ? ORDER BY category");
$cats_stmt->bind_param("i", $user_id);
$cats_stmt->execute();
$all_cats = $cats_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cats_stmt->close();

// Get all months for filter
$months_stmt = $db->prepare("SELECT DISTINCT DATE_FORMAT(date,'%Y-%m') as mon FROM records WHERE user_id = ? ORDER BY mon DESC");
$months_stmt->bind_param("i", $user_id);
$months_stmt->execute();
$all_months = $months_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$months_stmt->close();

// Heatmap data: daily spending for last 90 days
$heatmap_stmt = $db->prepare("SELECT date, SUM(amount) as total FROM records WHERE user_id = ? AND type='expense' AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY date");
$heatmap_stmt->bind_param("i", $user_id);
$heatmap_stmt->execute();
$heatmap_rows = $heatmap_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$heatmap_stmt->close();
$heatmap_data = [];
$heatmap_max = 1;
foreach ($heatmap_rows as $h) {
    $heatmap_data[$h['date']] = floatval($h['total']);
    if ($h['total'] > $heatmap_max) $heatmap_max = floatval($h['total']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FinTrack — All Records</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:root { --red:#e63946; --red2:#c1121f; --green:#10b981; --dark:#f5f7fa; --dark2:#ffffff; --dark3:#f0f1f3; --dark4:#e8e9ec; --light:#1a1a2e; --muted:#6b7280; --border:#e5e7eb; --gold:#f59e0b; --blue:#3b82f6; }
* { margin:0; padding:0; box-sizing:border-box; }
@keyframes fadeInUp { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }
body { background:var(--dark); font-family:'Sora',sans-serif; font-size:14px; color:var(--light); }
.navbar { background:rgba(255,255,255,0.85); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:0 32px; height:58px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; box-shadow:0 1px 8px rgba(0,0,0,0.05); }
.nav-logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
.nav-logo-icon { width:36px; height:36px; background:var(--red); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800; color:white; }
.nav-logo-text { font-size:17px; font-weight:800; color:var(--light); letter-spacing:-0.5px; }
.nav-logo-text span { color:var(--red); }
.nav-links { display:flex; align-items:center; gap:6px; }
.nav-link { padding:7px 14px; border-radius:7px; color:var(--muted); text-decoration:none; font-size:13px; font-weight:500; transition:all 0.2s; }
.nav-link:hover, .nav-link.active { color:var(--light); background:var(--dark3); }
.nav-right { display:flex; align-items:center; gap:12px; }
.nav-user { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--muted); }
.nav-avatar { width:32px; height:32px; background:var(--red); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:white; }
.btn-logout { padding:7px 14px; background:rgba(230,57,70,0.12); border:1px solid rgba(230,57,70,0.3); color:var(--red); border-radius:7px; text-decoration:none; font-size:12px; font-weight:600; }
.page-wrap { max-width:1100px; margin:0 auto; padding:28px 24px; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-title { font-size:22px; font-weight:700; letter-spacing:-0.5px; }
.filter-bar { background:var(--dark2); border:1px solid var(--border); border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; box-shadow:0 2px 12px rgba(0,0,0,0.04); animation:fadeInUp 0.4s ease both; }
.filter-group { display:flex; flex-direction:column; gap:6px; }
.filter-label { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); }
.filter-input, .filter-select { padding:8px 12px; background:var(--dark3); border:1.5px solid var(--border); border-radius:7px; font-family:'Sora',sans-serif; font-size:12px; color:var(--light); outline:none; }
.filter-select option { background:var(--dark2); }
.filter-input { min-width:180px; }
.btn-filter { padding:8px 18px; background:var(--red); color:white; border:none; border-radius:7px; font-family:'Sora',sans-serif; font-size:13px; font-weight:600; cursor:pointer; align-self:flex-end; transition:background 0.2s; }
.btn-filter:hover { background:var(--red2); }
.btn-clear { padding:8px 14px; background:transparent; color:var(--muted); border:1px solid var(--border); border-radius:7px; text-decoration:none; font-size:12px; align-self:flex-end; }
.summary-strip { display:flex; gap:12px; margin-bottom:20px; }
.strip-card { background:var(--dark2); border:1px solid var(--border); border-radius:10px; padding:14px 18px; flex:1; box-shadow:0 2px 12px rgba(0,0,0,0.04); animation:fadeInUp 0.4s ease both; transition:transform 0.3s,box-shadow 0.3s; }
.strip-card:nth-child(2){animation-delay:.06s}.strip-card:nth-child(3){animation-delay:.12s}.strip-card:nth-child(4){animation-delay:.18s}
.strip-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,0.07); }
.strip-label { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:1.5px; color:var(--muted); margin-bottom:5px; }
.strip-val { font-size:20px; font-weight:800; font-family:'JetBrains Mono',monospace; }
.strip-val.green{color:var(--green)}.strip-val.red{color:var(--red)}.strip-val.blue{color:var(--blue)}
.table-box { background:var(--dark2); border:1px solid var(--border); border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.04); animation:fadeInUp 0.5s ease 0.15s both; }
.table-box-header { padding:16px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.table-box-title { font-size:14px; font-weight:700; }
.records-table { width:100%; border-collapse:collapse; }
.records-table th { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--muted); padding:12px 18px; text-align:left; background:var(--dark3); border-bottom:1px solid var(--border); }
.records-table td { padding:13px 18px; border-bottom:1px solid var(--border); font-size:13px; vertical-align:middle; }
.records-table tr:last-child td { border-bottom:none; }
.records-table tr:hover td { background:rgba(0,0,0,0.02); }
.type-badge { font-size:10px; font-weight:700; padding:3px 8px; border-radius:4px; text-transform:uppercase; }
.type-expense { background:rgba(230,57,70,0.12); color:var(--red); }
.type-earning { background:rgba(16,185,129,0.12); color:var(--green); }
.cat-pill { font-size:11px; padding:3px 9px; background:var(--dark3); border:1px solid var(--border); border-radius:20px; color:var(--muted); }
.amount-cell { font-family:'JetBrains Mono',monospace; font-weight:600; font-size:13px; }
.amount-expense{color:var(--red)}.amount-earning{color:var(--green)}
.item-name { font-weight:600; color:var(--light); }
.item-note { font-size:11px; color:#999; margin-top:2px; }
.date-cell { color:var(--muted); font-size:12px; white-space:nowrap; }
.btn-del { background:rgba(230,57,70,0.08); border:1px solid rgba(230,57,70,0.2); color:var(--red); padding:4px 10px; border-radius:5px; font-size:11px; font-weight:600; cursor:pointer; font-family:'Sora',sans-serif; transition:all 0.2s; }
.btn-del:hover { background:rgba(230,57,70,0.18); }
.empty-state { text-align:center; padding:60px 20px; color:#999; }
.empty-state .icon { font-size:42px; margin-bottom:12px; }
.empty-state p { font-size:14px; }
.badge-admin { background:rgba(245,158,11,0.12); color:var(--gold); font-size:10px; padding:2px 7px; border-radius:20px; font-weight:600; }
.badge-user  { background:rgba(59,130,246,0.12); color:var(--blue); font-size:10px; padding:2px 7px; border-radius:20px; font-weight:600; }
/* Heatmap */
.heatmap-box { background:var(--dark2); border:1px solid var(--border); border-radius:12px; padding:22px 24px; margin-bottom:20px; box-shadow:0 2px 12px rgba(0,0,0,0.04); animation:fadeInUp 0.4s ease 0.08s both; }
.heatmap-title { font-size:14px; font-weight:700; margin-bottom:4px; display:flex; align-items:center; gap:8px; }
.heatmap-sub { font-size:12px; color:var(--muted); margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid var(--border); }
.heatmap-scroll { overflow-x:auto; }
.heatmap-weeks { display:flex; gap:3px; }
.heatmap-week { display:flex; flex-direction:column; gap:3px; }
.heatmap-cell { width:14px; height:14px; border-radius:3px; transition:transform 0.15s; cursor:pointer; }
.heatmap-cell:hover { transform:scale(1.5); z-index:2; }
.heatmap-legend { display:flex; align-items:center; gap:6px; margin-top:14px; font-size:11px; color:var(--muted); }
.heatmap-legend-cell { width:12px; height:12px; border-radius:2px; display:inline-block; }
.heatmap-months { display:flex; gap:3px; margin-bottom:6px; }
.heatmap-month-label { font-size:10px; color:var(--muted); min-width:14px; text-align:center; }
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
        <a href="records.php" class="nav-link active">All Records</a>
        <?php if ($role === 'admin'): ?>
        <a href="admin.php" class="nav-link">Admin Panel</a>
        <?php endif; ?>
    </div>
    <div class="nav-right">
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($fullname, 0, 1)) ?></div>
            <span><?= htmlspecialchars($fullname) ?></span>
            <span class="<?= $role === 'admin' ? 'badge-admin' : 'badge-user' ?>"><?= ucfirst($role) ?></span>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="page-wrap">
    <div class="page-header">
        <div class="page-title">All Records</div>
        <a href="index.php" style="color:var(--muted); font-size:13px; text-decoration:none;">← Back to Dashboard</a>
    </div>

    <!-- Spending Heatmap -->
    <div class="heatmap-box">
        <div class="heatmap-title">🔥 Daily Spending Heatmap</div>
        <div class="heatmap-sub">Last 90 days of expense activity</div>
        <div class="heatmap-scroll">
            <div class="heatmap-weeks">
            <?php
            $today = new DateTime();
            $start = (clone $today)->modify('-89 days');
            // Align start to Sunday
            $dow = intval($start->format('w'));
            $start->modify("-{$dow} days");
            $current = clone $start;
            while ($current <= $today) {
                echo '<div class="heatmap-week">';
                for ($d = 0; $d < 7; $d++) {
                    $dateStr = $current->format('Y-m-d');
                    $amount = $heatmap_data[$dateStr] ?? 0;
                    if ($current > $today) {
                        echo '<div class="heatmap-cell" style="background:transparent;"></div>';
                    } else {
                        $intensity = $heatmap_max > 0 ? ($amount / $heatmap_max) : 0;
                        if ($amount == 0) $color = '#ebedf0';
                        elseif ($intensity < 0.25) $color = '#fecaca';
                        elseif ($intensity < 0.5) $color = '#f87171';
                        elseif ($intensity < 0.75) $color = '#dc2626';
                        else $color = '#991b1b';
                        $title = $current->format('d M Y') . ($amount > 0 ? ': ₹' . number_format($amount, 0) : ': No spending');
                        echo "<div class=\"heatmap-cell\" style=\"background:{$color};\" title=\"{$title}\"></div>";
                    }
                    $current->modify('+1 day');
                }
                echo '</div>';
            }
            ?>
            </div>
        </div>
        <div class="heatmap-legend">
            Less
            <span class="heatmap-legend-cell" style="background:#ebedf0;"></span>
            <span class="heatmap-legend-cell" style="background:#fecaca;"></span>
            <span class="heatmap-legend-cell" style="background:#f87171;"></span>
            <span class="heatmap-legend-cell" style="background:#dc2626;"></span>
            <span class="heatmap-legend-cell" style="background:#991b1b;"></span>
            More
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label class="filter-label">Search</label>
            <input type="text" name="q" class="filter-input" placeholder="Search item or note..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-group">
            <label class="filter-label">Type</label>
            <select name="type" class="filter-select">
                <option value="all" <?= $filter_type==='all'?'selected':'' ?>>All Types</option>
                <option value="expense" <?= $filter_type==='expense'?'selected':'' ?>>Expense</option>
                <option value="earning" <?= $filter_type==='earning'?'selected':'' ?>>Income</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Category</label>
            <select name="cat" class="filter-select">
                <option value="all">All Categories</option>
                <?php foreach ($all_cats as $c): ?>
                <option value="<?= htmlspecialchars($c['category']) ?>" <?= $filter_cat===$c['category']?'selected':'' ?>><?= htmlspecialchars($c['category']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Month</label>
            <select name="month" class="filter-select">
                <option value="">All Months</option>
                <?php foreach ($all_months as $mo): ?>
                <option value="<?= $mo['mon'] ?>" <?= $filter_month===$mo['mon']?'selected':'' ?>><?= date('M Y', strtotime($mo['mon'].'-01')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-filter">Filter</button>
        <a href="records.php" class="btn-clear">Clear</a>
    </form>

    <!-- Summary Strip -->
    <div class="summary-strip">
        <div class="strip-card">
            <div class="strip-label">Total Income</div>
            <div class="strip-val green">₹<?= number_format($total_earning, 0) ?></div>
        </div>
        <div class="strip-card">
            <div class="strip-label">Total Expense</div>
            <div class="strip-val red">₹<?= number_format($total_expense, 0) ?></div>
        </div>
        <div class="strip-card">
            <div class="strip-label">Net</div>
            <div class="strip-val <?= ($total_earning-$total_expense)>=0?'green':'red' ?>"><?= ($total_earning-$total_expense)>=0?'+':'' ?>₹<?= number_format(abs($total_earning-$total_expense), 0) ?></div>
        </div>
        <div class="strip-card">
            <div class="strip-label">Records Found</div>
            <div class="strip-val blue"><?= count($records) ?></div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-box">
        <div class="table-box-header">
            <span class="table-box-title">Transactions</span>
            <span style="font-size:12px; color:var(--muted);"><?= count($records) ?> records</span>
        </div>

        <?php if (empty($records)): ?>
        <div class="empty-state">
            <div class="icon">🔍</div>
            <p>No records match your filters.</p>
        </div>
        <?php else: ?>
        <table class="records-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th style="text-align:right;">Amount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td>
                        <div class="item-name"><?= htmlspecialchars($r['item']) ?></div>
                        <?php if ($r['note']): ?><div class="item-note"><?= htmlspecialchars($r['note']) ?></div><?php endif; ?>
                    </td>
                    <td><span class="type-badge <?= $r['type'] === 'expense' ? 'type-expense' : 'type-earning' ?>"><?= $r['type'] === 'earning' ? 'Income' : 'Expense' ?></span></td>
                    <td><span class="cat-pill"><?= htmlspecialchars($r['category']) ?></span></td>
                    <td class="date-cell"><?= date('d M Y', strtotime($r['date'])) ?></td>
                    <td class="amount-cell <?= $r['type'] === 'expense' ? 'amount-expense' : 'amount-earning' ?>" style="text-align:right;">
                        <?= $r['type'] === 'earning' ? '+' : '-' ?>₹<?= number_format($r['amount'], 2) ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="rec_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn-del" onclick="return confirm('Delete this record?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
