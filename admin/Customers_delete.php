<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$id = positive_int(input('CustomerID', $_GET));
$customer = row('SELECT CustomerID, ContactName, is_active FROM Customers WHERE CustomerID = ?', [$id]);
if (!$customer) { throw new HttpError(404, 'Customer not found.'); }
$hasOrders = (bool)row('SELECT OrderID FROM Orders WHERE CustomerID = ? LIMIT 1', [$id]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $operation = input('operation');
    if (!in_array($operation, ['deactivate', 'delete'], true)) { throw new HttpError(422, 'Choose whether to deactivate or delete the customer.'); }
    transaction(function () use ($id, $operation): void {
        if (!row('SELECT CustomerID FROM Customers WHERE CustomerID = ? FOR UPDATE', [$id])) { throw new HttpError(404, 'Customer not found.'); }
        if ($operation === 'delete') {
            if (row('SELECT OrderID FROM Orders WHERE CustomerID = ? LIMIT 1 FOR UPDATE', [$id])) {
                throw new HttpError(409, 'This customer has order history. Deactivate the account to preserve its orders.');
            }
            execute('DELETE FROM Customers WHERE CustomerID = ?', [$id]);
        } else {
            execute('UPDATE Customers SET is_active = 0 WHERE CustomerID = ?', [$id]);
        }
    });
    unset($_SESSION['active_orders'][$id]);
    flash($operation === 'delete' ? 'Customer without order history deleted.' : 'Customer deactivated. All order history has been preserved.');
    redirect(url('admin/Customers_list.php'));
}
page_start($hasOrders ? 'Deactivate customer' : 'Delete customer', 'admin');
?><div class="form-panel"><p><?= $hasOrders ? 'Deactivate' : 'Permanently delete' ?> <?= e($customer['ContactName']) ?>?</p>
<p><?= $hasOrders ? 'This account has order history. Deactivation prevents sign-in and new orders while keeping every existing order. You can reactivate the account through Edit.' : 'This account has no orders. Its contact information and login will be permanently removed.' ?></p>
<form method="post"><?= csrf_field() ?><input type="hidden" name="operation" value="<?= $hasOrders ? 'deactivate' : 'delete' ?>"><button class="btn btn-danger" type="submit"><?= $hasOrders ? 'Confirm deactivation' : 'Confirm deletion' ?></button> <a class="btn btn-secondary" href="<?= e(url('admin/Customers_list.php')) ?>">Keep customer</a></form></div><?php page_end();
