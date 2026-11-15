<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_post();
$admin = current_admin();
$customer = $admin ? null : current_customer();
if ($admin || $customer) { audit_event('auth.logout'); }
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
if (!session_destroy() && ($admin || $customer)) {
    // Storage failure must not leave the old cookie usable. Revoke all account sessions.
    execute($admin ? 'UPDATE users_tb SET auth_version = auth_version + 1 WHERE id = ?' : 'UPDATE Customers SET auth_version = auth_version + 1 WHERE CustomerID = ?', [$admin ? $admin['id'] : $customer['CustomerID']]);
    audit_event('account.sessions_revoked', (int)($admin ? $admin['id'] : $customer['CustomerID']), 'success', $admin ? 'admin' : 'customer', (int)($admin ? $admin['id'] : $customer['CustomerID']));
}
redirect(url('index.php'));
