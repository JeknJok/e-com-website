<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$admin = current_admin();
$customer = $admin ? null : require_customer();
require_post();
cancel_order(positive_int(input('OrderID')), $admin ? null : (int)$customer['CustomerID']);
flash('Order cancelled. Its details remain available in order history.');
redirect(url($admin ? 'admin/Orders_list.php' : 'admin/Customers_orders.php'));
