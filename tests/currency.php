<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/currency.php';

$checks = 0;
function verify(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function rejects(callable $callback, string $message): void
{
    try { $callback(); } catch (RuntimeException $error) { verify(true, $message); return; }
    throw new RuntimeException($message);
}
$now = strtotime('2026-01-09T12:00:00Z');
$rows = [];
foreach (['USD' => 0.00006, 'EUR' => 0.000055, 'GBP' => 0.000048, 'CAD' => 0.000083, 'JPY' => 0.009] as $currency => $rate) {
    $rows[] = ['base' => 'IDR', 'quote' => $currency, 'rate' => $rate, 'date' => '2026-01-09'];
}
$folder = sys_get_temp_dir() . '/mae-fx-test-' . bin2hex(random_bytes(8));
mkdir($folder, 0700);
$path = $folder . '/rates.json';
try {
    $calls = 0;
    $fetch = function () use (&$calls, $rows): string { $calls++; return json_encode($rows, JSON_THROW_ON_ERROR); };
    $clock = function () use (&$now): int { return $now; };
    $service = new ExchangeRates($path, $fetch, $clock);
    $first = $service->latest();
    verify($first['base'] === 'IDR' && count($first['rates']) === 6 && $first['rates']['IDR'] === 1.0, 'Returns all six supported currencies from one IDR base.');
    verify(abs(120000 * $first['rates']['USD'] - 7.2) < 0.000001, 'Converts a real catalog price from IDR.');
    verify($first['dates']['CAD'] === '2026-01-09' && !$first['stale'], 'Retains the rate date for each currency.');
    verify(is_file($path) && $calls === 1, 'First request fetches and caches rates.');
    $now += 3599;
    $secondProcess = new ExchangeRates($path, function (): string { throw new RuntimeException('Should use cache.'); }, $clock);
    verify($secondProcess->latest() === $first, 'A second process reuses fresh rates without a provider request.');
    $now += 2;
    $service->latest();
    verify($calls === 2, 'An expired cache refreshes its provider data.');
    $now += 3601;
    $failures = 0;
    $outage = new ExchangeRates($path, function () use (&$failures): string { $failures++; throw new RuntimeException('Provider offline.'); }, $clock);
    $saved = $outage->latest();
    verify($saved['stale'] && $saved['fetched_at'] !== gmdate('c', $now), 'Outage returns dated saved rates without pretending they are newly fetched.');
    verify($saved['rates'] === $first['rates'], 'Failure preserves every cached quote.');
    $outage->latest();
    verify($failures === 1, 'Outage backoff prevents repeated provider calls.');
    $lock = fopen($path . '.lock', 'c');
    flock($lock, LOCK_EX);
    $now += 61;
    verify($outage->latest()['stale'], 'Concurrent refresh contention serves saved rates.');
    flock($lock, LOCK_UN); fclose($lock);
    $now += ExchangeRates::MAX_STALE + 1;
    rejects(fn() => $outage->latest(), 'Expired saved rates are rejected rather than displayed indefinitely.');

    $now = strtotime('2026-01-09T12:00:00Z');
    $invalidCases = [];
    $bad = $rows; $bad[0]['rate'] = 0; $invalidCases[] = $bad;
    $bad = $rows; $bad[0]['rate'] = -1; $invalidCases[] = $bad;
    $bad = $rows; $bad[0]['rate'] = '0.00006'; $invalidCases[] = $bad;
    $bad = $rows; $bad[0]['base'] = 'USD'; $invalidCases[] = $bad;
    $bad = $rows; $bad[0]['quote'] = 'BTC'; $invalidCases[] = $bad;
    $bad = $rows; $bad[0]['quote'] = 'IDR'; $invalidCases[] = $bad;
    $bad = $rows; $bad[1]['quote'] = 'USD'; $invalidCases[] = $bad;
    $bad = $rows; $bad[0]['date'] = '2026-02-30'; $invalidCases[] = $bad;
    $bad = $rows; $bad[0]['date'] = '2026-01-10'; $invalidCases[] = $bad;
    $bad = $rows; $bad[0]['date'] = '2025-12-01'; $invalidCases[] = $bad;
    $bad = $rows; $bad[0]['date'] = "2026-01-09\0"; $invalidCases[] = $bad;
    $invalidCases[] = array_slice($rows, 0, 4);
    $invalidCases[] = ['rates' => $rows];
    foreach ($invalidCases as $bad) rejects(fn() => ExchangeRates::validate($bad, $now), 'Invalid provider data must be refused.');
    $badRows = $rows; $badRows[0]['date'] = '2026-01-08';
    verify(ExchangeRates::validate($badRows, $now)['dates']['USD'] === '2026-01-08', 'Different publication dates are preserved per quote.');
    file_put_contents($path, '{corrupted-cache');
    verify(!$service->latest()['stale'], 'Corrupt cache data is replaced by validated provider data.');
    unlink($path);
    $malformed = new ExchangeRates($path, fn() => '<html>Provider error</html>', $clock);
    rejects(fn() => $malformed->latest(), 'Malformed responses without a cache fail safely.');
    verify(!isset(json_decode(file_get_contents($path), true)['rows']), 'An error body is never cached as exchange rates.');
    echo "PASS: $checks exchange-rate service assertions.\n";
} finally {
    foreach (glob($folder . '/*') ?: [] as $file) unlink($file);
    rmdir($folder);
}
