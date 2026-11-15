<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MAE_SETUP', true);
require dirname(__DIR__) . '/app/bootstrap.php';
$database = setting('DB_NAME', 'NealleDB');
if (!preg_match('/^[A-Za-z0-9_]+$/D', $database)) { throw new RuntimeException('Use a simple database identifier for provisioning.'); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$root = new mysqli(setting('DB_HOST', 'db'), 'root', setting('DB_ROOT_PASSWORD'), $database, (int)setting('DB_PORT', '3306'));
$identities = ['runtime' => 'mae_runtime', 'migration' => 'mae_migration', 'expiry' => 'mae_expiry', 'backup' => 'mae_backup', 'maintenance' => 'mae_maintenance'];
$passwords = array_map(fn($purpose) => setting('DB_' . strtoupper($purpose) . '_PASSWORD'), array_keys($identities));
if (count(array_unique([...$passwords, setting('DB_ROOT_PASSWORD')])) !== count($passwords) + 1) { throw new RuntimeException('Database identities must have distinct secrets.'); }
foreach ($identities as $purpose => $user) {
    $password = setting('DB_' . strtoupper($purpose) . '_PASSWORD');
    if (strlen($password) < 20) { throw new RuntimeException('Configure distinct generated database secret files before provisioning.'); }
    $statement = $root->prepare("CREATE USER IF NOT EXISTS '$user'@'%' IDENTIFIED BY ?");
    $statement->execute([$password]); $statement->close();
    $statement = $root->prepare("ALTER USER '$user'@'%' IDENTIFIED BY ?");
    $statement->execute([$password]); $statement->close();
    $root->query("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '$user'@'%'");
}
$root->query("GRANT ALL PRIVILEGES ON $database.* TO 'mae_migration'@'%'");
$ready = $root->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'SecurityEvents'")->fetch_row()[0];
if (!$ready) { echo "Identities ready. Run setup/migration with mae_migration, then repeat provisioning to grant runtime access.\n"; exit; }
foreach (['Customers', 'users_tb', 'Products', 'Orders', 'OrderDetails', 'RateLimits', 'RecoveryCodes'] as $table) {
    $root->query("GRANT SELECT, INSERT, UPDATE, DELETE ON $database.$table TO 'mae_runtime'@'%'");
}
$root->query("GRANT SELECT ON $database.MaeSchema TO 'mae_runtime'@'%'");
$root->query("GRANT INSERT ON $database.SecurityEvents TO 'mae_runtime'@'%'");
$root->query("GRANT SELECT, INSERT ON $database.ErasureLedger TO 'mae_runtime'@'%'");
$root->query("GRANT SELECT ON $database.MaeSchema TO 'mae_expiry'@'%'");
$root->query("GRANT SELECT (CustomerID, OrderID, OrderStatus, InvoiceExpiresAt), UPDATE (OrderStatus, InvoiceExpiresAt) ON $database.Orders TO 'mae_expiry'@'%'");
$root->query("GRANT SELECT, SHOW VIEW ON $database.* TO 'mae_backup'@'%'");
$root->query("GRANT SELECT ON $database.MaeSchema TO 'mae_maintenance'@'%'");
$root->query("GRANT SELECT (CustomerID, last_seen_at, erased_at, auth_version), UPDATE (CustomerName, ContactName, CAddress, City, PostalCode, Country, CEmail, CPassword, is_active, erased_at, auth_version) ON $database.Customers TO 'mae_maintenance'@'%'");
$root->query("GRANT SELECT (CustomerID, OrderStatus), UPDATE (OrderStatus, InvoiceExpiresAt) ON $database.Orders TO 'mae_maintenance'@'%'");
$root->query("GRANT SELECT (pcode, pimage) ON $database.Products TO 'mae_maintenance'@'%'");
foreach (['RateLimits', 'RecoveryCodes'] as $table) {
    $root->query("GRANT SELECT, DELETE ON $database.$table TO 'mae_maintenance'@'%'");
}
$root->query("GRANT INSERT, SELECT, DELETE ON $database.SecurityEvents TO 'mae_maintenance'@'%'");
$root->query("GRANT INSERT, SELECT ON $database.ErasureLedger TO 'mae_maintenance'@'%'");
$root->query("GRANT UPDATE (mfa_pending_secret, mfa_pending_expires), SELECT (mfa_pending_expires) ON $database.users_tb TO 'mae_maintenance'@'%'");
echo "Provisioned runtime CRUD, append-only runtime audit access, restricted expiry, read-only backup, and separate migration credentials. Root credentials stay out of the web service.\n";
