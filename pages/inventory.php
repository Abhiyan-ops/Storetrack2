<?php
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

$success = '';
$errors  = [];
$uid     = $_SESSION['user_id'];
$uname   = $_SESSION['user_name'];

// ============================================================
//  DELETE ITEM
// ============================================================
if (isset($_GET['delete'])) {
    $id   = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
    $stmt->close();
    header('Location: inventory.php?msg=deleted');
    exit;
}

// ============================================================
//  ADD NEW ITEM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name     = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $cost     = (float)($_POST['cost_price'] ?? 0);
    $sell     = (float)($_POST['selling_price'] ?? 0);

    if (empty($name))     $errors[] = 'Item name is required.';
    if (empty($category)) $errors[] = 'Category is required.';
    if ($quantity <= 0)   $errors[] = 'Quantity must be greater than 0.';
    if ($cost <= 0)       $errors[] = 'Cost price must be greater than 0.';
    if ($sell <= 0)       $errors[] = 'Selling price must be greater than 0.';
    if ($sell < $cost)    $errors[] = 'Selling price cannot be less than cost price.';

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO inventory (name, category, cost_price, selling_price, quantity, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddii", $name, $category, $cost, $sell, $quantity, $uid);
        if ($stmt->execute()) $success = 'Item added successfully!';
        else $errors[] = 'Failed to add item. Please try again.';
        $stmt->close();
    }
}

// ============================================================
//  EDIT ITEM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id       = (int)$_POST['edit_id'];
    $name     = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $cost     = (float)($_POST['cost_price'] ?? 0);
    $sell     = (float)($_POST['selling_price'] ?? 0);

    if (empty($name))     $errors[] = 'Item name is required.';
    if (empty($category)) $errors[] = 'Category is required.';
    if ($quantity <= 0)   $errors[] = 'Quantity must be greater than 0.';
    if ($cost <= 0)       $errors[] = 'Cost price must be greater than 0.';
    if ($sell <= 0)       $errors[] = 'Selling price must be greater than 0.';
    if ($sell < $cost)    $errors[] = 'Selling price cannot be less than cost price.';

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE inventory SET name=?, category=?, cost_price=?, selling_price=?, quantity=? WHERE id=? AND user_id=?");
        $stmt->bind_param("ssddiii", $name, $category, $cost, $sell, $quantity, $id, $uid);
        if ($stmt->execute()) $success = 'Item updated successfully!';
        else $errors[] = 'Failed to update item.';
        $stmt->close();
    }
}

// ============================================================
//  SEARCH + FETCH ITEMS (only this user's)
// ============================================================
$search              = trim($_GET['search'] ?? '');
$low_stock_threshold = 5;

if ($search !== '') {
    $like = "%$search%";
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE user_id = ? AND (name LIKE ? OR category LIKE ?) ORDER BY name ASC");
    $stmt->bind_param("iss", $uid, $like, $like);
} else {
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE user_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $uid);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Initials for avatar
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($uname)))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack — Inventory</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.5.1/css/all.css">
    <style>
        .inv-form-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .inv-form-row input,
        .inv-form-row select {
            flex: 1;
            min-width: 120px;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .inv-form-row input:focus,
        .inv-form-row select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
            background: white;
        }

        .btn-add {
            padding: 10px 22px;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-add:hover { background: #1D4ED8; transform: translateY(-1px); }

        .inv-search {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            padding: 11px 16px;
            transition: border-color 0.2s;
        }

        .inv-search:focus-within { border-color: var(--blue); }

        .inv-search input {
            border: none; outline: none;
            font-size: 13px; font-family: 'Inter', sans-serif;
            color: var(--text); background: transparent; width: 100%;
        }

        .inv-search input::placeholder { color: var(--text-muted); }

        .section-add-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .add-icon-btn {
            width: 34px; height: 34px;
            background: var(--blue);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .section-add-title { font-size: 15px; font-weight: 700; color: var(--text); }

        .cat-badge {
            display: inline-block;
            background: var(--blue-light);
            color: var(--blue);
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px; font-weight: 700;
            letter-spacing: 0.04em;
        }

        .stock-badge {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 30px; height: 30px;
            border-radius: 50%;
            font-weight: 700; font-size: 12px; color: white;
        }

        .stock-ok  { background: #22C55E; }
        .stock-low { background: #EF4444; }

        .margin-badge {
            display: inline-block;
            background: var(--green-bg);
            color: var(--green);
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px; font-weight: 700;
        }

        .btn-action {
            border: none; background: transparent;
            cursor: pointer; padding: 6px 8px;
            border-radius: 6px; transition: background 0.15s;
            font-size: 15px; text-decoration: none;
            display: inline-flex; align-items: center;
        }

        .btn-edit:hover   { background: var(--blue-light); }
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

        /* Edit Modal */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 200;
            align-items: center; justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal-box {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 28px;
            width: 100%; max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            margin: 20px;
        }

        .modal-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; }

        .modal-form { display: flex; flex-direction: column; gap: 12px; }

        .modal-form input,
        .modal-form select {
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 13px; font-family: 'Inter', sans-serif;
            color: var(--text); background: var(--bg);
            outline: none; width: 100%;
            transition: border-color 0.2s;
        }

        .modal-form input:focus,
        .modal-form select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
            background: white;
        }

        .modal-row { display: flex; gap: 10px; }
        .modal-row input { flex: 1; }

        .modal-actions {
            display: flex; gap: 10px;
            justify-content: flex-end; margin-top: 6px;
        }

        .btn-cancel {
            padding: 10px 20px;
            background: var(--bg); color: var(--text-sub);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 13px; font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer; transition: background 0.15s;
        }

        .btn-cancel:hover { background: #E2E8F0; }

        /* Toast */
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
        .toast.error   { border-left: 4px solid #EF4444; }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        @media (max-width: 768px) {
            .inv-form-row { flex-direction: column; }
            .inv-form-row input,
            .inv-form-row select,
            .btn-add { width: 100%; flex: unset; }
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
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
                Inventory
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
            <a href="inventory.php" class="nav-item active">
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
                <h1 class="page-title">Inventory</h1>
                <p class="page-sub">Manage your stock, prices and quantities.</p>
            </div>
        </div>

        <!-- Toasts -->
        <?php if ($success): ?>
            <div class="toast success" id="toast">✓ <?= htmlspecialchars($success) ?></div>
        <?php elseif (!empty($errors)): ?>
            <div class="toast error" id="toast">⚠ <?= htmlspecialchars($errors[0]) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="toast success" id="toast">✓ Item deleted successfully.</div>
        <?php endif; ?>

        <!-- ADD NEW ITEM -->
        <div class="section-card">
            <div class="section-add-header">
                <div class="add-icon-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                </div>
                <span class="section-add-title">Add New Item</span>
            </div>
            <form method="POST" action="inventory.php">
                <input type="hidden" name="action" value="add">
                <div class="inv-form-row">
                    <input type="text"   name="name"          placeholder="e.g. Blue Kurta"    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    <select name="category" required>
                        <option value="" disabled selected>Category</option>
                        <?php foreach (['Kurta','Saree','Jeans','Shirt','Dupatta','Sneakers','Other'] as $cat): ?>
                            <option value="<?= $cat ?>" <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantity"      placeholder="Quantity"           value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>"      min="1" required>
                    <input type="number" name="cost_price"    placeholder="Cost Price Rs"       value="<?= htmlspecialchars($_POST['cost_price'] ?? '') ?>"     min="1" step="0.01" required>
                    <input type="number" name="selling_price" placeholder="Selling Price Rs"    value="<?= htmlspecialchars($_POST['selling_price'] ?? '') ?>"  min="1" step="0.01" required>
                    <button type="submit" class="btn-add">Add Item</button>
                </div>
            </form>
        </div>

        <!-- SEARCH -->
        <form method="GET" action="inventory.php">
            <div class="inv-search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" name="search"
                       placeholder="Search items by name or category..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
        </form>

        <!-- STOCK LIST -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Stock List</h2>
                    <p class="section-sub">
                        <?= $search ? 'Results for "' . htmlspecialchars($search) . '"' : 'All your inventory items' ?>
                    </p>
                </div>
                <span class="items-count"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <p><?= $search ? 'No items found matching your search.' : 'No items yet. Add your first item above!' ?></p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Cost (Rs)</th>
                            <th>Sell (Rs)</th>
                            <th>Margin %</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item):
                            $margin    = $item['cost_price'] > 0
                                         ? round((($item['selling_price'] - $item['cost_price']) / $item['cost_price']) * 100, 1)
                                         : 0;
                            $is_low    = $item['quantity'] <= $low_stock_threshold;
                            $stk_class = $is_low ? 'stock-low' : 'stock-ok';
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                            <td><span class="cat-badge"><?= htmlspecialchars(strtoupper($item['category'])) ?></span></td>
                            <td><span class="stock-badge <?= $stk_class ?>"><?= $item['quantity'] ?></span></td>
                            <td>Rs<?= number_format($item['cost_price'], 2) ?></td>
                            <td>Rs<?= number_format($item['selling_price'], 2) ?></td>
                            <td><span class="margin-badge">+<?= $margin ?>%</span></td>
                            <td style="display:flex;gap:4px;align-items:center;">
                                <button class="btn-action btn-edit" title="Edit"
                                    onclick="openEdit(<?= $item['id'] ?>,'<?= addslashes($item['name']) ?>','<?= addslashes($item['category']) ?>',<?= $item['quantity'] ?>,<?= $item['cost_price'] ?>,<?= $item['selling_price'] ?>)">Edit</button>
                                <a href="inventory.php?delete=<?= $item['id'] ?>"
                                   class="btn-action btn-delete"
                                   onclick="return confirm('Delete <?= addslashes($item['name']) ?>?')"
                                   title="Delete">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-title">✏️ Edit Item</div>
        <form method="POST" action="inventory.php" class="modal-form">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="text"   name="name"          id="edit_name"     placeholder="Item name" required>
            <select name="category" id="edit_category" required>
                <?php foreach (['Kurta','Saree','Jeans','Shirt','Dupatta','Sneakers','Other'] as $cat): ?>
                    <option value="<?= $cat ?>"><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
            <div class="modal-row">
                <input type="number" name="quantity"      id="edit_qty"  placeholder="Qty"              min="1" required>
                <input type="number" name="cost_price"    id="edit_cost" placeholder="Cost Price Rs"     min="1" step="0.01" required>
                <input type="number" name="selling_price" id="edit_sell" placeholder="Selling Price Rs"  min="1" step="0.01" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEdit()">Cancel</button>
                <button type="submit" class="btn-add">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, category, qty, cost, sell) {
    document.getElementById('edit_id').value       = id;
    document.getElementById('edit_name').value     = name;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_qty').value      = qty;
    document.getElementById('edit_cost').value     = cost;
    document.getElementById('edit_sell').value     = sell;
    document.getElementById('editModal').classList.add('open');
}

function closeEdit() {
    document.getElementById('editModal').classList.remove('open');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});

const toast = document.getElementById('toast');
if (toast) setTimeout(() => toast.style.display = 'none', 3000);

document.querySelector('.inv-search input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        window.location.href = 'inventory.php?search=' + encodeURIComponent(this.value);
    }
});
</script>

</body>
</html>