<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/accounts.php';
$customer = require_customer();
$id = (int)$customer['CustomerID'];
handle_form(function () use ($id): void {
    save_customer($id);
    $_SESSION['ContactName'] = text_value('ContactName', 100);
    flash('Your profile has been updated.');
    redirect(url('admin/Customers_profile.php'));
});
$customer = row('SELECT ContactName, CEmail FROM Customers WHERE CustomerID = ?', [$id]);
page_start('Update demo account', 'customer');
portfolio_notice();
customer_form($customer, true);
page_end();
