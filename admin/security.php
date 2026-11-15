<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_admin();
handle_form(function () use ($admin): void {
    rate_limit('reauth', 'admin:' . $admin['id']);
    $operation = input('operation') ?: 'confirm';
    if (!in_array($operation, ['confirm', 'logout_all'], true)) { throw new HttpError(422, 'Choose an administrator security action.'); }
    $confirmed = transaction(function () use ($admin, $operation): bool {
        $account = row('SELECT pass, mfa_secret, mfa_last_counter, auth_version FROM users_tb WHERE id = ? FOR UPDATE', [$admin['id']]);
        if (!$account || (int)$account['auth_version'] !== (int)$_SESSION['auth']['version'] || !verify_password(input('current_password'), $account['pass'])) { return false; }
        $counter = valid_totp_counter(decrypt_secret($account['mfa_secret']), trim(input('code')), (int)$account['mfa_last_counter']);
        if ($counter === null) { return false; }
        execute('UPDATE users_tb SET mfa_last_counter = ? WHERE id = ?', [$counter, $admin['id']]);
        if ($operation === 'logout_all') { execute('UPDATE users_tb SET auth_version = auth_version + 1 WHERE id = ?', [$admin['id']]); }
        audit_event($operation === 'logout_all' ? 'account.sessions_revoked' : 'auth.reauthenticate', (int)$admin['id']);
        return true;
    });
    if (!$confirmed) { audit_event('auth.reauthenticate', (int)$admin['id'], 'failure'); throw new HttpError(422, 'Enter the current password and a new authenticator code.'); }
    if ($operation === 'logout_all') { clear_authentication(); flash('Administrator signed out on every device.'); redirect(url('index.php') . '#login'); }
    rotate_session_id();
    $_SESSION['auth']['mfa_at'] = time();
    flash('Administrator actions confirmed for five minutes.');
    redirect(url('admin/security.php'));
});
page_start('Administrator account security', 'admin');
?><div class="form-panel"><p>Account administration, detailed customer access, and payment-status changes require an administrator confirmation within the last five minutes. Authenticator codes cannot be reused, so wait for a new code if you just signed in.</p><?php form_errors(); ?><form method="post"><?= csrf_field() ?><?php field('current_password', 'Current administrator password', '', 'password', 256); ?><label class="form-label" for="code">New six-digit authenticator code</label><input class="form-control mb-3" id="code" name="code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required><div class="security-actions"><button class="btn btn-primary" name="operation" value="confirm" type="submit">Confirm sensitive actions</button><button class="btn btn-outline-danger" name="operation" value="logout_all" type="submit">Sign out all administrator devices</button></div></form><p class="form-text mt-3">Authenticator enrollment, replacement, and recovery-code rotation use the operator-only MFA command. Password-only web recovery cannot bypass this factor.</p></div><?php page_end();
