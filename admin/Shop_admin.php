<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$customers = rows('SELECT CustomerID, CustomerName, ContactName FROM Customers WHERE is_active = 1 ORDER BY CustomerID');
page_start('Shop on behalf of a customer', 'admin');
?><div class="table-wrap" role="region" aria-label="Customers available for shopping" tabindex="0"><table class="table table-hover"><thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Shop</th></tr></thead><tbody>
<?php foreach ($customers as $customer): ?><tr><td><?= e($customer['CustomerID']) ?></td><td><?= e($customer['CustomerName']) ?></td><td><?= e($customer['ContactName']) ?></td><td><a class="btn btn-secondary" href="<?= e(url('admin/catalog_admin.php', ['cid' => $customer['CustomerID']])) ?>">Shop as <?= e($customer['ContactName']) ?></a></td></tr><?php endforeach ?>
</tbody></table></div><?php if (!$customers): ?><p>Create a customer before placing an order.</p><?php endif; page_end();
