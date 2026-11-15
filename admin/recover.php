<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
handle_form(function (): void {
    $email = strtolower(trim(input('email')));
    rate_limit('recover', substr($email, 0, 254));
    $code = trim(input('recovery_code'));
    $hash = new_password(input('new_password'));
    $restored = transaction(function () use ($email, $code, $hash): bool {
        $account = row('SELECT CustomerID FROM Customers WHERE CEmail = ? AND is_active = 1 AND erased_at IS NULL FOR UPDATE', [$email]);
        if (!$account || !consume_recovery_code('customer', (int)$account['CustomerID'], $code)) { return false; }
        execute('UPDATE Customers SET CPassword = ?, auth_version = auth_version + 1 WHERE CustomerID = ?', [$hash, $account['CustomerID']]);
        execute("DELETE FROM RecoveryCodes WHERE account_kind = 'customer' AND account_id = ?", [$account['CustomerID']]);
        audit_event('account.recovered', (int)$account['CustomerID'], 'success', 'anonymous', null);
        return true;
    });
    if (!$restored) { audit_event('account.recovery_failed', null, 'failure', 'anonymous', null); throw new HttpError(422, 'Those demo recovery details were not accepted. Check your saved code, or create a new fictional account.'); }
    clear_authentication();
    flash('Demo password reset. Sign in with your new password, then generate a new recovery code in Account security.');
    redirect(url('index.php') . '#login');
});
page_start('Recover a demo account');
portfolio_notice();
?><div class="form-panel"><p>No email is sent. Use the private recovery code saved when you created the account or last generated a replacement. Administrator recovery is handled by the site operator.</p><?php form_errors(); ?><form method="post"><?= csrf_field() ?><?php field('email', 'Fictional login email', '', 'email', 254); field('recovery_code', 'Unused recovery code', '', 'password', 40); field('new_password', 'New demo password (8–128 characters)', '', 'password', 256); ?><button class="btn btn-primary" type="submit">Reset demo password</button></form></div><?php page_end();
