<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/accounts.php';
$admin = current_admin();
handle_form(function () use ($admin): void {
    save_customer();
    if ($admin) {
        flash('Customer created.');
        redirect(url('admin/Customers_list.php'));
    }
    login_account('customer', input('CEmail'), input('CPassword'));
    flash('Your account is ready. Welcome to M-A-E-Commerce.');
    redirect(url('admin/catalog.php'));
});
page_start($admin ? 'Create customer' : 'Register now', $admin ? 'admin' : 'public');
echo '<p class="text-muted">This is a portfolio prototype. Use fictional contact details.</p>';
customer_form([], false);
page_end();
