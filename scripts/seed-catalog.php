<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/catalog-seed.php';

$result = seed_catalog();
echo "Catalog ready: {$result['added']} added; {$result['skipped']} existing products kept.\n";
