<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
require_post();
change_payment_status(positive_int(input('OrderID')), false);
flash('Order reopened with a new 24-hour payment window.');
redirect(url('admin/Orders_list.php'));
