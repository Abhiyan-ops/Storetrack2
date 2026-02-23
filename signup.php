<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once 'includes/db.php';

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($name))
        $errors[] = 'Full name is required.';
    elseif (strlen($name) < 2)
        $errors[] = 'Name must be at least 2 characters.';
    elseif (!preg_match('/^[a-zA-Z\s]+$/', $name))
        $errors[] = 'Name can only contain letters and spaces.';

    if (empty($email))
        $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0)
            $errors[] = 'This email is already registered. Please log in.';
        $stmt->close();
    }

    if (empty($password))
        $errors[] = 'Password is required.';
    elseif (strlen($password) < 6)
        $errors[] = 'Password must be at least 6 characters.';
    else {
        $blocked = ['123456','123456789','password','password123','qwerty','abc123','111111'];
        if (in_array(strtolower($password), $blocked))
            $errors[] = 'This password is too common. Please choose a stronger one.';
        if (!empty($name) && stripos($password, $name) !== false)
            $errors[] = 'Password should not contain your name.';
        if (ctype_digit($password))
            $errors[] = 'Password cannot be all numbers.';
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashed);
        if ($stmt->execute()) { header('Location: login.php?registered=1'); exit; }
        else $errors[] = 'Something went wrong. Please try again.';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreTrack — Create Account</title>
    <link rel="stylesheet" href="assets/css/signup.css">
</head>
<body>

<div class="auth-wrapper">

    <!-- LEFT: Form -->
    <div class="auth-left">

        <div class="auth-brand">
            <div class="brand-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <rect x="3" y="3" width="7" height="7" rx="1.5" fill="white"/>
                    <rect x="14" y="3" width="7" height="7" rx="1.5" fill="white" opacity="0.7"/>
                    <rect x="3" y="14" width="7" height="7" rx="1.5" fill="white" opacity="0.7"/>
                    <rect x="14" y="14" width="7" height="7" rx="1.5" fill="white" opacity="0.4"/>
                </svg>
            </div>
            <span class="brand-name">StoreTrack</span>
        </div>

        <div class="auth-heading">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Start managing your store in minutes.</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <?php foreach ($errors as $e): ?>
                <div class="error-item">⚠ <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form class="auth-form" method="POST" id="signupForm">

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" id="nameInput"
                       class="form-input <?= !empty($errors) && empty($name) ? 'input-error' : '' ?>"
                       placeholder="John Doe"
                       value="<?= htmlspecialchars($name) ?>" required>
                <span class="field-hint" id="nameHint"></span>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" id="emailInput"
                       class="form-input"
                       placeholder="name@company.com"
                       value="<?= htmlspecialchars($email) ?>" required>
                <span class="field-hint" id="emailHint"></span>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Password <span class="label-hint">(min 6 characters)</span>
                </label>
                <div class="password-group">
                    <input type="password" name="password" id="passwordInput"
                           class="form-input" placeholder="Create a strong password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">Show</button>
                </div>

                <!-- Strength Bar -->
                <div class="strength-wrap" id="strengthWrap" style="display:none">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <span class="strength-label" id="strengthLabel"></span>
                </div>

                <!-- Checklist -->
                <ul class="strength-checklist" id="checklist" style="display:none">
                    <li id="check-length">✗ At least 6 characters</li>
                    <li id="check-letter">✗ Contains a letter</li>
                    <li id="check-number">✗ Contains a number</li>
                    <li id="check-special">✗ Contains a special character</li>
                </ul>
            </div>

            <button type="submit" class="auth-btn">Create Account →</button>
        </form>

        <div class="form-divider">or continue with</div>

        <div class="social-buttons">
            <button type="button" class="btn-social">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Google
            </button>
            <button type="button" class="btn-social">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff">
                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                </svg>
                GitHub
            </button>
        </div>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Sign in</a>
        </div>

    </div>

    <!-- RIGHT: Preview Panel -->
    <div class="auth-right">
        <div class="preview-card">
            <div style="font-size:32px;margin-bottom:12px">🏪</div>
            <div class="preview-title">Built for Store Owners</div>
            <p class="preview-sub">Track stock, record sales and see your profits grow — all from one simple dashboard.</p>
        </div>

        <div class="preview-features">
            <div class="preview-feature">
                <div class="preview-feature-icon">📦</div>
                <div>
                    <strong>Smart Inventory</strong>
                    Auto low-stock alerts
                </div>
            </div>
            <div class="preview-feature">
                <div class="preview-feature-icon">💰</div>
                <div>
                    <strong>Profit Tracking</strong>
                    Know your margins instantly
                </div>
            </div>
            <div class="preview-feature">
                <div class="preview-feature-icon">📊</div>
                <div>
                    <strong>Sales Reports</strong>
                    Full history & analytics
                </div>
            </div>
        </div>

        <div class="preview-dots">
            <div class="dot active"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('passwordInput');
    const btn   = document.querySelector('.password-toggle');
    input.type  = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? 'Show' : 'Hide';
}

document.getElementById('passwordInput').addEventListener('input', function () {
    const val  = this.value;
    const wrap = document.getElementById('strengthWrap');
    const fill = document.getElementById('strengthFill');
    const lbl  = document.getElementById('strengthLabel');
    const list = document.getElementById('checklist');

    if (!val.length) { wrap.style.display = 'none'; list.style.display = 'none'; return; }

    wrap.style.display = 'flex';
    list.style.display = 'block';

    const hasLength  = val.length >= 6;
    const hasLetter  = /[a-zA-Z]/.test(val);
    const hasNumber  = /[0-9]/.test(val);
    const hasSpecial = /[^a-zA-Z0-9]/.test(val);

    setCheck('check-length',  hasLength,  '✓ At least 6 characters',        '✗ At least 6 characters');
    setCheck('check-letter',  hasLetter,  '✓ Contains a letter',            '✗ Contains a letter');
    setCheck('check-number',  hasNumber,  '✓ Contains a number',            '✗ Contains a number');
    setCheck('check-special', hasSpecial, '✓ Contains a special character', '✗ Contains a special character');

    const score  = [hasLength, hasLetter, hasNumber, hasSpecial].filter(Boolean).length;
    const levels = [
        { label: '',          color: '#3D3D3D', width: '0%'   },
        { label: 'Weak',      color: '#EF4444', width: '25%'  },
        { label: 'Fair',      color: '#F97316', width: '50%'  },
        { label: 'Good',      color: '#EAB308', width: '75%'  },
        { label: 'Strong 💪', color: '#22C55E', width: '100%' },
    ];
    fill.style.width      = levels[score].width;
    fill.style.background = levels[score].color;
    lbl.textContent       = levels[score].label;
    lbl.style.color       = levels[score].color;
});

function setCheck(id, passed, yes, no) {
    const el = document.getElementById(id);
    el.textContent = passed ? yes : no;
    el.style.color = passed ? '#4ADE80' : '#555';
}

document.getElementById('emailInput').addEventListener('blur', function () {
    const hint  = document.getElementById('emailHint');
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
    hint.textContent = this.value && !valid ? '⚠ Invalid email format' : '';
    hint.style.color = '#F87171';
});

document.getElementById('nameInput').addEventListener('blur', function () {
    const hint = document.getElementById('nameHint');
    hint.textContent = this.value && this.value.trim().length < 2 ? '⚠ Name too short' : '';
    hint.style.color = '#F87171';
});
</script>

</body>
</html>