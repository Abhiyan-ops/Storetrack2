<?php
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

$uid   = $_SESSION['user_id'];
$uname = $_SESSION['user_name'];

// ============================================================
//  DELETE SALE
// ============================================================
if (isset($_GET['delete'])) {
    $id   = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM sales WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $stmt->close();
    header('Location: sales_history.php?msg=deleted');
    exit;
}

// ============================================================
//  FILTERS
// ============================================================
$search   = trim($_GET['search'] ?? '');
$filter   = $_GET['filter'] ?? 'all'; // all, today, week, month

$where  = "WHERE s.user_id = ?";
$params = [$uid];
$types  = "i";

if ($search !== '') {
    $like    = "%$search%";
    $where  .= " AND (s.item_name LIKE ? OR s.customer_name LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}

if ($filter === 'today') {
    $where .= " AND DATE(s.sale_date) = CURDATE()";
} elseif ($filter === 'week') {
    $where .= " AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter === 'month') {
    $where .= " AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// ============================================================
//  FETCH SALES
// ============================================================
$sql  = "SELECT * FROM sales s
         $where
         ORDER BY s.sale_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ============================================================
//  SUMMARY STATS (filtered)
// ============================================================
$total_revenue = 0;
$total_profit  = 0;
$total_qty     = 0;
foreach ($sales as $s) {
    $total_revenue += $s['selling_price'] * $s['quantity'];
    $total_profit  += ($s['selling_price'] - $s['cost_price']) * $s['quantity'];
    $total_qty     += $s['quantity'];
}
$total_sales = count($sales);

// Initials
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($uname)))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack — Sales History</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ── Filter Bar ── */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-search {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            padding: 10px 14px;
            flex: 1; min-width: 200px;
            transition: border-color 0.2s;
        }

        .filter-search:focus-within { border-color: var(--blue); }

        .filter-search input {
            border: none; outline: none;
            font-size: 13px; font-family: 'Inter', sans-serif;
            color: var(--text); background: transparent; width: 100%;
        }

        .filter-search input::placeholder { color: var(--text-muted); }

        .filter-tabs {
            display: flex;
            gap: 6px;
        }

        .filter-tab {
            padding: 9px 16px;
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            background: var(--surface);
            font-size: 12px; font-weight: 600;
            color: var(--text-sub);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .filter-tab:hover { background: var(--bg); color: var(--text); }

        .filter-tab.active {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
        }

        /* ── Mini Stat Row ── */
        .mini-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .mini-stat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            box-shadow: var(--shadow);
        }

        .mini-stat-label {
            font-size: 11px; font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.07em;
            margin-bottom: 8px;
        }

        .mini-stat-value {
            font-size: 22px; font-weight: 800;
            color: var(--text); letter-spacing: -0.02em;
        }

        .mini-stat-value.green { color: var(--green); }

        /* ── Table extras ── */
        .sale-item-name { font-weight: 600; color: var(--text); }

        .customer-name {
            display: flex; align-items: center; gap: 7px;
        }

        .customer-dot {
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--blue-light);
            color: var(--blue);
            font-size: 10px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .qty-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px;
            background: var(--blue-light); color: var(--blue);
            border-radius: 50%; font-weight: 700; font-size: 12px;
        }

        .profit-badge {
            display: inline-block;
            background: var(--green-bg); color: var(--green);
            padding: 3px 10px; border-radius: 50px;
            font-weight: 700; font-size: 12px;
        }

        .loss-badge {
            display: inline-block;
            background: #FEF2F2; color: #DC2626;
            padding: 3px 10px; border-radius: 50px;
            font-weight: 700; font-size: 12px;
        }

        .btn-action {
            border: none; background: transparent;
            cursor: pointer; padding: 6px 8px;
            border-radius: 6px; transition: background 0.15s;
            font-size: 15px; text-decoration: none;
            display: inline-flex; align-items: center;
        }

        .btn-delete:hover { background: #FEE2E2; }

        .items-count {
            font-size: 12px; font-weight: 600;
            color: var(--text-muted);
            background: var(--bg);
            border: 1px solid var(--border);
            padding: 3px 10px; border-radius: 50px;
        }

        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-muted);
        }

        .empty-state-icon { font-size: 40px; margin-bottom: 12px; }
        .empty-state p    { font-size: 14px; }

        .toast {
            position: fixed; bottom: 24px; right: 24px;
            background: #0F172A; color: white;
            padding: 12px 20px; border-radius: var(--radius);
            font-size: 13px; font-weight: 500;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            z-index: 999;
            animation: slideUp 0.3s ease;
        }

        .toast.success { border-left: 4px solid #22C55E; }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        @media (max-width: 900px) {
            .mini-stats { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 540px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .mini-stats { grid-template-columns: repeat(2, 1fr); }
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
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Sales History
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
            <a href="sales_history.php" class="nav-item active">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                Sales History
            </a>
            <a href="profit_report.php" class="nav-item">
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
                <h1 class="page-title">Sales History</h1>
                <p class="page-sub">View and manage all your past transactions.</p>
            </div>
            <a href="record_sales.php" style="
                display:inline-flex; align-items:center; gap:7px;
                background:var(--blue); color:white;
                padding:9px 18px; border-radius:var(--radius);
                font-size:13px; font-weight:600; text-decoration:none;
                transition: background 0.15s;
            ">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Record New Sale
            </a>
        </div>

        <!-- Toast -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="toast success" id="toast">✓ Sale deleted successfully.</div>
        <?php endif; ?>

        <!-- Mini Stats (filtered) -->
        <div class="mini-stats">
            <div class="mini-stat">
                <div class="mini-stat-label">Total Sales</div>
                <div class="mini-stat-value"><?= $total_sales ?></div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Items Sold</div>
                <div class="mini-stat-value"><?= $total_qty ?></div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Revenue</div>
                <div class="mini-stat-value">₹<?= number_format($total_revenue, 0) ?></div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Net Profit</div>
                <div class="mini-stat-value green">₹<?= number_format($total_profit, 0) ?></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="sales_history.php">
            <div class="filter-bar">
                <div class="filter-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" name="search"
                           placeholder="Search by item name or customer..."
                           value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                </div>
                <div class="filter-tabs">
                    <a href="?filter=all"   class="filter-tab <?= $filter === 'all'   ? 'active' : '' ?>">All Time</a>
                    <a href="?filter=today" class="filter-tab <?= $filter === 'today' ? 'active' : '' ?>">Today</a>
                    <a href="?filter=week"  class="filter-tab <?= $filter === 'week'  ? 'active' : '' ?>">This Week</a>
                    <a href="?filter=month" class="filter-tab <?= $filter === 'month' ? 'active' : '' ?>">This Month</a>
                </div>
            </div>
        </form>

        <!-- Sales Table -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Transactions</h2>
                    <p class="section-sub">
                        <?php
                        $label = ['all' => 'All time', 'today' => 'Today', 'week' => 'Last 7 days', 'month' => 'Last 30 days'];
                        echo htmlspecialchars($label[$filter] ?? 'All time');
                        if ($search) echo ' · Search: "' . htmlspecialchars($search) . '"';
                        ?>
                    </p>
                </div>
                <span class="items-count"><?= $total_sales ?> sale<?= $total_sales !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (empty($sales)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🧾</div>
                    <p><?= $search ? 'No sales found matching your search.' : 'No sales recorded yet. <a href="record_sales.php">Record your first sale →</a>' ?></p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Revenue</th>
                            <th>Profit</th>
                            <th>Customer</th>
                            <th>Date & Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $i => $sale):
                            $revenue   = $sale['selling_price'] * $sale['quantity'];
                            $profit    = ($sale['selling_price'] - $sale['cost_price']) * $sale['quantity'];
                            $is_profit = $profit >= 0;
                            $customer  = $sale['customer_name'] ?: 'Walk-in';
                            $c_initial = strtoupper($customer[0]);
                            $item_name = $sale['item_name'] ?? 'Unknown Item';
                            $date      = date('M j, Y', strtotime($sale['sale_date']));
                            $time      = date('g:i A', strtotime($sale['sale_date']));
                        ?>
                        <tr>
                            <td class="muted"><?= $total_sales - $i ?></td>
                            <td class="sale-item-name"><?= htmlspecialchars($item_name) ?></td>
                            <td><span class="qty-badge"><?= $sale['quantity'] ?></span></td>
                            <td>₹<?= number_format($revenue, 0) ?></td>
                            <td>
                                <?php if ($is_profit): ?>
                                    <span class="profit-badge">₹<?= number_format($profit, 0) ?></span>
                                <?php else: ?>
                                    <span class="loss-badge">-₹<?= number_format(abs($profit), 0) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="customer-name">
                                    <span class="customer-dot"><?= $c_initial ?></span>
                                    <?= htmlspecialchars($customer) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:13px;"><?= $date ?></div>
                                <div class="muted"><?= $time ?></div>
                            </td>
                            <td>
                                <a href="sales_history.php?delete=<?= $sale['id'] ?>"
                                   class="btn-action btn-delete"
                                   onclick="return confirm('Delete this sale record?')"
                                   title="Delete">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
const toast = document.getElementById('toast');
if (toast) setTimeout(() => toast.style.display = 'none', 3000);

// Submit search on Enter
document.querySelector('.filter-search input[name="search"]').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        this.closest('form').submit();
    }
});
</script>

</body>
</html>