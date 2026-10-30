<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MAE_SETUP', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$schema = setting('DB_NAME', 'NealleDB');
$marker = row('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$schema, 'MaeSchema']);
if ($marker && row('SELECT version FROM MaeSchema WHERE version = 2')) {
    echo "Database is already current.\n";
    exit;
}
// Explicit COPY rebuilds avoid instant-add metadata inconsistencies on older MariaDB.
// Version 1 is the earlier repair schema. Upgrade it without repeating legacy transforms.
if ($marker && row('SELECT version FROM MaeSchema WHERE version = 1')) {
    if (!row('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$schema, 'Customers', 'is_active'])) {
        execute('ALTER TABLE Customers ADD is_active TINYINT(1) NOT NULL DEFAULT 1, ALGORITHM=COPY');
    }
    execute('INSERT INTO MaeSchema (version) VALUES (2)');
    echo "Migration complete; customer availability added and order history retained.\n";
    exit;
}
foreach (['Customers', 'users_tb', 'Products', 'Orders', 'OrderDetails'] as $table) {
    if (!row('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$schema, $table])) {
        throw new RuntimeException('Required legacy table is missing: ' . $table . '. Restore the complete database before migrating.');
    }
}
// Preflight before DDL: never silently remove or merge existing accounts/items.
foreach ([['Customers', 'CEmail'], ['users_tb', 'email']] as [$table, $column]) {
    if (row("SELECT LOWER(TRIM($column)) FROM $table GROUP BY LOWER(TRIM($column)) HAVING COUNT(*) > 1 LIMIT 1")) {
        throw new RuntimeException('Duplicate email addresses exist in ' . $table . '. Resolve duplicates in a backup copy before migration.');
    }
}
if (row('SELECT OrderID, pcode FROM OrderDetails GROUP BY OrderID, pcode HAVING COUNT(*) > 1 LIMIT 1')) {
    throw new RuntimeException('Duplicate product lines exist in OrderDetails. Resolve them before migration; no rows have been deleted.');
}
execute("UPDATE Customers SET PostalCode = '' WHERE PostalCode IS NULL");
foreach (['Customers', 'users_tb', 'Products', 'Orders', 'OrderDetails'] as $table) {
    execute("ALTER TABLE $table ENGINE=InnoDB");
    execute("ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
execute("ALTER TABLE Customers MODIFY CustomerName VARCHAR(100) NOT NULL, MODIFY ContactName VARCHAR(100) NOT NULL, MODIFY City VARCHAR(100) NOT NULL, MODIFY Country VARCHAR(100) NOT NULL, MODIFY PostalCode VARCHAR(20) NOT NULL DEFAULT '', MODIFY CEmail VARCHAR(254) NOT NULL, MODIFY CPassword VARCHAR(255) NOT NULL");
execute("UPDATE users_tb SET lastname = '' WHERE lastname IS NULL");
execute("ALTER TABLE users_tb MODIFY firstname VARCHAR(100) NOT NULL, MODIFY lastname VARCHAR(100) NOT NULL DEFAULT '', MODIFY email VARCHAR(254) NOT NULL, MODIFY pass VARCHAR(255) NOT NULL, MODIFY username VARCHAR(100) NOT NULL DEFAULT '', MODIFY mobile VARCHAR(30) NOT NULL DEFAULT ''");
execute('ALTER TABLE Products MODIFY pname VARCHAR(100) NOT NULL, MODIFY pcategory VARCHAR(100) NOT NULL');

if (!row('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$schema, 'Customers', 'is_active'])) {
    execute('ALTER TABLE Customers ADD is_active TINYINT(1) NOT NULL DEFAULT 1, ALGORITHM=COPY');
}

if (!row('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$schema, 'Products', 'is_active'])) {
    execute('ALTER TABLE Products ADD is_active TINYINT(1) NOT NULL DEFAULT 1, ALGORITHM=COPY');
}
if (!row('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$schema, 'Orders', 'InvoiceExpiresAt'])) {
    execute('ALTER TABLE Orders ADD InvoiceExpiresAt DATETIME NULL, ALGORITHM=COPY');
}
execute("UPDATE Orders SET InvoiceExpiresAt = DATE_ADD(OrderDate, INTERVAL 24 HOUR) WHERE OrderStatus = 'INVOICE' AND InvoiceExpiresAt IS NULL");
foreach ([['Customers', 'CustomerID', 'CEmail', 'CPassword'], ['users_tb', 'id', 'email', 'pass']] as [$table, $id, $email, $password]) {
    execute("UPDATE $table SET $email = LOWER(TRIM($email))");
    foreach (rows("SELECT $id, $password FROM $table") as $account) {
        if (password_get_info($account[$password])['algo'] === null) {
            execute("UPDATE $table SET $password = ? WHERE $id = ?", [password_hash($account[$password], PASSWORD_DEFAULT), $account[$id]]);
        }
    }
}
foreach ([['Customers', 'customers_email', 'UNIQUE', 'CEmail'], ['users_tb', 'users_email', 'UNIQUE', 'email'], ['OrderDetails', 'order_product', 'UNIQUE', 'OrderID, pcode'], ['Orders', 'orders_customer_status', '', 'CustomerID, OrderStatus'], ['Orders', 'orders_expiry', '', 'OrderStatus, InvoiceExpiresAt']] as [$table, $name, $type, $columns]) {
    if (!row('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?', [$schema, $table, $name])) {
        execute("ALTER TABLE $table ADD $type INDEX $name ($columns)");
    }
}
execute('CREATE TABLE IF NOT EXISTS MaeSchema (version INT PRIMARY KEY) ENGINE=InnoDB');
execute('INSERT INTO MaeSchema (version) VALUES (2)');
expire_orders();
$orphans = row('SELECT COUNT(*) AS count FROM OrderDetails d LEFT JOIN Orders o ON o.OrderID = d.OrderID WHERE o.OrderID IS NULL');
echo "Migration complete; existing passwords are hashed and records retained.\n";
echo 'Pre-existing orphan detail rows retained for manual review: ' . $orphans['count'] . ".\n";
