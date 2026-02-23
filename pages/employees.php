<?php
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

$uid       = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$errors    = [];
$success   = '';

if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt   = $conn->prepare("DELETE FROM employees WHERE id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $del_id, $uid);
    $stmt->execute(); $stmt->close();
    header("Location: employees.php?msg=deleted"); exit;
}

if (isset($_GET['toggle'])) {
    $tog_id = (int)$_GET['toggle'];
    $stmt   = $conn->prepare("UPDATE employees SET is_active = NOT is_active WHERE id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $tog_id, $uid);
    $stmt->execute(); $stmt->close();
    header("Location: employees.php?msg=updated"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name = trim($_POST['name'] ?? '');
    $pin  = trim($_POST['pin']  ?? '');
    if (empty($name))       $errors[] = 'Name is required.';
    if (strlen($pin) !== 6) $errors[] = 'PIN must be exactly 6 digits.';
    if (!ctype_digit($pin)) $errors[] = 'PIN must contain numbers only.';
    if (empty($errors)) {
        $chk = $conn->prepare("SELECT id FROM employees WHERE name = ? AND owner_id = ?");
        $chk->bind_param("si", $name, $uid); $chk->execute();
        if ($chk->get_result()->num_rows > 0) $errors[] = "\"$name\" already exists.";
        $chk->close();
    }
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO employees (owner_id, name, pin, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iss", $uid, $name, $pin);
        if ($stmt->execute()) $success = "Employee \"$name\" added!";
        else $errors[] = 'Failed to add employee.';
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_pin') {
    $emp_id  = (int)$_POST['emp_id'];
    $new_pin = trim($_POST['new_pin'] ?? '');
    if (strlen($new_pin) !== 6) $errors[] = 'PIN must be exactly 6 digits.';
    if (!ctype_digit($new_pin)) $errors[] = 'PIN must contain numbers only.';
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE employees SET pin = ? WHERE id = ? AND owner_id = ?");
        $stmt->bind_param("sii", $new_pin, $emp_id, $uid);
        if ($stmt->execute()) $success = 'PIN updated successfully.';
        $stmt->close();
    }
}

$emp_stmt = $conn->prepare("
    SELECT e.*, COUNT(s.id) AS total_sales,
        COALESCE(SUM(s.quantity * s.selling_price), 0) AS total_revenue,
        MAX(s.sale_date) AS last_sale
    FROM employees e
    LEFT JOIN sales s ON s.employee_id = e.id
    WHERE e.owner_id = ?
    GROUP BY e.id ORDER BY e.is_active DESC, e.name ASC
");
$emp_stmt->bind_param("i", $uid);
$emp_stmt->execute();
$employees = $emp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$emp_stmt->close();

$msg      = $_GET['msg'] ?? '';
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($user_name)))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack2 — Employees</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .emp-grid{display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start}
        .emp-list{display:flex;flex-direction:column;gap:14px}
        .emp-card{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:20px 22px;box-shadow:0 1px 4px rgba(0,0,0,.06);transition:box-shadow .15s}
        .emp-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1)}
        .emp-card.inactive{opacity:.55}
        .emp-top{display:flex;align-items:center;gap:14px;margin-bottom:16px}
        .emp-avatar{width:44px;height:44px;border-radius:50%;background:var(--blue);color:#fff;font-size:16px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .emp-avatar.inactive{background:#94A3B8}
        .emp-name{font-size:15px;font-weight:700}
        .emp-meta{font-size:12px;color:var(--text-sub);margin-top:2px}
        .status-badge{margin-left:auto;padding:4px 12px;border-radius:50px;font-size:11px;font-weight:700}
        .status-badge.active{background:#F0FDF4;color:#16A34A}
        .status-badge.inactive{background:#F1F5F9;color:#64748B}
        .emp-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
        .emp-stat{text-align:center;padding:10px 8px;background:var(--bg);border-radius:8px}
        .emp-stat-val{font-size:16px;font-weight:800}
        .emp-stat-val.green{color:#16A34A}
        .emp-stat-lbl{font-size:10px;color:var(--text-sub);font-weight:600;text-transform:uppercase;margin-top:2px}
        .emp-actions{display:flex;gap:8px;flex-wrap:wrap}
        .btn-emp{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;border:1.5px solid transparent;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:all .15s}
        .btn-emp-ghost{background:#fff;color:var(--text);border-color:var(--border)}
        .btn-emp-ghost:hover{border-color:#94A3B8}
        .btn-emp-warn{background:#FFFBEB;color:#D97706;border-color:#FDE68A}
        .btn-emp-danger{background:#FEF2F2;color:#DC2626;border-color:#FECACA}
        .btn-emp-success{background:#F0FDF4;color:#16A34A;border-color:#BBF7D0}
        .pin-form{display:none;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)}
        .pin-form.open{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
        .pin-form label{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-sub);display:block;margin-bottom:6px}
        .pin-input{padding:9px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:15px;font-family:monospace;letter-spacing:.4em;width:150px;outline:none}
        .pin-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.08)}
        .form-group{margin-bottom:16px}
        .form-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-sub);margin-bottom:7px}
        .form-control{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:border-color .2s;-webkit-appearance:none}
        .form-control:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.1);background:#fff}
        .form-hint{font-size:11px;color:var(--text-sub);margin-top:5px}
        .pin-dots{display:flex;gap:8px;margin-top:10px}
        .pin-dot{width:10px;height:10px;border-radius:50%;background:#E2E8F0;transition:background .15s}
        .pin-dot.filled{background:var(--blue)}
        .btn-add{padding:10px 22px;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;width:100%;margin-top:4px;transition:background .2s}
        .btn-add:hover{background:#1D4ED8}
        .url-hint{margin-top:16px;padding:14px;background:#EFF6FF;border-radius:8px;border:1px solid #BFDBFE}
        .url-hint-title{font-size:12px;font-weight:700;color:var(--blue);margin-bottom:4px}
        .url-hint-val{font-size:12px;color:#374151;word-break:break-all}
        .url-hint-sub{font-size:11px;color:var(--text-sub);margin-top:4px}
        .toast{position:fixed;bottom:24px;right:24px;background:#0F172A;color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.2);z-index:999;animation:slideUp .3s ease}
        .toast.success{border-left:4px solid #22C55E}
        .toast.error{border-left:4px solid #EF4444}
        .toast.info{border-left:4px solid var(--blue)}
        @keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
        .empty-state{text-align:center;padding:48px 20px;color:var(--text-sub)}
        .empty-state-icon{font-size:40px;margin-bottom:12px}
        @media(max-width:960px){.emp-grid{grid-template-columns:1fr}}
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
            <span class="breadcrumb-active">Employees</span>
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
            
            <a href="employees.php" class="nav-item active">
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
                <h1 class="page-title">Manage Employees</h1>
                <p class="page-sub">Add staff accounts, set PINs and control access.</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="toast success" id="toast">✓ <?= htmlspecialchars($success) ?></div>
        <?php elseif (!empty($errors)): ?>
            <div class="toast error" id="toast">⚠ <?= htmlspecialchars($errors[0]) ?></div>
        <?php elseif ($msg === 'deleted'): ?>
            <div class="toast info" id="toast">Employee removed.</div>
        <?php elseif ($msg === 'updated'): ?>
            <div class="toast success" id="toast">✓ Status updated.</div>
        <?php endif; ?>

        <div class="emp-grid">
            <div>
                <?php if (empty($employees)): ?>
                    <div class="section-card">
                        <div class="empty-state">
                            <div class="empty-state-icon">👥</div>
                            <p>No employees yet. Add your first staff member using the form.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="emp-list">
                        <?php foreach ($employees as $emp):
                            $active  = (bool)$emp['is_active'];
                            $initial = strtoupper($emp['name'][0]);
                            $last    = $emp['last_sale'] ? 'Last sale: '.date('M j, g:i A', strtotime($emp['last_sale'])) : 'No sales yet';
                        ?>
                        <div class="emp-card <?= $active ? '' : 'inactive' ?>">
                            <div class="emp-top">
                                <div class="emp-avatar <?= $active ? '' : 'inactive' ?>"><?= $initial ?></div>
                                <div>
                                    <div class="emp-name"><?= htmlspecialchars($emp['name']) ?></div>
                                    <div class="emp-meta"><?= $last ?></div>
                                </div>
                                <span class="status-badge <?= $active ? 'active' : 'inactive' ?>"><?= $active ? '● Active' : '○ Inactive' ?></span>
                            </div>
                            <div class="emp-stats">
                                <div class="emp-stat"><div class="emp-stat-val"><?= $emp['total_sales'] ?></div><div class="emp-stat-lbl">Sales</div></div>
                                <div class="emp-stat"><div class="emp-stat-val green">Rs<?= number_format($emp['total_revenue'],0) ?></div><div class="emp-stat-lbl">Revenue</div></div>
                                <div class="emp-stat"><div class="emp-stat-val">••••••</div><div class="emp-stat-lbl">PIN</div></div>
                            </div>
                            <div class="emp-actions">
                                <a href="employee_stats.php?emp=<?= $emp['id'] ?>" class="btn-emp btn-emp-ghost">📊 Stats</a>
                                <button class="btn-emp btn-emp-warn" onclick="togglePinForm(<?= $emp['id'] ?>)">🔑 Change PIN</button>
                                <a href="?toggle=<?= $emp['id'] ?>" class="btn-emp <?= $active ? 'btn-emp-ghost' : 'btn-emp-success' ?>"
                                   onclick="return confirm('<?= $active ? 'Deactivate' : 'Activate' ?> <?= htmlspecialchars($emp['name']) ?>?')">
                                    <?= $active ? '⏸ Deactivate' : '▶ Activate' ?>
                                </a>
                                <a href="?delete=<?= $emp['id'] ?>" class="btn-emp btn-emp-danger"
                                   onclick="return confirm('Delete <?= htmlspecialchars($emp['name']) ?>?')">🗑 Delete</a>
                            </div>
                            <div class="pin-form" id="pinForm<?= $emp['id'] ?>">
                                <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                                    <input type="hidden" name="action" value="update_pin">
                                    <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                    <div>
                                        <label>New 6-digit PIN</label>
                                        <input type="password" name="new_pin" class="pin-input" maxlength="6" inputmode="numeric" placeholder="••••••" required>
                                    </div>
                                    <button type="submit" class="btn-emp btn-emp-ghost" style="background:var(--blue);color:#fff;border-color:var(--blue);">Save</button>
                                    <button type="button" class="btn-emp btn-emp-ghost" onclick="togglePinForm(<?= $emp['id'] ?>)">Cancel</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <div class="section-header" style="margin-bottom:20px;">
                    <div><h2 class="section-title">Add New Employee</h2><p class="section-sub">They log in with name + PIN</p></div>
                </div>
                <form method="POST" action="employees.php">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Ram Thapa"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">6-Digit PIN</label>
                        <input type="password" name="pin" id="pinInput" class="form-control" placeholder="••••••"
                               maxlength="6" inputmode="numeric" autocomplete="new-password" required oninput="updateDots()">
                        <div class="pin-dots" id="pinDots">
                            <div class="pin-dot"></div><div class="pin-dot"></div><div class="pin-dot"></div>
                            <div class="pin-dot"></div><div class="pin-dot"></div><div class="pin-dot"></div>
                        </div>
                        <p class="form-hint">Employee uses this PIN to sign into the staff portal</p>
                    </div>
                    <button type="submit" class="btn-add">+ Add Employee</button>
                </form>
                <div class="url-hint">
                    <div class="url-hint-title">Staff Login URL</div>
                    <div class="url-hint-val">localhost/StoreTrack2/staff/login.php</div>
                    <div class="url-hint-sub">Share this link with your employees</div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function togglePinForm(id){document.getElementById('pinForm'+id).classList.toggle('open')}
function updateDots(){const v=document.getElementById('pinInput').value;document.querySelectorAll('.pin-dot').forEach((d,i)=>d.classList.toggle('filled',i<v.length))}
const toast=document.getElementById('toast');
if(toast)setTimeout(()=>toast.style.display='none',3000);
</script>
</body>
</html>