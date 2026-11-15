<?php
declare(strict_types=1);

function upgrade_security_schema(): void
{
    $columns = [
        'Customers' => ['auth_version' => 'INT UNSIGNED NOT NULL DEFAULT 1', 'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'last_seen_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'erased_at' => 'DATETIME NULL'],
        'users_tb' => ['auth_version' => 'INT UNSIGNED NOT NULL DEFAULT 1', 'mfa_secret' => 'TEXT NULL', 'mfa_last_counter' => 'BIGINT NOT NULL DEFAULT -1', 'mfa_pending_secret' => 'TEXT NULL', 'mfa_pending_expires' => 'DATETIME NULL'],
    ];
    foreach ($columns as $table => $definitions) {
        foreach ($definitions as $column => $definition) {
            if (!row('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column])) {
                execute("ALTER TABLE $table ADD $column $definition, ALGORITHM=COPY");
            }
        }
    }
    if (!row("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Customers' AND INDEX_NAME = 'customers_retention'")) {
        execute('ALTER TABLE Customers ADD INDEX customers_retention (erased_at, last_seen_at)');
    }
    foreach (explode(';', file_get_contents(dirname(__DIR__) . '/database/security-tables.sql')) as $sql) {
        if (trim($sql) !== '') { execute($sql); }
    }
    execute('INSERT IGNORE INTO MaeSchema (version) VALUES (3)');
    echo "Security schema version 3 ready. Existing sessions must sign in again; enroll each administrator with scripts/admin-mfa.php.\n";
}
