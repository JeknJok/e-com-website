<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_admin();
handle_form(function () use ($admin): void {
    rate_limit('reauth', 'admin:' . $admin['id']);
    $confirmed = transaction(function () use ($admin): bool {
        $account = row('SELECT pass, mfa_secret, mfa_last_counter, auth_version FROM users_tb WHERE id = ? FOR UPDATE', [$admin['id']]);
        if (!$account || (int)$account['auth_version'] !== (int)$_SESSION['auth']['version'] || !verify_password(input('current_password'), $account['pass'])) { return false; }
        $counter = valid_totp_counter(decrypt_secret($account['mfa_secret']), trim(input('code')), (int)$account['mfa_last_counter']);
        if ($counter === null) { return false; }
        execute('UPDATE users_tb SET mfa_last_counter = ? WHERE id = ?', [$counter, $admin['id']]);
        audit_event('auth.reauthenticate', (int)$admin['id']);
        return true;
    });
    if (!$confirmed) { audit_event('auth.reauthenticate', (int)$admin['id'], 'failure'); throw new HttpError(422, 'Enter the current password and a new authenticator code.'); }
    session_regenerate_id(true);
    $_SESSION['auth']['mfa_at'] = time();
    flash('Administrator actions confirmed for five minutes.');
    redirect(url('admin/security.php'));
});
page_start('Administrator account security', 'admin');
?><div class="form-panel"><p>Account administration, detailed customer access, and payment-status changes require an administrator confirmation within the last five minutes. Authenticator codes cannot be reused, so wait for a new code if you just signed in.</p><?php form_errors(); ?><form method="post"><?= csrf_field() ?><?php field('current_password', 'Current administrator password', '', 'password', 256); ?><label class="form-label" for="code">New six-digit authenticator code</label><input class="form-control mb-3" id="code" name="code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required><button class="btn btn-primary" type="submit">Confirm sensitive actions</button></form><p class="form-text mt-3">Authenticator enrollment, replacement, and recovery-code rotation use the operator-only MFA command. Password-only web recovery cannot bypass this factor.</p></div><?php page_end();
