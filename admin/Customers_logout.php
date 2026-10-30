<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_customer();
page_start('Log out', 'customer');
action_form('logout.php', [], 'Confirm logout');
page_end();
