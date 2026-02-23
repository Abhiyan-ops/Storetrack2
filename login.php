<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = 'admin';
            header('Location: index.php');
            exit;
        } else {
            $error = 'Incorrect email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack2 — Admin Login</title>
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
            min-height: 540px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
        }

        .login-left {
            background: linear-gradient(145deg, #0F172A 0%, #1E293B 50%, #0F2744 100%);
            padding: 60px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: rgba(37,99,235,0.08);
            top: -80px; right: -80px;
        }

        .login-left::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(37,99,235,0.05);
            bottom: -40px; left: -40px;
        }

        .brand { display: flex; align-items: center; gap: 10px; position: relative; z-index: 1; }

        .brand-icon {
            width: 36px; height: 36px;
            background: #2563EB;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }

        .brand-name { font-size: 18px; font-weight: 700; color: white; }

        .left-content {
            flex: 1;
            display: flex; flex-direction: column; justify-content: center;
            padding: 40px 0;
            position: relative; z-index: 1;
        }

        .admin-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(37,99,235,0.2);
            border: 1px solid rgba(37,99,235,0.3);
            border-radius: 100px;
            padding: 6px 14px;
            font-size: 12px; font-weight: 600;
            color: #93C5FD;
            margin-bottom: 24px;
            width: fit-content;
        }

        .left-title {
            font-size: 34px; font-weight: 800;
            color: white; line-height: 1.2;
            margin-bottom: 14px;
        }

        .left-title span { color: #60A5FA; }
        .left-sub { font-size: 14px; color: #94A3B8; line-height: 1.7; }

        .left-features {
            margin-top: 40px;
            display: flex; flex-direction: column; gap: 14px;
        }

        .feature-item {
            display: flex; align-items: center; gap: 12px;
            font-size: 13px; color: #CBD5E1;
        }

        .feature-icon {
            width: 28px; height: 28px; border-radius: 6px;
            background: rgba(37,99,235,0.2);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 13px;
        }

        .login-right {
            background: white;
            padding: 60px 48px;
            display: flex; flex-direction: column; justify-content: center;
        }

        .form-title { font-size: 26px; font-weight: 800; color: #0F172A; margin-bottom: 6px; }
        .form-sub { font-size: 13px; color: #64748B; margin-bottom: 36px; line-height: 1.5; }

        .error-box {
            background: #FEF2F2; border: 1px solid #FECACA;
            border-radius: 10px; padding: 12px 16px;
            font-size: 13px; color: #DC2626;
            margin-bottom: 24px;
        }

        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            font-size: 11px; font-weight: 700;
            color: #374151;
            text-transform: uppercase; letter-spacing: 0.07em;
            margin-bottom: 8px;
        }

        .input-wrap { position: relative; }

        .input-icon {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            pointer-events: none;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px 12px 42px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px; font-family: 'Inter', sans-serif;
            color: #0F172A; background: #F9FAFB;
            outline: none;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            -webkit-appearance: none;
        }

        input:focus {
            border-color: #2563EB;
            background: white;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
        }

        .btn-login {
            width: 100%; padding: 14px;
            background: #2563EB; color: white;
            border: none; border-radius: 10px;
            font-size: 15px; font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer; margin-top: 8px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            -webkit-appearance: none;
        }

        .btn-login:hover {
            background: #1D4ED8;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37,99,235,0.3);
        }

        .divider {
            text-align: center; margin: 28px 0 20px;
            font-size: 12px; color: #94A3B8;
            position: relative;
        }

        .divider::before, .divider::after {
            content: ''; position: absolute;
            top: 50%; width: 38%; height: 1px; background: #E5E7EB;
        }

        .divider::before { left: 0; }
        .divider::after  { right: 0; }

        .staff-link {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 13px; color: #64748B; text-decoration: none;
            padding: 10px; border: 1.5px solid #E5E7EB; border-radius: 10px;
            transition: border-color 0.15s, background 0.15s;
        }

        .staff-link:hover { border-color: #2563EB; background: #EFF6FF; color: #2563EB; }

        .signup-line { text-align: center; font-size: 13px; color: #64748B; margin-top: 20px; }
        .signup-line a { color: #2563EB; font-weight: 600; text-decoration: none; }
        .signup-line a:hover { text-decoration: underline; }

        @media (max-width: 700px) {
            .login-wrap { grid-template-columns: 1fr; width: 95%; }
            .login-left { display: none; }
        }
    </style>
</head>
<body>

<div class="login-wrap">

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
            <div class="admin-badge">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Admin Portal
            </div>
            <h1 class="left-title">Manage Your<br><span>Store Smarter</span></h1>
            <p class="left-sub">Full control over inventory, sales, employee performance and profit analytics.</p>

            <div class="left-features">
                <div class="feature-item"><div class="feature-icon">📊</div>Profit reports and analytics</div>
                <div class="feature-item"><div class="feature-icon">👥</div>Manage and track employees</div>
                <div class="feature-item"><div class="feature-icon">📦</div>Full inventory control</div>
                <div class="feature-item"><div class="feature-icon">🔔</div>Low stock alerts</div>
            </div>
        </div>
    </div>

    <div class="login-right">
        <h2 class="form-title">Admin Sign In</h2>
        <p class="form-sub">Enter your credentials to access the admin dashboard.</p>

        <?php if ($error): ?>
            <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrap">
                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <input type="email" name="email" placeholder="admin@example.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           autocomplete="email" required>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrap">
                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" name="password" placeholder="Enter your password"
                           autocomplete="current-password" required>
                </div>
            </div>

            <button type="submit" class="btn-login">
                Sign In
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>
        </form>

        <div class="divider">or</div>

        <a href="staff/login.php" class="staff-link">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            Sign in as a Staff Member instead
        </a>

        <p class="signup-line">Don't have an account? <a href="signup.php">Create one →</a></p>
    </div>

</div>

</body>
</html>