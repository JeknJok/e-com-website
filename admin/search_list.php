<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
redirect(url('admin/products_list.php', ['search' => mb_substr(input('search', $source), 0, 100)]));
