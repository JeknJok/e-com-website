<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/products.php';
require_admin();
$id = positive_int(input('pcode', $_GET));
$product = row('SELECT * FROM Products WHERE pcode = ?', [$id]);
if (!$product) { throw new HttpError(404, 'Product not found.'); }
handle_form(function () use ($id): void {
    save_product($id);
    flash('Product updated. Existing order prices are unchanged.');
    redirect(url('admin/products_list.php'));
});
page_start('Update product #' . $id, 'admin');
product_form($product, true);
page_end();
