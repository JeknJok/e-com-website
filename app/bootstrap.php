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
        error_log((string)$error);
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

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('mae_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => rtrim(setting('APP_BASE_PATH'), '/') . '/',
        'secure' => setting('COOKIE_SECURE') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=utf-8');
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

function new_password(string $password): string
{
    if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new HttpError(422, 'Use a password between 8 and 72 bytes long.');
    }
    return password_hash($password, PASSWORD_DEFAULT);
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
                $ready = $connection->query('SELECT version FROM MaeSchema WHERE version = 2')->fetch_assoc();
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

function current_admin(): ?array
{
    return isset($_SESSION['AdminID']) ? row('SELECT id, firstname, lastname, email FROM users_tb WHERE id = ?', [$_SESSION['AdminID']]) : null;
}

function current_customer(): ?array
{
    if (!isset($_SESSION['CustomerID'])) { return null; }
    $customer = row('SELECT CustomerID, ContactName FROM Customers WHERE CustomerID = ? AND is_active = 1', [$_SESSION['CustomerID']]);
    if (!$customer) {
        unset($_SESSION['CustomerID'], $_SESSION['ContactName'], $_SESSION['active_orders']);
    }
    return $customer;
}

function require_admin(): array
{
    if ($admin = current_admin()) {
        return $admin;
    }
    if (current_customer()) {
        throw new HttpError(403, 'Administrator access is required.');
    }
    redirect(url('index.php') . '#login');
}

function require_customer(): array
{
    if ($customer = current_customer()) {
        return $customer;
    }
    if (current_admin()) {
        throw new HttpError(403, 'Choose a customer from Shop As to place an order.');
    }
    redirect(url('index.php') . '#login');
}

function login_account(string $kind, string $email, string $password): bool
{
    $accounts = $kind === 'admin'
        ? rows('SELECT id AS account_id, firstname AS display_name, pass AS password_hash FROM users_tb WHERE email = ?', [strtolower(trim($email))])
        : rows('SELECT CustomerID AS account_id, ContactName AS display_name, CPassword AS password_hash FROM Customers WHERE CEmail = ? AND is_active = 1', [strtolower(trim($email))]);
    if (count($accounts) !== 1 || $password === '' || strlen($password) > 72 || !password_verify($password, $accounts[0]['password_hash'])) {
        return false;
    }
    $account = $accounts[0];
    if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        execute($kind === 'admin' ? 'UPDATE users_tb SET pass = ? WHERE id = ?' : 'UPDATE Customers SET CPassword = ? WHERE CustomerID = ?', [$hash, $account['account_id']]);
    }
    session_regenerate_id(true);
    $_SESSION = [$kind === 'admin' ? 'AdminID' : 'CustomerID' => (int)$account['account_id'], 'ContactName' => $account['display_name']];
    csrf_token();
    return true;
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function money(mixed $value): string
{
    return 'Rp. ' . number_format((int)$value, 0, ',', '.');
}

function image_url(string $image): string
{
    if (filter_var($image, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        return $image;
    }
    $image = preg_replace('#^(\.\./|\./)+#', '', $image);
    if (str_starts_with($image, 'assets/') && !str_contains($image, '..') && !str_contains($image, '\\')) {
        return url($image);
    }
    return url('assets/img/logo-mae.png');
}

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/views.php';
