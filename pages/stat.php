<?php
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

$uid       = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$period = $_GET['period'] ?? '30';
$date_filter = match($period) {
    '7'  => "AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30' => "AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    '90' => "AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
    default => "",
};
$period_labels = ['7'=>'Last 7 Days','30'=>'Last 30 Days','90'=>'Last 90 Days','all'=>'All Time'];

$selected_emp = (int)($_GET['emp'] ?? 0);

$stmt = $conn->prepare("
    SELECT e.id, e.name, e.is_active,
        COUNT(s.id) AS total_sales,
        COALESCE(SUM(s.quantity),0) AS total_items,
        COALESCE(SUM(s.quantity*s.selling_price),0) AS total_revenue,
        COALESCE(SUM(s.quantity*(s.selling_price-s.cost_price)),0) AS total_profit,
        MAX(s.sale_date) AS last_sale
    FROM employees e
    LEFT JOIN sales s ON s.employee_id = e.id $date_filter
    WHERE e.owner_id = ?
    GROUP BY e.id ORDER BY total_revenue DESC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$admin_stmt = $conn->prepare("
    SELECT COUNT(*) AS total_sales, COALESCE(SUM(quantity),0) AS total_items,
        COALESCE(SUM(quantity*selling_price),0) AS total_revenue,
        COALESCE(SUM(quantity*(selling_price-cost_price)),0) AS total_profit
    FROM sales s WHERE user_id = ? AND employee_id IS NULL $date_filter
");
$admin_stmt->bind_param("i", $uid);
$admin_stmt->execute();
$admin_sales = $admin_stmt->get_result()->fetch_assoc();
$admin_stmt->close();

$emp_detail = null; $emp_sales = []; $emp_items = [];
if ($selected_emp > 0) {
    $ed = $conn->prepare("SELECT * FROM employees WHERE id = ? AND owner_id = ?");
    $ed->bind_param("ii", $selected_emp, $uid); $ed->execute();
    $emp_detail = $ed->get_result()->fetch_assoc(); $ed->close();
    if ($emp_detail) {
        $es = $conn->prepare("SELECT item_name,quantity,selling_price,customer_name,sale_date FROM sales s WHERE employee_id = ? $date_filter ORDER BY sale_date DESC LIMIT 10");
        $es->bind_param("i", $selected_emp); $es->execute();
        $emp_sales = $es->get_result()->fetch_all(MYSQLI_ASSOC); $es->close();
        $ei = $conn->prepare("SELECT item_name,SUM(quantity) AS units,SUM(quantity*selling_price) AS revenue FROM sales s WHERE employee_id = ? $date_filter GROUP BY item_name ORDER BY revenue DESC LIMIT 5");
        $ei->bind_param("i", $selected_emp); $ei->execute();
        $emp_items = $ei->get_result()->fetch_all(MYSQLI_ASSOC); $ei->close();
    }
}

$all_revs = array_column($employees, 'total_revenue');
$all_revs[] = $admin_sales['total_revenue'];
$max_rev = max(max($all_revs), 1);

$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($user_name)))));
$initials = substr($initials, 0, 2);
$colors   = ['2563EB','16A34A','DC2626','D97706','7C3AED','0891B2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack2 — Staff Stats</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .period-tabs{display:flex;gap:6px;flex-wrap:wrap}
        .period-tab{padding:7px 16px;border-radius:50px;border:1.5px solid var(--border);background:#fff;font-size:12px;font-weight:600;color:var(--text-sub);text-decoration:none;transition:all .15s}
        .period-tab:hover{border-color:var(--blue);color:var(--blue)}
        .period-tab.active{background:var(--blue);color:#fff;border-color:var(--blue)}
        .lb-wrap{overflow:hidden}
        .lb-row{display:grid;grid-template-columns:40px 1fr 130px 80px 100px 110px;align-items:center;padding:13px 20px;border-bottom:1px solid #F8FAFC;transition:background .1s}
        .lb-row:last-child{border-bottom:none}
        .lb-row:hover{background:#FAFAFA}
        .lb-row.lb-head{background:#FAFAFA;border-bottom:1px solid var(--border);font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-sub);letter-spacing:.06em}
        .lb-rank{font-size:15px;font-weight:800}
        .lb-rank.gold{color:#F59E0B}.lb-rank.silver{color:#94A3B8}.lb-rank.bronze{color:#D97706}
        .lb-name-wrap{display:flex;align-items:center;gap:12px}
        .lb-avatar{width:36px;height:36px;border-radius:50%;color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .lb-name{font-size:13px;font-weight:700}
        .lb-you{font-size:10px;font-weight:700;background:#EFF6FF;color:var(--blue);padding:2px 7px;border-radius:50px;margin-left:6px}
        .lb-sub{font-size:11px;color:var(--text-sub);margin-top:1px}
        .lb-rev{font-size:13px;font-weight:700;color:#16A34A}
        .lb-stat{font-size:13px;font-weight:600}
        .progress-wrap{height:5px;background:#F1F5F9;border-radius:50px;overflow:hidden;margin-top:4px;width:100px}
        .progress-bar{height:100%;border-radius:50px}
        .btn-detail{padding:6px 12px;background:#EFF6FF;color:var(--blue);border:1px solid #BFDBFE;border-radius:7px;font-size:11px;font-weight:700;text-decoration:none;white-space:nowrap;transition:background .15s}
        .btn-detail:hover{background:#DBEAFE}
        .btn-detail.selected{background:var(--blue);color:#fff;border-color:var(--blue)}
        .stat-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
        .stat-mini{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);position:relative;overflow:hidden}
        .stat-mini::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
        .stat-mini.blue::before{background:var(--blue)}.stat-mini.green::before{background:#16A34A}.stat-mini.purple::before{background:#7C3AED}.stat-mini.orange::before{background:#D97706}
        .stat-mini-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-sub);margin-bottom:8px}
        .stat-mini-val{font-size:24px;font-weight:800}
        .stat-mini-val.green{color:#16A34A}.stat-mini-val.purple{color:#7C3AED}
        .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .qty-badge{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:#EFF6FF;color:var(--blue);border-radius:6px;font-size:11px;font-weight:700}
        .empty-state{text-align:center;padding:40px 20px;color:var(--text-sub);font-size:13px}
        .empty-state-icon{font-size:36px;margin-bottom:10px}
        @media(max-width:960px){.lb-row{grid-template-columns:36px 1fr 100px 70px}.lb-row>*:nth-child(5),.lb-row>*:nth-child(6){display:none}.stat-grid-4{grid-template-columns:1fr 1fr}.detail-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>

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
            <span>My Store</span><span class="breadcrumb-sep">›</span>
            <span class="breadcrumb-active">Staff Stats</span>
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
                <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                <span class="user-role">Owner</span>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<div class="app-layout">
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="../index.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>Dashboard
            </a>
            <a href="inventory.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>Inventory
            </a>
            <a href="record_sales.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>Record Sale
            </a>
            <a href="sales_history.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>Sales History
            </a>
            <a href="profit_report.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>Profit Report
            </a>
            <a href="employees.php" class="nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>Employees
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
                </svg>Help Center
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Staff Performance</h1>
                <p class="page-sub">Compare employee sales — <?= $period_labels[$period] ?? 'Last 30 Days' ?></p>
            </div>
            <div class="period-tabs">
                <a href="?period=7"   class="period-tab <?= $period==='7'   ? 'active':'' ?>">7 Days</a>
                <a href="?period=30"  class="period-tab <?= $period==='30'  ? 'active':'' ?>">30 Days</a>
                <a href="?period=90"  class="period-tab <?= $period==='90'  ? 'active':'' ?>">90 Days</a>
                <a href="?period=all" class="period-tab <?= $period==='all' ? 'active':'' ?>">All Time</a>
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="section-card" style="padding:0;overflow:hidden;margin-bottom:24px;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h2 class="section-title">🏆 Sales Leaderboard</h2>
                    <p class="section-sub">Ranked by revenue — <?= $period_labels[$period] ?? 'Last 30 Days' ?></p>
                </div>
            </div>
            <div class="lb-wrap">
                <div class="lb-row lb-head">
                    <div>#</div><div>Name</div><div>Revenue</div><div>Sales</div><div>Items</div><div></div>
                </div>
                <?php
                $leaderboard = $employees;
                $leaderboard[] = ['id'=>0,'name'=>$user_name.' (You)','is_active'=>1,
                    'total_sales'=>$admin_sales['total_sales'],'total_items'=>$admin_sales['total_items'],
                    'total_revenue'=>$admin_sales['total_revenue'],'total_profit'=>$admin_sales['total_profit'],'last_sale'=>null];
                usort($leaderboard, fn($a,$b) => $b['total_revenue'] <=> $a['total_revenue']);
                $rank_icons = ['🥇','🥈','🥉'];
                foreach ($leaderboard as $rank => $person):
                    $is_admin = $person['id'] === 0;
                    $color    = $colors[$rank % count($colors)];
                    $pct      = $max_rev > 0 ? ($person['total_revenue']/$max_rev)*100 : 0;
                    $is_sel   = $person['id'] === $selected_emp && !$is_admin;
                    $rank_lbl = $rank_icons[$rank] ?? ($rank+1);
                ?>
                <div class="lb-row">
                    <div class="lb-rank <?= $rank===0?'gold':($rank===1?'silver':($rank===2?'bronze':'')) ?>"><?= $rank_lbl ?></div>
                    <div class="lb-name-wrap">
                        <div class="lb-avatar" style="background:#<?= $color ?>"><?= strtoupper($person['name'][0]) ?></div>
                        <div>
                            <div class="lb-name"><?= htmlspecialchars($person['name']) ?><?= $is_admin ? '<span class="lb-you">Admin</span>' : '' ?></div>
                            <div class="progress-wrap"><div class="progress-bar" style="width:<?= round($pct) ?>%;background:#<?= $color ?>"></div></div>
                        </div>
                    </div>
                    <div class="lb-rev">Rs<?= number_format($person['total_revenue'],0) ?></div>
                    <div class="lb-stat"><?= $person['total_sales'] ?></div>
                    <div class="lb-stat"><?= $person['total_items'] ?></div>
                    <div><?php if (!$is_admin): ?>
                        <a href="?period=<?= $period ?>&emp=<?= $person['id'] ?>" class="btn-detail <?= $is_sel?'selected':'' ?>"><?= $is_sel?'✓ Viewing':'View Detail' ?></a>
                    <?php endif; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Employee detail -->
        <?php if ($emp_detail):
            $emp_stats_row = null;
            foreach ($employees as $e) { if ($e['id']===$selected_emp) { $emp_stats_row=$e; break; } }
        ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
            <h2 class="section-title">📋 <?= htmlspecialchars($emp_detail['name']) ?>'s Detail</h2>
            <a href="?period=<?= $period ?>" style="font-size:12px;color:var(--text-sub);text-decoration:none;">✕ Close</a>
        </div>

        <?php if ($emp_stats_row): ?>
        <div class="stat-grid-4">
            <div class="stat-mini blue"><div class="stat-mini-label">Total Sales</div><div class="stat-mini-val"><?= $emp_stats_row['total_sales'] ?></div></div>
            <div class="stat-mini green"><div class="stat-mini-label">Revenue</div><div class="stat-mini-val green">Rs<?= number_format($emp_stats_row['total_revenue'],0) ?></div></div>
            <div class="stat-mini purple"><div class="stat-mini-label">Profit Generated</div><div class="stat-mini-val purple">Rs<?= number_format($emp_stats_row['total_profit'],0) ?></div></div>
            <div class="stat-mini orange"><div class="stat-mini-label">Items Sold</div><div class="stat-mini-val"><?= $emp_stats_row['total_items'] ?></div></div>
        </div>
        <?php endif; ?>

        <div class="detail-grid">
            <div class="section-card" style="padding:0;overflow:hidden;">
                <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;">
                    <h2 class="section-title">Recent Sales</h2>
                    <span style="font-size:12px;color:var(--text-sub);">Last 10</span>
                </div>
                <?php if (empty($emp_sales)): ?>
                    <div class="empty-state"><div class="empty-state-icon">🧾</div><p>No sales in this period.</p></div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Item</th><th>Qty</th><th>Revenue</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($emp_sales as $s):
                            $rev = $s['selling_price'] * $s['quantity'];
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['item_name']) ?></strong></td>
                            <td><span class="qty-badge"><?= $s['quantity'] ?></span></td>
                            <td><span class="profit-badge">Rs<?= number_format($rev,0) ?></span></td>
                            <td class="muted"><?= date('M j, g:i A', strtotime($s['sale_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="section-card" style="padding:0;overflow:hidden;">
                <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;">
                    <h2 class="section-title">Top Items Sold</h2>
                    <span style="font-size:12px;color:var(--text-sub);">By revenue</span>
                </div>
                <?php if (empty($emp_items)): ?>
                    <div class="empty-state"><div class="empty-state-icon">📦</div><p>No data yet.</p></div>
                <?php else:
                    $top_rev = max(array_column($emp_items,'revenue'));
                ?>
                    <?php foreach ($emp_items as $idx => $it):
                        $bar = $top_rev > 0 ? ($it['revenue']/$top_rev)*100 : 0;
                    ?>
                    <div style="padding:14px 20px;border-bottom:1px solid #F8FAFC;<?= $idx===count($emp_items)-1?'border-bottom:none':'' ?>">
                        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                            <span style="font-size:13px;font-weight:600;"><?= htmlspecialchars($it['item_name']) ?></span>
                            <span class="profit-badge">Rs<?= number_format($it['revenue'],0) ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="flex:1;height:5px;background:#F1F5F9;border-radius:50px;overflow:hidden;">
                                <div style="width:<?= round($bar) ?>%;height:100%;background:var(--blue);border-radius:50px;"></div>
                            </div>
                            <span style="font-size:11px;color:var(--text-sub);"><?= $it['units'] ?> units</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($employees)): ?>
        <div class="section-card">
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <p>No employees yet. <a href="employees.php" style="color:var(--blue);font-weight:600;">Add employees →</a></p>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>