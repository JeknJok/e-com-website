<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MAE_NO_AUTH_JOB', true);
require dirname(__DIR__) . '/app/bootstrap.php';
$apply = in_array('--apply', $argv, true); $count = 0;
$lock = fopen(media_lock_path(), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('An upload or backup is running. Retry later.'); }
try {
    foreach (new DirectoryIterator(dirname(__DIR__) . '/assets/uploads') as $entry) {
        if (!$entry->isFile() || $entry->isLink() || $entry->getMTime() > time() - 3600 || !preg_match('/^product_[0-9]+_[a-f0-9]{24}\.(?:jpg|png|webp)(?:\.part)?$/D', $entry->getFilename())) { continue; }
        if (row('SELECT pcode FROM Products WHERE pimage = ? LIMIT 1', ['assets/uploads/' . $entry->getFilename()])) { continue; }
        $count++;
        if ($apply && !unlink($entry->getPathname())) { throw new RuntimeException('Could not remove an unused upload.'); }
    }
} finally { flock($lock, LOCK_UN); fclose($lock); }
echo $count . ' unused images ' . ($apply ? 'removed' : 'would be removed (dry run; pass --apply)') . ".\n";
