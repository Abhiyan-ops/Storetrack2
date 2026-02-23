<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

$uid   = $_SESSION['user_id'];
$uname = $_SESSION['user_name'];

// ============================================================
//  INVENTORY STATS
// ============================================================
$inv = $conn->prepare("
    SELECT
        COUNT(*)                          AS total_items,
        COALESCE(SUM(quantity), 0)        AS total_stock,
        COALESCE(SUM(quantity * cost_price), 0) AS stock_value
    FROM inventory
    WHERE user_id = ?
");
$inv->bind_param("i", $uid);
$inv->execute();
$inv_data = $inv->get_result()->fetch_assoc();
$inv->close();

// ============================================================
//  SALES STATS
// ============================================================
$sal = $conn->prepare("
    SELECT
        COUNT(*)                                              AS total_sales,
        COALESCE(SUM(quantity * selling_price), 0)           AS total_revenue,
        COALESCE(SUM(quantity * (selling_price - cost_price)), 0) AS total_profit,
        COALESCE(SUM(quantity), 0)                           AS total_qty_sold
    FROM sales
    WHERE user_id = ?
");
$sal->bind_param("i", $uid);
$sal->execute();
$sal_data = $sal->get_result()->fetch_assoc();
$sal->close();

// ============================================================
//  LOW STOCK ITEMS (qty <= 5)
// ============================================================
$low = $conn->prepare("
    SELECT name, quantity
    FROM inventory
    WHERE user_id = ? AND quantity <= 5
    ORDER BY quantity ASC
    LIMIT 5
");
$low->bind_param("i", $uid);
$low->execute();
$low_items = $low->get_result()->fetch_all(MYSQLI_ASSOC);
$low->close();

// ============================================================
//  RECENT SALES (last 5)
// ============================================================
$rec = $conn->prepare("
    SELECT item_name, quantity, selling_price, cost_price, customer_name, sale_date
    FROM sales
    WHERE user_id = ?
    ORDER BY sale_date DESC
    LIMIT 5
");
$rec->bind_param("i", $uid);
$rec->execute();
$recent_sales = $rec->get_result()->fetch_all(MYSQLI_ASSOC);
$rec->close();

// Initials
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($uname)))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack — Dashboard</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<!-- TOP NAVBAR -->
<header class="top-nav">
    <div class="top-nav-left">
        <div class="brand">
            <div class="brand-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <rect x="3" y="3" width="7" height="7" rx="1.5" fill="white"/>
                    <rect x="14" y="3" width="7" height="7" rx="1.5" fill="white" opacity="0.8"/>
                    <rect x="3" y="14" width="7" height="7" rx="1.5" fill="white" opacity="0.8"/>
                    <rect x="14" y="14" width="7" height="7" rx="1.5" fill="white" opacity="0.5"/>
                </svg>
            </div>
            <span class="brand-name">StoreTrack2</span>
        </div>
        <div class="breadcrumb">
            <span>My Store</span>
            <span class="breadcrumb-sep">›</span>
            <span class="breadcrumb-active">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Dashboard
            </span>
        </div>
    </div>
    <div class="top-nav-right">
        <div class="search-bar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" placeholder="Search...">
        </div>
        <button class="icon-btn" title="Notifications">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <?php if (!empty($low_items)): ?>
                <span class="notif-dot"></span>
            <?php endif; ?>
        </button>
        <div class="user-pill">
            <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($uname) ?></span>
                <span class="user-role">Owner</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<div class="app-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item active">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Dashboard
            </a>
            <a href="pages/inventory.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
                Inventory
            </a>
            <a href="pages/record_sales.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                Record Sale
            </a>
            <a href="pages/sales_history.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Sales History
            </a>
            <a href="pages/profit_report.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>
                Profit Report
            </a>
            <a href="pages/employees.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M7 21v-2a4 4 0 0 1 3-3.87"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Employees
            </a> 
            <a href="pages/stat.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 3v18h18"/>
                    <path d="M18 17V9"/>
                    <path d="M6 17V9"/>
                    <path d="M3 12h18"/>
                </svg>
                Statistics
            </a>
                   
        </nav>
        <div class="sidebar-footer">
            <div class="help-link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                Help Center
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-sub">Welcome back, <?= htmlspecialchars(explode(' ', $uname)[0]) ?>! Here's what's happening in your store.</p>
            </div>
            <span class="last-updated">🕐 Updated just now</span>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($low_items)): ?>
        <div class="alert-bar" id="alertBar">
            <span>⚠️</span>
            <span>
                <strong>Low Stock Alert:</strong>
                <?= implode(', ', array_map(fn($i) => htmlspecialchars($i['name']) . ' (' . $i['quantity'] . ' left)', $low_items)) ?>
            </span>
            <button class="alert-close" onclick="document.getElementById('alertBar').style.display='none'">✕</button>
        </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Total Stock</span>
                    <span class="stat-more">···</span>
                </div>
                <div class="stat-value"><?= number_format($inv_data['total_stock']) ?></div>
                <div class="stat-sub">
                    pieces across <?= $inv_data['total_items'] ?> item<?= $inv_data['total_items'] != 1 ? 's' : '' ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Total Revenue</span>
                    <span class="stat-more">···</span>
                </div>
                <div class="stat-value">Rs<?= number_format($sal_data['total_revenue'], 0) ?></div>
                <div class="stat-sub">from <?= $sal_data['total_sales'] ?> sale<?= $sal_data['total_sales'] != 1 ? 's' : '' ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Stock Value</span>
                    <span class="stat-more">···</span>
                </div>
                <div class="stat-value">Rs<?= number_format($inv_data['stock_value'], 0) ?></div>
                <div class="stat-sub">at cost price</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Net Profit</span>
                    <span class="stat-more">···</span>
                </div>
                <div class="stat-value <?= $sal_data['total_profit'] >= 0 ? 'green' : '' ?>">
                    Rs<?= number_format($sal_data['total_profit'], 0) ?>
                </div>
                <div class="stat-sub <?= $sal_data['total_profit'] >= 0 ? 'positive' : '' ?>">
                    <?= $sal_data['total_profit'] >= 0 ? '▲ Profit' : '▼ Loss' ?> this month
                </div>
            </div>

        </div>

        <!-- Recent Sales -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Recent Sales</h2>
                    <p class="section-sub">Last <?= count($recent_sales) ?> transaction<?= count($recent_sales) != 1 ? 's' : '' ?></p>
                </div>
                <a href="pages/sales_history.php" class="view-all">View all →</a>
            </div>

            <?php if (empty($recent_sales)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🧾</div>
                    <p>No sales yet. <a href="pages/record_sales.php" style="color:var(--blue);font-weight:600;">Record your first sale →</a></p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Revenue</th>
                            <th>Profit</th>
                            <th>Customer</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale):
                            $revenue = $sale['selling_price'] * $sale['quantity'];
                            $profit  = ($sale['selling_price'] - $sale['cost_price']) * $sale['quantity'];
                            $date    = date('M j, g:i A', strtotime($sale['sale_date']));
                            $customer = $sale['customer_name'] ?: 'Walk-in';
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sale['item_name']) ?></strong></td>
                            <td><span class="qty-badge"><?= $sale['quantity'] ?></span></td>
                            <td>Rs<?= number_format($revenue, 0) ?></td>
                            <td><span class="profit-badge">Rs<?= number_format($profit, 0) ?></span></td>
                            <td><?= htmlspecialchars($customer) ?></td>
                            <td class="muted"><?= $date ?></td>
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