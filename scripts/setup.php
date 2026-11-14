<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MAE_SETUP', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$existing = row('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (\'Customers\', \'users_tb\', \'Products\', \'Orders\', \'OrderDetails\', \'MaeSchema\') LIMIT 1', [setting('DB_NAME', 'NealleDB')]);
if ($existing) {
    throw new RuntimeException('Database already contains application tables. Use scripts/migrate.php for a legacy database; setup will not overwrite it.');
}
foreach (explode(';', file_get_contents(dirname(__DIR__) . '/database/schema.sql')) as $sql) {
    if (trim($sql) !== '') { execute($sql); }
}
if (in_array('--seed', $argv, true)) {
    require dirname(__DIR__) . '/app/catalog-seed.php';
    $result = seed_catalog();
    echo "Catalog ready: {$result['added']} products added.\n";
}
echo "Database initialized. Create an administrator with scripts/create-admin.php.\n";
