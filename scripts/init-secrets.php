<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['directory:']);
$directory = $options['directory'] ?? '';
if (!is_string($directory) || !str_starts_with($directory, '/')) { throw new RuntimeException('Provide --directory=/absolute/private/directory outside the checkout.'); }
if (!is_dir($directory) && !mkdir($directory, 0700, true)) { throw new RuntimeException('Could not create the secret directory.'); }
$directory = realpath($directory);
if (!$directory || is_link($options['directory']) || str_starts_with($directory . '/', dirname(__DIR__) . '/')) { throw new RuntimeException('Keep secrets outside the website.'); }
chmod($directory, 0700);
foreach (['app_key', 'backup_key', 'db_root', 'db_runtime', 'db_migration', 'db_expiry', 'db_backup', 'db_maintenance'] as $name) {
    $path = $directory . '/' . $name;
    if (file_exists($path)) { throw new RuntimeException('An existing secret was found; existing keys are never overwritten.'); }
}
foreach (['app_key', 'backup_key', 'db_root', 'db_runtime', 'db_migration', 'db_expiry', 'db_backup', 'db_maintenance'] as $name) {
    $path = $directory . '/' . $name;
    $handle = fopen($path, 'x');
    if (!$handle) { throw new RuntimeException('Could not create a secret file.'); }
    fwrite($handle, base64_encode(random_bytes(32)) . "\n");
    fclose($handle);
    // The private parent protects host access; file-backed Compose mounts retain file modes.
    // Readable files allow the unprivileged container user to read its explicitly granted mounts.
    chmod($path, 0444);
}
echo "Created eight private secret files. Keep the parent directory mode 0700, mount only each service's required files, and retain keys separately from encrypted backups.\n";
