<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$id = positive_int(input('pcode', $_GET));
$product = row('SELECT pcode, pname FROM Products WHERE pcode = ? AND is_active = 1', [$id]);
if (!$product) { throw new HttpError(404, 'Available product not found.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    execute('UPDATE Products SET is_active = 0 WHERE pcode = ?', [$id]);
    flash('Product removed from the catalog. Existing order history is preserved.');
    redirect(url('admin/products_list.php'));
}
page_start('Archive product', 'admin');
?><p>Remove <?= e($product['pname']) ?> from the catalog? Existing orders will retain their product name and price. You can restore availability through Edit.</p><form method="post"><?= csrf_field() ?><button class="btn btn-danger" type="submit">Confirm archive</button> <a class="btn btn-secondary" href="<?= e(url('admin/products_list.php')) ?>">Keep product</a></form><?php page_end();
