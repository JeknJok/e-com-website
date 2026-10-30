<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$admin = current_admin();
$customer = $admin ? null : require_customer();
$id = positive_int(input('OrderID', $_GET));
$order = accessible_order($id, $admin ? null : (int)$customer['CustomerID']);
expire_orders((int)$order['CustomerID']);
$order = accessible_order($id, $admin ? null : (int)$customer['CustomerID']);
$items = rows('SELECT pcode, pname, pprice, qty FROM OrderDetails WHERE OrderID = ? ORDER BY OrderDetailsID', [$id]);
page_start('Order #' . $id, $admin ? 'admin' : 'customer');
?><div class="order-meta"><?= order_status($order['OrderStatus']) ?><span>Ordered at <?= e($order['OrderDate']) ?> UTC</span></div>
<?php if ($order['OrderStatus'] === 'INVOICE'): ?><p>Payment window ends <?= e($order['InvoiceExpiresAt']) ?> UTC. Unpaid invoices are cancelled after 24 hours.</p><a class="btn btn-primary mb-3" href="<?= e(url($admin ? 'admin/catalog_admin.php' : 'admin/catalog.php', $admin ? ['cid' => $order['CustomerID'], 'oid' => $id] : ['oid' => $id])) ?>">Continue shopping</a><?php endif ?>
<div class="table-wrap" role="region" aria-label="Order items" tabindex="0"><table class="table table-hover"><thead><tr><th scope="col">Product code</th><th scope="col">Name</th><th scope="col" class="order-total">Unit price</th><th scope="col">Quantity</th><th scope="col" class="order-total">Subtotal</th></tr></thead><tbody>
<?php $total = $quantity = 0; foreach ($items as $item): $subtotal = (int)$item['pprice'] * (int)$item['qty']; $total += $subtotal; $quantity += (int)$item['qty']; ?><tr><td><?= e($item['pcode']) ?></td><td><?= e($item['pname']) ?></td><td class="order-total"><?= e(money($item['pprice'])) ?></td><td><?= e($item['qty']) ?></td><td class="order-total"><?= e(money($subtotal)) ?></td></tr><?php endforeach ?>
</tbody><tfoot><tr><th colspan="3" scope="row">Total</th><td><?= e($quantity) ?> items</td><td class="order-total"><strong><?= e(money($total)) ?></strong></td></tr></tfoot></table></div><?php if ($order['OrderStatus'] === 'INVOICE'): action_form('Customers_cancelOrder.php', ['OrderID' => $id], 'Cancel order', 'outline-danger'); endif; page_end();
