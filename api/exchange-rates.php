<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/currency.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Use GET to retrieve exchange rates.']);
    exit;
}
try {
    echo json_encode((new ExchangeRates())->latest(), JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    header('Retry-After: 60');
    echo json_encode(['error' => 'Exchange rates are temporarily unavailable. Prices remain in IDR.']);
}
