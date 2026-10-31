<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

header('X-Robots-Tag: noindex, nofollow');

$db = new Database();
$auth = new AuthManager($db);
$settings = $db->getSettingsMap();

$alertMessage = '';
$alertType = 'error';

if (isset($_GET['logout'])) {
    $auth->logout();
    $auth->ensureSessionStarted();
    $alertMessage = 'Anda telah berhasil keluar dari sesi Dashboard Admin.';
    $alertType = 'success';
}

if ($auth->isLoggedIn()) {
    header('Location: admin.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!$auth->verifyCsrfToken($csrf)) {
        $alertMessage = 'Token keamanan CSRF tidak valid. Silakan muat ulang halaman dan coba lagi.';
        $alertType = 'error';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $res = $auth->login($username, $password);
        if ($res['success']) {
            header('Location: admin.php');
            exit;
        }
        $alertMessage = $res['message'];
        $alertType = 'error';
    }
}

$csrfToken = $auth->getCsrfToken();

function eLogin(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Portal Login Admin Terenkripsi - <?= eLogin($settings['brand_name'] ?? 'Madu Nusantara') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto+Slab:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-page-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-brand-header">
            <div class="login-logo-hex">
                <svg viewBox="0 0 44 44" fill="none">
                    <polygon points="22,2 40,12 40,32 22,42 4,32 4,12" fill="#C2884F" stroke="#FDE68A" stroke-width="2"/>
                    <path d="M22,11 C27,18 29.5,22 29.5,26.5 C29.5,30.6 26.1,34 22,34 C17.9,34 14.5,30.6 14.5,26.5 C14.5,22 17,18 22,11 Z" fill="#FFFBEB"/>
                </svg>
            </div>
            <span class="login-security-pill">🔒 PORTAL ADMIN TERSEMBUNYI &amp; TERENKRIPSI</span>
            <h1><?= eLogin($settings['brand_name'] ?? 'MADU NUSANTARA') ?></h1>
            <p>Masuk untuk mengelola seluruh informasi, produk, link Shopee &amp; layanan CS di Landing Page.</p>
        </div>

        <?php if ($alertMessage !== ''): ?>
        <div class="login-alert <?= $alertType === 'success' ? 'alert-success' : 'alert-error' ?>" role="alert">
            <?= eLogin($alertMessage) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="admin-login.php" class="login-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= eLogin($csrfToken) ?>">

            <div class="form-field">
                <label for="usernameInput">Username Administrator</label>
                <div class="input-icon-wrap">
                    <input type="text" id="usernameInput" name="username" required autofocus placeholder="Masukkan username admin" value="<?= eLogin($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="form-field">
                <label for="passwordInput">Password Terenkripsi</label>
                <div class="password-input-wrap">
                    <input type="password" id="passwordInput" name="password" required placeholder="Masukkan password admin">
                    <button type="button" class="toggle-pass-btn" id="togglePassBtn" aria-label="Tampilkan Password">
                        Lihat
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login-submit">
                Masuk ke Dashboard Admin →
            </button>
        </form>

        <div class="login-encryption-box">
            <div class="enc-badge-title">🛡️ Spesifikasi Keamanan &amp; Enkripsi Password Aktif:</div>
            <ul class="enc-spec-list">
                <li><strong>Cipher Enkripsi Data:</strong> AES-256-GCM (Authenticated Encryption)</li>
                <li><strong>Derivasi Kunci (KDF):</strong> PBKDF2-HMAC-SHA256 (100.000 Iterasi)</li>
                <li><strong>Password Hashing:</strong> Bcrypt Adaptive Hash (Cost 12) + HMAC-SHA256 Pepper</li>
                <li><strong>Proteksi Sesi:</strong> CSRF Token + Brute-Force Auto Lockout</li>
            </ul>
        </div>

        <div class="login-demo-helper">
            <div>
                <strong>Kredensial Default:</strong> User: <code>admin</code> | Pass: <code>MaduAdmin#2026</code>
            </div>
            <button type="button" class="btn-fill-demo" onclick="fillDefaultCredentials()">Isi Otomatis</button>
        </div>

        <div class="login-footer-links">
            <a href="index.php">← Kembali ke Landing Page Publik</a>
        </div>
    </div>
</div>

<script>
    const toggleBtn = document.getElementById('togglePassBtn');
    const passInput = document.getElementById('passwordInput');
    if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', function () {
            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleBtn.textContent = 'Sembunyikan';
            } else {
                passInput.type = 'password';
                toggleBtn.textContent = 'Lihat';
            }
        });
    }

    function fillDefaultCredentials() {
        document.getElementById('usernameInput').value = 'admin';
        document.getElementById('passwordInput').value = 'MaduAdmin#2026';
    }
</script>
</body>
</html>
