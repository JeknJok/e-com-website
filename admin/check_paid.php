<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
require_post();
change_payment_status(positive_int(input('OrderID')), true);
flash('Order marked paid.');
redirect(url('admin/Orders_list.php'));
