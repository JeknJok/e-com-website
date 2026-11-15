<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$email = strtolower(trim(setting('ADMIN_EMAIL')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    throw new RuntimeException('Set ADMIN_EMAIL to a valid email address.');
}
$hash = new_password(setting('ADMIN_PASSWORD'), true);
if (row('SELECT id FROM users_tb WHERE email = ?', [$email])) {
    throw new RuntimeException('That administrator already exists. No account was changed.');
}
transaction(function () use ($email, $hash): void {
    execute('INSERT INTO users_tb (firstname, lastname, email, pass, username, mobile) VALUES (?, ?, ?, ?, ?, ?)', ['Administrator', '', $email, $hash, '', '']);
    audit_event('admin.created', (int)db()->insert_id, 'success', 'operator', null);
});
echo "Administrator created. No password is printed or stored in configuration.\n";

echo "Enroll an authenticator using scripts/admin-mfa.php before this administrator can sign in.\n";
