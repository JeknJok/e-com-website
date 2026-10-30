<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/products.php';
require_admin();
handle_form(function (): void {
    save_product();
    flash('Product added.');
    redirect(url('admin/products_list.php'));
});
page_start('New product', 'admin');
product_form([], false);
page_end();
