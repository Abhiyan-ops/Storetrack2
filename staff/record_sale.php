<?php
require_once 'staff_auth.php';
require_once '../includes/db.php';

$emp_id   = $_SESSION['employee_id'];
$emp_name = $_SESSION['employee_name'];
$owner_id = $_SESSION['owner_id'];

$errors  = [];
$success = '';

// Fetch inventory for this owner — no cost_price exposed
$inv_stmt = $conn->prepare("
    SELECT id, name, selling_price, quantity
    FROM inventory
    WHERE user_id = ? AND quantity > 0
    ORDER BY name ASC
");
$inv_stmt->bind_param("i", $owner_id);
$inv_stmt->execute();
$inventory = $inv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$inv_stmt->close();

// Pre-select item if coming from inventory page
$preselect_id = (int)($_GET['item_id'] ?? 0);

// ── HANDLE SALE SUBMISSION ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id       = (int)($_POST['item_id']       ?? 0);
    $quantity      = (int)($_POST['quantity']       ?? 0);
    $customer_name = trim($_POST['customer_name']   ?? '');

    if ($item_id <= 0)  $errors[] = 'Please select an item.';
    if ($quantity <= 0) $errors[] = 'Quantity must be at least 1.';

    if (empty($errors)) {
        // Verify item belongs to owner and has stock
        // Fetch cost_price here only for saving — never shown to employee
        $check = $conn->prepare("
            SELECT id, name, selling_price, cost_price, quantity
            FROM inventory
            WHERE id = ? AND user_id = ?
        ");
        $check->bind_param("ii", $item_id, $owner_id);
        $check->execute();
        $item = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$item) {
            $errors[] = 'Item not found.';
        } elseif ($quantity > $item['quantity']) {
            $errors[] = "Not enough stock. Only {$item['quantity']} units available.";
        }
    }

    if (empty($errors)) {
        $item_name     = $item['name'];
        $selling_price = (float)$item['selling_price'];
        $cost_price    = (float)$item['cost_price'];
        $customer      = $customer_name ?: 'Walk-in';

        // INSERT — employee_id tags this sale to this employee
        $stmt = $conn->prepare("
            INSERT INTO sales
                (item_id, item_name, quantity, selling_price, cost_price, customer_name, user_id, employee_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // isiddsi i = int,str,int,dec,dec,str,int,int
        $stmt->bind_param("isiddsii",
            $item_id, $item_name, $quantity,
            $selling_price, $cost_price,
            $customer, $owner_id, $emp_id
        );

        if ($stmt->execute()) {
            // Deduct stock
            $new_qty = $item['quantity'] - $quantity;
            $upd = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ? AND user_id = ?");
            $upd->bind_param("iii", $new_qty, $item_id, $owner_id);
            $upd->execute();
            $upd->close();

            $success = "Sale recorded! Sold {$quantity}x {$item_name} to {$customer}.";

            // Refresh inventory list
            $inv_stmt2 = $conn->prepare("SELECT id, name, selling_price, quantity FROM inventory WHERE user_id = ? AND quantity > 0 ORDER BY name ASC");
            $inv_stmt2->bind_param("i", $owner_id);
            $inv_stmt2->execute();
            $inventory = $inv_stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $inv_stmt2->close();
        } else {
            $errors[] = 'Failed to record sale: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Recent sales by THIS employee
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

$initials = strtoupper(substr($emp_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack2 — Record Sale</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #F1F5F9; --surface: #FFFFFF; --border: #E2E8F0;
            --blue: #2563EB; --blue-light: #EFF6FF;
            --text: #0F172A; --text-muted: #64748B;
            --green: #16A34A; --red: #DC2626;
            --orange: #D97706; --orange-bg: #FFFBEB;
            --radius: 10px; --shadow: 0 1px 4px rgba(0,0,0,0.06);
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

        /* Page header */
        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 22px; font-weight: 800; }
        .page-sub { font-size: 13px; color: var(--text-muted); margin-top: 3px; }

        /* Employee badge */
        .emp-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--blue-light); border: 1px solid #BFDBFE;
            border-radius: 100px; padding: 6px 14px;
            font-size: 12px; font-weight: 600; color: var(--blue);
            margin-bottom: 20px;
        }

        /* Toast */
        .toast {
            padding: 12px 18px; border-radius: var(--radius);
            font-size: 13px; font-weight: 600; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .toast.success { background: #F0FDF4; border: 1px solid #BBF7D0; color: var(--green); }
        .toast.error   { background: #FEF2F2; border: 1px solid #FECACA; color: var(--red); }

        /* Two column grid */
        .sale-grid { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }

        /* Cards */
        .card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 24px; box-shadow: var(--shadow); }
        .card-title { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
        .card-sub { font-size: 12px; color: var(--text-muted); margin-bottom: 20px; }

        /* Form elements */
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); margin-bottom: 7px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: var(--radius); font-size: 13px; font-family: 'Inter', sans-serif; color: var(--text); background: #FAFAFA; outline: none; transition: border-color 0.2s, box-shadow 0.2s; -webkit-appearance: none; }
        .form-control:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,0.08); background: white; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        /* Item preview card */
        .item-preview {
            background: #FAFAFA; border: 1.5px solid var(--border);
            border-radius: var(--radius); padding: 16px;
            margin-top: 10px; display: none;
        }
        .item-preview.visible { display: block; }
        .preview-name { font-size: 15px; font-weight: 700; margin-bottom: 12px; }
        .preview-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
        .preview-row:last-child { border-bottom: none; }
        .preview-row span:first-child { color: var(--text-muted); }

        .stock-warn { background: var(--orange-bg); border: 1px solid #FDE68A; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; color: #92400E; margin-top: 10px; }

        /* Submit button */
        .btn-submit { width: 100%; padding: 13px; background: var(--blue) !important; color: white !important; border: none !important; border-radius: var(--radius) !important; font-size: 14px !important; font-weight: 700 !important; font-family: 'Inter', sans-serif !important; cursor: pointer; margin-top: 6px; transition: background 0.2s, transform 0.15s; -webkit-appearance: none; }
        .btn-submit:hover { background: #1D4ED8 !important; transform: translateY(-1px); }
        .btn-submit:disabled { background: #E2E8F0 !important; color: var(--text-muted) !important; cursor: not-allowed; transform: none; }

        /* Summary rows */
        .summary-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
        .summary-row:last-child { border-bottom: none; }
        .summary-label { color: var(--text-muted); }
        .summary-value { font-weight: 600; }
        .summary-value.big { font-size: 20px; font-weight: 800; color: var(--text); }

        /* Recent sales */
        .recent-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border); }
        .recent-item:last-child { border-bottom: none; }
        .recent-name { font-size: 13px; font-weight: 600; }
        .recent-meta { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .recent-rev { font-size: 13px; font-weight: 700; color: var(--green); }

        /* Empty */
        .empty { text-align: center; padding: 24px; color: var(--text-muted); font-size: 13px; }

        @media (max-width: 900px) {
            .sale-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
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
        <a href="record_sale.php" class="nav-item active">
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
            <h1 class="page-title">Record Sale</h1>
            <p class="page-sub">Select an item, enter quantity and customer details</p>
        </div>

        <!-- Employee tag -->
        <div class="emp-badge">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            Recording as: <strong><?= htmlspecialchars($emp_name) ?></strong>
        </div>

        <!-- Toast messages -->
        <?php if ($success): ?>
            <div class="toast success" id="toast">✓ <?= htmlspecialchars($success) ?></div>
        <?php elseif (!empty($errors)): ?>
            <div class="toast error" id="toast">⚠ <?= htmlspecialchars($errors[0]) ?></div>
        <?php endif; ?>

        <?php if (empty($inventory)): ?>
            <div class="card" style="text-align:center; padding:60px;">
                <div style="font-size:40px; margin-bottom:14px;">📦</div>
                <p style="font-weight:700; font-size:16px; margin-bottom:8px;">No items in stock</p>
                <p style="color:var(--text-muted); font-size:13px;">All items are out of stock. Please inform your admin.</p>
            </div>
        <?php else: ?>

        <div class="sale-grid">

            <!-- LEFT: Form -->
            <div class="card">
                <div class="card-title">Sale Details</div>
                <div class="card-sub">Fill in the details below to record this sale</div>

                <?php if (!empty($errors)): ?>
                    <div class="toast error" style="margin-bottom:16px;">
                        <?php foreach ($errors as $e): ?>
                            <div>⚠ <?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="record_sale.php" id="saleForm">
                    <div class="form-group">
                        <label class="form-label">Select Item</label>
                        <select name="item_id" id="itemSelect" class="form-control" required onchange="updatePreview(this)">
                            <option value="" disabled <?= $preselect_id === 0 ? 'selected' : '' ?>>Choose an item...</option>
                            <?php foreach ($inventory as $inv): ?>
                                <option value="<?= $inv['id'] ?>"
                                    data-name="<?= htmlspecialchars($inv['name']) ?>"
                                    data-sell="<?= $inv['selling_price'] ?>"
                                    data-stock="<?= $inv['quantity'] ?>"
                                    <?= $preselect_id === (int)$inv['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inv['name']) ?> (<?= $inv['quantity'] ?> in stock)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Item preview -->
                        <div class="item-preview" id="itemPreview">
                            <div class="preview-name" id="prevName"></div>
                            <div class="preview-row">
                                <span>Selling Price</span>
                                <span id="prevSell" style="font-weight:700;"></span>
                            </div>
                            <div class="preview-row">
                                <span>Available Stock</span>
                                <span id="prevStock"></span>
                            </div>
                            <div id="stockWarn" class="stock-warn" style="display:none">
                                ⚠ Low stock — only <span id="warnCount"></span> units left
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="qtyInput"
                                   class="form-control" placeholder="e.g. 2"
                                   min="1" required
                                   value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>"
                                   oninput="updateSummary()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Customer Name <span style="font-weight:400;">(optional)</span></label>
                            <input type="text" name="customer_name" class="form-control"
                                   placeholder="Leave blank for Walk-in"
                                   value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">Record Sale →</button>
                </form>
            </div>

            <!-- RIGHT: Summary + Recent -->
            <div style="display:flex; flex-direction:column; gap:16px;">

                <!-- Sale summary -->
                <div class="card">
                    <div class="card-title" style="margin-bottom:16px;">Sale Summary</div>
                    <div class="summary-row">
                        <span class="summary-label">Item</span>
                        <span class="summary-value" id="sumItem">—</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Quantity</span>
                        <span class="summary-value" id="sumQty">—</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Price per unit</span>
                        <span class="summary-value" id="sumPrice">—</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Total</span>
                        <span class="summary-value big" id="sumTotal">—</span>
                    </div>
                </div>

                <!-- Recent sales -->
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                        <div class="card-title">My Recent Sales</div>
                        <a href="my_sales.php" style="font-size:12px; font-weight:600; color:var(--blue); text-decoration:none;">View all →</a>
                    </div>

                    <?php if (empty($recent_sales)): ?>
                        <div class="empty">No sales recorded yet.</div>
                    <?php else: ?>
                        <?php foreach ($recent_sales as $rs):
                            $rev  = $rs['selling_price'] * $rs['quantity'];
                            $time = date('M j, g:i A', strtotime($rs['sale_date']));
                            $cust = $rs['customer_name'] ?: 'Walk-in';
                        ?>
                        <div class="recent-item">
                            <div>
                                <div class="recent-name"><?= htmlspecialchars($rs['item_name']) ?></div>
                                <div class="recent-meta"><?= $rs['quantity'] ?>x · <?= htmlspecialchars($cust) ?> · <?= $time ?></div>
                            </div>
                            <span class="recent-rev">Rs.<?= number_format($rev, 0) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <?php endif; ?>

    </main>
</div>

<!-- Item data for JS — no cost_price in JS either -->
<script>
const items = {
    <?php foreach ($inventory as $inv): ?>
    <?= $inv['id'] ?>: {
        name:  "<?= addslashes($inv['name']) ?>",
        sell:  <?= $inv['selling_price'] ?>,
        stock: <?= $inv['quantity'] ?>
    },
    <?php endforeach; ?>
};

let selected = null;

function updatePreview(sel) {
    const item = items[sel.value];
    if (!item) return;
    selected = item;

    document.getElementById('itemPreview').classList.add('visible');
    document.getElementById('prevName').textContent  = item.name;
    document.getElementById('prevSell').textContent  = 'Rs.' + item.sell.toLocaleString('en-IN');
    document.getElementById('prevStock').textContent = item.stock + ' units';

    const warn = document.getElementById('stockWarn');
    if (item.stock <= 5) {
        warn.style.display = 'block';
        document.getElementById('warnCount').textContent = item.stock;
    } else {
        warn.style.display = 'none';
    }

    document.getElementById('qtyInput').max = item.stock;
    updateSummary();
}

function updateSummary() {
    if (!selected) return;
    const qty = parseInt(document.getElementById('qtyInput').value) || 0;

    document.getElementById('sumItem').textContent  = selected.name;
    document.getElementById('sumQty').textContent   = qty + ' unit' + (qty !== 1 ? 's' : '');
    document.getElementById('sumPrice').textContent = 'Rs.' + selected.sell.toLocaleString('en-IN');
    document.getElementById('sumTotal').textContent = qty > 0
        ? 'Rs.' + (selected.sell * qty).toLocaleString('en-IN') : '—';

    const btn = document.getElementById('submitBtn');
    if (qty > selected.stock) {
        btn.disabled = true;
        btn.textContent = 'Not enough stock';
    } else {
        btn.disabled = false;
        btn.textContent = 'Record Sale →';
    }
}

// Auto-trigger preview if item pre-selected from inventory page
const preselect = <?= $preselect_id ?>;
if (preselect) {
    const sel = document.getElementById('itemSelect');
    sel.value = preselect;
    updatePreview(sel);
}

// Auto dismiss toast
const toast = document.getElementById('toast');
if (toast) setTimeout(() => toast.style.opacity = '0', 3500);
</script>

</body>
</html>