<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MAE_NO_AUTH_JOB', true);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/privacy.php';
$options = getopt('', ['apply', 'export-ledger', 'reapply-ledger:']);
if (isset($options['export-ledger'])) {
    echo json_encode(rows('SELECT customer_id, erased_at FROM ErasureLedger ORDER BY customer_id'), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
    exit;
}
if (isset($options['reapply-ledger'])) {
    $path = realpath($options['reapply-ledger']);
    if (!$path || filesize($path) > 10485760 || str_starts_with($path, dirname(__DIR__) . '/')) { throw new RuntimeException('Use a bounded trusted ledger outside the web directory.'); }
    $ledger = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($ledger) || !array_is_list($ledger)) { throw new RuntimeException('Invalid erasure ledger.'); }
    $ids = [];
    foreach ($ledger as $entry) {
        if (!is_array($entry) || !isset($entry['customer_id'])) { throw new RuntimeException('Invalid erasure ledger entry.'); }
        $ids[] = positive_int($entry['customer_id']);
    }
    if (isset($options['apply'])) { foreach (array_unique($ids) as $id) { erase_customer($id); } }
    echo count(array_unique($ids)) . ' ledger identities ' . (isset($options['apply']) ? 'reapplied' : 'would be reapplied (dry run)') . ".\n";
    exit;
}
$expired = rows('SELECT CustomerID FROM Customers WHERE erased_at IS NULL AND last_seen_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY) ORDER BY CustomerID LIMIT 100');
if (isset($options['apply'])) {
    foreach ($expired as $account) { erase_customer((int)$account['CustomerID']); }
    execute('DELETE FROM RateLimits WHERE expires_at < UNIX_TIMESTAMP() LIMIT 10000');
    execute('DELETE FROM RecoveryCodes WHERE expires_at < CURRENT_TIMESTAMP LIMIT 10000');
    execute('DELETE FROM SecurityEvents WHERE occurred_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY) LIMIT 10000');
    execute('UPDATE users_tb SET mfa_pending_secret = NULL, mfa_pending_expires = NULL WHERE mfa_pending_expires < CURRENT_TIMESTAMP');
}
echo count($expired) . ' inactive demo accounts ' . (isset($options['apply']) ? 'erased' : 'would be erased (dry run; pass --apply)') . ". Batch limit: 100.\n";
