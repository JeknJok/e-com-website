<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
if (!extension_loaded('gd') || !is_file(dirname(__DIR__) . '/vendor/autoload.php')) { throw new RuntimeException('Install GD and locked Composer dependencies before starting MAE.'); }
if (in_array('--database', $argv, true)) { db(); }
echo "MAE security configuration validated.\n";
