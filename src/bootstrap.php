<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'lemariku');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('UPLOAD_DIR', dirname(__DIR__) . '/public/uploads');
define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024);
define('UNUSED_DAYS', 90);
define('SEASONS', ['Semua musim', 'Kemarau', 'Penghujan']);
// Urutan tampil section di halaman lemari. Kategori di luar daftar ini tampil paling bawah.
define('CATEGORY_ORDER', ['Atasan', 'Bawahan', 'Dress', 'Outerwear', 'Sepatu', 'Aksesoris']);

// Kredit di footer. Ganti AUTHOR_URL kalau mau menunjuk ke portofolio atau LinkedIn.
define('AUTHOR_NAME', 'Richky');
define('AUTHOR_URL', 'https://github.com/richkyrifadi-create');

// Form masukan dan pengaduan
define('FEEDBACK_CATEGORIES', ['Masukan', 'Pengaduan Fasilitas', 'Bug/Error', 'Lainnya']);
define('FEEDBACK_SUBJECT_MAX', 100);
define('FEEDBACK_MESSAGE_MIN', 20);
define('FEEDBACK_MESSAGE_MAX', 1000);
define('FEEDBACK_IMAGE_MAX_BYTES', 2 * 1024 * 1024);
define('FEEDBACK_RATE_LIMIT', 3);
define('FEEDBACK_RATE_WINDOW_MINUTES', 10);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash_set(string $message): void
{
    $_SESSION['flash'] = $message;
}

function flash_get(): ?string
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        exit('Token tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function require_login(): int
{
    $id = current_user_id();
    if ($id === null) {
        redirect('login.php');
    }
    return $id;
}

function rupiah(?float $amount): string
{
    return $amount === null ? '-' : 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Ubah teks jadi slug aman untuk dipakai sebagai id anchor HTML.
 */
function slug(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text), '-'));
    return $slug !== '' ? $slug : 'lainnya';
}

/**
 * Kelompokkan daftar pakaian per kategori, mengikuti urutan CATEGORY_ORDER.
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<string, array<int, array<string, mixed>>>
 */
function group_by_category(array $items): array
{
    $groups = [];
    foreach ($items as $item) {
        $groups[$item['category_name']][] = $item;
    }

    $ordered = [];
    foreach (CATEGORY_ORDER as $name) {
        if (isset($groups[$name])) {
            $ordered[$name] = $groups[$name];
            unset($groups[$name]);
        }
    }
    // Kategori yang tidak ada di CATEGORY_ORDER ditaruh di akhir, urut abjad.
    ksort($groups);
    return $ordered + $groups;
}

/**
 * Batas upload dari php.ini dalam byte (0 kalau tidak terbaca).
 */
function upload_limit_bytes(): int
{
    $raw = trim((string) ini_get('upload_max_filesize'));
    if ($raw === '') {
        return 0;
    }
    $unit = strtolower(substr($raw, -1));
    $value = (int) $raw;
    return match ($unit) {
        'g' => $value * 1024 * 1024 * 1024,
        'm' => $value * 1024 * 1024,
        'k' => $value * 1024,
        default => $value,
    };
}

/**
 * Simpan foto yang diupload. Mengembalikan [namaFile|null, pesanError|null].
 */
function save_upload(array $file, ?int $maxBytes = null, ?array $allowedMime = null): array
{
    $allowedMime ??= ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $maxBytes ??= UPLOAD_MAX_BYTES;

    // Batas efektif: yang terkecil antara batas aplikasi dan batas php.ini.
    $iniLimit = upload_limit_bytes();
    $limit = min($maxBytes, $iniLimit ?: $maxBytes);
    $limitMb = round($limit / 1048576, 1);

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, "Ukuran foto terlalu besar. Maksimal {$limitMb} MB."];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $reasons = [
            UPLOAD_ERR_PARTIAL => 'Upload terputus, coba lagi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server tidak punya folder sementara untuk upload.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file upload.',
            UPLOAD_ERR_EXTENSION => 'Upload diblokir oleh ekstensi PHP.',
        ];
        return [null, $reasons[$file['error']] ?? 'Upload foto gagal, coba lagi.'];
    }
    if ($file['size'] > $limit) {
        return [null, "Ukuran foto maksimal {$limitMb} MB."];
    }
    // Tipe diperiksa dari isi file, bukan dari nama atau header kiriman browser.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = $allowedMime[$mime] ?? null;
    if ($ext === null) {
        $names = array_values(array_unique(array_map('strtoupper', array_values($allowedMime))));
        $last = array_pop($names);
        $list = $names ? implode(', ', $names) . ' atau ' . $last : $last;
        return [null, "Format foto harus $list."];
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        return [null, 'Gagal menyimpan foto.'];
    }
    return [$name, null];
}

function delete_image(?string $name): void
{
    // Hanya hapus file yang berformat nama kita sendiri (aman dari path traversal).
    if ($name !== null && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name)) {
        @unlink(UPLOAD_DIR . '/' . $name);
    }
}

function client_ip(): string
{
    // Sengaja tidak memakai X-Forwarded-For: header itu gampang dipalsukan,
    // jadi tidak layak dipakai sebagai dasar pembatasan.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Batasi pengiriman masukan: maksimal FEEDBACK_RATE_LIMIT kali
 * dalam FEEDBACK_RATE_WINDOW_MINUTES menit per alamat IP.
 */
function feedback_rate_exceeded(PDO $pdo, string $ip): bool
{
    if ($ip === '') {
        return false;
    }
    $cutoff = date('Y-m-d H:i:s', time() - FEEDBACK_RATE_WINDOW_MINUTES * 60);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM feedback WHERE ip = ? AND created_at >= ?');
    $stmt->execute([$ip, $cutoff]);
    return (int) $stmt->fetchColumn() >= FEEDBACK_RATE_LIMIT;
}
