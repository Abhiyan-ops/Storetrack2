<?php
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

$uid    = $_SESSION['user_id'];
$uname  = $_SESSION['user_name'];
$errors  = [];
$success = '';

// ============================================================
//  FETCH THIS USER'S INVENTORY for dropdown
// ============================================================
$stmt = $conn->prepare("SELECT id, name, selling_price, cost_price, quantity FROM inventory WHERE user_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $uid);
$stmt->execute();
$inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ============================================================
//  RECORD SALE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id       = (int)($_POST['item_id'] ?? 0);
    $quantity      = (int)($_POST['quantity'] ?? 0);
    $customer_name = trim($_POST['customer_name'] ?? '');

    // Validate
    if ($item_id <= 0)   $errors[] = 'Please select an item.';
    if ($quantity <= 0)  $errors[] = 'Quantity must be at least 1.';

    // Fetch item to verify it belongs to this user and has enough stock
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $item_id, $uid);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item) {
            $errors[] = 'Item not found.';
        } elseif ($quantity > $item['quantity']) {
            $errors[] = "Not enough stock. Only {$item['quantity']} units available.";
        }
    }

    // Save sale & deduct stock
    if (empty($errors)) {
        $selling_price = $item['selling_price'];
        $cost_price    = $item['cost_price'];
        $item_name     = $item['name'];
        $customer      = $customer_name ?: 'Walk-in';
        $selling_price = (float)$item['selling_price'];
        $cost_price    = (float)$item['cost_price'];
        $quantity      = (int)$quantity;
        $item_id       = (int)$item_id;
        $uid_int       = (int)$uid;

        // Insert into sales — types: i=int, s=string, d=decimal
        $stmt = $conn->prepare("INSERT INTO sales (item_id, item_name, quantity, selling_price, cost_price, customer_name, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiddsi", $item_id, $item_name, $quantity, $selling_price, $cost_price, $customer, $uid_int);

        if ($stmt->execute()) {
            // Deduct stock from inventory
            $new_qty = $item['quantity'] - $quantity;
            $upd = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ? AND user_id = ?");
            $upd->bind_param("iii", $new_qty, $item_id, $uid);
            $upd->execute();
            $upd->close();

            $success = "Sale recorded! Sold {$quantity}x {$item_name} to {$customer}.";

            // Refresh inventory list after stock update
            $stmt2 = $conn->prepare("SELECT id, name, selling_price, cost_price, quantity FROM inventory WHERE user_id = ? ORDER BY name ASC");
            $stmt2->bind_param("i", $uid);
            $stmt2->execute();
            $inventory = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();
        } else {
            $errors[] = 'Failed to record sale: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Initials
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($uname)))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack — Record Sale</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
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
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                Record Sale
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
            <a href="record_sales.php" class="nav-item active">
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
            <a href="profit_report.php" class="nav-item">
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

        <div class="page-header">
            <div>
                <h1 class="page-title">Record Sale</h1>
                <p class="page-sub">Select an item, enter quantity and customer details.</p>
            </div>
        </div>

        <!-- Toast -->
        <?php if ($success): ?>
            <div class="toast success" id="toast">✓ <?= htmlspecialchars($success) ?></div>
        <?php elseif (!empty($errors)): ?>
            <div class="toast error" id="toast">⚠ <?= htmlspecialchars($errors[0]) ?></div>
        <?php endif; ?>

        <?php if (empty($inventory)): ?>
            <!-- No inventory state -->
            <div class="section-card" style="text-align:center; padding: 60px 20px;">
                <div style="font-size:48px; margin-bottom:16px;">📦</div>
                <h2 style="font-size:18px; font-weight:700; margin-bottom:8px;">No Inventory Yet</h2>
                <p style="color:var(--text-muted); margin-bottom:20px;">You need to add items to your inventory before recording a sale.</p>
                <a href="inventory.php" style="
                    display:inline-flex; align-items:center; gap:8px;
                    background:var(--blue); color:white;
                    padding:10px 20px; border-radius:var(--radius);
                    font-size:13px; font-weight:600; text-decoration:none;
                ">→ Go to Inventory</a>
            </div>
        <?php else: ?>

        <div class="sale-grid">

            <!-- LEFT: Sale Form -->
            <div class="section-card">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Sale Details</h2>
                        <p class="section-sub">Fill in the details below to record a sale.</p>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert error" style="margin-bottom:16px;">
                        <?php foreach ($errors as $e): ?>
                            <div>⚠ <?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert success" style="margin-bottom:16px;">
                        ✓ <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="record_sales.php" id="saleForm">
                    <div style="display:flex; flex-direction:column; gap:16px;">

                        <!-- Item Select -->
                        <div class="form-group">
                            <label class="form-label">Select Item</label>
                            <select name="item_id" id="itemSelect" class="form-control" required onchange="updatePreview(this)">
                                <option value="" disabled selected>Choose an item from inventory...</option>
                                <?php foreach ($inventory as $inv): ?>
                                    <option value="<?= $inv['id'] ?>"
                                        data-name="<?= htmlspecialchars($inv['name']) ?>"
                                        data-sell="<?= $inv['selling_price'] ?>"
                                        data-cost="<?= $inv['cost_price'] ?>"
                                        data-stock="<?= $inv['quantity'] ?>"
                                        <?= $inv['quantity'] == 0 ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($inv['name']) ?>
                                        (<?= $inv['quantity'] ?> in stock)
                                        <?= $inv['quantity'] == 0 ? '— OUT OF STOCK' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Item Preview -->
                            <div class="item-preview" id="itemPreview">
                                <div class="item-preview-name" id="previewName"></div>
                                <div class="item-preview-row">
                                    <span>Selling Price</span>
                                    <span id="previewSell"></span>
                                </div>
                                <div class="item-preview-row">
                                    <span>Cost Price</span>
                                    <span id="previewCost"></span>
                                </div>
                                <div class="item-preview-row">
                                    <span>Profit per unit</span>
                                    <span id="previewMargin" style="color:var(--green)"></span>
                                </div>
                                <div class="item-preview-row">
                                    <span>Available Stock</span>
                                    <span id="previewStock"></span>
                                </div>
                                <div id="stockWarning" class="stock-warning" style="display:none">
                                    ⚠ Low stock! Only <span id="stockWarnCount"></span> units left.
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <!-- Quantity -->
                            <div class="form-group">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" id="qtyInput"
                                       class="form-control" placeholder="e.g. 2"
                                       min="1" required
                                       value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>"
                                       oninput="updateSummary()">
                            </div>

                            <!-- Customer -->
                            <div class="form-group">
                                <label class="form-label">Customer Name <span style="font-weight:400;color:var(--text-muted)">(optional)</span></label>
                                <input type="text" name="customer_name" class="form-control"
                                       placeholder="Leave blank for Walk-in"
                                       value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn-submit" id="submitBtn">
                            Record Sale →
                        </button>

                    </div>
                </form>
            </div>

            <!-- RIGHT: Summary + Recent -->
            <div style="display:flex; flex-direction:column; gap:16px;">

                <!-- Sale Summary -->
                <div class="section-card">
                    <div class="section-header" style="margin-bottom:0; padding-bottom:14px;">
                        <h2 class="section-title">Sale Summary</h2>
                    </div>
                    <div class="summary-rows">
                        <div class="summary-row">
                            <span class="summary-row-label">Item</span>
                            <span class="summary-row-value" id="sumItem">—</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-row-label">Quantity</span>
                            <span class="summary-row-value" id="sumQty">—</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-row-label">Price per unit</span>
                            <span class="summary-row-value" id="sumPrice">—</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-row-label">Total Revenue</span>
                            <span class="summary-row-value big" id="sumRevenue">—</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-row-label">Estimated Profit</span>
                            <span class="summary-row-value green" id="sumProfit">—</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales -->
                <?php
                $recent_stmt = $conn->prepare("SELECT * FROM sales WHERE user_id = ? ORDER BY sale_date DESC LIMIT 5");
                $recent_stmt->bind_param("i", $uid);
                $recent_stmt->execute();
                $recent_sales = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $recent_stmt->close();
                ?>

                <div class="section-card">
                    <div class="section-header" style="padding-bottom:14px; margin-bottom:0;">
                        <h2 class="section-title">Recent Sales</h2>
                        <a href="sales_history.php" class="view-all">View all →</a>
                    </div>

                    <?php if (empty($recent_sales)): ?>
                        <p style="font-size:13px; color:var(--text-muted); padding:16px 0;">No sales yet.</p>
                    <?php else: ?>
                        <?php foreach ($recent_sales as $rs):
                            $rs_profit = ($rs['selling_price'] - $rs['cost_price']) * $rs['quantity'];
                            $rs_time   = date('M j, g:i A', strtotime($rs['sale_date']));
                        ?>
                        <div class="recent-sale-item">
                            <div>
                                <div class="recent-item-name"><?= htmlspecialchars($rs['item_name']) ?></div>
                                <div class="recent-item-meta"><?= $rs['quantity'] ?>x · <?= $rs_time ?></div>
                            </div>
                            <span class="recent-item-profit">+Rs<?= number_format($rs_profit, 0) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <?php endif; ?>

    </main>
</div>

<!-- Item data for JS -->
<script>
// Build item map from PHP
const items = {
    <?php foreach ($inventory as $inv): ?>
    <?= $inv['id'] ?>: {
        name:  "<?= addslashes($inv['name']) ?>",
        sell:  <?= $inv['selling_price'] ?>,
        cost:  <?= $inv['cost_price'] ?>,
        stock: <?= $inv['quantity'] ?>
    },
    <?php endforeach; ?>
};

let selectedItem = null;

function updatePreview(sel) {
    const id   = sel.value;
    const item = items[id];
    if (!item) return;

    selectedItem = item;

    document.getElementById('itemPreview').classList.add('visible');
    document.getElementById('previewName').textContent   = item.name;
    document.getElementById('previewSell').textContent   = 'Rs' + item.sell.toLocaleString('en-IN');
    document.getElementById('previewCost').textContent   = 'Rs' + item.cost.toLocaleString('en-IN');
    document.getElementById('previewMargin').textContent = 'Rs' + (item.sell - item.cost).toLocaleString('en-IN');
    document.getElementById('previewStock').textContent  = item.stock + ' units';

    // Stock warning
    const warn = document.getElementById('stockWarning');
    if (item.stock <= 5) {
        warn.style.display = 'block';
        document.getElementById('stockWarnCount').textContent = item.stock;
    } else {
        warn.style.display = 'none';
    }

    // Max quantity
    document.getElementById('qtyInput').max = item.stock;

    updateSummary();
}

function updateSummary() {
    if (!selectedItem) return;
    const qty = parseInt(document.getElementById('qtyInput').value) || 0;

    document.getElementById('sumItem').textContent    = selectedItem.name;
    document.getElementById('sumQty').textContent     = qty + ' unit' + (qty !== 1 ? 's' : '');
    document.getElementById('sumPrice').textContent   = 'Rs' + selectedItem.sell.toLocaleString('en-IN');
    document.getElementById('sumRevenue').textContent = qty > 0 ? 'Rs' + (selectedItem.sell * qty).toLocaleString('en-IN') : '—';
    document.getElementById('sumProfit').textContent  = qty > 0 ? '+Rs' + ((selectedItem.sell - selectedItem.cost) * qty).toLocaleString('en-IN') : '—';

    // Disable submit if over stock
    const btn = document.getElementById('submitBtn');
    if (qty > selectedItem.stock) {
        btn.disabled = true;
        btn.textContent = 'Not enough stock';
    } else {
        btn.disabled = false;
        btn.textContent = 'Record Sale →';
    }
}

// Auto dismiss toast
const toast = document.getElementById('toast');
if (toast) setTimeout(() => toast.style.display = 'none', 4000);
</script>

</body>
</html>