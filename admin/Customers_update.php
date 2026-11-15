<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/accounts.php';
require_fresh_admin();
$id = positive_int(input('CustomerID', $_GET));
$customer = row('SELECT ContactName, CEmail, is_active FROM Customers WHERE CustomerID = ? AND erased_at IS NULL', [$id]);
if (!$customer) { throw new HttpError(404, 'Customer not found.'); }
audit_event('privacy.customer_detail_viewed', $id);
handle_form(function () use ($id): void {
    save_customer($id, true);
    flash('Customer updated.');
    redirect(url('admin/Customers_list.php'));
});
page_start('Update customer #' . $id, 'admin');
customer_form($customer, true, true);
page_end();
