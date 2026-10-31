<?php
declare(strict_types=1);

/**
 * Multi-Layer Password Security & Authenticated Encryption Engine
 *
 * Layer 1: HMAC-SHA256 Peppering
 * Layer 2: Bcrypt Adaptive Hashing (Cost 12)
 * Layer 3: AES-256-GCM Authenticated Encryption at Rest (with PBKDF2-HMAC-SHA256 Key Derivation)
 */
class SecurityCrypto
{
    private const CIPHER_ALGO = 'aes-256-gcm';
    private const PBKDF2_ITERATIONS = 100000;
    private const BCRYPT_COST = 12;
    private string $masterSecret;
    private string $pepperSecret;

    public function __construct(?string $keyFilePath = null)
    {
        $keyFile = $keyFilePath ?? (__DIR__ . '/../data/.security_keys.json');
        $dataDir = dirname($keyFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        if (file_exists($keyFile)) {
            $keys = json_decode((string)file_get_contents($keyFile), true);
            if (is_array($keys) && !empty($keys['master_secret']) && !empty($keys['pepper_secret'])) {
                $this->masterSecret = (string)$keys['master_secret'];
                $this->pepperSecret = (string)$keys['pepper_secret'];
                return;
            }
        }

        // Generate cryptographically secure 256-bit secrets
        $this->masterSecret = bin2hex(random_bytes(32));
        $this->pepperSecret = bin2hex(random_bytes(32));

        file_put_contents(
            $keyFile,
            json_encode([
                'master_secret' => $this->masterSecret,
                'pepper_secret' => $this->pepperSecret,
                'created_at'    => date('c'),
                'cipher'        => self::CIPHER_ALGO,
                'kdf'           => 'PBKDF2-HMAC-SHA256',
            ], JSON_PRETTY_PRINT)
        );
    }

    /**
     * Derives a 256-bit encryption key from master secret and salt using PBKDF2-HMAC-SHA256.
     */
    private function deriveKey(string $salt): string
    {
        return hash_pbkdf2(
            'sha256',
            $this->masterSecret,
            $salt,
            self::PBKDF2_ITERATIONS,
            32,
            true
        );
    }

    /**
     * Encrypts plaintext data using AES-256-GCM authenticated encryption.
     * Format: base64(json({v, alg, kdf, iter, salt, iv, tag, ct}))
     */
    public function encryptData(string $plaintext): string
    {
        $salt = random_bytes(16);
        $ivLen = openssl_cipher_iv_length(self::CIPHER_ALGO);
        $iv = random_bytes($ivLen ?: 12);
        $key = $this->deriveKey($salt);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'MADU_ADMIN_AUTH_V1',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Gagal mengenkripsi data dengan AES-256-GCM.');
        }

        $payload = [
            'v'    => 1,
            'alg'  => 'AES-256-GCM',
            'kdf'  => 'PBKDF2-HMAC-SHA256',
            'iter' => self::PBKDF2_ITERATIONS,
            'salt' => base64_encode($salt),
            'iv'   => base64_encode($iv),
            'tag'  => base64_encode($tag),
            'ct'   => base64_encode($ciphertext),
        ];

        return base64_encode((string)json_encode($payload));
    }

    /**
     * Decrypts and verifies an AES-256-GCM encrypted payload.
     * Returns null if tampered or invalid.
     */
    public function decryptData(string $encryptedEnvelope): ?string
    {
        $json = base64_decode($encryptedEnvelope, true);
        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);
        if (
            !is_array($payload) ||
            empty($payload['salt']) ||
            empty($payload['iv']) ||
            empty($payload['tag']) ||
            !isset($payload['ct'])
        ) {
            return null;
        }

        $salt = base64_decode((string)$payload['salt'], true);
        $iv   = base64_decode((string)$payload['iv'], true);
        $tag  = base64_decode((string)$payload['tag'], true);
        $ct   = base64_decode((string)$payload['ct'], true);

        if ($salt === false || $iv === false || $tag === false || $ct === false) {
            return null;
        }

        $key = $this->deriveKey($salt);

        $plaintext = openssl_decrypt(
            $ct,
            self::CIPHER_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'MADU_ADMIN_AUTH_V1'
        );

        return $plaintext === false ? null : $plaintext;
    }

    /**
     * Hashes and encrypts a password using:
     * 1. HMAC-SHA256 with secret server pepper
     * 2. Bcrypt adaptive hashing (cost 12)
     * 3. AES-256-GCM authenticated encryption at rest
     */
    public function createEncryptedPasswordHash(string $plainPassword): string
    {
        $peppered = hash_hmac('sha256', $plainPassword, $this->pepperSecret);
        $bcryptHash = password_hash($peppered, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
        return $this->encryptData($bcryptHash);
    }

    /**
     * Verifies a plain password against an AES-256-GCM encrypted Bcrypt hash.
     */
    public function verifyEncryptedPassword(string $plainPassword, string $encryptedHash): bool
    {
        $bcryptHash = $this->decryptData($encryptedHash);
        if ($bcryptHash === null) {
            return false;
        }

        $peppered = hash_hmac('sha256', $plainPassword, $this->pepperSecret);
        return password_verify($peppered, $bcryptHash);
    }

    /**
     * Inspects an encrypted password envelope for the Admin Security Dashboard
     * without exposing any secret keys or plaintext hash.
     */
    public function inspectEnvelope(string $encryptedEnvelope): array
    {
        $json = base64_decode($encryptedEnvelope, true);
        $payload = $json ? json_decode($json, true) : null;
        if (!is_array($payload)) {
            return [
                'valid' => false,
                'algorithm' => 'Unknown',
            ];
        }

        return [
            'valid'            => true,
            'version'          => $payload['v'] ?? 1,
            'cipher_algorithm' => $payload['alg'] ?? 'AES-256-GCM',
            'kdf_algorithm'    => $payload['kdf'] ?? 'PBKDF2-HMAC-SHA256',
            'kdf_iterations'   => $payload['iter'] ?? self::PBKDF2_ITERATIONS,
            'hash_algorithm'   => 'Bcrypt (Cost ' . self::BCRYPT_COST . ') + HMAC-SHA256 Pepper',
            'iv_Preview'       => substr((string)($payload['iv'] ?? ''), 0, 12) . '...',
            'tag_Preview'      => substr((string)($payload['tag'] ?? ''), 0, 12) . '...',
            'ciphertext_Preview' => substr((string)($payload['ct'] ?? ''), 0, 24) . '...',
            'envelope_length'  => strlen($encryptedEnvelope),
        ];
    }
}
