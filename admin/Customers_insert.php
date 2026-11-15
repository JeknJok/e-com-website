<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/accounts.php';
$admin = current_admin();
handle_form(function () use ($admin): void {
    $id = save_customer(null, (bool)$admin);
    if ($admin) {
        flash('Customer created.');
        redirect(url('admin/Customers_list.php'));
    }
    login_account('customer', input('CEmail'), input('CPassword'));
    $_SESSION['new_recovery_codes'] = transaction(fn() => create_recovery_codes('customer', $id));
    flash('Your demo account is ready. Save your recovery code, then explore the catalog.');
    redirect(url('admin/account-security.php'));
});
page_start($admin ? 'Create customer' : 'Register now', $admin ? 'admin' : 'public');
portfolio_notice();
customer_form([], false);
page_end();
