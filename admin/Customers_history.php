<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$customer = require_customer();
$cid = (int)$customer['CustomerID'];
expire_orders($cid);
page_start('Order history', 'customer');
render_orders(list_orders($cid, true));
?><p class="invoice-note">Unpaid invoices expire after 24 hours. Paid and cancelled orders remain in your history.</p><?php page_end();
