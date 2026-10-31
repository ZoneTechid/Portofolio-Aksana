<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

class AuthManager
{
    private Database $db;
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300; // 5 minutes

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->ensureSessionStarted();
    }

    public function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public function getCsrfToken(): string
    {
        $this->ensureSessionStarted();
        return (string)$_SESSION['csrf_token'];
    }

    public function verifyCsrfToken(?string $token): bool
    {
        if (!$token || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    public function isLoggedIn(): bool
    {
        $this->ensureSessionStarted();
        return !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id']);
    }

    public function getCurrentAdmin(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        $stmt = $this->db->getPdo()->prepare("SELECT id, username, display_name, encrypted_password, last_login, password_updated_at, created_at FROM admins WHERE id = ?");
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Authenticates an admin user using AES-256-GCM + Bcrypt + HMAC-SHA256 verification.
     * Returns ['success' => bool, 'message' => string]
     */
    public function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Username dan password wajib diisi.',
            ];
        }

        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (!$admin) {
            // Timing-safe dummy verification to prevent username enumeration timing attacks
            $dummyEnvelope = $this->db->getCrypto()->createEncryptedPasswordHash('dummy_password_protection');
            $this->db->getCrypto()->verifyEncryptedPassword($password, $dummyEnvelope);
            return [
                'success' => false,
                'message' => 'Kredensial login tidak valid. Periksa kembali username atau password Anda.',
            ];
        }

        // Check lockout status
        if (!empty($admin['locked_until'])) {
            $lockTime = strtotime((string)$admin['locked_until']);
            if ($lockTime !== false && $lockTime > time()) {
                $remaining = $lockTime - time();
                return [
                    'success' => false,
                    'message' => "Akun dikunci sementara karena terlalu banyak percobaan gagal. Silakan coba lagi dalam {$remaining} detik.",
                ];
            }
        }

        // Verify password against AES-256-GCM encrypted Bcrypt + HMAC-SHA256 envelope
        $isValid = $this->db->getCrypto()->verifyEncryptedPassword($password, (string)$admin['encrypted_password']);

        if (!$isValid) {
            $attempts = (int)$admin['failed_attempts'] + 1;
            $lockedUntil = null;
            if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_SECONDS);
            }

            $upd = $pdo->prepare("UPDATE admins SET failed_attempts = ?, locked_until = ? WHERE id = ?");
            $upd->execute([$attempts, $lockedUntil, $admin['id']]);

            $remAttempts = max(0, self::MAX_FAILED_ATTEMPTS - $attempts);
            $msg = $remAttempts > 0
                ? "Kredensial login tidak valid. Sisa percobaan: {$remAttempts}x."
                : "Terlalu banyak percobaan gagal! Akun dikunci selama 5 menit.";

            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        // Successful login: reset failed attempts and update last_login
        $now = date('Y-m-d H:i:s');
        $upd = $pdo->prepare("UPDATE admins SET failed_attempts = 0, locked_until = NULL, last_login = ? WHERE id = ?");
        $upd->execute([$now, $admin['id']]);

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = (int)$admin['id'];
        $_SESSION['admin_username']  = (string)$admin['username'];
        $_SESSION['admin_name']      = (string)$admin['display_name'];
        $_SESSION['login_time']      = time();

        return [
            'success' => true,
            'message' => 'Login berhasil! Mengalihkan ke Dashboard Admin...',
        ];
    }

    public function logout(): void
    {
        $this->ensureSessionStarted();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_destroy();
        }
    }

    /**
     * Changes the admin password and/or username/display_name after verifying the current password.
     */
    public function updateAdminCredentials(
        int $adminId,
        string $currentPassword,
        string $newUsername,
        string $newDisplayName,
        ?string $newPassword = null
    ): array {
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

        if (!$admin) {
            return ['success' => false, 'message' => 'Data admin tidak ditemukan.'];
        }

        if (!$this->db->getCrypto()->verifyEncryptedPassword($currentPassword, (string)$admin['encrypted_password'])) {
            return ['success' => false, 'message' => 'Password saat ini salah! Perubahan dibatalkan.'];
        }

        $newUsername = trim($newUsername) ?: (string)$admin['username'];
        $newDisplayName = trim($newDisplayName) ?: (string)$admin['display_name'];

        $encryptedPassword = (string)$admin['encrypted_password'];
        $passwordUpdatedAt = (string)$admin['password_updated_at'];

        if ($newPassword !== null && $newPassword !== '') {
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Password baru minimal harus terdiri dari 8 karakter.'];
            }
            $encryptedPassword = $this->db->getCrypto()->createEncryptedPasswordHash($newPassword);
            $passwordUpdatedAt = date('Y-m-d H:i:s');
        }

        $upd = $pdo->prepare("
            UPDATE admins
            SET username = :u,
                display_name = :d,
                encrypted_password = :p,
                password_updated_at = :u_at
            WHERE id = :id
        ");
        $upd->execute([
            ':u'    => $newUsername,
            ':d'    => $newDisplayName,
            ':p'    => $encryptedPassword,
            ':u_at' => $passwordUpdatedAt,
            ':id'   => $adminId,
        ]);

        $_SESSION['admin_username'] = $newUsername;
        $_SESSION['admin_name']     = $newDisplayName;

        return [
            'success' => true,
            'message' => 'Kredensial & enkripsi password admin berhasil diperbarui!',
            'encryption_info' => $this->db->getCrypto()->inspectEnvelope($encryptedPassword),
        ];
    }
}
