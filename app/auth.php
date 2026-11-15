<?php
declare(strict_types=1);

function password_options(): array { return ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1]; }

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID, password_options());
}

function new_password(string $password, bool $administrator = false): string
{
    // Demo customers can use simple, disposable passwords. Administrators control the whole demo.
    $minimum = $administrator ? 15 : 8;
    if (!mb_check_encoding($password, 'UTF-8') || mb_strlen($password, 'UTF-8') < $minimum || mb_strlen($password, 'UTF-8') > 128 || strlen($password) > 1024 || str_contains($password, "\0")) {
        throw new HttpError(422, 'Use a ' . ($administrator ? 'unique administrator' : 'disposable demo') . ' password of ' . $minimum . '–128 characters.');
    }
    if ($administrator && in_array(strtolower($password), array_map('strtolower', file(__DIR__ . '/common-passwords.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)), true)) {
        throw new HttpError(422, 'Choose a less predictable administrator passphrase.');
    }
    return hash_password($password);
}

function verify_password(string $password, string $hash): bool
{
    if ($password === '' || strlen($password) > 1024 || str_contains($password, "\0") || !mb_check_encoding($password, 'UTF-8')) { return false; }
    if (str_starts_with($hash, '$2') && strlen($password) > 72) { return false; }
    return password_verify($password, $hash);
}

function clear_authentication(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
    $_SESSION = [];
    csrf_token();
}

function authenticated_account(string $kind): ?array
{
    $idKey = $kind === 'admin' ? 'AdminID' : 'CustomerID';
    if (!isset($_SESSION[$idKey])) { return null; }
    $now = time();
    $idle = $kind === 'admin' ? 900 : 1800;
    $absolute = $kind === 'admin' ? 28800 : 43200;
    $state = $_SESSION['auth'] ?? [];
    if (!isset($state['issued_at'], $state['last_seen'], $state['version']) || $state['issued_at'] > $now || $state['last_seen'] > $now || $now - (int)$state['last_seen'] >= $idle || $now - (int)$state['issued_at'] >= $absolute) {
        clear_authentication();
        return null;
    }
    $account = $kind === 'admin'
        ? row('SELECT id, firstname, lastname, email, auth_version, mfa_secret FROM users_tb WHERE id = ?', [$_SESSION[$idKey]])
        : row('SELECT CustomerID, ContactName, auth_version FROM Customers WHERE CustomerID = ? AND is_active = 1 AND erased_at IS NULL', [$_SESSION[$idKey]]);
    if (!$account || (int)$account['auth_version'] !== (int)$state['version'] || ($kind === 'admin' && (!$account['mfa_secret'] || empty($state['mfa_at'])))) {
        clear_authentication();
        return null;
    }
    $_SESSION['auth']['last_seen'] = $now;
    if ($kind === 'customer') {
        execute('UPDATE Customers SET last_seen_at = CURRENT_TIMESTAMP WHERE CustomerID = ? AND last_seen_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)', [$account['CustomerID']]);
    }
    unset($account['mfa_secret']);
    return $account;
}

function current_admin(): ?array { return authenticated_account('admin'); }
function current_customer(): ?array { return authenticated_account('customer'); }

function require_admin(): array
{
    if ($admin = current_admin()) { return $admin; }
    if (current_customer()) { throw new HttpError(403, 'Administrator access is required.'); }
    redirect(url(isset($_SESSION['pending_admin']) ? 'admin/mfa.php' : 'index.php') . (isset($_SESSION['pending_admin']) ? '' : '#login'));
}

function require_customer(): array
{
    if ($customer = current_customer()) { return $customer; }
    if (current_admin()) { throw new HttpError(403, 'Choose a customer from Shop As to place an order.'); }
    redirect(url('index.php') . '#login');
}

function require_fresh_admin(): array
{
    $admin = require_admin();
    if (time() - (int)($_SESSION['auth']['mfa_at'] ?? 0) >= 300) {
        throw new HttpError(403, 'Your administrator confirmation expired. Open Account security, confirm your password and authenticator, then submit this action again.');
    }
    return $admin;
}

function complete_login(string $kind, int $id, string $name, int $version, bool $mfa = false): void
{
    audit_event('auth.login', $id, 'success', $kind, $id);
    session_regenerate_id(true);
    $_SESSION = [$kind === 'admin' ? 'AdminID' : 'CustomerID' => $id, 'ContactName' => $name,
        'auth' => ['version' => $version, 'issued_at' => time(), 'last_seen' => time(), 'mfa_at' => $mfa ? time() : 0]];
    csrf_token();
}

function login_account(string $kind, string $email, string $password): bool
{
    if (!in_array($kind, ['admin', 'customer'], true)) { throw new LogicException('Invalid account kind.'); }
    $email = strtolower(trim($email));
    rate_limit('login', $kind . ':' . substr($email, 0, 254));
    $account = strlen($email) > 254 ? null : ($kind === 'admin'
        ? row('SELECT id AS account_id, firstname AS display_name, pass AS password_hash, auth_version, mfa_secret FROM users_tb WHERE email = ?', [$email])
        : row('SELECT CustomerID AS account_id, ContactName AS display_name, CPassword AS password_hash, auth_version FROM Customers WHERE CEmail = ? AND is_active = 1 AND erased_at IS NULL', [$email]));
    // A fixed Argon2id hash performs comparable work for a missing identity.
    $dummy = '$argon2id$v=19$m=19456,t=2,p=1$R2xYUkV5QXJ5MjB0Mkp3Rw$e8+EfdfRIjxjofZIrMje/UVW4e4ODnDQKgoLwABjrDE';
    $valid = verify_password($password, $account['password_hash'] ?? $dummy);
    if (!$account || !$valid || strlen($email) > 254) {
        audit_event('auth.login', null, 'failure', $kind, null);
        return false;
    }
    if (password_needs_rehash($account['password_hash'], PASSWORD_ARGON2ID, password_options())) {
        // Compare the old hash so a concurrent credential change cannot be overwritten.
        execute($kind === 'admin' ? 'UPDATE users_tb SET pass = ? WHERE id = ? AND pass = ?' : 'UPDATE Customers SET CPassword = ? WHERE CustomerID = ? AND CPassword = ?', [hash_password($password), $account['account_id'], $account['password_hash']]);
    }
    if ($kind === 'admin') {
        clear_authentication();
        $_SESSION['pending_admin'] = ['id' => (int)$account['account_id'], 'version' => (int)$account['auth_version'], 'issued_at' => time()];
        return true;
    }
    execute('UPDATE Customers SET last_seen_at = CURRENT_TIMESTAMP WHERE CustomerID = ?', [$account['account_id']]);
    complete_login($kind, (int)$account['account_id'], $account['display_name'], (int)$account['auth_version']);
    return true;
}

function totp(string $secret): \OTPHP\TOTP
{
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $otp = \OTPHP\TOTP::createFromSecret($secret);
    $otp->setIssuer('MAE Portfolio');
    return $otp;
}

function valid_totp_counter(string $secret, string $code, int $lastCounter): ?int
{
    if (!preg_match('/^\d{6}$/D', $code)) { return null; }
    $otp = totp($secret); $counter = intdiv(time(), 30);
    foreach ([$counter - 1, $counter, $counter + 1] as $candidate) {
        if ($candidate > $lastCounter && hash_equals($otp->at($candidate * 30), $code)) { return $candidate; }
    }
    return null;
}

function create_recovery_codes(string $kind, int $id, int $count = 1): array
{
    execute('DELETE FROM RecoveryCodes WHERE account_kind = ? AND account_id = ?', [$kind, $id]);
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $code = bin2hex(random_bytes(20));
        execute('INSERT INTO RecoveryCodes (token_hash, account_kind, account_id, expires_at) VALUES (?, ?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY))', [hash('sha256', $code), $kind, $id]);
        $codes[] = $code;
    }
    return $codes;
}

function consume_recovery_code(string $kind, int $id, string $code): bool
{
    if (!preg_match('/^[a-f0-9]{40}$/D', $code)) { return false; }
    $token = row('SELECT token_hash FROM RecoveryCodes WHERE token_hash = ? AND account_kind = ? AND account_id = ? AND expires_at > CURRENT_TIMESTAMP FOR UPDATE', [hash('sha256', $code), $kind, $id]);
    if (!$token) { return false; }
    execute('DELETE FROM RecoveryCodes WHERE token_hash = ?', [$token['token_hash']]);
    return true;
}

function confirm_admin_mfa(string $code): bool
{
    $pending = $_SESSION['pending_admin'] ?? null;
    if (!$pending || time() - (int)$pending['issued_at'] >= 300) { clear_authentication(); return false; }
    rate_limit('mfa', (string)$pending['id']);
    $account = transaction(function () use ($pending, $code): ?array {
        $admin = row('SELECT id, firstname, auth_version, mfa_secret, mfa_last_counter FROM users_tb WHERE id = ? FOR UPDATE', [$pending['id']]);
        if (!$admin || !$admin['mfa_secret'] || (int)$admin['auth_version'] !== (int)$pending['version']) { return null; }
        $counter = valid_totp_counter(decrypt_secret($admin['mfa_secret']), $code, (int)$admin['mfa_last_counter']);
        if ($counter === null && !consume_recovery_code('admin', (int)$admin['id'], $code)) { return null; }
        if ($counter !== null) { execute('UPDATE users_tb SET mfa_last_counter = ? WHERE id = ?', [$counter, $admin['id']]); }
        audit_event('auth.mfa', (int)$admin['id'], 'success', 'admin', (int)$admin['id']);
        return $admin;
    });
    if (!$account) { audit_event('auth.mfa', null, 'failure', 'anonymous', null); return false; }
    complete_login('admin', (int)$account['id'], $account['firstname'], (int)$account['auth_version'], true);
    return true;
}

function confirm_current_password(array $account, string $password): void
{
    rate_limit('reauth', 'customer:' . $account['CustomerID']);
    if (!verify_password($password, $account['CPassword'])) {
        audit_event('auth.reauthenticate', (int)$account['CustomerID'], 'failure');
        throw new HttpError(422, 'Enter your current demo password to confirm this change.');
    }
}

function refresh_customer_session(int $id, int $version): void
{
    if (isset($_SESSION['CustomerID']) && (int)$_SESSION['CustomerID'] === $id) {
        session_regenerate_id(true);
        $_SESSION['auth']['version'] = $version;
        $_SESSION['auth']['issued_at'] = $_SESSION['auth']['last_seen'] = time();
        unset($_SESSION['active_orders']);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}
