<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MAE_SETUP', true);
define('MAE_NO_AUTH_JOB', true);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/backup.php';
$options = getopt('', ['create:', 'restore:', 'uploads:']);
if (isset($options['create']) === isset($options['restore'])) { throw new RuntimeException('Use --create=/private/path/snapshot.maebackup or --restore=/private/path/snapshot.maebackup --uploads=/private/empty/restore-media.'); }
backup_key();
$work = private_directory() . '/backup-' . bin2hex(random_bytes(12));
if (!mkdir($work, 0700)) { throw new RuntimeException('Could not create private backup workspace.'); }
$lock = fopen(media_lock_path(), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Media is busy. Retry the backup later.'); }
try {
    if (isset($options['create'])) {
        $destination = $options['create'];
        $parent = realpath(dirname($destination));
        if (!$parent || !str_starts_with($destination, '/') || str_starts_with($parent . '/', dirname(__DIR__) . '/') || is_file($destination)) { throw new RuntimeException('Choose a new backup path outside the website directory.'); }
        $version = backup_schema_version();
        dump_database($work . '/database.sql');
        $archive = new PharData($work . '/archive.tar');
        $archive->addFile($work . '/database.sql', 'database.sql');
        $manifest = ['format' => 1, 'schema' => $version, 'created_at' => gmdate(DATE_ATOM), 'files' => ['database.sql' => hash_file('sha256', $work . '/database.sql')]];
        foreach (new DirectoryIterator(dirname(__DIR__) . '/assets/uploads') as $entry) {
            if (!$entry->isFile() || $entry->isLink() || !preg_match('/^[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$/iD', $entry->getFilename())) { continue; }
            $name = 'uploads/' . $entry->getFilename();
            $archive->addFile($entry->getPathname(), $name);
            $manifest['files'][$name] = hash_file('sha256', $entry->getPathname());
        }
        $archive->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        unset($archive);
        if (filesize($work . '/archive.tar') > 536870912) { throw new RuntimeException('Archive exceeds the demo backup limit.'); }
        encrypt_backup($work . '/archive.tar', $destination . '.part');
        if (!rename($destination . '.part', $destination)) { throw new RuntimeException('Could not finalize encrypted backup.'); }
        echo "Encrypted backup created. Copy it to separated protected storage and test restoration.\n";
        error_log(json_encode(['security_event' => 'backup.created', 'request_id' => request_id()]));
    } else {
        $uploads = $options['uploads'] ?? '';
        $uploads = $uploads !== '' ? realpath($uploads) : false;
        if (!$uploads || str_starts_with($uploads . '/', dirname(__DIR__) . '/') || count(array_diff(scandir($uploads), ['.', '..'])) !== 0) { throw new RuntimeException('Restore media into an empty isolated directory outside the website.'); }
        if (row('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() LIMIT 1')) { throw new RuntimeException('Restore requires an empty isolated database.'); }
        decrypt_backup($options['restore'], $work . '/archive.tar');
        $archive = new PharData($work . '/archive.tar');
        if (!isset($archive['manifest.json']) || $archive['manifest.json']->getSize() > 1048576) { throw new RuntimeException('Missing or invalid backup manifest.'); }
        $manifest = json_decode($archive['manifest.json']->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 1 || !in_array($manifest['schema'] ?? null, [0, 1, 2, 3], true) || !is_array($manifest['files'] ?? null) || !isset($manifest['files']['database.sql'])) { throw new RuntimeException('Unsupported backup manifest.'); }
        $names = [];
        foreach (new RecursiveIteratorIterator($archive) as $entry) {
            $name = substr($entry->getPathname(), strlen('phar://' . $work . '/archive.tar/'));
            if ($entry->isLink() || !$entry->isFile() || ($name !== 'manifest.json' && $name !== 'database.sql' && !preg_match('#^uploads/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$#iD', $name))) { throw new RuntimeException('Unsafe backup archive entry.'); }
            if ($name === 'manifest.json') { continue; }
            if (!isset($manifest['files'][$name]) || !hash_equals((string)$manifest['files'][$name], hash_file('sha256', $entry->getPathname()))) { throw new RuntimeException('Backup file checksum failed.'); }
            $names[] = $name;
        }
        if (count($names) !== count($manifest['files'])) { throw new RuntimeException('Incomplete backup archive.'); }
        $archive->extractTo($work . '/restored', array_merge(['manifest.json'], $names), false);
        import_database($work . '/restored/database.sql');
        foreach ($names as $name) { if (str_starts_with($name, 'uploads/')) { if (!copy($work . '/restored/' . $name, $uploads . '/' . basename($name))) { throw new RuntimeException('Could not restore media.'); } } }
        echo "Restored into the isolated database and media directory. Run migrations for legacy snapshots, then reapply the latest separately retained erasure ledger before any release. Verify the restore before switching traffic.\n";
        error_log(json_encode(['security_event' => 'backup.restored', 'request_id' => request_id()]));
    }
} finally {
    flock($lock, LOCK_UN); fclose($lock);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
    rmdir($work);
}
