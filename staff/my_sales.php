<?php
require_once 'staff_auth.php';
require_once '../includes/db.php';

$emp_id   = $_SESSION['employee_id'];
$emp_name = $_SESSION['employee_name'];
$owner_id = $_SESSION['owner_id'];

// Period filter
$period = $_GET['period'] ?? 'today';
$period_map = [
    'today' => 'Today',
    'week'  => 'This Week',
    'month' => 'This Month',
    'all'   => 'All Time',
];

$date_filter = match($period) {
    'today' => "AND DATE(sale_date) = CURDATE()",
    'week'  => "AND sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month' => "AND sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    default => "",
};

// Search
$search = trim($_GET['search'] ?? '');
$search_filter = $search !== '' ? "AND (item_name LIKE ? OR customer_name LIKE ?)" : "";

// Summary stats for this employee in this period
$sum_stmt = $conn->prepare("
    SELECT
        COUNT(*)                                    AS total_sales,
        COALESCE(SUM(quantity), 0)                  AS total_items,
        COALESCE(SUM(quantity * selling_price), 0)  AS total_revenue
    FROM sales
    WHERE employee_id = ? $date_filter
");
$sum_stmt->bind_param("i", $emp_id);
$sum_stmt->execute();
$stats = $sum_stmt->get_result()->fetch_assoc();
$sum_stmt->close();

// Sales list
if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT *
        FROM sales
        WHERE employee_id = ? $date_filter $search_filter
        ORDER BY sale_date DESC
    ");
    $like = "%$search%";
    $stmt->bind_param("iss", $emp_id, $like, $like);
} else {
    $stmt = $conn->prepare("
        SELECT *
        FROM sales
        WHERE employee_id = ? $date_filter
        ORDER BY sale_date DESC
    ");
    $stmt->bind_param("i", $emp_id);
}
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$initials = strtoupper(substr($emp_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack2 — My Sales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #F1F5F9; --surface: #FFFFFF; --border: #E2E8F0;
            --blue: #2563EB; --blue-light: #EFF6FF;
            --text: #0F172A; --text-muted: #64748B;
            --green: #16A34A; --green-bg: #F0FDF4;
            --red: #DC2626; --radius: 10px;
            --shadow: 0 1px 4px rgba(0,0,0,0.06);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }

        /* Navbar */
        .navbar { background: white; border-bottom: 1px solid var(--border); padding: 0 24px; height: 56px; display: flex; align-items: center; justify-content: space-between; position: fixed; top: 0; left: 0; right: 0; z-index: 100; box-shadow: var(--shadow); }
        .nav-left { display: flex; align-items: center; gap: 12px; }
        .brand-icon { width: 30px; height: 30px; background: var(--blue); border-radius: 7px; display: flex; align-items: center; justify-content: center; }
        .brand-name { font-size: 16px; font-weight: 800; }
        .staff-tag { background: var(--blue-light); color: var(--blue); font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 100px; border: 1px solid #BFDBFE; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .user-pill { display: flex; align-items: center; gap: 8px; background: var(--bg); border-radius: 100px; padding: 5px 12px 5px 5px; }
        .avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--blue); color: white; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .user-name { font-size: 13px; font-weight: 600; }
        .logout-btn { padding: 7px 14px; background: #FEF2F2; color: var(--red); border: 1px solid #FECACA; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; }

        /* Layout */
        .layout { display: flex; margin-top: 56px; min-height: calc(100vh - 56px); }
        .sidebar { width: 200px; flex-shrink: 0; background: white; border-right: 1px solid var(--border); padding: 20px 12px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--text-muted); text-decoration: none; margin-bottom: 2px; transition: background 0.15s, color 0.15s; }
        .nav-item:hover { background: var(--bg); color: var(--text); }
        .nav-item.active { background: var(--blue-light); color: var(--blue); font-weight: 600; }

        .main { flex: 1; padding: 28px; }

        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 22px; font-weight: 800; }
        .page-sub { font-size: 13px; color: var(--text-muted); margin-top: 3px; }

        /* Period tabs */
        .period-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
        .period-tab { padding: 8px 16px; border-radius: 100px; border: 1.5px solid var(--border); background: white; font-size: 12px; font-weight: 600; color: var(--text-muted); text-decoration: none; transition: all 0.15s; white-space: nowrap; }
        .period-tab:hover { border-color: var(--blue); color: var(--blue); }
        .period-tab.active { background: var(--blue); color: white; border-color: var(--blue); }

        /* Stats cards */
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
        .stat-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; box-shadow: var(--shadow); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .stat-card.blue::before   { background: var(--blue); }
        .stat-card.green::before  { background: var(--green); }
        .stat-card.purple::before { background: #8B5CF6; }
        .stat-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 8px; }
        .stat-value { font-size: 26px; font-weight: 800; letter-spacing: -0.02em; }
        .stat-value.green { color: var(--green); }
        .stat-sub { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

        /* Search bar */
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-wrap { display: flex; align-items: center; gap: 10px; background: white; border: 1.5px solid var(--border); border-radius: var(--radius); padding: 0 14px; flex: 1; transition: border-color 0.2s; }
        .search-wrap:focus-within { border-color: var(--blue); }
        .search-wrap input { border: none; outline: none; font-size: 13px; font-family: 'Inter', sans-serif; background: none; width: 100%; padding: 11px 0; color: var(--text); }
        .search-wrap input::placeholder { color: var(--text-muted); }
        .btn-search { padding: 10px 18px; background: var(--blue); color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; }

        /* Table card */
        .table-card { background: white; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow); overflow: hidden; }
        .table-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: 14px; font-weight: 700; }
        .table-count { font-size: 12px; color: var(--text-muted); }

        table { width: 100%; border-collapse: collapse; }
        th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); padding: 12px 20px; text-align: left; background: #FAFAFA; border-bottom: 1px solid var(--border); }
        td { padding: 14px 20px; font-size: 13px; border-bottom: 1px solid #F8FAFC; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #FAFAFA; }

        /* Badges */
        .qty-badge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; background: var(--blue-light); color: var(--blue); border-radius: 6px; font-size: 12px; font-weight: 700; }

        .revenue-text { font-weight: 700; color: var(--text); }

        .customer-dot { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #E0E7FF; color: #4338CA; font-size: 11px; font-weight: 700; margin-right: 6px; }

        /* Sale number */
        .sale-num { font-size: 11px; color: var(--text-muted); font-weight: 600; }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-icon { font-size: 40px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; color: var(--text-muted); line-height: 1.6; }
        .empty-state a { color: var(--blue); font-weight: 600; text-decoration: none; }

        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
            .period-tabs { }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-left">
        <div class="brand-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="3" width="7" height="7" rx="1.5" fill="white"/>
                <rect x="14" y="3" width="7" height="7" rx="1.5" fill="white" opacity="0.8"/>
                <rect x="3" y="14" width="7" height="7" rx="1.5" fill="white" opacity="0.8"/>
                <rect x="14" y="14" width="7" height="7" rx="1.5" fill="white" opacity="0.5"/>
            </svg>
        </div>
        <span class="brand-name">StoreTrack2</span>
        <span class="staff-tag">Staff</span>
    </div>
    <div class="nav-right">
        <div class="user-pill">
            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            <span class="user-name"><?= htmlspecialchars($emp_name) ?></span>
        </div>
        <a href="logout.php" class="logout-btn">Sign Out</a>
    </div>
</nav>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <a href="dashboard.php" class="nav-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            Dashboard
        </a>
        <a href="inventory.php" class="nav-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            </svg>
            Inventory
        </a>
        <a href="record_sale.php" class="nav-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            Record Sale
        </a>
        <a href="my_sales.php" class="nav-item active">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            My Sales
        </a>
    </aside>

    <!-- MAIN -->
    <main class="main">

        <div class="page-header">
            <div>
                <h1 class="page-title">My Sales</h1>
                <p class="page-sub">Your personal sales history — <?= $period_map[$period] ?></p>
            </div>
            <div class="period-tabs">
                <a href="?period=today" class="period-tab <?= $period === 'today' ? 'active' : '' ?>">Today</a>
                <a href="?period=week"  class="period-tab <?= $period === 'week'  ? 'active' : '' ?>">This Week</a>
                <a href="?period=month" class="period-tab <?= $period === 'month' ? 'active' : '' ?>">This Month</a>
                <a href="?period=all"   class="period-tab <?= $period === 'all'   ? 'active' : '' ?>">All Time</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card blue">
                <div class="stat-label">Sales Recorded</div>
                <div class="stat-value"><?= $stats['total_sales'] ?></div>
                <div class="stat-sub"><?= $period_map[$period] ?></div>
            </div>
            <div class="stat-card green">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value green">Rs.<?= number_format($stats['total_revenue'], 0) ?></div>
                <div class="stat-sub">from your sales</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-label">Items Sold</div>
                <div class="stat-value"><?= $stats['total_items'] ?></div>
                <div class="stat-sub">total units</div>
            </div>
        </div>

        <!-- Search -->
        <form method="GET" action="my_sales.php">
            <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
            <div class="search-bar">
                <div class="search-wrap">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" name="search" placeholder="Search by item or customer..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn-search">Search</button>
                <?php if ($search): ?>
                    <a href="?period=<?= $period ?>" style="padding:10px 14px; font-size:13px; color:var(--text-muted); text-decoration:none; font-weight:500; display:flex; align-items:center;">✕ Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Sales table -->
        <div class="table-card">
            <div class="table-header">
                <span class="table-title">
                    <?= $period_map[$period] ?> Sales
                    <?= $search ? '— search: "' . htmlspecialchars($search) . '"' : '' ?>
                </span>
                <span class="table-count"><?= count($sales) ?> transaction<?= count($sales) !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (empty($sales)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🧾</div>
                    <?php if ($search): ?>
                        <p>No sales found for "<?= htmlspecialchars($search) ?>"</p>
                    <?php else: ?>
                        <p>No sales recorded <?= $period === 'today' ? 'today' : 'for this period' ?> yet.<br>
                        <a href="record_sale.php">Record your first sale →</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Revenue</th>
                            <th>Customer</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $i => $sale):
                            $revenue  = $sale['selling_price'] * $sale['quantity'];
                            $customer = $sale['customer_name'] ?: 'Walk-in';
                            $avatar   = strtoupper($customer[0]);
                            $datetime = date('M j, g:i A', strtotime($sale['sale_date']));
                            $date     = date('M j', strtotime($sale['sale_date']));
                            $time     = date('g:i A', strtotime($sale['sale_date']));
                        ?>
                        <tr>
                            <td><span class="sale-num">#<?= $sale['id'] ?></span></td>
                            <td><strong><?= htmlspecialchars($sale['item_name']) ?></strong></td>
                            <td><span class="qty-badge"><?= $sale['quantity'] ?></span></td>
                            <td><span class="revenue-text">Rs.<?= number_format($revenue, 0) ?></span></td>
                            <td>
                                <span class="customer-dot"><?= $avatar ?></span>
                                <?= htmlspecialchars($customer) ?>
                            </td>
                            <td>
                                <div style="font-size:13px; font-weight:500;"><?= $date ?></div>
                                <div style="font-size:11px; color:var(--text-muted);"><?= $time ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </main>
</div>

</body>
</html>