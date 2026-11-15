<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/privacy.php';
$customer = require_customer();
$id = (int)$customer['CustomerID'];
handle_form(function () use ($id): void {
    $account = row('SELECT CustomerID, CPassword, auth_version FROM Customers WHERE CustomerID = ? AND is_active = 1 AND erased_at IS NULL', [$id]);
    if (!$account) { throw new HttpError(403, 'Sign in again.'); }
    confirm_current_password($account, input('current_password'));
    if ((int)$account['auth_version'] !== (int)$_SESSION['auth']['version']) { throw new HttpError(403, 'Sign in again.'); }
    $operation = input('operation');
    if (!in_array($operation, ['recovery', 'logout_all', 'erase'], true)) { throw new HttpError(422, 'Choose an account security action.'); }
    if ($operation === 'erase' && input('confirmation') !== 'ERASE') { throw new HttpError(422, 'Type ERASE to confirm removal of this demo account.'); }
    $codes = transaction(function () use ($account, $id, $operation): array {
        $locked = row('SELECT auth_version FROM Customers WHERE CustomerID = ? FOR UPDATE', [$id]);
        if (!$locked || (int)$locked['auth_version'] !== (int)$account['auth_version']) { throw new HttpError(409, 'This account changed. Sign in again.'); }
        if ($operation === 'recovery') {
            $codes = create_recovery_codes('customer', $id);
            audit_event('account.recovery_rotated', $id);
            return $codes;
        }
        if ($operation === 'logout_all') {
            execute('UPDATE Customers SET auth_version = auth_version + 1 WHERE CustomerID = ?', [$id]);
            audit_event('account.sessions_revoked', $id);
        }
        return [];
    });
    if ($operation === 'erase') { erase_customer($id, (int)$account['auth_version']); }
    if ($operation !== 'recovery') { clear_authentication(); flash($operation === 'erase' ? 'Your demo account has been erased.' : 'Signed out on all devices.'); redirect(url('index.php') . '#login'); }
    $_SESSION['new_recovery_codes'] = $codes;
    flash('Your old recovery code no longer works. Save the new code below.');
    redirect(url('admin/account-security.php'));
});
$codes = $_SESSION['new_recovery_codes'] ?? [];
unset($_SESSION['new_recovery_codes']);
page_start('Account security', 'customer');
portfolio_notice();
if ($codes) {
    ?><div class="recovery-note" role="status"><h2>Save your recovery code</h2><p>This code is shown once and expires in 30 days. It replaces email recovery for this fictional account. Keep it private; anyone with it can reset your demo password.</p><?php foreach ($codes as $code): ?><code class="recovery-code"><?= e($code) ?></code><?php endforeach ?><p class="mb-0">Lost it? Generate a replacement here while signed in, or create another demo account.</p></div><?php
}
?><div class="form-panel"><p>Confirm your current demo password before changing security settings. Password and email changes revoke other sessions and old recovery codes.</p><?php form_errors(); ?><form method="post"><?= csrf_field() ?><?php field('current_password', 'Current demo password', '', 'password', 256); ?>
<div class="security-actions"><button class="btn btn-primary" name="operation" value="recovery" type="submit">Generate new recovery code</button><button class="btn btn-outline-dark" name="operation" value="logout_all" type="submit">Sign out all devices</button></div>
<hr><h2>Erase this demo account</h2><p>Your login and contact fields will be removed. Order items and totals remain linked to a deleted-account placeholder; unpaid demo invoices are cancelled.</p><label class="form-label" for="confirmation">Type ERASE to confirm</label><input class="form-control mb-3" id="confirmation" name="confirmation" autocomplete="off" maxlength="5"><button class="btn btn-outline-danger" name="operation" value="erase" type="submit">Erase my demo account</button></form></div><?php page_end();
