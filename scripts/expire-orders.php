<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MAE_NO_AUTH_JOB', true);
require dirname(__DIR__) . '/app/bootstrap.php';
expire_orders();
echo "Expired invoices cancelled.\n";
