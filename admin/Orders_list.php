<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
expire_orders();
page_start('Orders', 'admin');
render_orders(list_orders(), true);
?><p class="invoice-note">Payment status is recorded manually. A paid invoice can be reopened only when the customer has no other open invoice.</p><?php page_end();
