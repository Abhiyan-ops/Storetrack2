<?php
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

$uid   = $_SESSION['user_id'];
$uname = $_SESSION['user_name'];

// ============================================================
//  PERIOD FILTER
// ============================================================
$period = $_GET['period'] ?? '30';
$period_map = [
    '7'   => 'Last 7 Days',
    '30'  => 'Last 30 Days',
    '90'  => 'Last 90 Days',
    'all' => 'All Time',
];
$period_label = $period_map[$period] ?? 'Last 30 Days';

$date_filter = match($period) {
    '7'  => "AND sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30' => "AND sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    '90' => "AND sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
    default => "",
};

// ============================================================
//  OVERALL SUMMARY
// ============================================================
$sum_stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(quantity * selling_price), 0)                AS revenue,
        COALESCE(SUM(quantity * cost_price), 0)                   AS cost,
        COALESCE(SUM(quantity * (selling_price - cost_price)), 0) AS profit,
        COALESCE(SUM(quantity), 0)                                AS units_sold,
        COUNT(*)                                                  AS total_sales
    FROM sales
    WHERE user_id = ? $date_filter
");
$sum_stmt->bind_param("i", $uid);
$sum_stmt->execute();
$summary = $sum_stmt->get_result()->fetch_assoc();
$sum_stmt->close();

$margin_pct = $summary['revenue'] > 0
    ? round(($summary['profit'] / $summary['revenue']) * 100, 1)
    : 0;

// ============================================================
//  DAILY REVENUE & PROFIT (for chart) — last N days
// ============================================================
$chart_days = ($period === 'all') ? 30 : (int)$period;
$chart_stmt = $conn->prepare("
    SELECT
        DATE(sale_date)                                           AS day,
        SUM(quantity * selling_price)                            AS revenue,
        SUM(quantity * (selling_price - cost_price))             AS profit
    FROM sales
    WHERE user_id = ?
      AND sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY DATE(sale_date)
    ORDER BY day ASC
");
$chart_stmt->bind_param("ii", $uid, $chart_days);
$chart_stmt->execute();
$chart_raw = $chart_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$chart_stmt->close();

// Build date-keyed map
$chart_map = [];
foreach ($chart_raw as $row) {
    $chart_map[$row['day']] = $row;
}

// Fill every day (even with 0)
$chart_labels  = [];
$chart_revenue = [];
$chart_profit  = [];
for ($i = $chart_days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[]  = date('M j', strtotime($d));
    $chart_revenue[] = isset($chart_map[$d]) ? round($chart_map[$d]['revenue'], 0) : 0;
    $chart_profit[]  = isset($chart_map[$d]) ? round($chart_map[$d]['profit'], 0)  : 0;
}

// ============================================================
//  TOP SELLING ITEMS
// ============================================================
$top_stmt = $conn->prepare("
    SELECT
        item_name,
        SUM(quantity)                                            AS units,
        SUM(quantity * selling_price)                            AS revenue,
        SUM(quantity * (selling_price - cost_price))             AS profit
    FROM sales
    WHERE user_id = ? $date_filter
    GROUP BY item_name
    ORDER BY revenue DESC
    LIMIT 6
");
$top_stmt->bind_param("i", $uid);
$top_stmt->execute();
$top_items = $top_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$top_stmt->close();

// ============================================================
//  CATEGORY BREAKDOWN (for donut chart)
// ============================================================
$cat_stmt = $conn->prepare("
    SELECT
        i.category,
        SUM(s.quantity * s.selling_price) AS revenue
    FROM sales s
    JOIN inventory i ON s.item_id = i.id
    WHERE s.user_id = ? $date_filter
    GROUP BY i.category
    ORDER BY revenue DESC
");
$cat_stmt->bind_param("i", $uid);
$cat_stmt->execute();
$categories = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cat_stmt->close();

// ============================================================
//  RECENT TRANSACTIONS
// ============================================================
$tx_stmt = $conn->prepare("
    SELECT item_name, quantity, selling_price, cost_price, customer_name, sale_date
    FROM sales
    WHERE user_id = ? $date_filter
    ORDER BY sale_date DESC
    LIMIT 8
");
$tx_stmt->bind_param("i", $uid);
$tx_stmt->execute();
$transactions = $tx_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$tx_stmt->close();

// Initials
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($uname)))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack — Profit Report</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        /* ── Report layout ── */
        .report-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
        }

        .report-left  { display: flex; flex-direction: column; gap: 20px; }
        .report-right { display: flex; flex-direction: column; gap: 20px; }

        /* ── Period tabs ── */
        .period-tabs { display: flex; gap: 6px; }

        .period-tab {
            padding: 8px 16px;
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            background: var(--surface);
            font-size: 12px; font-weight: 600;
            color: var(--text-sub);
            cursor: pointer; text-decoration: none;
            transition: all 0.15s; white-space: nowrap;
        }

        .period-tab:hover { background: var(--bg); }
        .period-tab.active { background: var(--blue); color: white; border-color: var(--blue); }

        /* ── Summary cards ── */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }

        .summary-card.blue::before  { background: var(--blue); }
        .summary-card.green::before { background: #22C55E; }
        .summary-card.orange::before{ background: #F59E0B; }
        .summary-card.purple::before{ background: #8B5CF6; }

        .sc-label {
            font-size: 11px; font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.07em;
            margin-bottom: 10px;
        }

        .sc-value {
            font-size: 24px; font-weight: 800;
            color: var(--text); letter-spacing: -0.02em;
            line-height: 1;
        }

        .sc-value.green  { color: #16A34A; }
        .sc-value.orange { color: #D97706; }

        .sc-change {
            font-size: 11px; font-weight: 600;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* ── Chart card ── */
        .chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--shadow);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 15px; font-weight: 700; color: var(--text);
        }

        .chart-subtitle {
            font-size: 12px; color: var(--text-muted); margin-top: 2px;
        }

        .chart-legend {
            display: flex; gap: 14px;
        }

        .legend-item {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 600; color: var(--text-sub);
        }

        .legend-dot {
            width: 8px; height: 8px; border-radius: 50%;
        }

        .chart-wrap { position: relative; height: 220px; }

        /* ── Top items table ── */
        .rank-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px;
            border-radius: 50%;
            font-size: 11px; font-weight: 700;
            background: var(--bg); color: var(--text-muted);
        }

        .rank-badge.gold   { background: #FEF3C7; color: #D97706; }
        .rank-badge.silver { background: #F1F5F9; color: #64748B; }
        .rank-badge.bronze { background: #FEF0E7; color: #C2540A; }

        .item-bar-wrap {
            width: 100%; background: var(--bg);
            border-radius: 4px; height: 5px; margin-top: 4px;
        }

        .item-bar {
            height: 5px; border-radius: 4px;
            background: var(--blue);
        }

        /* ── Donut chart ── */
        .donut-wrap {
            position: relative; height: 180px;
            display: flex; align-items: center; justify-content: center;
        }

        .donut-center {
            position: absolute;
            text-align: center;
            pointer-events: none;
        }

        .donut-center-value {
            font-size: 18px; font-weight: 800; color: var(--text);
        }

        .donut-center-label {
            font-size: 10px; font-weight: 600;
            color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;
        }

        .donut-legend {
            display: flex; flex-direction: column; gap: 8px;
            margin-top: 12px;
        }

        .donut-legend-item {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 12px;
        }

        .donut-legend-left { display: flex; align-items: center; gap: 8px; color: var(--text-sub); }

        .donut-dot {
            width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
        }

        .donut-pct { font-weight: 700; color: var(--text); }

        /* ── Transactions ── */
        .tx-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 11px 0; border-bottom: 1px solid var(--border);
        }

        .tx-item:last-child { border-bottom: none; }

        .tx-left { display: flex; align-items: center; gap: 10px; }

        .tx-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--blue-light); color: var(--blue);
            font-size: 11px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .tx-name { font-size: 13px; font-weight: 600; color: var(--text); }
        .tx-meta { font-size: 11px; color: var(--text-muted); margin-top: 1px; }

        .tx-profit {
            font-size: 13px; font-weight: 700;
            color: #16A34A;
        }

        .tx-profit.loss { color: #DC2626; }

        /* ── Margin indicator ── */
        .margin-ring {
            display: flex; align-items: center; justify-content: center;
            flex-direction: column;
            padding: 12px 0;
        }

        .margin-pct-big {
            font-size: 36px; font-weight: 800;
            color: var(--text); letter-spacing: -0.03em;
        }

        .margin-bar-outer {
            width: 100%; height: 8px;
            background: var(--bg); border-radius: 50px;
            margin-top: 10px; overflow: hidden;
        }

        .margin-bar-inner {
            height: 100%; border-radius: 50px;
            background: linear-gradient(90deg, #3B82F6, #22C55E);
            transition: width 1s ease;
        }

        @media (max-width: 1100px) {
            .report-grid    { grid-template-columns: 1fr; }
            .summary-cards  { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 540px) {
            .summary-cards  { grid-template-columns: repeat(2, 1fr); }
            .period-tabs    { flex-wrap: wrap; }
        }
    </style>
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
            <span class="brand-name">StoreTrack</span>
        </div>
        <div class="breadcrumb">
            <span>My Store</span>
            <span class="breadcrumb-sep">›</span>
            <span class="breadcrumb-active">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>
                Profit Report
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
        <button class="icon-btn">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
        </button>
        <div class="user-pill">
            <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($uname) ?></span>
                <span class="user-role">Owner</span>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<div class="app-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="../index.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Dashboard
            </a>
            <a href="inventory.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
                Inventory
            </a>
            <a href="record_sales.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                Record Sale
            </a>
            <a href="sales_history.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Sales History
            </a>
            <a href="profit_report.php" class="nav-item active">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>
                Profit Report
            </a>
            <a href="employees.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M7 21v-2a4 4 0 0 1 3-3.87"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Employees
            </a> 
            <a href="stat.php" class="nav-item">
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
                <h1 class="page-title">Profit Report</h1>
                <p class="page-sub">Detailed analytics for <?= $period_label ?></p>
            </div>
            <div class="period-tabs">
                <a href="?period=7"   class="period-tab <?= $period === '7'   ? 'active' : '' ?>">7 Days</a>
                <a href="?period=30"  class="period-tab <?= $period === '30'  ? 'active' : '' ?>">30 Days</a>
                <a href="?period=90"  class="period-tab <?= $period === '90'  ? 'active' : '' ?>">90 Days</a>
                <a href="?period=all" class="period-tab <?= $period === 'all' ? 'active' : '' ?>">All Time</a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card blue">
                <div class="sc-label">Total Revenue</div>
                <div class="sc-value">Rs<?= number_format($summary['revenue'], 0) ?></div>
                <div class="sc-change">from <?= $summary['total_sales'] ?> sale<?= $summary['total_sales'] != 1 ? 's' : '' ?></div>
            </div>
            <div class="summary-card orange">
                <div class="sc-label">Total Cost</div>
                <div class="sc-value orange">Rs<?= number_format($summary['cost'], 0) ?></div>
                <div class="sc-change"><?= $summary['units_sold'] ?> units sold</div>
            </div>
            <div class="summary-card green">
                <div class="sc-label">Net Profit</div>
                <div class="sc-value green">Rs<?= number_format($summary['profit'], 0) ?></div>
                <div class="sc-change"><?= $margin_pct ?>% profit margin</div>
            </div>
            <div class="summary-card purple">
                <div class="sc-label">Profit Margin</div>
                <div class="sc-value"><?= $margin_pct ?>%</div>
                <div class="sc-change">revenue to profit ratio</div>
            </div>
        </div>

        <div class="report-grid">

            <!-- LEFT COLUMN -->
            <div class="report-left">

                <!-- Revenue & Profit Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">Revenue & Profit Over Time</div>
                            <div class="chart-subtitle"><?= $period_label ?></div>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <div class="legend-dot" style="background:#3B82F6"></div>
                                Revenue
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot" style="background:#22C55E"></div>
                                Profit
                            </div>
                        </div>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Top Selling Items -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <div class="chart-title">Top Items by Revenue</div>
                            <div class="chart-subtitle">Best performing products</div>
                        </div>
                    </div>

                    <?php if (empty($top_items)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📊</div>
                            <p>No sales data for this period.</p>
                        </div>
                    <?php else:
                        $max_rev = $top_items[0]['revenue'];
                    ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Item</th>
                                    <th>Units</th>
                                    <th>Revenue</th>
                                    <th>Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_items as $i => $item):
                                    $bar_width = $max_rev > 0 ? round(($item['revenue'] / $max_rev) * 100) : 0;
                                    $rank_class = match($i) { 0 => 'gold', 1 => 'silver', 2 => 'bronze', default => '' };
                                    $item_profit = round($item['profit'], 0);
                                ?>
                                <tr>
                                    <td><span class="rank-badge <?= $rank_class ?>"><?= $i + 1 ?></span></td>
                                    <td>
                                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($item['item_name']) ?></div>
                                        <div class="item-bar-wrap">
                                            <div class="item-bar" style="width:<?= $bar_width ?>%"></div>
                                        </div>
                                    </td>
                                    <td><?= $item['units'] ?></td>
                                    <td>Rs<?= number_format($item['revenue'], 0) ?></td>
                                    <td><span class="profit-badge">Rs<?= number_format($item_profit, 0) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            </div>

            <!-- RIGHT COLUMN -->
            <div class="report-right">

                <!-- Profit Margin Ring -->
                <div class="chart-card">
                    <div class="chart-title" style="margin-bottom:6px;">Profit Margin</div>
                    <div class="chart-subtitle">Revenue → Profit efficiency</div>
                    <div class="margin-ring">
                        <div class="margin-pct-big"><?= $margin_pct ?>%</div>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">of revenue is profit</div>
                        <div class="margin-bar-outer">
                            <div class="margin-bar-inner" id="marginBar" style="width:0%"></div>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-top:12px;">
                        <div>
                            <div style="color:var(--text-muted); font-weight:600; margin-bottom:2px;">REVENUE</div>
                            <div style="font-weight:700;">Rs<?= number_format($summary['revenue'], 0) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="color:var(--text-muted); font-weight:600; margin-bottom:2px;">PROFIT</div>
                            <div style="font-weight:700; color:#16A34A;">Rs<?= number_format($summary['profit'], 0) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Category Donut Chart -->
                <div class="chart-card">
                    <div class="chart-title" style="margin-bottom:4px;">Sales by Category</div>
                    <div class="chart-subtitle">Revenue distribution</div>

                    <?php if (empty($categories)): ?>
                        <div style="text-align:center; padding:20px; color:var(--text-muted); font-size:13px;">No data yet</div>
                    <?php else: ?>
                        <div class="donut-wrap">
                            <canvas id="donutChart" width="160" height="160" style="max-width:160px; max-height:160px;"></canvas>
                            <div class="donut-center">
                                <div class="donut-center-value"><?= count($categories) ?></div>
                                <div class="donut-center-label">categories</div>
                            </div>
                        </div>
                        <div class="donut-legend">
                            <?php
                            $donut_colors = ['#3B82F6','#22C55E','#F59E0B','#8B5CF6','#EF4444','#14B8A6'];
                            $cat_total    = array_sum(array_column($categories, 'revenue'));
                            foreach ($categories as $ci => $cat):
                                $pct = $cat_total > 0 ? round(($cat['revenue'] / $cat_total) * 100) : 0;
                                $color = $donut_colors[$ci % count($donut_colors)];
                            ?>
                            <div class="donut-legend-item">
                                <div class="donut-legend-left">
                                    <div class="donut-dot" style="background:<?= $color ?>"></div>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </div>
                                <span class="donut-pct"><?= $pct ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Transactions -->
                <div class="chart-card">
                    <div class="chart-header" style="margin-bottom:12px;">
                        <div class="chart-title">Transactions</div>
                        <a href="sales_history.php" class="view-all" style="font-size:12px;">View all →</a>
                    </div>

                    <?php if (empty($transactions)): ?>
                        <div style="text-align:center; padding:20px; color:var(--text-muted); font-size:13px;">No transactions yet</div>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx):
                            $tx_profit  = ($tx['selling_price'] - $tx['cost_price']) * $tx['quantity'];
                            $tx_revenue = $tx['selling_price'] * $tx['quantity'];
                            $tx_time    = date('M j, g:i A', strtotime($tx['sale_date']));
                            $customer   = $tx['customer_name'] ?: 'Walk-in';
                            $avatar_ch  = strtoupper($customer[0]);
                            $is_profit  = $tx_profit >= 0;
                        ?>
                        <div class="tx-item">
                            <div class="tx-left">
                                <div class="tx-avatar"><?= $avatar_ch ?></div>
                                <div>
                                    <div class="tx-name"><?= htmlspecialchars($tx['item_name']) ?></div>
                                    <div class="tx-meta"><?= $tx['quantity'] ?>x · <?= htmlspecialchars($customer) ?> · <?= $tx_time ?></div>
                                </div>
                            </div>
                            <div class="tx-profit <?= !$is_profit ? 'loss' : '' ?>">
                                <?= $is_profit ? '+' : '-' ?>Rs<?= number_format(abs($tx_profit), 0) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </main>
</div>

<script>
// ── Revenue & Profit Line Chart ──
const labels  = <?= json_encode($chart_labels) ?>;
const revenue = <?= json_encode($chart_revenue) ?>;
const profit  = <?= json_encode($chart_profit) ?>;

const ctx = document.getElementById('revenueChart').getContext('2d');

const revenueGrad = ctx.createLinearGradient(0, 0, 0, 220);
revenueGrad.addColorStop(0, 'rgba(59,130,246,0.2)');
revenueGrad.addColorStop(1, 'rgba(59,130,246,0)');

const profitGrad = ctx.createLinearGradient(0, 0, 0, 220);
profitGrad.addColorStop(0, 'rgba(34,197,94,0.15)');
profitGrad.addColorStop(1, 'rgba(34,197,94,0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'Revenue',
                data: revenue,
                borderColor: '#3B82F6',
                backgroundColor: revenueGrad,
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#3B82F6',
                fill: true,
                tension: 0.4,
            },
            {
                label: 'Profit',
                data: profit,
                borderColor: '#22C55E',
                backgroundColor: profitGrad,
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#22C55E',
                fill: true,
                tension: 0.4,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0F172A',
                titleColor: '#94A3B8',
                bodyColor: '#fff',
                padding: 12,
                borderRadius: 8,
                callbacks: {
                    label: ctx => ` Rs${ctx.parsed.y.toLocaleString('en-IN')}`
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    color: '#94A3B8', font: { size: 11 },
                    maxTicksLimit: 8,
                }
            },
            y: {
                grid: { color: '#F1F5F9' },
                ticks: {
                    color: '#94A3B8', font: { size: 11 },
                    callback: v => 'Rs ' + v.toLocaleString('en-IN')
                }
            }
        }
    }
});

// ── Donut Chart ──
<?php if (!empty($categories)): ?>
const donutCtx = document.getElementById('donutChart').getContext('2d');
new Chart(donutCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($categories, 'category')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($categories, 'revenue')) ?>,
            backgroundColor: ['#3B82F6','#22C55E','#F59E0B','#8B5CF6','#EF4444','#14B8A6'],
            borderWidth: 0,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: false,
        cutout: '68%',
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0F172A',
                callbacks: {
                    label: ctx => ` Rs${ctx.parsed.toLocaleString('en-IN')}`
                }
            }
        }
    }
});
<?php endif; ?>

// ── Margin bar animation ──
window.addEventListener('load', () => {
    setTimeout(() => {
        document.getElementById('marginBar').style.width = '<?= min($margin_pct, 100) ?>%';
    }, 300);
});
</script>

</body>
</html>