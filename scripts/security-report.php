<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MAE_NO_AUTH_JOB', true);
require dirname(__DIR__) . '/app/bootstrap.php';
$options = getopt('', ['backup-directory:']);
$counts = row("SELECT COALESCE(SUM(event_name = 'auth.login' AND actor_kind = 'admin' AND outcome = 'failure'),0) AS admin_failures, COALESCE(SUM(event_name = 'auth.mfa' AND outcome = 'failure'),0) AS mfa_failures, COALESCE(SUM(event_name = 'admin.created'),0) AS new_admins, COALESCE(SUM(event_name IN ('admin.updated','admin.mfa_enrolled','admin.recovery_rotated')),0) AS admin_security_changes FROM SecurityEvents WHERE occurred_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)");
$alerts = [];
if ((int)$counts['admin_failures'] >= 10) { $alerts[] = 'repeated_administrator_login_failures'; }
if ((int)$counts['mfa_failures'] >= 6) { $alerts[] = 'repeated_second_factor_failures'; }
if ((int)$counts['new_admins'] > 0) { $alerts[] = 'administrator_created'; }
if ((int)$counts['admin_security_changes'] > 0) { $alerts[] = 'administrator_security_changed'; }
$lastBackup = null;
if (isset($options['backup-directory'])) {
    $directory = realpath($options['backup-directory']);
    if (!$directory || str_starts_with($directory . '/', dirname(__DIR__) . '/')) { throw new RuntimeException('Use a private backup directory outside the website.'); }
    foreach (glob($directory . '/*.maebackup') as $file) { if (is_file($file) && !is_link($file)) { $lastBackup = max($lastBackup ?? 0, filemtime($file)); } }
    if ($lastBackup === null || time() - $lastBackup > 86400) { $alerts[] = 'encrypted_backup_missing_or_stale'; }
}
echo json_encode(['checked_at' => gmdate(DATE_ATOM), 'window_minutes' => 15, 'counts' => array_map('intval', $counts), 'last_local_backup_at' => $lastBackup === null ? null : gmdate(DATE_ATOM, $lastBackup), 'alerts' => $alerts], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
exit($alerts ? 2 : 0);
