<?php
require_once 'staff_auth.php';
require_once '../includes/db.php';

$emp_id   = $_SESSION['employee_id'];
$emp_name = $_SESSION['employee_name'];
$owner_id = $_SESSION['owner_id'];

// Today's stats for this employee
$today = $conn->prepare("
    SELECT
        COUNT(*)                                                  AS sales_count,
        COALESCE(SUM(quantity * selling_price), 0)               AS revenue,
        COALESCE(SUM(quantity), 0)                               AS items_sold
    FROM sales
    WHERE employee_id = ? AND DATE(sale_date) = CURDATE()
");
$today->bind_param("i", $emp_id);
$today->execute();
$today_stats = $today->get_result()->fetch_assoc();
$today->close();

// This week's stats
$week = $conn->prepare("
    SELECT
        COUNT(*)                                                  AS sales_count,
        COALESCE(SUM(quantity * selling_price), 0)               AS revenue,
        COALESCE(SUM(quantity), 0)                               AS items_sold
    FROM sales
    WHERE employee_id = ? AND sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$week->bind_param("i", $emp_id);
$week->execute();
$week_stats = $week->get_result()->fetch_assoc();
$week->close();

// Recent sales by this employee
$recent = $conn->prepare("
    SELECT item_name, quantity, selling_price, customer_name, sale_date
    FROM sales
    WHERE employee_id = ?
    ORDER BY sale_date DESC
    LIMIT 5
");
$recent->bind_param("i", $emp_id);
$recent->execute();
$recent_sales = $recent->get_result()->fetch_all(MYSQLI_ASSOC);
$recent->close();

// Low stock items (so employee knows what's running out)
$low = $conn->prepare("
    SELECT name, quantity FROM inventory
    WHERE user_id = ? AND quantity <= 5 AND quantity > 0
    ORDER BY quantity ASC
");
$low->bind_param("i", $owner_id);
$low->execute();
$low_items = $low->get_result()->fetch_all(MYSQLI_ASSOC);
$low->close();

$initials = strtoupper(substr($emp_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack2 — Staff Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #F1F5F9;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --blue: #2563EB;
            --text: #0F172A;
            --text-muted: #64748B;
            --green: #16A34A;
            --radius: 10px;
            --shadow: 0 1px 4px rgba(0,0,0,0.06);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }

        /* Navbar */
        .navbar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            height: 56px;
            display: flex; align-items: center; justify-content: space-between;
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            box-shadow: var(--shadow);
        }

        .nav-left { display: flex; align-items: center; gap: 12px; }

        .brand-icon {
            width: 30px; height: 30px; background: var(--blue);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
        }

        .brand-name { font-size: 16px; font-weight: 800; color: var(--text); }

        .staff-tag {
            background: #EFF6FF; color: var(--blue);
            font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 100px;
            border: 1px solid #BFDBFE;
        }

        .nav-right { display: flex; align-items: center; gap: 12px; }

        .user-pill {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg); border-radius: 100px;
            padding: 5px 12px 5px 5px;
        }

        .avatar {
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--blue); color: white;
            font-size: 12px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }

        .user-name { font-size: 13px; font-weight: 600; }

        .logout-btn {
            padding: 7px 14px;
            background: #FEF2F2; color: #DC2626;
            border: 1px solid #FECACA;
            border-radius: 8px;
            font-size: 12px; font-weight: 600;
            text-decoration: none;
            transition: background 0.15s;
        }

        .logout-btn:hover { background: #FEE2E2; }

        /* Sidebar */
        .layout { display: flex; margin-top: 56px; min-height: calc(100vh - 56px); }

        .sidebar {
            width: 200px; flex-shrink: 0;
            background: white;
            border-right: 1px solid var(--border);
            padding: 20px 12px;
        }

        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 8px;
            font-size: 13px; font-weight: 500;
            color: var(--text-muted); text-decoration: none;
            margin-bottom: 2px;
            transition: background 0.15s, color 0.15s;
        }

        .nav-item:hover { background: var(--bg); color: var(--text); }
        .nav-item.active { background: #EFF6FF; color: var(--blue); font-weight: 600; }

        /* Main */
        .main { flex: 1; padding: 28px 28px; }

        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 22px; font-weight: 800; }
        .page-sub { font-size: 13px; color: var(--text-muted); margin-top: 3px; }

        /* Low stock alert */
        .alert-bar {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: var(--radius);
            padding: 12px 16px;
            font-size: 13px; color: #92400E;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px;
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            position: relative; overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px;
        }

        .stat-card.blue::before  { background: var(--blue); }
        .stat-card.green::before { background: var(--green); }
        .stat-card.orange::before{ background: #F59E0B; }

        .stat-period {
            font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--text-muted); margin-bottom: 6px;
        }

        .stat-label { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
        .stat-value { font-size: 26px; font-weight: 800; letter-spacing: -0.02em; }
        .stat-value.green { color: var(--green); }
        .stat-sub { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

        /* Section card */
        .section-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 18px;
        }

        .section-title { font-size: 15px; font-weight: 700; }
        .section-sub { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        .view-all {
            font-size: 12px; font-weight: 600;
            color: var(--blue); text-decoration: none;
        }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th {
            font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--text-muted);
            padding: 8px 12px; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        td { padding: 12px 12px; font-size: 13px; border-bottom: 1px solid #F8FAFC; }
        tr:last-child td { border-bottom: none; }

        .qty-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 24px; height: 24px;
            background: #EFF6FF; color: var(--blue);
            border-radius: 6px; font-size: 12px; font-weight: 700;
        }

        .empty-state {
            text-align: center; padding: 40px 20px;
            color: var(--text-muted); font-size: 13px;
        }

        .empty-icon { font-size: 36px; margin-bottom: 10px; }

        /* Quick action buttons */
        .quick-actions {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 14px; margin-bottom: 24px;
        }

        .quick-btn {
            display: flex; align-items: center; gap: 14px;
            background: white; border: 1.5px solid var(--border);
            border-radius: 12px; padding: 18px 20px;
            text-decoration: none; color: var(--text);
            transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
            box-shadow: var(--shadow);
        }

        .quick-btn:hover {
            border-color: var(--blue);
            box-shadow: 0 4px 16px rgba(37,99,235,0.12);
            transform: translateY(-2px);
        }

        .quick-icon {
            width: 44px; height: 44px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }

        .quick-icon.blue   { background: #EFF6FF; }
        .quick-icon.green  { background: #F0FDF4; }

        .quick-btn-title { font-size: 14px; font-weight: 700; }
        .quick-btn-sub   { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
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
        <a href="dashboard.php" class="nav-item active">
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
        <a href="my_sales.php" class="nav-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            My Sales
        </a>
    </aside>

    <!-- MAIN -->
    <main class="main">

        <div class="page-header">
            <h1 class="page-title">Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?= htmlspecialchars(explode(' ', $emp_name)[0]) ?>! 👋</h1>
            <p class="page-sub"><?= date('l, F j, Y') ?></p>
        </div>

        <!-- Low stock warning -->
        <?php if (!empty($low_items)): ?>
        <div class="alert-bar">
            ⚠️ <strong>Low Stock:</strong>
            <?= implode(', ', array_map(fn($i) => htmlspecialchars($i['name']) . ' (' . $i['quantity'] . ' left)', $low_items)) ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-period">Today</div>
                <div class="stat-label">Sales Recorded</div>
                <div class="stat-value"><?= $today_stats['sales_count'] ?></div>
                <div class="stat-sub"><?= $today_stats['items_sold'] ?> items sold</div>
            </div>
            <div class="stat-card green">
                <div class="stat-period">Today</div>
                <div class="stat-label">Revenue Generated</div>
                <div class="stat-value green">Rs.<?= number_format($today_stats['revenue'], 0) ?></div>
                <div class="stat-sub">from your sales</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-period">This Week</div>
                <div class="stat-label">Total Sales</div>
                <div class="stat-value"><?= $week_stats['sales_count'] ?></div>
                <div class="stat-sub">Rs.<?= number_format($week_stats['revenue'], 0) ?> revenue</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="record_sale.php" class="quick-btn">
                <div class="quick-icon blue">🧾</div>
                <div>
                    <div class="quick-btn-title">Record a Sale</div>
                    <div class="quick-btn-sub">Add a new transaction</div>
                </div>
            </a>
            <a href="inventory.php" class="quick-btn">
                <div class="quick-icon green">📦</div>
                <div>
                    <div class="quick-btn-title">View Inventory</div>
                    <div class="quick-btn-sub">Check stock levels</div>
                </div>
            </a>
        </div>

        <!-- Recent Sales -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <div class="section-title">My Recent Sales</div>
                    <div class="section-sub">Your last <?= count($recent_sales) ?> transactions</div>
                </div>
                <a href="my_sales.php" class="view-all">View all →</a>
            </div>

            <?php if (empty($recent_sales)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🧾</div>
                    <p>No sales recorded yet today.<br>
                    <a href="record_sale.php" style="color:var(--blue); font-weight:600;">Record your first sale →</a></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Revenue</th>
                            <th>Customer</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale):
                            $revenue  = $sale['selling_price'] * $sale['quantity'];
                            $customer = $sale['customer_name'] ?: 'Walk-in';
                            $time     = date('g:i A', strtotime($sale['sale_date']));
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sale['item_name']) ?></strong></td>
                            <td><span class="qty-badge"><?= $sale['quantity'] ?></span></td>
                            <td>Rs.<?= number_format($revenue, 0) ?></td>
                            <td><?= htmlspecialchars($customer) ?></td>
                            <td style="color:var(--text-muted)"><?= $time ?></td>
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