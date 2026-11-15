<?php
declare(strict_types=1);

function write_all($stream, string $value): void
{
    for ($offset = 0, $length = strlen($value); $offset < $length;) {
        $written = fwrite($stream, substr($value, $offset));
        if (!$written) { throw new RuntimeException('Backup storage write failed.'); }
        $offset += $written;
    }
}

function backup_key(): string
{
    $key = base64_decode(setting('BACKUP_KEY'), true);
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES || (setting('APP_KEY') !== '' && hash_equals($key, app_key()))) {
        throw new RuntimeException('Use a separate private 32-byte base64 BACKUP_KEY.');
    }
    return $key;
}

function encrypt_backup(string $source, string $destination): void
{
    $input = fopen($source, 'rb'); $output = fopen($destination, 'xb');
    if (!$input || !$output) { throw new RuntimeException('Could not open backup streams.'); }
    chmod($destination, 0600);
    try {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push(backup_key());
        write_all($output, "MAE\x01" . $header);
        while (!feof($input)) {
            $chunk = fread($input, 1048576);
            if ($chunk === false) { throw new RuntimeException('Could not read backup input.'); }
            if ($chunk === '') { break; }
            $encrypted = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk);
            write_all($output, pack('N', strlen($encrypted)) . $encrypted);
        }
        $final = sodium_crypto_secretstream_xchacha20poly1305_push($state, '', '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        write_all($output, pack('N', strlen($final)) . $final);
    } finally { fclose($input); fclose($output); }
}

function read_exact($stream, int $length): string
{
    $value = '';
    while (strlen($value) < $length && !feof($stream)) {
        $chunk = fread($stream, $length - strlen($value));
        if ($chunk === false) { throw new RuntimeException('Backup stream failed.'); }
        $value .= $chunk;
    }
    if (strlen($value) !== $length) { throw new RuntimeException('Backup is incomplete.'); }
    return $value;
}

function decrypt_backup(string $source, string $destination): void
{
    $input = fopen($source, 'rb'); $output = fopen($destination, 'xb');
    if (!$input || !$output) { throw new RuntimeException('Could not open restore streams.'); }
    chmod($destination, 0600);
    try {
        if (read_exact($input, 4) !== "MAE\x01") { throw new RuntimeException('Unsupported backup format.'); }
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(read_exact($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), backup_key());
        $total = 0;
        while (true) {
            $size = unpack('Nsize', read_exact($input, 4))['size'];
            if ($size < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $size > 1048576 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) { throw new RuntimeException('Invalid backup block.'); }
            $message = sodium_crypto_secretstream_xchacha20poly1305_pull($state, read_exact($input, $size));
            if ($message === false) { throw new RuntimeException('Backup authentication failed.'); }
            [$plain, $tag] = $message;
            $total += strlen($plain);
            if ($total > 536870912) { throw new RuntimeException('Restore exceeds the demo backup size limit.'); }
            if (!in_array($tag, [SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL], true)) { throw new RuntimeException('Invalid backup tag.'); }
            write_all($output, $plain);
            if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                if (fread($input, 1) !== '') { throw new RuntimeException('Unexpected trailing backup data.'); }
                return;
            }
        }
    } finally { fclose($input); fclose($output); }
}

function dump_database(string $destination): void
{
    $connection = db();
    $output = fopen($destination, 'xb');
    if (!$output) { throw new RuntimeException('Could not create database backup.'); }
    chmod($destination, 0600);
    $supported = ['Customers', 'users_tb', 'Products', 'Orders', 'OrderDetails', 'Cart', 'RateLimits', 'RecoveryCodes', 'SecurityEvents', 'ErasureLedger', 'MaeSchema'];
    $existing = array_column(rows('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'), 'TABLE_NAME');
    if (array_diff($existing, $supported) || array_diff(array_slice($supported, 0, 5), $existing)) { throw new RuntimeException('Use a provider backup for databases containing unknown or missing application tables.'); }
    $tables = array_values(array_intersect($supported, $existing));
    if (row("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE <> 'InnoDB' LIMIT 1")) { throw new RuntimeException('Use an offline/provider backup for nontransactional legacy tables.'); }
    $connection->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT | MYSQLI_TRANS_START_READ_ONLY);
    try {
        write_all($output, "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\n");
        foreach ($tables as $table) {
            $definition = $connection->query('SHOW CREATE TABLE ' . $table)->fetch_row()[1];
            write_all($output, $definition . ";\n");
            $result = $connection->query('SELECT * FROM ' . $table, MYSQLI_USE_RESULT);
            while ($record = $result->fetch_row()) {
                $values = array_map(fn($value) => $value === null ? 'NULL' : "CONVERT(0x" . bin2hex((string)$value) . " USING utf8mb4)", $record);
                // An empty hex literal is not valid SQL; represent an empty string explicitly.
                $values = array_map(fn($value) => $value === 'CONVERT(0x USING utf8mb4)' ? "''" : $value, $values);
                write_all($output, 'INSERT INTO ' . $table . ' VALUES (' . implode(',', $values) . ");\n");
                if (ftell($output) > 400000000) { throw new RuntimeException('Database exceeds the demo backup size limit.'); }
            }
            $result->free();
        }
        write_all($output, "SET FOREIGN_KEY_CHECKS = 1;\n");
        $connection->commit();
    } catch (Throwable $error) { $connection->rollback(); throw $error; }
    finally { fclose($output); }
}

function backup_schema_version(): int
{
    if (!row("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'MaeSchema'")) { return 0; }
    $version = (int)(row('SELECT MAX(version) AS version FROM MaeSchema')['version'] ?? 0);
    if ($version < 0 || $version > 3) { throw new RuntimeException('Use the matching backup tool for this schema version.'); }
    return $version;
}

function import_database(string $source): void
{
    if (row('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() LIMIT 1')) { throw new RuntimeException('Restore requires an empty, isolated database.'); }
    $input = fopen($source, 'rb'); $statement = '';
    try {
        while (($line = fgets($input)) !== false) {
            $statement .= $line;
            if (strlen($statement) > 1048576) { throw new RuntimeException('Restore SQL statement exceeds its limit.'); }
            if (str_ends_with(rtrim($line), ';')) { db()->query($statement); $statement = ''; }
        }
        if (trim($statement) !== '') { throw new RuntimeException('Incomplete restore SQL.'); }
    } finally { fclose($input); }
}
