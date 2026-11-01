<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_admin();
expire_orders();
$customers = row('SELECT COUNT(*) AS total FROM Customers WHERE is_active = 1')['total'];
$products = row('SELECT COUNT(*) AS total FROM Products WHERE is_active = 1')['total'];
$invoices = row("SELECT COUNT(*) AS total FROM Orders WHERE OrderStatus = 'INVOICE'")['total'];
$paid = row("SELECT COUNT(*) AS total FROM Orders WHERE OrderStatus = 'PAID'")['total'];
$revenue = row("SELECT COALESCE(SUM(d.pprice * d.qty), 0) AS total FROM OrderDetails d JOIN Orders o ON o.OrderID = d.OrderID WHERE o.OrderStatus = 'PAID'")['total'];
page_start('Dashboard', 'admin');
?><p class="page-intro">Welcome, <?= e($admin['firstname']) ?>.</p><div class="row g-3"><?php foreach (['Active customers' => [$customers, 'Customers_list.php'], 'Available products' => [$products, 'products_list.php'], 'Unpaid invoices' => [$invoices, 'Orders_list.php'], 'Paid orders' => [$paid, 'Orders_list.php'], 'Paid order value' => [$revenue, 'Orders_list.php']] as $label => [$value, $destination]): ?><div class="col-sm-6 col-lg-4"><a class="dashboard-link" href="<?= e(url('admin/' . $destination)) ?>"><h2><?= e($label) ?></h2><p><?= $label === 'Paid order value' ? price_html($value) : e($value) ?></p></a></div><?php endforeach ?></div><p class="text-muted mt-4">Payment status is managed manually in this portfolio prototype.</p><?php page_end();
