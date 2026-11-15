<?php
declare(strict_types=1);

// Configuration and transport are shared by web requests and operator commands.
function production(): bool { return setting('APP_ENV', 'development') === 'production'; }

function private_directory(): string
{
    static $directory;
    if ($directory !== null) { return $directory; }
    $directory = setting('RUNTIME_DIR', sys_get_temp_dir() . '/mae-' . substr(hash('sha256', dirname(__DIR__)), 0, 12));
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Private runtime storage is unavailable.');
    }
    $resolved = realpath($directory);
    $root = realpath(dirname(__DIR__));
    if (!$resolved || is_link($directory) || $resolved === $root || str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) || !is_writable($resolved)) {
        throw new RuntimeException('RUNTIME_DIR must be writable and outside the website directory.');
    }
    $directory = $resolved;
    return $directory;
}

function media_lock_path(): string
{
    $directory = setting('MEDIA_LOCK_DIR') ?: private_directory();
    $resolved = realpath($directory);
    if (!$resolved || !is_writable($resolved) || str_starts_with($resolved . '/', dirname(__DIR__) . '/')) {
        throw new RuntimeException('Private media-lock storage is unavailable.');
    }
    return $resolved . '/uploads.lock';
}

function app_key(): string
{
    static $key;
    if ($key !== null) { return $key; }
    $encoded = setting('APP_KEY');
    if ($encoded === '' && !production()) {
        $path = private_directory() . '/development.key';
        $handle = @fopen($path . '.lock', 'c');
        if (!$handle || !flock($handle, LOCK_EX)) { throw new RuntimeException('Private key storage is unavailable.'); }
        chmod($path . '.lock', 0600);
        if (!is_file($path) || filesize($path) === 0) {
            if (file_put_contents($path, base64_encode(random_bytes(32))) === false) { throw new RuntimeException('Private key storage is unavailable.'); }
            chmod($path, 0600);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
        $encoded = trim((string)@file_get_contents($path));
    }
    $key = base64_decode($encoded, true);
    if (!is_string($key) || strlen($key) !== 32) {
        throw new RuntimeException('Set APP_KEY to a private base64-encoded 32-byte key.');
    }
    return $key;
}

function ip_in_range(string $ip, string $range): bool
{
    [$network, $bits] = array_pad(explode('/', trim($range), 2), 2, null);
    $address = @inet_pton($ip); $network = @inet_pton($network);
    if ($address === false || $network === false || strlen($address) !== strlen($network)) { return false; }
    $maximum = strlen($address) * 8;
    if ($bits === null) { return hash_equals($network, $address); }
    if (!ctype_digit($bits) || (int)$bits > $maximum) { return false; }
    $bits = (int)$bits;
    $bytes = intdiv($bits, 8); $remaining = $bits % 8;
    return substr($address, 0, $bytes) === substr($network, 0, $bytes)
        && (!$remaining || (ord($address[$bytes]) & (255 << (8 - $remaining))) === (ord($network[$bytes]) & (255 << (8 - $remaining))));
}

function trusted_proxy(): bool
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    foreach (explode(',', setting('TRUSTED_PROXIES')) as $range) {
        if (trim($range) !== '' && ip_in_range($remote, $range)) { return true; }
    }
    return false;
}

function request_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (trusted_proxy() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function client_ip(): string
{
    // The production edge overwrites this header; arbitrary X-Forwarded-For chains are ignored.
    $candidate = trusted_proxy() ? ($_SERVER['HTTP_X_REAL_IP'] ?? '') : '';
    return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function canonical_origin(): string
{
    $origin = rtrim(setting('APP_ORIGIN'), '/');
    if ($origin !== '' && (!filter_var($origin, FILTER_VALIDATE_URL) || parse_url($origin, PHP_URL_USER) !== null || parse_url($origin, PHP_URL_QUERY) !== null || parse_url($origin, PHP_URL_FRAGMENT) !== null || !in_array(parse_url($origin, PHP_URL_PATH), [null, '', '/'], true))) {
        throw new RuntimeException('APP_ORIGIN must contain only a scheme, host, and optional port.');
    }
    return $origin;
}

function validate_security_configuration(): void
{
    if (!in_array(setting('APP_ENV', 'development'), ['development', 'production'], true)) {
        throw new RuntimeException('APP_ENV must be development or production.');
    }
    $origin = canonical_origin();
    if (!preg_match('#^(?:/[A-Za-z0-9_-]+)*$#D', setting('APP_BASE_PATH'))) {
        throw new RuntimeException('APP_BASE_PATH must be an empty string or a simple absolute path.');
    }
    if (!function_exists('sodium_crypto_secretbox') || !defined('PASSWORD_ARGON2ID')) {
        throw new RuntimeException('MAE requires Sodium and Argon2id support.');
    }
    $authRequired = !(PHP_SAPI === 'cli' && defined('MAE_NO_AUTH_JOB'));
    if ($authRequired) { app_key(); }
    if (production()) {
        if (!str_starts_with($origin, 'https://') || setting('COOKIE_SECURE') !== '1' || setting('DB_USER') === '' || setting('DB_USER') === 'root' || setting('DB_PASSWORD') === '' || ($authRequired && setting('APP_KEY') === '')) {
            throw new RuntimeException('Production requires an HTTPS APP_ORIGIN, COOKIE_SECURE=1, APP_KEY, and non-root database credentials.');
        }
    }
}

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store');
    header('Cross-Origin-Opener-Policy: same-origin');
    // All scripts, styles, fonts, and product imagery are bundled locally.
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'; object-src 'none'; frame-ancestors 'none'");
    if (production() && request_https() && setting('HSTS_ENABLED') === '1') { header('Strict-Transport-Security: max-age=31536000'); }
    header('Content-Type: text/html; charset=utf-8');
}

function audit_event(string $event, ?int $target = null, string $outcome = 'success', ?string $kind = null, ?int $actor = null): void
{
    if (!preg_match('/^[a-z_.]{1,64}$/D', $event) || !in_array($outcome, ['success', 'failure', 'blocked'], true)) {
        throw new LogicException('Invalid security event.');
    }
    $kind ??= isset($_SESSION['AdminID']) ? 'admin' : (isset($_SESSION['CustomerID']) ? 'customer' : 'anonymous');
    $actor ??= isset($_SESSION['AdminID']) ? (int)$_SESSION['AdminID'] : (isset($_SESSION['CustomerID']) ? (int)$_SESSION['CustomerID'] : null);
    execute('INSERT INTO SecurityEvents (event_name, actor_kind, actor_id, target_id, outcome, request_id) VALUES (?, ?, ?, ?, ?, ?)', [$event, $kind, $actor, $target, $outcome, request_id()]);
    // Emit only the same bounded fields, for a host-controlled log collector.
    error_log(json_encode(['security_event' => $event, 'actor_kind' => $kind, 'actor_id' => $actor, 'target_id' => $target, 'outcome' => $outcome, 'request_id' => request_id()], JSON_UNESCAPED_SLASHES));
}

function request_id(): string
{
    static $id;
    return $id ??= bin2hex(random_bytes(12));
}

function rate_limit(string $action, string $identifier = ''): void
{
    $policies = [
        'login' => [60, 10, 900], 'register' => [10, 5, 3600],
        'mfa' => [30, 6, 300], 'reauth' => [30, 6, 300],
        'recover' => [20, 5, 900], 'upload' => [30, 20, 900], 'mutation' => [240, 120, 60],
    ];
    if (!isset($policies[$action])) { throw new LogicException('Unknown limiter.'); }
    [$ipLimit, $accountLimit, $seconds] = $policies[$action];
    // Operators may tune limits; tests use a larger IP budget for shared-loopback clients.
    $ipLimit = max(1, min(1000, (int)setting('RATE_' . strtoupper($action) . '_IP', (string)$ipLimit)));
    foreach ([['ip', client_ip(), $ipLimit], ['account', strtolower(trim($identifier)), $accountLimit]] as [$scope, $value, $limit]) {
        if ($scope === 'account' && $value === '') { continue; }
        $key = hash_hmac('sha256', $action . ':' . $scope . ':' . $value, app_key());
        $bucket = transaction(function () use ($key, $seconds): array {
            execute('INSERT INTO RateLimits (bucket_key, hits, expires_at) VALUES (?, 1, UNIX_TIMESTAMP() + ?) ON DUPLICATE KEY UPDATE hits = IF(expires_at <= UNIX_TIMESTAMP(), 1, hits + 1), expires_at = IF(expires_at <= UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + ?, expires_at)', [$key, $seconds, $seconds]);
            return row('SELECT hits, expires_at FROM RateLimits WHERE bucket_key = ? FOR UPDATE', [$key]);
        }, 3);
        if ((int)$bucket['hits'] > $limit) {
            if ((int)$bucket['hits'] === $limit + 1) { audit_event('auth.throttled', null, 'blocked'); }
            header('Retry-After: ' . max(1, (int)$bucket['expires_at'] - time()));
            throw new HttpError(429, 'Too many attempts. Please wait before trying again.');
        }
    }
}

function encrypt_secret(string $plain): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, app_key()));
}

function decrypt_secret(string $encoded): string
{
    $value = base64_decode($encoded, true);
    if ($value === false || strlen($value) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) { throw new RuntimeException('Invalid encrypted authenticator.'); }
    $plain = sodium_crypto_secretbox_open(substr($value, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($value, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), app_key());
    if ($plain === false) { throw new RuntimeException('Authenticator key is unavailable.'); }
    return $plain;
}

function local_image_path(string $image): ?string
{
    $image = preg_replace('#^(\./|\.\./)+#', '', $image);
    if (!preg_match('#^assets/(?:img|uploads)/[A-Za-z0-9_./-]+\.(?:png|jpe?g|webp|svg)$#iD', $image) || str_contains($image, '..')) { return null; }
    // SVG is allowed only in the repository's trusted illustration directory.
    if (str_ends_with(strtolower($image), '.svg') && !str_starts_with($image, 'assets/img/')) { return null; }
    return $image;
}
