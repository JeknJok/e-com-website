<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$page = max(1, min(10000, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * 50;
$customers = rows('SELECT c.CustomerID, c.ContactName, c.is_active, c.erased_at, EXISTS(SELECT 1 FROM Orders o WHERE o.CustomerID = c.CustomerID) AS has_orders FROM Customers c ORDER BY c.CustomerID LIMIT 51 OFFSET ' . $offset);
$hasNext = count($customers) > 50;
$customers = array_slice($customers, 0, 50);
page_start('Demo customers', 'admin');
?><p>Only account aliases and status appear here. Detailed account access requires recent administrator confirmation and is recorded in the audit trail.</p><a class="btn btn-primary mb-3" href="<?= e(url('admin/Customers_insert.php')) ?>">Create demo customer</a>
<div class="table-wrap" role="region" aria-label="Customers" tabindex="0"><table class="table table-hover"><thead><tr><th>ID</th><th>Demo username</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($customers as $customer): ?><tr><td><?= e($customer['CustomerID']) ?></td><td><?= e($customer['ContactName']) ?></td><td><?= $customer['erased_at'] ? 'Erased' : ($customer['is_active'] ? 'Active' : 'Deactivated') ?></td><td><?php if (!$customer['erased_at']): ?><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/Customers_update.php', ['CustomerID' => $customer['CustomerID']])) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= e(url('admin/Customers_delete.php', ['CustomerID' => $customer['CustomerID']])) ?>"><?= $customer['has_orders'] ? 'Deactivate' : 'Delete' ?></a><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div>
<nav aria-label="Customer pages"><?php if ($page > 1): ?><a class="btn btn-outline-dark" href="<?= e(url('admin/Customers_list.php', ['page' => $page - 1])) ?>">Previous</a><?php endif ?> <?php if ($hasNext): ?><a class="btn btn-outline-dark" href="<?= e(url('admin/Customers_list.php', ['page' => $page + 1])) ?>">Next</a><?php endif ?></nav>
<?php if (!$customers): ?><p>No demo customers yet.</p><?php endif; page_end();
