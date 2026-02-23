<?php
session_start();

// If already logged in as employee, go to staff dashboard
if (isset($_SESSION['employee_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $pin  = trim($_POST['pin']  ?? '');

    if (empty($name) || empty($pin)) {
        $error = 'Please select your name and enter your PIN.';
    } else {
        // Find employee by name only, check PIN in PHP to avoid type issues
        $stmt = $conn->prepare("SELECT * FROM employees WHERE name = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Use trim() on both to avoid whitespace/type mismatch
        if ($employee && trim($employee['pin']) === trim($pin)) {
            // PIN matched — log them in
            $_SESSION['employee_id']    = $employee['id'];
            $_SESSION['employee_name']  = $employee['name'];
            $_SESSION['owner_id']       = $employee['owner_id'];
            $_SESSION['role']           = 'employee';

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Incorrect name or PIN. Please try again.';
        }
    }
}

// Get all active employees for the dropdown
$emp_list = $conn->query("SELECT name FROM employees WHERE is_active = 1 ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack2 — Staff Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #F1F5F9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrap {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 900px;
            min-height: 520px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
        }

        /* Left panel */
        .login-left {
            background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 100%);
            padding: 60px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand { display: flex; align-items: center; gap: 10px; }

        .brand-icon {
            width: 36px; height: 36px;
            background: #2563EB;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }

        .brand-name { font-size: 18px; font-weight: 700; color: white; }

        .left-content { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 40px 0; }

        .staff-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 100px;
            padding: 6px 14px;
            font-size: 12px; font-weight: 600;
            color: #93C5FD;
            margin-bottom: 24px;
            width: fit-content;
        }

        .left-title {
            font-size: 32px; font-weight: 800;
            color: white; line-height: 1.2;
            margin-bottom: 14px;
        }

        .left-title span { color: #60A5FA; }

        .left-sub { font-size: 14px; color: #94A3B8; line-height: 1.6; }

        .left-features { margin-top: 40px; display: flex; flex-direction: column; gap: 14px; }

        .feature-item {
            display: flex; align-items: center; gap: 12px;
            font-size: 13px; color: #CBD5E1;
        }

        .feature-dot {
            width: 6px; height: 6px;
            border-radius: 50%; background: #2563EB;
            flex-shrink: 0;
        }

        /* Right panel */
        .login-right {
            background: white;
            padding: 60px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-title {
            font-size: 24px; font-weight: 800;
            color: #0F172A; margin-bottom: 6px;
        }

        .form-sub {
            font-size: 13px; color: #64748B;
            margin-bottom: 36px;
        }

        .error-box {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px; color: #DC2626;
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            font-size: 12px; font-weight: 700;
            color: #374151;
            text-transform: uppercase; letter-spacing: 0.06em;
            margin-bottom: 8px;
        }

        select, input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px; font-family: 'Inter', sans-serif;
            color: #0F172A;
            background: #F9FAFB;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
            -webkit-appearance: none;
        }

        select:focus, input[type="password"]:focus {
            border-color: #2563EB;
            background: white;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
        }

        /* PIN dots display */
        .pin-wrap { position: relative; }

        .pin-dots {
            display: flex; gap: 10px;
            margin-top: 12px;
        }

        .pin-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            background: #E5E7EB;
            transition: background 0.15s;
        }

        .pin-dot.filled { background: #2563EB; }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: #2563EB;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px; font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-login:hover {
            background: #1D4ED8;
            transform: translateY(-1px);
        }

        .divider {
            text-align: center;
            margin: 28px 0 20px;
            font-size: 12px; color: #94A3B8;
            position: relative;
        }

        .divider::before, .divider::after {
            content: '';
            position: absolute;
            top: 50%; width: 40%;
            height: 1px; background: #E5E7EB;
        }

        .divider::before { left: 0; }
        .divider::after  { right: 0; }

        .admin-link {
            display: block; text-align: center;
            font-size: 13px; color: #64748B;
            text-decoration: none;
        }

        .admin-link span { color: #2563EB; font-weight: 600; }
        .admin-link:hover span { text-decoration: underline; }

        @media (max-width: 700px) {
            .login-wrap { grid-template-columns: 1fr; width: 95%; }
            .login-left { display: none; }
        }
    </style>
</head>
<body>

<div class="login-wrap">

    <!-- LEFT -->
    <div class="login-left">
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

        <div class="left-content">
            <div class="staff-badge">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Staff Portal
            </div>
            <h1 class="left-title">Welcome,<br><span>Team Member</span></h1>
            <p class="left-sub">Sign in with your name and PIN to access the employee dashboard.</p>

            <div class="left-features">
                <div class="feature-item"><div class="feature-dot"></div>Record sales quickly</div>
                <div class="feature-item"><div class="feature-dot"></div>View inventory levels</div>
                <div class="feature-item"><div class="feature-dot"></div>Track your daily sales</div>
            </div>
        </div>
    </div>

    <!-- RIGHT -->
    <div class="login-right">
        <h2 class="form-title">Staff Sign In</h2>
        <p class="form-sub">Select your name and enter your 6-digit PIN</p>

        <?php if ($error): ?>
            <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="form-group">
                <label>Your Name</label>
                <select name="name" required>
                    <option value="" disabled selected>Select your name...</option>
                    <?php while ($emp = $emp_list->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($emp['name']) ?>"
                            <?= ($_POST['name'] ?? '') === $emp['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>PIN</label>
                <div class="pin-wrap">
                    <input type="password" name="pin" id="pinInput"
                           placeholder="Enter your 6-digit PIN"
                           maxlength="6" inputmode="numeric"
                           autocomplete="off" required>
                    <div class="pin-dots" id="pinDots">
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                        <div class="pin-dot"></div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-login">Sign In →</button>
        </form>

        <div class="divider">or</div>
        <a href="../login.php" class="admin-link">Are you an admin? <span>Sign in here →</span></a>
    </div>

</div>

<script>
// PIN dot animation
const pinInput = document.getElementById('pinInput');
const dots     = document.querySelectorAll('.pin-dot');

pinInput.addEventListener('input', () => {
    const len = pinInput.value.length;
    dots.forEach((dot, i) => {
        dot.classList.toggle('filled', i < len);
    });
});
</script>

</body>
</html>