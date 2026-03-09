<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('avidmock_admin_session');
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path'     => '/',
        'domain'   => 'admin.sat.avidmock.com',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Already logged in — go to dashboard
if (isset($_SESSION['admin_id'], $_SESSION['admin_role'])) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, name, email, password, role
                 FROM admin_users
                 WHERE email = :email AND status = 'active'
                 LIMIT 1"
            );
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']            = $admin['id'];
                $_SESSION['admin_name']          = $admin['name'];
                $_SESSION['admin_email']         = $admin['email'];
                $_SESSION['admin_role']          = $admin['role'];
                $_SESSION['admin_last_activity'] = time();

                $redirect = $_SESSION['admin_redirect_after_login'] ?? '/index.php';
                unset($_SESSION['admin_redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: #0c1f1d;
    color: #e8f3f1;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.box {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 16px;
    padding: 40px;
    width: 100%;
    max-width: 400px;
}
.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 28px;
}
.logo-mark {
    width: 36px; height: 36px;
    border-radius: 9px;
    background: linear-gradient(135deg, #1fe290, #13c474);
    display: flex; align-items: center; justify-content: center;
}
.logo-mark svg { width: 18px; height: 18px; fill: #143230; }
.logo-name { font-size: 1rem; font-weight: 800; color: #fff; letter-spacing: -.02em; }
.logo-sub  { font-size: .6rem; color: #5a8580; text-transform: uppercase; letter-spacing: .6px; }
h1 { font-size: 1.375rem; font-weight: 800; color: #fff; letter-spacing: -.025em; margin-bottom: 6px; }
.sub { font-size: .875rem; color: #9dbfba; margin-bottom: 28px; }
.field { margin-bottom: 16px; }
label { display: block; font-size: .75rem; font-weight: 700; color: #9dbfba; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
input {
    width: 100%;
    padding: 11px 14px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 9px;
    color: #fff;
    font-family: 'DM Sans', sans-serif;
    font-size: .9375rem;
    outline: none;
    transition: border-color .18s;
}
input:focus { border-color: #1fe290; }
input::placeholder { color: #5a8580; }
.error {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.2);
    border-radius: 9px;
    padding: 10px 14px;
    font-size: .8125rem;
    color: #ef4444;
    margin-bottom: 18px;
}
.btn {
    width: 100%;
    padding: 12px;
    background: #1fe290;
    color: #143230;
    border: none;
    border-radius: 9px;
    font-family: 'DM Sans', sans-serif;
    font-size: .9375rem;
    font-weight: 800;
    cursor: pointer;
    transition: background .18s, transform .18s;
    margin-top: 4px;
}
.btn:hover { background: #13c474; transform: translateY(-1px); }
</style>
</head>
<body>
<div class="box">
    <div class="logo">
        <div class="logo-mark">
            <svg viewBox="0 0 20 20"><path d="M10 2L13 8H19L14 12.5L16 18.5L10 15L4 18.5L6 12.5L1 8H7L10 2Z"/></svg>
        </div>
        <div>
            <div class="logo-name">Avidmock SAT</div>
            <div class="logo-sub">Admin Panel</div>
        </div>
    </div>

    <h1>Welcome back</h1>
    <p class="sub">Sign in to your admin account.</p>

    <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/auth/login.php">
        <div class="field">
            <label>Email</label>
            <input type="email" name="email" placeholder="admin@avidmock.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn">Sign In</button>
    </form>
</div>
</body>
</html>