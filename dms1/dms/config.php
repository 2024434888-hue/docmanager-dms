<?php
// ─────────────────────────────────────────────────
// config.php — Document Management System
// ─────────────────────────────────────────────────

// ── Environment ──────────────────────────────────
define('APP_NAME',    'DocManager');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     'development'); // 'development' | 'production'
define('BASE_URL',    'http://localhost/dms');

// ── Database ─────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'dms');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// ── Session ───────────────────────────────────────
define('SESSION_NAME',     'dms_session');
define('SESSION_LIFETIME', 3600);        // seconds (1 hour)
define('SESSION_SECURE',   false);       // true in production (HTTPS only)
define('SESSION_HTTPONLY',  true);

// ── Security ─────────────────────────────────────
define('BCRYPT_COST',        12);        // bcrypt work factor
define('TOKEN_EXPIRY',       3600);      // password reset token expiry (seconds)
define('MAX_LOGIN_ATTEMPTS', 5);         // lockout after N failed attempts
define('LOCKOUT_TIME',       900);       // lockout duration in seconds (15 min)

// ── File Upload ───────────────────────────────────
define('UPLOAD_DIR',      __DIR__ . '/uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024);  // 10 MB in bytes
define('ALLOWED_TYPES',   ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png']);

// ── Error Reporting ───────────────────────────────
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ─────────────────────────────────────────────────
// Database Connection (PDO)
// ─────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_ENV === 'development') {
                die('Database connection failed: ' . $e->getMessage());
            } else {
                die('Service unavailable. Please try again later.');
            }
        }
    }

    return $pdo;
}

// ─────────────────────────────────────────────────
// Session Setup
// ─────────────────────────────────────────────────
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => SESSION_SECURE,
            'httponly' => SESSION_HTTPONLY,
            'samesite' => 'Strict',
        ]);

        // Suppress the decode warning; we'll detect and recover from corruption below
        @session_start();

        // If the session data was corrupted (decode failed), wipe and restart clean
        if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION) ) {
            session_destroy();
            session_start();
        } elseif (isset($_SESSION) && !is_array($_SESSION)) {
            // $_SESSION is set but not an array — another sign of corruption
            session_destroy();
            session_start();
        }
    }
}

// ─────────────────────────────────────────────────
// Auth Helpers
// ─────────────────────────────────────────────────

/** Returns currently logged-in user array, or null */
function getAuthUser(): ?array {
    startSecureSession();
    return $_SESSION['user'] ?? null;
}

/** Check if user is logged in; redirect to login if not */
function requireLogin(): void {
    if (!getAuthUser()) {
        header('Location: ' . BASE_URL . '/index.php?page=login');
        exit;
    }
}

/** Check if logged-in user is admin; redirect if not */
function requireAdmin(): void {
    requireLogin();
    $user = getAuthUser();
    if (($user['role'] ?? '') !== 'admin') {
        header('Location: ' . BASE_URL . '/index.php?page=dashboard');
        exit;
    }
}

/** Hash a plain-text password */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

/** Verify plain-text password against stored hash */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/** Generate a secure random token */
function generateToken(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

// ─────────────────────────────────────────────────
// Utility Helpers
// ─────────────────────────────────────────────────

/** Sanitize output to prevent XSS */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Redirect to a page */
function redirect(string $page, array $params = []): void {
    $url = BASE_URL . '/index.php?page=' . $page;
    foreach ($params as $key => $val) {
        $url .= '&' . urlencode($key) . '=' . urlencode($val);
    }
    header('Location: ' . $url);
    exit;
}

/** Flash message: set or get */
function flash(string $key, string $message = null): ?string {
    startSecureSession();
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/** Format file size for display */
function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

/** Get file extension from filename */
function getFileExtension(string $filename): string {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/** Check if a file type is allowed */
function isAllowedType(string $filename): bool {
    return in_array(getFileExtension($filename), ALLOWED_TYPES, true);
}