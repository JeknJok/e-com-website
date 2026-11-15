<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (current_admin()) { redirect(url('admin/dashboard.php')); }
$pending = $_SESSION['pending_admin'] ?? null;
if (!$pending || time() - (int)$pending['issued_at'] >= 300) { clear_authentication(); redirect(url('index.php') . '#login'); }
$configured = row('SELECT mfa_secret FROM users_tb WHERE id = ? AND auth_version = ?', [$pending['id'], $pending['version']]);
handle_form(function (): void {
    if (!confirm_admin_mfa(trim(input('code')))) { throw new HttpError(422, 'The confirmation was not accepted. Try a new authenticator code or an unused recovery code.'); }
    redirect(url('admin/dashboard.php'));
});
page_start('Administrator confirmation');
portfolio_notice();
if (!$configured || !$configured['mfa_secret']) {
    echo '<div class="form-panel"><p>This administrator needs an authenticator. The site operator must finish enrollment with the administrator MFA command before sign-in is available.</p></div>';
} else {
    ?><div class="form-panel"><p>Enter a current six-digit authenticator code, or an unused administrator recovery code. This step expires after five minutes.</p><?php form_errors(); ?><form method="post"><?= csrf_field() ?><label class="form-label" for="code">Authenticator or recovery code</label><input class="form-control mb-3" id="code" name="code" type="password" autocomplete="one-time-code" maxlength="40" required><button class="btn btn-primary" type="submit">Confirm administrator login</button></form></div><?php
}
page_end();
