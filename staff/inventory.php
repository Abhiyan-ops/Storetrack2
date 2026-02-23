<?php
require_once 'staff_auth.php';
require_once '../includes/db.php';

$emp_id   = $_SESSION['employee_id'];
$emp_name = $_SESSION['employee_name'];
$owner_id = $_SESSION['owner_id'];

// Search
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

// Build query — employee sees NO cost_price
if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT id, name, category, selling_price, quantity
        FROM inventory
        WHERE user_id = ?
          AND (name LIKE ? OR category LIKE ?)
        ORDER BY name ASC
    ");
    $like = "%$search%";
    $stmt->bind_param("iss", $owner_id, $like, $like);
} elseif ($category_filter !== '') {
    $stmt = $conn->prepare("
        SELECT id, name, category, selling_price, quantity
        FROM inventory
        WHERE user_id = ? AND category = ?
        ORDER BY name ASC
    ");
    $stmt->bind_param("is", $owner_id, $category_filter);
} else {
    $stmt = $conn->prepare("
        SELECT id, name, category, selling_price, quantity
        FROM inventory
        WHERE user_id = ?
        ORDER BY name ASC
    ");
    $stmt->bind_param("i", $owner_id);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Categories for filter tabs
$cat_stmt = $conn->prepare("SELECT DISTINCT category FROM inventory WHERE user_id = ? ORDER BY category ASC");
$cat_stmt->bind_param("i", $owner_id);
$cat_stmt->execute();
$categories = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cat_stmt->close();

// Summary counts
$total_items = count($items);
$low_stock   = array_filter($items, fn($i) => $i['quantity'] <= 5 && $i['quantity'] > 0);
$out_stock   = array_filter($items, fn($i) => $i['quantity'] === 0);

$initials = strtoupper(substr($emp_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack2 — Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:         #F1F5F9;
            --surface:    #FFFFFF;
            --border:     #E2E8F0;
            --blue:       #2563EB;
            --blue-light: #EFF6FF;
            --text:       #0F172A;
            --text-muted: #64748B;
            --green:      #16A34A;
            --green-bg:   #F0FDF4;
            --red:        #DC2626;
            --red-bg:     #FEF2F2;
            --orange:     #D97706;
            --orange-bg:  #FFFBEB;
            --radius:     10px;
            --shadow:     0 1px 4px rgba(0,0,0,0.06);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }

        /* ── Navbar ── */
        .navbar {
            background: white; border-bottom: 1px solid var(--border);
            padding: 0 24px; height: 56px;
            display: flex; align-items: center; justify-content: space-between;
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            box-shadow: var(--shadow);
        }
        .nav-left { display: flex; align-items: center; gap: 12px; }
        .brand-icon { width: 30px; height: 30px; background: var(--blue); border-radius: 7px; display: flex; align-items: center; justify-content: center; }
        .brand-name { font-size: 16px; font-weight: 800; }
        .staff-tag { background: var(--blue-light); color: var(--blue); font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 100px; border: 1px solid #BFDBFE; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .user-pill { display: flex; align-items: center; gap: 8px; background: var(--bg); border-radius: 100px; padding: 5px 12px 5px 5px; }
        .avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--blue); color: white; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .user-name { font-size: 13px; font-weight: 600; }
        .logout-btn { padding: 7px 14px; background: #FEF2F2; color: var(--red); border: 1px solid #FECACA; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; }

        /* ── Layout ── */
        .layout { display: flex; margin-top: 56px; min-height: calc(100vh - 56px); }

        /* ── Sidebar ── */
        .sidebar { width: 200px; flex-shrink: 0; background: white; border-right: 1px solid var(--border); padding: 20px 12px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--text-muted); text-decoration: none; margin-bottom: 2px; transition: background 0.15s, color 0.15s; }
        .nav-item:hover { background: var(--bg); color: var(--text); }
        .nav-item.active { background: var(--blue-light); color: var(--blue); font-weight: 600; }

        /* ── Main ── */
        .main { flex: 1; padding: 28px; }

        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 22px; font-weight: 800; }
        .page-sub { font-size: 13px; color: var(--text-muted); margin-top: 3px; }

        /* ── Summary mini cards ── */
        .mini-stats { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .mini-card { background: white; border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 18px; box-shadow: var(--shadow); min-width: 140px; }
        .mini-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 4px; }
        .mini-value { font-size: 22px; font-weight: 800; }
        .mini-value.orange { color: var(--orange); }
        .mini-value.red    { color: var(--red); }
        .mini-value.green  { color: var(--green); }

        /* ── Search + filter bar ── */
        .filter-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }

        .search-wrap {
            display: flex; align-items: center; gap: 10px;
            background: white; border: 1.5px solid var(--border);
            border-radius: var(--radius); padding: 0 14px;
            flex: 1; min-width: 200px;
            transition: border-color 0.2s;
        }
        .search-wrap:focus-within { border-color: var(--blue); }
        .search-wrap input { border: none; outline: none; font-size: 13px; font-family: 'Inter', sans-serif; background: none; width: 100%; padding: 11px 0; color: var(--text); }
        .search-wrap input::placeholder { color: var(--text-muted); }

        .btn-search { padding: 10px 18px; background: var(--blue); color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; white-space: nowrap; }

        /* ── Category tabs ── */
        .cat-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
        .cat-tab { padding: 7px 14px; border-radius: 100px; border: 1.5px solid var(--border); background: white; font-size: 12px; font-weight: 600; color: var(--text-muted); text-decoration: none; transition: all 0.15s; white-space: nowrap; }
        .cat-tab:hover { border-color: var(--blue); color: var(--blue); }
        .cat-tab.active { background: var(--blue); color: white; border-color: var(--blue); }

        /* ── Table ── */
        .table-card { background: white; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow); overflow: hidden; }

        .table-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: 14px; font-weight: 700; }
        .items-count { font-size: 12px; color: var(--text-muted); }

        table { width: 100%; border-collapse: collapse; }
        th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); padding: 12px 20px; text-align: left; background: #FAFAFA; border-bottom: 1px solid var(--border); }
        td { padding: 14px 20px; font-size: 13px; border-bottom: 1px solid #F8FAFC; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #FAFAFA; }

        /* ── Badges ── */
        .cat-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 100px; font-size: 11px; font-weight: 600; background: var(--blue-light); color: var(--blue); }

        .stock-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 100px; font-size: 12px; font-weight: 700; }
        .stock-badge.ok      { background: var(--green-bg); color: var(--green); }
        .stock-badge.low     { background: var(--orange-bg); color: var(--orange); }
        .stock-badge.out     { background: var(--red-bg); color: var(--red); }

        /* ── Record sale quick button ── */
        .btn-sell { padding: 7px 14px; background: var(--blue); color: white; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; text-decoration: none; display: inline-block; transition: background 0.15s; }
        .btn-sell:hover { background: #1D4ED8; }
        .btn-sell.disabled { background: #E2E8F0; color: var(--text-muted); cursor: not-allowed; pointer-events: none; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-icon { font-size: 40px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; color: var(--text-muted); }

        /* ── Low stock alert ── */
        .alert-bar { background: var(--orange-bg); border: 1px solid #FDE68A; border-radius: var(--radius); padding: 12px 16px; font-size: 13px; color: #92400E; display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
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
        <a href="inventory.php" class="nav-item active">
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
            <div>
                <h1 class="page-title">Inventory</h1>
                <p class="page-sub">View stock levels and prices — contact your admin to make changes</p>
            </div>
            <a href="record_sale.php" class="btn-sell">
                + Record a Sale
            </a>
        </div>

        <!-- Low stock alert -->
        <?php if (!empty($low_stock) || !empty($out_stock)): ?>
        <div class="alert-bar">
            ⚠️ <strong>Heads up:</strong>
            <?php if (!empty($out_stock)): ?>
                <?= count($out_stock) ?> item(s) are out of stock.
            <?php endif; ?>
            <?php if (!empty($low_stock)): ?>
                <?= count($low_stock) ?> item(s) are running low.
            <?php endif; ?>
            Please inform your manager.
        </div>
        <?php endif; ?>

        <!-- Mini stats -->
        <div class="mini-stats">
            <div class="mini-card">
                <div class="mini-label">Total Items</div>
                <div class="mini-value"><?= $total_items ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Low Stock</div>
                <div class="mini-value orange"><?= count($low_stock) ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">Out of Stock</div>
                <div class="mini-value red"><?= count($out_stock) ?></div>
            </div>
            <div class="mini-card">
                <div class="mini-label">In Stock</div>
                <div class="mini-value green"><?= $total_items - count($out_stock) ?></div>
            </div>
        </div>

        <!-- Search bar -->
        <form method="GET" action="inventory.php">
            <div class="filter-bar">
                <div class="search-wrap">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" name="search" placeholder="Search by name or category..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn-search">Search</button>
                <?php if ($search): ?>
                    <a href="inventory.php" style="padding:10px 14px; font-size:13px; color:var(--text-muted); text-decoration:none; font-weight:500;">✕ Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Category filter tabs -->
        <?php if (!empty($categories)): ?>
        <div class="cat-tabs">
            <a href="inventory.php" class="cat-tab <?= $category_filter === '' ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= urlencode($cat['category']) ?>"
                   class="cat-tab <?= $category_filter === $cat['category'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['category']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Inventory table -->
        <div class="table-card">
            <div class="table-header">
                <span class="table-title">Stock List</span>
                <span class="items-count"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <p><?= $search ? "No items found for \"$search\"" : 'No inventory items yet.' ?></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Selling Price</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item):
                            $qty = (int)$item['quantity'];
                            if ($qty === 0) {
                                $stock_class = 'out';
                                $stock_label = '✕ Out of Stock';
                            } elseif ($qty <= 5) {
                                $stock_class = 'low';
                                $stock_label = '⚠ ' . $qty . ' left';
                            } else {
                                $stock_class = 'ok';
                                $stock_label = $qty . ' in stock';
                            }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                            <td><span class="cat-badge"><?= htmlspecialchars($item['category']) ?></span></td>
                            <td style="font-weight:600;">Rs.<?= number_format($item['selling_price'], 2) ?></td>
                            <td><span class="stock-badge <?= $stock_class ?>"><?= $stock_label ?></span></td>
                            <td>
                                <?php if ($qty > 0): ?>
                                    <a href="record_sale.php?item_id=<?= $item['id'] ?>" class="btn-sell">Sell</a>
                                <?php else: ?>
                                    <span class="btn-sell disabled">Out of Stock</span>
                                <?php endif; ?>
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