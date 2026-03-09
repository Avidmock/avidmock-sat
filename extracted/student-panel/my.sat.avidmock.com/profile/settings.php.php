<?php
/**
 * profile/settings/index.php — Student Settings Page
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';

Auth::requireStudent();
$userId = $_SESSION['user_id'];
$user   = User::findById($userId);

$firstName = trim($user['first_name'] ?? 'Student');
$lastName  = trim($user['last_name']  ?? '');
$fullName  = trim($firstName . ' ' . $lastName) ?: 'Student';

$success = $_SESSION['settings_success'] ?? null;
$error   = $_SESSION['settings_error']   ?? null;
unset($_SESSION['settings_success'], $_SESSION['settings_error']);

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $newFirst = trim($_POST['first_name'] ?? '');
        $newLast  = trim($_POST['last_name']  ?? '');
        $email    = trim($_POST['email']      ?? '');
        if (strlen($newFirst) < 2) {
            $_SESSION['settings_error'] = 'First name must be at least 2 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['settings_error'] = 'Please enter a valid email address.';
        } else {
            User::update($userId, [
                'first_name' => $newFirst,
                'last_name'  => $newLast,
                'email'      => $email,
            ]);
            $_SESSION['settings_success'] = 'Profile updated successfully.';
        }
        header('Location: /profile/settings/'); exit;
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password_hash'] ?? '')) {
            $_SESSION['settings_error'] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $_SESSION['settings_error'] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $_SESSION['settings_error'] = 'New passwords do not match.';
        } else {
            User::update($userId, ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
            $_SESSION['settings_success'] = 'Password changed successfully.';
        }
        header('Location: /profile/settings/'); exit;
    }

    if ($action === 'delete_account') {
        if (($_POST['delete_confirm'] ?? '') === 'DELETE') {
            $_SESSION['settings_error'] = 'Account deletion is disabled. Contact support.';
        } else {
            $_SESSION['settings_error'] = 'Type DELETE exactly to confirm account deletion.';
        }
        header('Location: /profile/settings/'); exit;
    }
}

$activePage  = 'profile';
$topbarTitle = 'Settings';
$topbarSub   = 'Profile › Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16"   href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32"   href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180"      href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════
   TOKENS
═══════════════════════════════════════════════ */
:root {
    --dk:  #143230;
    --dk2: #1a3f3c;
    --dk3: #0e2624;
    --ac:  #1fe290;
    --ac2: #17c87a;
    --tx:  #1a1a2e;
    --tx2: #4a4a5a;
    --tx3: #8a8a9a;
    --bg:  #f4f8f7;
    --bg2: #ffffff;
    --bd:  #e2ebe9;
    --bd2: #d0dbd8;
    --err:  #ef4444;
    --warn: #f59e0b;
    --ff: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    --sidebar-w: 260px;
    --topbar-h:  64px;
    --radius:    14px;
    --radius-lg: 20px;
    --shadow-sm: 0 1px 4px rgba(20,50,48,.06), 0 4px 16px rgba(20,50,48,.04);
    --shadow-md: 0 4px 16px rgba(20,50,48,.09), 0 12px 40px rgba(20,50,48,.06);
    --pad:    36px;
    --pad-sm: 18px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--ff);
    background: var(--bg);
    color: var(--tx);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    overflow-x: hidden;
    min-height: 100vh;
}
a    { text-decoration: none; color: inherit; }
button { font-family: var(--ff); cursor: pointer; border: none; background: none; }

/* ═══════════════════════════════════════════════
   SCROLL REVEAL
═══════════════════════════════════════════════ */
.sr { opacity: 0; transform: translateY(18px); transition: opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1); }
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .05s; }
.d2 { transition-delay: .11s; }
.d3 { transition-delay: .17s; }

/* ═══════════════════════════════════════════════
   LAYOUT SHELL
═══════════════════════════════════════════════ */
.main-content {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 36px var(--pad) 96px;
    display: grid;
    grid-template-columns: 248px 1fr;
    gap: 24px;
    align-items: start;
    max-width: calc(1080px + var(--sidebar-w));
}
.sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 250;
    opacity: 0;
    transition: opacity .28s;
    pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; }

/* ═══════════════════════════════════════════════
   STICKY LEFT NAVIGATION
═══════════════════════════════════════════════ */
.settings-nav-col {
    position: sticky;
    top: calc(var(--topbar-h) + 20px);
}

/* User identity mini-card atop nav */
.nav-identity {
    background: var(--dk);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.125rem;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
}
.nav-identity::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.03) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}
.nav-avatar {
    width: 44px; height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--ac), #0da367);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--dk);
    flex-shrink: 0;
    position: relative; z-index: 1;
    overflow: hidden;
}
.nav-avatar img { width: 100%; height: 100%; object-fit: cover; }
.nav-id-text { position: relative; z-index: 1; min-width: 0; }
.nav-id-name {
    font-size: .875rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.015em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.nav-id-sub {
    font-size: .625rem;
    color: rgba(255,255,255,.3);
    font-weight: 600;
    margin-top: 1px;
}

/* Nav list */
.settings-nav {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.sn-section-label {
    padding: 10px 16px 6px;
    font-size: .5rem;
    font-weight: 800;
    color: var(--tx3);
    text-transform: uppercase;
    letter-spacing: .9px;
}
.sn-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 16px;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background .15s, color .15s, border-color .15s;
    font-size: .875rem;
    font-weight: 600;
    color: var(--tx2);
    border-bottom: 1px solid var(--bd);
    text-decoration: none;
}
.sn-item:last-child { border-bottom: none; }
.sn-item:hover { background: var(--bg); color: var(--dk); }
.sn-item.active {
    background: rgba(31,226,144,.06);
    border-left-color: var(--ac);
    color: var(--dk);
    font-weight: 700;
}
.sn-item svg {
    width: 16px; height: 16px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
    opacity: .65;
    transition: opacity .15s;
}
.sn-item:hover svg,
.sn-item.active svg { opacity: 1; }
.sn-item.danger { color: var(--err); }
.sn-item.danger:hover  { background: rgba(239,68,68,.04); border-left-color: rgba(239,68,68,.4); }
.sn-item.danger.active { background: rgba(239,68,68,.05); border-left-color: var(--err); }

/* Divider inside nav */
.sn-divider { height: 1px; background: var(--bd); margin: 4px 0; }

/* Back link */
.btn-back {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    margin-top: 10px;
    padding: 10px 14px;
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    border-radius: var(--radius);
    font-family: var(--ff);
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx2);
    text-decoration: none;
    transition: border-color .2s, color .2s, background .2s, transform .2s;
    box-shadow: var(--shadow-sm);
}
.btn-back:hover {
    border-color: var(--ac);
    color: var(--dk);
    background: rgba(31,226,144,.05);
    transform: translateY(-1px);
}
.btn-back svg {
    width: 14px; height: 14px;
    stroke: currentColor; fill: none;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
}

/* ═══════════════════════════════════════════════
   ALERTS
═══════════════════════════════════════════════ */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    padding: 14px 16px;
    border-radius: var(--radius);
    margin-bottom: 20px;
    animation: alertIn .38s cubic-bezier(.16,1,.3,1);
}
@keyframes alertIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: none; } }
.alert-success { background: rgba(31,226,144,.07);  border: 1px solid rgba(31,226,144,.22); }
.alert-error   { background: rgba(239,68,68,.06);   border: 1px solid rgba(239,68,68,.18); }

.alert-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.alert-success .alert-icon { background: rgba(31,226,144,.15); }
.alert-error   .alert-icon { background: rgba(239,68,68,.1); }
.alert-icon svg {
    width: 17px; height: 17px;
    fill: none; stroke-width: 2.2;
    stroke-linecap: round; stroke-linejoin: round;
}
.alert-success .alert-icon svg { stroke: var(--ac2); }
.alert-error   .alert-icon svg { stroke: var(--err); }

.alert-title { font-size: .875rem; font-weight: 800; }
.alert-success .alert-title { color: #0b6b40; }
.alert-error   .alert-title { color: #b91c1c; }
.alert-sub { font-size: .8125rem; color: var(--tx3); margin-top: 2px; line-height: 1.5; }

/* ═══════════════════════════════════════════════
   SECTION PANELS
═══════════════════════════════════════════════ */
.section-panel { display: none; }
.section-panel.active {
    display: block;
    animation: panelIn .32s cubic-bezier(.16,1,.3,1);
}
@keyframes panelIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

/* Section header (before the first card) */
.section-eyebrow {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}
.section-eyebrow-ico {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: rgba(20,50,48,.07);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.section-eyebrow-ico svg {
    width: 15px; height: 15px;
    stroke: var(--dk); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
.section-eyebrow-title { font-size: 1.0625rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.section-eyebrow-sub   { font-size: .75rem; color: var(--tx3); margin-top: 1px; }

/* ═══════════════════════════════════════════════
   CARDS
═══════════════════════════════════════════════ */
.card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 16px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow .25s, border-color .2s;
}
.card:last-child { margin-bottom: 0; }
.card:hover { box-shadow: var(--shadow-md); }

.card-hd {
    padding: 1.125rem 1.5rem;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    gap: 13px;
    background: rgba(20,50,48,.018);
}
.card-hd-ico {
    width: 40px; height: 40px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.card-hd-ico svg {
    width: 18px; height: 18px;
    fill: none; stroke-width: 1.9;
    stroke-linecap: round; stroke-linejoin: round;
}
.ico-ac  { background: rgba(31,226,144,.1); }  .ico-ac  svg { stroke: var(--ac2); }
.ico-dk  { background: rgba(20,50,48,.08); }   .ico-dk  svg { stroke: var(--dk); }
.ico-err { background: rgba(239,68,68,.09); }  .ico-err svg { stroke: var(--err); }

.card-hd-title { font-size: .9375rem; font-weight: 800; color: var(--tx); letter-spacing: -.015em; }
.card-hd-sub   { font-size: .75rem; color: var(--tx3); margin-top: 2px; }

.card-body { padding: 1.5rem; }

/* ═══════════════════════════════════════════════
   FORM ELEMENTS
═══════════════════════════════════════════════ */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
.form-row.one-col  { grid-template-columns: 1fr; }
.form-row:last-of-type { margin-bottom: 0; }

.form-group { display: flex; flex-direction: column; gap: 6px; }

.form-label {
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx);
    display: flex;
    align-items: center;
    gap: 5px;
}
.form-label-req {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: var(--ac2);
    flex-shrink: 0;
}

.form-input {
    width: 100%;
    padding: 11px 14px;
    background: var(--bg2);
    border: 1.5px solid var(--bd2);
    border-radius: var(--radius);
    font-family: var(--ff);
    font-size: .9375rem;
    color: var(--tx);
    outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
    -webkit-appearance: none;
}
.form-input:hover  { border-color: var(--bd2); background: #fdffff; }
.form-input:focus  { border-color: var(--ac); box-shadow: 0 0 0 3.5px rgba(31,226,144,.12); background: #fff; }
.form-input::placeholder { color: var(--tx3); }
.form-input.has-error { border-color: var(--err); box-shadow: 0 0 0 3.5px rgba(239,68,68,.1); }

.form-hint { font-size: .75rem; color: var(--tx3); line-height: 1.55; }

/* Footer bar inside card-body */
.form-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-top: 1.25rem;
    border-top: 1px solid var(--bd);
    margin-top: 1.25rem;
    flex-wrap: wrap;
}
.form-footer-note { font-size: .75rem; color: var(--tx3); line-height: 1.5; }

/* ═══════════════════════════════════════════════
   PASSWORD STRENGTH METER
═══════════════════════════════════════════════ */
.pw-meter { margin-top: 7px; }
.pw-track {
    height: 4px;
    background: var(--bd);
    border-radius: 2px;
    overflow: hidden;
}
.pw-fill {
    height: 100%;
    border-radius: 2px;
    width: 0;
    transition: width .4s cubic-bezier(.16,1,.3,1), background .3s;
}
.pw-label {
    font-size: .6875rem;
    font-weight: 700;
    color: var(--tx3);
    margin-top: 4px;
    transition: color .3s;
}

/* ═══════════════════════════════════════════════
   BUTTONS
═══════════════════════════════════════════════ */
.btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 24px;
    background: var(--dk);
    color: #fff;
    font-family: var(--ff);
    font-size: .9375rem;
    font-weight: 800;
    border-radius: var(--radius);
    border: none;
    cursor: pointer;
    transition: background .22s, transform .22s cubic-bezier(.16,1,.3,1), box-shadow .22s;
    white-space: nowrap;
    min-height: 44px;
}
.btn-primary:hover {
    background: var(--dk2);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(20,50,48,.18);
}
.btn-primary:active { transform: none; box-shadow: none; }
.btn-primary svg {
    width: 15px; height: 15px;
    stroke: var(--ac); fill: none;
    stroke-width: 2.1; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.btn-primary.danger { background: var(--err); }
.btn-primary.danger svg { stroke: #fff; }
.btn-primary.danger:hover { background: #dc2626; box-shadow: 0 6px 22px rgba(239,68,68,.3); }

.btn-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 11px 22px;
    background: transparent;
    color: var(--tx2);
    font-family: var(--ff);
    font-size: .9375rem;
    font-weight: 700;
    border-radius: var(--radius);
    border: 1.5px solid var(--bd);
    cursor: pointer;
    transition: border-color .2s, background .2s, color .2s;
    min-height: 44px;
}
.btn-ghost:hover { border-color: var(--bd2); background: var(--bg); color: var(--tx); }

/* ═══════════════════════════════════════════════
   DANGER ZONE
═══════════════════════════════════════════════ */
.danger-card {
    background: var(--bg2);
    border: 1px solid rgba(239,68,68,.18);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.danger-card .card-hd {
    background: rgba(239,68,68,.025);
    border-bottom-color: rgba(239,68,68,.1);
}
.dz-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(239,68,68,.07);
    flex-wrap: wrap;
    transition: background .15s;
}
.dz-item:last-child { border-bottom: none; }
.dz-item:hover { background: rgba(239,68,68,.02); }

.dz-info { flex: 1; min-width: 180px; }
.dz-title {
    font-size: .9375rem;
    font-weight: 800;
    color: var(--tx);
    margin-bottom: 4px;
    letter-spacing: -.01em;
}
.dz-desc {
    font-size: .8125rem;
    color: var(--tx3);
    line-height: 1.55;
}

.btn-danger-outline {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 16px;
    border: 1.5px solid rgba(239,68,68,.3);
    background: transparent;
    color: var(--err);
    font-family: var(--ff);
    font-size: .8125rem;
    font-weight: 700;
    border-radius: var(--radius);
    cursor: pointer;
    transition: background .18s, border-color .18s, transform .18s;
    flex-shrink: 0;
    white-space: nowrap;
    min-height: 40px;
}
.btn-danger-outline:hover {
    background: rgba(239,68,68,.07);
    border-color: var(--err);
    transform: translateY(-1px);
}
.btn-danger-outline svg {
    width: 14px; height: 14px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

/* ═══════════════════════════════════════════════
   MODAL
═══════════════════════════════════════════════ */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(10,22,20,.72);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    pointer-events: none;
    transition: opacity .3s;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }

.modal-box {
    background: var(--bg2);
    border-radius: 22px;
    width: 100%;
    max-width: 400px;
    max-height: calc(100dvh - 40px);
    overflow-y: auto;
    scrollbar-width: none;
    padding: 2rem;
    transform: translateY(20px) scale(.97);
    transition: transform .35s cubic-bezier(.16,1,.3,1);
    box-shadow: 0 24px 64px rgba(0,0,0,.22), 0 4px 16px rgba(0,0,0,.12);
    position: relative;
}
.modal-box::-webkit-scrollbar { display: none; }
.modal-overlay.open .modal-box { transform: none; }

.modal-close {
    position: absolute;
    top: 14px; right: 14px;
    width: 30px; height: 30px;
    border-radius: 50%;
    border: 1.5px solid var(--bd);
    background: var(--bg);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.modal-close:hover { border-color: var(--err); background: rgba(239,68,68,.06); }
.modal-close svg {
    width: 12px; height: 12px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

.modal-ico {
    width: 52px; height: 52px;
    border-radius: 15px;
    background: rgba(239,68,68,.08);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 1.125rem;
}
.modal-ico svg {
    width: 24px; height: 24px;
    stroke: var(--err); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
.modal-title {
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.025em;
    margin-bottom: .5rem;
}
.modal-desc {
    font-size: .9375rem;
    color: var(--tx3);
    line-height: 1.65;
    margin-bottom: 1.5rem;
}
.modal-desc strong { color: var(--tx); font-weight: 700; }
.modal-actions { display: flex; flex-direction: column; gap: 10px; }

/* ═══════════════════════════════════════════════
   RESPONSIVE
   ─────────────────────────────────────────────
   ≥ 1000  : Two-column layout, sticky left nav
   ≤ 1000  : Remove sidebar margin (mobile nav)
   ≤  900  : Single column stacked
   ≤  640  : Form rows → 1-col, tighter spacing
   ≤  480  : Tightest — modals full screen bottom
═══════════════════════════════════════════════ */
@media (max-width: 1000px) {
    .main-content { margin-left: 0; }
}

@media (max-width: 900px) {
    .main-content {
        grid-template-columns: 1fr;
        padding: 22px var(--pad-sm) 80px;
        gap: 16px;
    }
    .settings-nav-col { position: static; }

    /* On mobile, show nav as horizontal scroll strip */
    .settings-nav {
        display: flex;
        overflow-x: auto;
        scrollbar-width: none;
        border-radius: var(--radius);
        -webkit-overflow-scrolling: touch;
    }
    .settings-nav::-webkit-scrollbar { display: none; }
    .sn-section-label { display: none; }
    .sn-item {
        flex-shrink: 0;
        border-bottom: none;
        border-left: none;
        border-bottom: 3px solid transparent;
        padding: 12px 18px;
        border-radius: 0;
        white-space: nowrap;
    }
    .sn-item.active {
        border-bottom-color: var(--ac);
        border-left-color: transparent;
        background: rgba(31,226,144,.05);
    }
    .sn-item.danger.active { border-bottom-color: var(--err); }
    .sn-divider { display: none; }

    .nav-identity { padding: 1rem; }
    .nav-id-name { font-size: .8125rem; }
}

@media (max-width: 640px) {
    .form-row { grid-template-columns: 1fr; }
    .dz-item  { flex-direction: column; align-items: flex-start; gap: 14px; }
    .form-footer { flex-direction: column; align-items: stretch; gap: 10px; }
    .form-footer .btn-primary { width: 100%; }
    .card-body { padding: 1.125rem; }
    .card-hd   { padding: 1rem 1.125rem; }
}

@media (max-width: 480px) {
    /* Bottom-sheet style modals */
    .modal-overlay { align-items: flex-end; padding: 0; }
    .modal-box {
        border-radius: 22px 22px 0 0;
        max-width: 100%;
        max-height: 90dvh;
        padding: 1.75rem 1.25rem 2rem;
    }
    .modal-close { top: 12px; right: 12px; }

    .main-content { padding: 16px var(--pad-sm) 80px; }
    .nav-identity { padding: .875rem 1rem; }
    .btn-back { padding: 9px 12px; font-size: .75rem; }
}

/* ── Safe area insets (notched phones) ── */
@supports (padding: max(0px)) {
    .main-content {
        padding-left:   max(var(--pad-sm), env(safe-area-inset-left));
        padding-right:  max(var(--pad-sm), env(safe-area-inset-right));
        padding-bottom: max(96px, calc(96px + env(safe-area-inset-bottom)));
    }
    @media (min-width: 901px) {
        .main-content {
            padding-left:  max(var(--pad), env(safe-area-inset-left));
            padding-right: max(var(--pad), env(safe-area-inset-right));
        }
    }
    @media (max-width: 480px) {
        .modal-box {
            padding-bottom: max(2rem, calc(2rem + env(safe-area-inset-bottom)));
        }
    }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main-content" id="mainContent">

    <!-- ══════════════════════════════════════
         LEFT NAV COLUMN
    ══════════════════════════════════════ -->
    <div class="settings-nav-col sr d1">

        <!-- Mini identity card -->
        <div class="nav-identity">
            <div class="nav-avatar">
                <?php if (!empty($user['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="<?= htmlspecialchars($fullName) ?>">
                <?php else: ?>
                    <?= strtoupper(substr($firstName, 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="nav-id-text">
                <div class="nav-id-name"><?= htmlspecialchars($fullName) ?></div>
                <div class="nav-id-sub"><?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
        </div>

        <!-- Nav list -->
        <nav class="settings-nav" role="tablist" aria-label="Settings sections">
            <div class="sn-section-label">Account</div>
            <a class="sn-item active"
               href="#profile"
               onclick="switchSection('profile', this)"
               role="tab" aria-selected="true" aria-controls="section-profile">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Profile
            </a>
            <a class="sn-item"
               href="#password"
               onclick="switchSection('password', this)"
               role="tab" aria-selected="false" aria-controls="section-password">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Password
            </a>
            <div class="sn-divider"></div>
            <a class="sn-item danger"
               href="#danger"
               onclick="switchSection('danger', this)"
               role="tab" aria-selected="false" aria-controls="section-danger">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Danger Zone
            </a>
        </nav>

        <a href="/profile/" class="btn-back">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Profile
        </a>
    </div>

    <!-- ══════════════════════════════════════
         RIGHT CONTENT COLUMN
    ══════════════════════════════════════ -->
    <div class="sr d2">

        <!-- ── Alerts ── -->
        <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <div class="alert-icon">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <div class="alert-title">Changes saved</div>
                <div class="alert-sub"><?= htmlspecialchars($success) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error" role="alert">
            <div class="alert-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
                <div class="alert-title">Something went wrong</div>
                <div class="alert-sub"><?= htmlspecialchars($error) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════
             PROFILE SECTION
        ══════════════════════════════════ -->
        <div id="section-profile" class="section-panel active" role="tabpanel">
            <div class="section-eyebrow">
                <div class="section-eyebrow-ico">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div>
                    <div class="section-eyebrow-title">Profile Information</div>
                    <div class="section-eyebrow-sub">Update your name and email address</div>
                </div>
            </div>

            <form method="POST" novalidate>
                <input type="hidden" name="action" value="profile">
                <div class="card">
                    <div class="card-hd">
                        <div class="card-hd-ico ico-ac">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div>
                            <div class="card-hd-title">Basic Details</div>
                            <div class="card-hd-sub">Your public name and contact email</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="first_name">
                                    First Name
                                    <span class="form-label-req" aria-hidden="true"></span>
                                </label>
                                <input type="text"
                                       id="first_name"
                                       name="first_name"
                                       class="form-input"
                                       value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                                       placeholder="First name"
                                       required
                                       autocomplete="given-name">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="last_name">Last Name</label>
                                <input type="text"
                                       id="last_name"
                                       name="last_name"
                                       class="form-input"
                                       value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                                       placeholder="Last name"
                                       autocomplete="family-name">
                            </div>
                        </div>
                        <div class="form-row one-col">
                            <div class="form-group">
                                <label class="form-label" for="email">
                                    Email Address
                                    <span class="form-label-req" aria-hidden="true"></span>
                                </label>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       class="form-input"
                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                       placeholder="you@example.com"
                                       required
                                       autocomplete="email">
                                <span class="form-hint">Email changes may require re-verification.</span>
                            </div>
                        </div>
                        <div class="form-footer">
                            <span class="form-footer-note">Only you can see your email address.</span>
                            <button type="submit" class="btn-primary">
                                <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div><!-- /profile -->

        <!-- ══════════════════════════════════
             PASSWORD SECTION
        ══════════════════════════════════ -->
        <div id="section-password" class="section-panel" role="tabpanel">
            <div class="section-eyebrow">
                <div class="section-eyebrow-ico">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                </div>
                <div>
                    <div class="section-eyebrow-title">Change Password</div>
                    <div class="section-eyebrow-sub">Use at least 8 characters — mix letters, numbers and symbols</div>
                </div>
            </div>

            <form method="POST" novalidate>
                <input type="hidden" name="action" value="password">
                <div class="card">
                    <div class="card-hd">
                        <div class="card-hd-ico ico-dk">
                            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        </div>
                        <div>
                            <div class="card-hd-title">Update Password</div>
                            <div class="card-hd-sub">Confirm your current password first</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row one-col" style="margin-bottom:20px">
                            <div class="form-group">
                                <label class="form-label" for="current_password">
                                    Current Password
                                    <span class="form-label-req" aria-hidden="true"></span>
                                </label>
                                <input type="password"
                                       id="current_password"
                                       name="current_password"
                                       class="form-input"
                                       placeholder="Enter your current password"
                                       autocomplete="current-password"
                                       required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="new_password">
                                    New Password
                                    <span class="form-label-req" aria-hidden="true"></span>
                                </label>
                                <input type="password"
                                       id="new_password"
                                       name="new_password"
                                       class="form-input"
                                       placeholder="At least 8 characters"
                                       autocomplete="new-password"
                                       required
                                       minlength="8"
                                       oninput="checkStrength(this.value)">
                                <div class="pw-meter">
                                    <div class="pw-track">
                                        <div class="pw-fill" id="pwFill"></div>
                                    </div>
                                    <div class="pw-label" id="pwLabel">Enter a password</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">
                                    Confirm Password
                                    <span class="form-label-req" aria-hidden="true"></span>
                                </label>
                                <input type="password"
                                       id="confirm_password"
                                       name="confirm_password"
                                       class="form-input"
                                       placeholder="Repeat your new password"
                                       autocomplete="new-password"
                                       required
                                       minlength="8"
                                       oninput="checkMatch()">
                                <div class="form-hint" id="matchHint" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="form-footer">
                            <span class="form-footer-note">You'll stay logged in after changing your password.</span>
                            <button type="submit" class="btn-primary">
                                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                                Update Password
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div><!-- /password -->

        <!-- ══════════════════════════════════
             DANGER ZONE SECTION
        ══════════════════════════════════ -->
        <div id="section-danger" class="section-panel" role="tabpanel">
            <div class="section-eyebrow">
                <div class="section-eyebrow-ico" style="background:rgba(239,68,68,.09)">
                    <svg viewBox="0 0 24 24" style="stroke:var(--err)"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <div class="section-eyebrow-title" style="color:var(--err)">Danger Zone</div>
                    <div class="section-eyebrow-sub">Irreversible actions — proceed with caution</div>
                </div>
            </div>

            <div class="danger-card">
                <div class="card-hd">
                    <div class="card-hd-ico ico-err">
                        <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <div>
                        <div class="card-hd-title" style="color:var(--err)">Danger Zone</div>
                        <div class="card-hd-sub">These actions are permanent and cannot be undone</div>
                    </div>
                </div>

                <div class="dz-item">
                    <div class="dz-info">
                        <div class="dz-title">Reset Progress</div>
                        <div class="dz-desc">Clear all quiz attempts, scores, streaks, and study history. Your account remains active but starts fresh.</div>
                    </div>
                    <button type="button" class="btn-danger-outline" onclick="openModal('resetModal')">
                        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                        Reset Progress
                    </button>
                </div>

                <div class="dz-item">
                    <div class="dz-info">
                        <div class="dz-title">Delete Account</div>
                        <div class="dz-desc">Permanently delete your account and all associated data. This cannot be reversed under any circumstances.</div>
                    </div>
                    <button type="button" class="btn-danger-outline" onclick="openModal('deleteModal')">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        Delete Account
                    </button>
                </div>
            </div>
        </div><!-- /danger -->

    </div><!-- /right column -->
</main>

<!-- ══════════════════════════════════════════
     RESET PROGRESS MODAL
══════════════════════════════════════════ -->
<div class="modal-overlay" id="resetModal"
     role="dialog" aria-modal="true" aria-labelledby="resetTitle"
     onclick="if(event.target===this)closeModal('resetModal')">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('resetModal')" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="modal-ico">
            <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
        </div>
        <div class="modal-title" id="resetTitle">Reset All Progress?</div>
        <div class="modal-desc">
            This will permanently delete all quiz attempts, scores, streaks, and study history. Your account will remain active but start fresh.
            <br><br><strong>This cannot be undone.</strong>
        </div>
        <div class="modal-actions">
            <form method="POST">
                <input type="hidden" name="action" value="reset_progress">
                <button type="submit" class="btn-primary danger" style="width:100%">
                    <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                    Yes, Reset Everything
                </button>
            </form>
            <button type="button" class="btn-ghost" onclick="closeModal('resetModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     DELETE ACCOUNT MODAL
══════════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal"
     role="dialog" aria-modal="true" aria-labelledby="deleteTitle"
     onclick="if(event.target===this)closeModal('deleteModal')">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('deleteModal')" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="modal-ico">
            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </div>
        <div class="modal-title" id="deleteTitle">Delete Your Account?</div>
        <div class="modal-desc">
            All your data — scores, achievements, streaks, and history — will be permanently erased.
            Type <strong>DELETE</strong> below to confirm.
        </div>
        <div class="modal-actions">
            <form method="POST" style="display:flex;flex-direction:column;gap:10px">
                <input type="hidden" name="action" value="delete_account">
                <input type="text"
                       name="delete_confirm"
                       class="form-input"
                       placeholder="Type DELETE to confirm"
                       autocomplete="off"
                       style="text-align:center;font-weight:800;letter-spacing:.1em;font-size:.9375rem"
                       oninput="toggleDeleteBtn(this.value)">
                <button type="submit" class="btn-primary danger" id="deleteSubmitBtn" disabled style="width:100%;opacity:.45;transition:opacity .2s">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                    Permanently Delete Account
                </button>
            </form>
            <button type="button" class="btn-ghost" onclick="closeModal('deleteModal')">Cancel, Keep My Account</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    /* ── Sidebar overlay ── */
    var ov = document.getElementById('sidebarOverlay');
    if (ov) {
        ov.addEventListener('click', function () {
            var sb = document.getElementById('sidebar');
            if (sb) sb.classList.remove('open');
            ov.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            var sb = document.getElementById('sidebar');
            if (sb) sb.classList.remove('open');
            if (ov) ov.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    /* ── Scroll reveal ── */
    var srObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('v'); srObs.unobserve(e.target); }
        });
    }, { threshold: .05 });
    document.querySelectorAll('.sr').forEach(function (el) { srObs.observe(el); });

    /* ── Section switching ── */
    window.switchSection = function (id, btn) {
        event.preventDefault();
        document.querySelectorAll('.section-panel').forEach(function (s) { s.classList.remove('active'); });
        document.querySelectorAll('.sn-item').forEach(function (b) {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
        });
        document.getElementById('section-' + id).classList.add('active');
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');

        /* On mobile scroll right col into view */
        if (window.innerWidth < 900) {
            var rightCol = document.querySelector('.sr.d2');
            if (rightCol) {
                setTimeout(function () {
                    rightCol.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 60);
            }
        }
    };

    /* ── Password strength ── */
    window.checkStrength = function (pw) {
        var fill  = document.getElementById('pwFill');
        var label = document.getElementById('pwLabel');
        if (!fill || !label) return;

        var s = 0;
        if (pw.length >= 8)             s++;
        if (pw.length >= 12)            s++;
        if (/[A-Z]/.test(pw))           s++;
        if (/[0-9]/.test(pw))           s++;
        if (/[^A-Za-z0-9]/.test(pw))    s++;

        var levels = [
            { p: 0,   bg: 'var(--bd)',    t: 'Enter a password',  c: 'var(--tx3)'  },
            { p: 20,  bg: 'var(--err)',   t: 'Very weak',         c: 'var(--err)'  },
            { p: 40,  bg: 'var(--warn)',  t: 'Weak',              c: 'var(--warn)' },
            { p: 60,  bg: '#eab308',      t: 'Fair',              c: '#92600a'     },
            { p: 80,  bg: 'var(--ac2)',   t: 'Strong',            c: 'var(--ac2)'  },
            { p: 100, bg: 'var(--ac)',    t: 'Very strong',       c: '#0b6b40'     },
        ];
        var lv = levels[s] || levels[0];
        fill.style.width      = lv.p + '%';
        fill.style.background = lv.bg;
        label.textContent     = lv.t;
        label.style.color     = lv.c;
    };

    /* ── Password match check ── */
    window.checkMatch = function () {
        var pw   = document.getElementById('new_password');
        var con  = document.getElementById('confirm_password');
        var hint = document.getElementById('matchHint');
        if (!pw || !con || !hint) return;
        if (!con.value) { hint.textContent = ''; return; }
        var match = pw.value === con.value;
        hint.textContent = match ? '✓ Passwords match' : '✗ Passwords do not match';
        hint.style.color = match ? 'var(--ac2)' : 'var(--err)';
    };

    /* ── Delete confirm gating ── */
    window.toggleDeleteBtn = function (val) {
        var btn = document.getElementById('deleteSubmitBtn');
        if (!btn) return;
        var ok = val === 'DELETE';
        btn.disabled     = !ok;
        btn.style.opacity = ok ? '1' : '.45';
    };

    /* ── Modal open / close ── */
    window.openModal = function (id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.add('open');
        document.body.style.overflow = 'hidden';
        /* Focus first focusable element after transition */
        setTimeout(function () {
            var first = m.querySelector('input, button:not(.modal-close)');
            if (first) first.focus();
        }, 360);
    };
    window.closeModal = function (id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('open');
        document.body.style.overflow = '';
    };

    /* Keyboard: Escape closes modals */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
                closeModal(m.id);
            });
        }
    });

    /* Focus trap inside modals */
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || !overlay.classList.contains('open')) return;
            var focusable = Array.from(overlay.querySelectorAll(
                'button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
            ));
            if (!focusable.length) return;
            var first = focusable[0];
            var last  = focusable[focusable.length - 1];
            if (e.shiftKey  && document.activeElement === first) { e.preventDefault(); last.focus(); }
            if (!e.shiftKey && document.activeElement === last)  { e.preventDefault(); first.focus(); }
        });
    });

    /* ── Auto-dismiss alerts after 5 s ── */
    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (a) {
            a.style.transition = 'opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1)';
            a.style.opacity    = '0';
            a.style.transform  = 'translateY(-10px)';
            setTimeout(function () { a.remove(); }, 520);
        });
    }, 5000);

}());
</script>
</body>
</html>