<?php
declare(strict_types=1);

/** Display-only reference rates. All stored prices and order calculations remain IDR. */
final class ExchangeRates
{
    public const CURRENCIES = [
        'IDR' => 'Indonesian rupiah',
        'USD' => 'US dollar',
        'EUR' => 'Euro',
        'GBP' => 'British pound',
        'CAD' => 'Canadian dollar',
        'JPY' => 'Japanese yen',
    ];
    public const TTL = 3600;
    public const MAX_STALE = 604800;
    private const RETRY_DELAY = 60;
    private const URL = 'https://api.frankfurter.dev/v2/rates?base=IDR&quotes=USD,EUR,GBP,CAD,JPY';
    private string $cacheFile;
    private Closure $fetcher;
    private Closure $clock;

    public function __construct(?string $cacheFile = null, ?callable $fetcher = null, ?callable $clock = null)
    {
        $directory = getenv('FX_CACHE_DIR');
        $this->cacheFile = $cacheFile ?? ($directory ? rtrim($directory, '/\\') . '/rates.json' : sys_get_temp_dir() . '/mae-fx-' . substr(hash('sha256', __DIR__), 0, 16) . '.json');
        $this->fetcher = Closure::fromCallable($fetcher ?? [self::class, 'download']);
        $this->clock = Closure::fromCallable($clock ?? 'time');
    }

    public function latest(): array
    {
        $now = ($this->clock)();
        $cache = $this->readCache();
        $saved = $this->savedRates($cache, $now);
        if ($saved && $now - $cache['fetched_at'] < self::TTL) {
            return $this->response($saved, $cache['fetched_at'], false);
        }
        if (is_int($cache['attempted_at'] ?? null) && $cache['attempted_at'] <= $now && $now - $cache['attempted_at'] < self::RETRY_DELAY) {
            return $this->fallback($saved, $cache);
        }

        $lock = @fopen($this->cacheFile . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) { fclose($lock); }
            return $this->fallback($saved, $cache);
        }
        try {
            // Another process may have refreshed the cache between the first read and lock.
            $cache = $this->readCache();
            $saved = $this->savedRates($cache, $now);
            if ($saved && $now - $cache['fetched_at'] < self::TTL) {
                return $this->response($saved, $cache['fetched_at'], false);
            }
            if (is_int($cache['attempted_at'] ?? null) && $cache['attempted_at'] <= $now && $now - $cache['attempted_at'] < self::RETRY_DELAY) {
                return $this->fallback($saved, $cache);
            }
            try {
                $rows = json_decode(($this->fetcher)(), true, 32, JSON_THROW_ON_ERROR);
                $rates = self::validate($rows, $now);
            } catch (Throwable $error) {
                $this->writeCache(['attempted_at' => $now] + $cache);
                return $this->fallback($saved, $cache);
            }
            $this->writeCache(['attempted_at' => $now, 'fetched_at' => $now, 'rows' => $rows]);
            return $this->response($rates, $now, false);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function validate(mixed $rows, int $now): array
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 5) {
            throw new RuntimeException('Incomplete exchange-rate response.');
        }
        $rates = ['IDR' => 1.0];
        $dates = [];
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['base'] ?? null) !== 'IDR' || !is_string($row['quote'] ?? null) ||
                !isset(self::CURRENCIES[$row['quote']]) || isset($rates[$row['quote']]) ||
                !(is_int($row['rate'] ?? null) || is_float($row['rate'] ?? null)) ||
                !is_finite((float)$row['rate']) || $row['rate'] <= 0 || !is_string($row['date'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row['date'])) {
                throw new RuntimeException('Invalid exchange-rate response.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $row['date'], new DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d') !== $row['date'] || $date->getTimestamp() > $now || $now - $date->getTimestamp() > self::MAX_STALE) {
                throw new RuntimeException('Exchange-rate date is not current.');
            }
            $rates[$row['quote']] = (float)$row['rate'];
            $dates[$row['quote']] = $row['date'];
        }
        return ['base' => 'IDR', 'rates' => $rates, 'dates' => $dates];
    }

    private function response(array $rates, int $fetchedAt, bool $stale): array
    {
        return $rates + ['source' => 'Frankfurter', 'fetched_at' => gmdate('c', $fetchedAt), 'expires_at' => gmdate('c', $fetchedAt + self::TTL), 'stale' => $stale];
    }

    private function fallback(?array $saved, array $cache): array
    {
        if ($saved) { return $this->response($saved, $cache['fetched_at'], true); }
        throw new RuntimeException('Exchange rates are temporarily unavailable.');
    }

    private function readCache(): array
    {
        if (!is_file($this->cacheFile) || filesize($this->cacheFile) > 65536) { return []; }
        $data = @file_get_contents($this->cacheFile);
        if ($data === false) { return []; }
        try {
            $cache = json_decode($data, true, 32, JSON_THROW_ON_ERROR);
            return is_array($cache) ? $cache : [];
        } catch (JsonException $error) { return []; }
    }

    private function savedRates(array $cache, int $now): ?array
    {
        if (!is_int($cache['fetched_at'] ?? null) || $cache['fetched_at'] > $now || $now - $cache['fetched_at'] > self::MAX_STALE) { return null; }
        try { return self::validate($cache['rows'] ?? null, $now); }
        catch (RuntimeException $error) { return null; }
    }

    private function writeCache(array $cache): void
    {
        $temp = @tempnam(dirname($this->cacheFile), 'mae-fx-');
        if ($temp === false) { return; }
        try {
            if (@file_put_contents($temp, json_encode($cache, JSON_THROW_ON_ERROR), LOCK_EX) !== false) {
                @chmod($temp, 0600);
                @rename($temp, $this->cacheFile);
            }
        } finally { if (is_file($temp)) { @unlink($temp); } }
    }

    public static function download(): string
    {
        $context = stream_context_create([
            'http' => ['timeout' => 5, 'follow_location' => 0, 'header' => "Accept: application/json\r\nUser-Agent: MAE-Ecommerce-Currency/1.0\r\n"],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents(self::URL, false, $context, 0, 65537);
        if ($body === false || strlen($body) > 65536 || !preg_match('#^HTTP/\S+ 200\b#', $http_response_header[0] ?? '')) {
            throw new RuntimeException('Exchange-rate provider unavailable.');
        }
        return $body;
    }
}
