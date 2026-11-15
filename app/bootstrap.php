<?php
declare(strict_types=1);
date_default_timezone_set('UTC');

// All web entry points load this before sending output.
$localConfig = dirname(__DIR__) . '/config.local.php';
$config = is_file($localConfig) ? require $localConfig : [];
function setting(string $name, string $default = ''): string
{
    global $config;
    $value = getenv($name);
    $file = getenv($name . '_FILE');
    if ($file !== false && $file !== '') {
        if ($value !== false && $value !== '') { throw new RuntimeException('Configure a value or its _FILE source, not both.'); }
        if (!is_file($file) || !is_readable($file) || filesize($file) > 16384) { throw new RuntimeException('A configured secret file is unavailable.'); }
        return rtrim((string)file_get_contents($file), "\r\n");
    }
    return $value !== false ? $value : (string)($config[$name] ?? $default);
}

function url(string $path = '', array $query = []): string
{
    $base = rtrim(setting('APP_BASE_PATH'), '/');
    return $base . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

final class HttpError extends RuntimeException
{
    public function __construct(public int $status, string $message)
    {
        parent::__construct($message);
    }
}

set_exception_handler(function (Throwable $error): void {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(1);
    }
    $status = $error instanceof HttpError ? $error->status : 500;
    if ($status === 500) {
        error_log(json_encode(['application_error' => get_class($error), 'request_id' => request_id()]));
    }
    http_response_code($status);
    $message = $error instanceof HttpError ? $error->getMessage() : 'The request could not be completed. Please try again or contact the site administrator.';
    if (function_exists('page_start')) {
        page_start('Request unsuccessful');
        echo '<div class="alert alert-danger" role="alert">' . e($message) . '</div><a class="btn btn-secondary" href="' . e(url('index.php')) . '">Return home</a>';
        page_end();
    } else {
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Request unsuccessful</title><h1>Request unsuccessful</h1><p>' . e($message) . '</p></html>';
    }
});

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
validate_security_configuration();

if (PHP_SAPI !== 'cli') {
    security_headers();
    if (production()) {
        $authority = parse_url(canonical_origin(), PHP_URL_HOST) . (parse_url(canonical_origin(), PHP_URL_PORT) ? ':' . parse_url(canonical_origin(), PHP_URL_PORT) : '');
        if (!request_https() || strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== strtolower($authority)) {
            throw new HttpError(400, 'Use the configured HTTPS address for this portfolio demo.');
        }
    }
    $sessionDirectory = private_directory() . '/sessions';
    if (!is_dir($sessionDirectory) && !@mkdir($sessionDirectory, 0700) && !is_dir($sessionDirectory)) { throw new RuntimeException('Session storage is unavailable.'); }
    session_save_path($sessionDirectory);
    ini_set('session.gc_maxlifetime', '43200');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('mae_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim(setting('APP_BASE_PATH'), '/') . '/',
        'secure' => setting('COOKIE_SECURE') === '1' || request_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function input(string $name, ?array $source = null): string
{
    $value = ($source ?? $_POST)[$name] ?? '';
    if (!is_string($value)) {
        throw new HttpError(400, 'Invalid form value.');
    }
    return $value;
}

function positive_int(mixed $value, int $maximum = 2147483647): int
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) {
        throw new HttpError(422, 'Enter a positive whole number.');
    }
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]);
    if ($number === false) {
        throw new HttpError(422, 'The number is outside the allowed range.');
    }
    return $number;
}

function text_value(string $name, int $maximum, bool $required = true): string
{
    $value = trim(input($name));
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $maximum || str_contains($value, "\0")) {
        throw new HttpError(422, 'Please complete ' . $name . ' within the allowed length.');
    }
    return $value;
}

function email_value(string $name): string
{
    $email = strtolower(text_value($name, 254));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new HttpError(422, 'Enter a valid email address.');
    }
    return $email;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        throw new HttpError(405, 'This action requires a submitted form.');
    }
    if (!hash_equals(csrf_token(), input('csrf'))) {
        throw new HttpError(403, 'The form expired. Reload the page and try again.');
    }
    $actor = isset($_SESSION['AdminID']) ? 'admin:' . $_SESSION['AdminID'] : (isset($_SESSION['CustomerID']) ? 'customer:' . $_SESSION['CustomerID'] : '');
    rate_limit('mutation', $actor);
}

function db(): mysqli
{
    static $connection;
    if (!$connection) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $connection = new mysqli(setting('DB_HOST', 'localhost'), setting('DB_USER', 'root'), setting('DB_PASSWORD'), setting('DB_NAME', 'NealleDB'), (int)setting('DB_PORT', '3306'), setting('DB_SOCKET') ?: null);
        $connection->set_charset('utf8mb4');
        $connection->query("SET time_zone = '+00:00'");
        if (!defined('MAE_SETUP')) {
            try {
                $ready = $connection->query('SELECT version FROM MaeSchema WHERE version = 3')->fetch_assoc();
            } catch (mysqli_sql_exception $error) {
                $ready = null;
            }
            if (!$ready) {
                throw new HttpError(503, 'Database setup is required. The site administrator must run scripts/setup.php or scripts/migrate.php from the command line.');
            }
        }
    }
    return $connection;
}

function query(string $sql, array $params = []): mysqli_stmt
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function rows(string $sql, array $params = []): array
{
    $statement = query($sql, $params);
    $result = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    return $result;
}

function row(string $sql, array $params = []): ?array
{
    return rows($sql, $params)[0] ?? null;
}

function execute(string $sql, array $params = []): void
{
    query($sql, $params)->close();
}

function transaction(callable $callback, int $retries = 0): mixed
{
    for ($attempt = 0; ; $attempt++) {
        db()->begin_transaction();
        try {
            $result = $callback();
            db()->commit();
            return $result;
        } catch (Throwable $error) {
            db()->rollback();
            // Only callers with database-only callbacks opt into replay. Uploads must not.
            if ($error instanceof mysqli_sql_exception && in_array($error->getCode(), [1205, 1213], true) && $attempt < $retries) {
                usleep(random_int(2000, 15000) * ($attempt + 1));
                continue;
            }
            throw $error;
        }
    }
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function money(mixed $value): string
{
    return 'Rp. ' . number_format((int)$value, 0, ',', '.');
}

function price_html(mixed $value): string
{
    $amount = (int)$value;
    return '<span class="money" data-money-idr="' . e($amount) . '">' . e(money($amount)) . '</span>';
}

function image_url(string $image): string
{
    return url(local_image_path($image) ?? 'assets/img/logo-mae.png');
}

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/currency.php';
require_once __DIR__ . '/views.php';
