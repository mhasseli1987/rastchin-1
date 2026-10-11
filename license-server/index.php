<?php
/**
 * ENZO License Server — reference implementation (single file + SQLite).
 *
 * Implements the exact contract the ENZO plugin (LicenseManager / Updater) speaks:
 *   POST /verify-license.php   {license_key, product, domain, version} → {valid, message?, expires, product, domain}
 *   POST /update-check.php     {license_key, product, domain, version} → {version, url, package, requires, requires_php, changelog}
 *   GET  /download.php?key=…&product=…&domain=…                        → package zip stream (gated)
 *   GET  /admin.php?k=ADMIN_KEY                                        → minimal admin UI
 *
 * Deployment: any PHP 8.1+ host with pdo_sqlite. No MySQL required.
 * SECURITY: data/ must NOT be web-accessible — see .htaccess in this folder
 * (Apache) or the equivalent location rule in README-FA.md (nginx).
 *
 * This is a REFERENCE implementation for sellers who self-host licensing.
 * When selling through RTL-Theme, prefer RTL-Theme's own license service.
 */

declare(strict_types=1);

/* ----------------------------------------------------------- configuration */

$CONFIG = [
    // Set a long random admin key. Keep it secret; it controls license issuing.
    'admin_key'   => getenv('ENZO_LICENSE_ADMIN_KEY') ?: 'CHANGE-ME-admin-key',
    // Data directory (SQLite db + packages). Move OUTSIDE the web root if possible.
    'data_dir'    => __DIR__ . '/data',
    // Product slugs this server issues licenses for.
    'products'    => ['enzo'],
];

/* ------------------------------------------------------------------ boot */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak paths/secrets into responses

$dataDir = $CONFIG['data_dir'];
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0750, true);
    @file_put_contents($dataDir . '/.htaccess', "Require all denied\n");
    @file_put_contents($dataDir . '/index.html', '');
}
if (!is_dir($dataDir . '/packages')) {
    @mkdir($dataDir . '/packages', 0750, true);
    @file_put_contents($dataDir . '/packages/.htaccess', "Require all denied\n");
}

function db(string $dataDir): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . $dataDir . '/licenses.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            license_key TEXT NOT NULL UNIQUE,
            product TEXT NOT NULL,
            domain TEXT DEFAULT "",
            expires TEXT DEFAULT "",
            status TEXT NOT NULL DEFAULT "active",
            created_at TEXT NOT NULL,
            last_checked_at TEXT DEFAULT ""
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS packages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version TEXT NOT NULL UNIQUE,
            file TEXT NOT NULL,
            requires TEXT NOT NULL DEFAULT "6.5",
            requires_php TEXT NOT NULL DEFAULT "8.1",
            changelog TEXT DEFAULT "",
            created_at TEXT NOT NULL
        )');
    }
    return $pdo;
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function client_domain(): string
{
    $host = strtolower((string) parse_url((string) ($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), PHP_URL_HOST));
    return preg_replace('/^www\./', '', $host) ?: '';
}

function norm_domain(string $d): string
{
    $d = strtolower(trim($d));
    return preg_replace('/^www\./', '', $d) ?: '';
}

/** Find a license row; enforces product match and expiry. */
function find_license(PDO $pdo, string $key, string $product): ?array
{
    if ($key === '' || $product === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM licenses WHERE license_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['product'] !== $product || $row['status'] !== 'active') {
        return null;
    }
    if ($row['expires'] !== '' && strtotime($row['expires']) < time()) {
        return null;
    }
    $pdo->prepare('UPDATE licenses SET last_checked_at = ? WHERE id = ?')
        ->execute([gmdate('Y-m-d H:i:s'), $row['id']]);
    return $row;
}

/* --------------------------------------------------------------- routing */

$uri  = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$route = preg_replace('#^' . preg_quote($base, '#') . '#', '', $uri) ?: '/';

// Slow down brute-force attempts a little (reference-grade protection).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $body = $_POST;
    if (empty($body)) {
        $raw = file_get_contents('php://input') ?: '';
        $parsed = json_decode($raw, true);
        $body = is_array($parsed) ? $parsed : $_POST;
    }
}

/* POST /verify-license.php */
if ($route === '/verify-license.php' || $route === '/verify-license.php/') {
    $key     = trim((string) ($body['license_key'] ?? ''));
    $product = trim((string) ($body['product'] ?? ''));
    $domain  = norm_domain((string) ($body['domain'] ?? ''));

    $lic = find_license(db($dataDir), $key, $product);
    if (!$lic) {
        usleep(500000); // brute-force damping on failure only
        json_out(['valid' => false, 'message' => 'کلید لایسنس نامعتبر یا منقضی است.']);
    }

    $bound = norm_domain((string) $lic['domain']);
    $domainMatch = $bound === '' || $bound === $domain;
    json_out([
        'valid'   => true,
        'expires' => (string) $lic['expires'],
        'product' => (string) $lic['product'],
        'domain'  => $bound !== '' ? $bound : $domain,
        'domain_match' => $domainMatch, // informational (plugin fail-open)
    ]);
}

/* POST /update-check.php */
if ($route === '/update-check.php' || $route === '/update-check.php/') {
    $key     = trim((string) ($body['license_key'] ?? ''));
    $product = trim((string) ($body['product'] ?? ''));
    $current = (string) ($body['version'] ?? '');

    $lic = find_license(db($dataDir), $key, $product);
    if (!$lic) {
        // Fail-safe: without a valid license the plugin simply sees "no update".
        json_out(['message' => 'license_required'], 200);
    }

    $stmt = db($dataDir)->query('SELECT * FROM packages ORDER BY id DESC LIMIT 1');
    $pkg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pkg || version_compare($current, (string) $pkg['version'], '>=')) {
        json_out(['message' => 'up_to_date'], 200); // no version key → plugin treats as none
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $self   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $base;
    json_out([
        'version'      => (string) $pkg['version'],
        'url'          => 'https://rtl-theme.com/plugins/enzo',
        'package'      => $self . '/download.php?key=' . urlencode($key) . '&product=' . urlencode($product),
        'requires'     => (string) $pkg['requires'],
        'requires_php' => (string) $pkg['requires_php'],
        'changelog'    => (string) $pkg['changelog'],
    ]);
}

/* GET /download.php — license-gated package download */
if ($route === '/download.php' || $route === '/download.php/') {
    // Secure update contract: packages are only ever served over HTTPS.
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'HTTPS required';
        exit;
    }
    $key     = trim((string) ($_GET['key'] ?? ''));
    $product = trim((string) ($_GET['product'] ?? ''));
    $lic = find_license(db($dataDir), $key, $product);
    if (!$lic) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'license_required';
        exit;
    }
    $stmt = db($dataDir)->query('SELECT * FROM packages ORDER BY id DESC LIMIT 1');
    $pkg = $stmt->fetch(PDO::FETCH_ASSOC);
    $file = $dataDir . '/packages/' . basename((string) ($pkg['file'] ?? ''));
    if (!$pkg || !is_file($file)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'package_not_found';
        exit;
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . (string) filesize($file));
    readfile($file);
    exit;
}

/* GET /admin.php — minimal issuing UI (protected by admin_key) */
if ($route === '/admin.php' || $route === '/admin.php/' || $route === '/' || $route === '') {
    $k = (string) ($_REQUEST['k'] ?? '');
    if (!hash_equals($CONFIG['admin_key'], $k)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'admin key required: /admin.php?k=ADMIN_KEY';
        exit;
    }

    $pdo = db($dataDir);
    $msg = '';

    // ---- actions
    $action = (string) ($_REQUEST['do'] ?? '');
    if ($action === 'issue') {
        $product = (string) ($_REQUEST['product'] ?? '');
        $domain  = norm_domain((string) ($_REQUEST['domain'] ?? ''));
        $expires = trim((string) ($_REQUEST['expires'] ?? ''));
        if (in_array($product, $CONFIG['products'], true) && ($expires === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires))) {
            $key = 'ENZO-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)))
                 . '-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
            $pdo->prepare('INSERT INTO licenses (license_key, product, domain, expires, created_at) VALUES (?,?,?,?,?)')
                ->execute([$key, $product, $domain, $expires, gmdate('Y-m-d H:i:s')]);
            $msg = 'لایسنس صادر شد: ' . $key;
        } else {
            $msg = 'خطا: محصول نامعتبر یا تاریخ بد.';
        }
    }
    if ($action === 'bind') {
        $pdo->prepare('UPDATE licenses SET domain = ? WHERE id = ?')
            ->execute([norm_domain((string) ($_REQUEST['domain'] ?? '')), (int) $_REQUEST['id']]);
        $msg = 'دامنه به‌روزرسانی شد.';
    }
    if ($action === 'toggle') {
        $pdo->prepare("UPDATE licenses SET status = CASE status WHEN 'active' THEN 'revoked' ELSE 'active' END WHERE id = ?")
            ->execute([(int) $_REQUEST['id']]);
        $msg = 'وضعیت تغییر کرد.';
    }
    if ($action === 'package') {
        if (!empty($_FILES['zip']['tmp_name']) && is_uploaded_file($_FILES['zip']['tmp_name'])) {
            $version = trim((string) ($_REQUEST['version'] ?? ''));
            if ($version !== '' && strtolower(pathinfo((string) $_FILES['zip']['name'], PATHINFO_EXTENSION)) === 'zip') {
                $file = 'enzo-' . $version . '.zip';
                move_uploaded_file($_FILES['zip']['tmp_name'], $dataDir . '/packages/' . $file);
                $pdo->prepare('INSERT OR REPLACE INTO packages (version, file, requires, requires_php, changelog, created_at) VALUES (?,?,?,?,?,?)')
                    ->execute([
                        $version, $file,
                        trim((string) ($_REQUEST['requires'] ?? '6.5')),
                        trim((string) ($_REQUEST['requires_php'] ?? '8.1')),
                        trim((string) ($_REQUEST['changelog'] ?? '')),
                        gmdate('Y-m-d H:i:s'),
                    ]);
                $msg = 'بسته نسخه ' . $version . ' ثبت شد.';
            } else {
                $msg = 'خطا: نسخه یا فایل نامعتبر.';
            }
        }
    }

    $licenses = $pdo->query('SELECT * FROM licenses ORDER BY id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
    $packages = $pdo->query('SELECT * FROM packages ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
    h_out($CONFIG, $k, $msg, $licenses, $packages);
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'unknown endpoint';

/* ------------------------------------------------------------- admin html */

function h_out(array $CONFIG, string $k, string $msg, array $licenses, array $packages): void
{
    header('Content-Type: text/html; charset=utf-8');
    $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    ?>
<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8">
<title>سرور لایسنس انزو</title>
<style>
body{font-family:Tahoma,Arial,sans-serif;background:#f0f2f5;margin:0;padding:24px;color:#0f2a43}
.wrap{max-width:960px;margin:0 auto}h1{font-size:20px}table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;font-size:12px}
th,td{padding:8px;border-bottom:1px solid #f1f5f9;text-align:right}th{background:#f8fafc}
form.inline{display:flex;flex-wrap:wrap;gap:8px;align-items:end;background:#fff;border:1px solid #e2e8f0;padding:12px;margin-bottom:16px}
label{font-size:11px;color:#475569;display:block}input,select,textarea{padding:6px;border:1px solid #949494;border-radius:4px;font-family:inherit}
button{padding:8px 16px;background:#14b8a6;color:#fff;border:none;border-radius:6px;cursor:pointer}
.ok{background:#dcfce7;border:1px solid #86efac;padding:10px;border-radius:8px;margin-bottom:12px}
h2{font-size:15px;margin:24px 0 8px}code{background:#f1f5f9;padding:2px 6px;border-radius:4px;direction:ltr;display:inline-block}
.mut{color:#64748b;font-size:11px}
</style></head><body><div class="wrap">
<h1>🔑 سرور لایسنس انزو — پیاده‌سازی مرجع</h1>
<?php if ($msg): ?><div class="ok"><?= $h($msg) ?></div><?php endif; ?>

<h2>صدور لایسنس جدید</h2>
<form class="inline" method="get">
<input type="hidden" name="k" value="<?= $h($k) ?>"><input type="hidden" name="do" value="issue">
<div><label>محصول</label><select name="product"><?php foreach ($CONFIG['products'] as $p): ?><option value="<?= $h($p) ?>"><?= $h($p) ?></option><?php endforeach; ?></select></div>
<div><label>دامنه (اختیاری)</label><input name="domain" placeholder="example.com"></div>
<div><label>انقضا (اختیاری، YYYY-MM-DD)</label><input name="expires" placeholder="2027-01-01"></div>
<button>صدور</button>
</form>

<h2>لایسنس‌ها (<?= count($licenses) ?>)</h2>
<table><tr><th>#</th><th>کلید</th><th>محصول</th><th>دامنه</th><th>انقضا</th><th>وضعیت</th><th>آخرین بررسی</th><th>عملیات</th></tr>
<?php foreach ($licenses as $L): ?>
<tr><td><?= (int) $L['id'] ?></td><td><code><?= $h($L['license_key']) ?></code></td><td><?= $h($L['product']) ?></td>
<td><?= $h($L['domain'] ?: '— بدون قید دامنه —') ?></td><td><?= $h($L['expires'] ?: 'مادام‌العمر') ?></td>
<td><?= $L['status'] === 'active' ? '✅ فعال' : '⛔ لغوشده' ?></td><td><?= $h($L['last_checked_at'] ?: '—') ?></td>
<td class="act">
<form method="get" style="display:inline"><input type="hidden" name="k" value="<?= $h($k) ?>"><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int) $L['id'] ?>"><button style="background:#64748b"><?= $L['status'] === 'active' ? 'لغو' : 'فعال‌سازی' ?></button></form>
<form method="get" style="display:inline"><input type="hidden" name="k" value="<?= $h($k) ?>"><input type="hidden" name="do" value="bind"><input type="hidden" name="id" value="<?= (int) $L['id'] ?>"><input name="domain" placeholder="دامنه جدید" style="width:110px"><button style="background:#2563eb">قید دامنه</button></form>
</td></tr>
<?php endforeach; ?>
</table>

<h2>بسته به‌روزرسانی</h2>
<form class="inline" method="post" enctype="multipart/form-data">
<input type="hidden" name="k" value="<?= $h($k) ?>"><input type="hidden" name="do" value="package">
<div><label>فایل ZIP افزونه</label><input type="file" name="zip" accept=".zip"></div>
<div><label>نسخه</label><input name="version" placeholder="1.1.0"></div>
<div><label>حداقل وردپرس</label><input name="requires" value="6.5" style="width:70px"></div>
<div><label>حداقل PHP</label><input name="requires_php" value="8.1" style="width:70px"></div>
<div style="flex:1"><label>تغییرات (Changelog)</label><textarea name="changelog" rows="2" style="width:100%"></textarea></div>
<button>ثبت بسته</button>
</form>
<?php if ($packages): ?>
<table><tr><th>نسخه</th><th>فایل</th><th>حداقل WP</th><th>حداقل PHP</th><th>تاریخ</th></tr>
<?php foreach ($packages as $P): ?><tr><td><b><?= $h($P['version']) ?></b></td><td><code><?= $h($P['file']) ?></code></td><td><?= $h($P['requires']) ?></td><td><?= $h($P['requires_php']) ?></td><td><?= $h($P['created_at']) ?></td></tr><?php endforeach; ?>
</table>
<?php endif; ?>

<p class="mut">endpointها برای افزونه: <code>verify-license.php</code> · <code>update-check.php</code> · <code>download.php</code> — مستندات: README-FA.md</p>
</div></body></html>
    <?php
}
