<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$customers = rows('SELECT c.CustomerID, c.CustomerName, c.ContactName, c.CAddress, c.City, c.PostalCode, c.Country, c.CEmail, c.is_active, EXISTS(SELECT 1 FROM Orders o WHERE o.CustomerID = c.CustomerID) AS has_orders FROM Customers c ORDER BY c.CustomerID');
page_start('Customers', 'admin');
?><a class="btn btn-primary mb-3" href="<?= e(url('admin/Customers_insert.php')) ?>">Create customer</a>
<div class="table-wrap" role="region" aria-label="Customers" tabindex="0"><table class="table table-hover"><thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($customers as $customer): ?><tr><td><?= e($customer['CustomerID']) ?></td><td><?= e($customer['CustomerName']) ?></td><td><?= e($customer['ContactName']) ?></td><td><?= e($customer['CEmail']) ?></td><td><?= e(implode(', ', [$customer['CAddress'], $customer['City'], $customer['PostalCode'], $customer['Country']])) ?></td><td><?= $customer['is_active'] ? 'Active' : 'Deactivated' ?></td><td><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/Customers_update.php', ['CustomerID' => $customer['CustomerID']])) ?>">Edit</a><?php if ($customer['is_active'] || !$customer['has_orders']): ?> <a class="btn btn-sm btn-outline-danger" href="<?= e(url('admin/Customers_delete.php', ['CustomerID' => $customer['CustomerID']])) ?>"><?= $customer['has_orders'] ? 'Deactivate' : 'Delete' ?></a><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div><?php if (!$customers): ?><p>No customers yet.</p><?php endif; page_end();
