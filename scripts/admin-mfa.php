<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$options = getopt('', ['email:', 'begin', 'confirm', 'rotate-recovery']);
$email = strtolower(trim((string)($options['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || count(array_intersect(['begin', 'confirm', 'rotate-recovery'], array_keys($options))) !== 1) {
    throw new RuntimeException('Use --email=owner@example.test and exactly one of --begin, --confirm, or --rotate-recovery. Supply ADMIN_PASSWORD and MFA_CODE through the environment or secret files.');
}
$result = transaction(function () use ($email, $options): array {
    $account = row('SELECT * FROM users_tb WHERE email = ? FOR UPDATE', [$email]);
    if (!$account || !verify_password(setting('ADMIN_PASSWORD'), $account['pass'])) { throw new RuntimeException('Administrator confirmation failed.'); }
    $id = (int)$account['id'];
    if (isset($options['confirm'])) {
        if (!$account['mfa_pending_secret'] || !$account['mfa_pending_expires'] || strtotime($account['mfa_pending_expires'] . ' UTC') <= time()) { throw new RuntimeException('Start a new MFA enrollment.'); }
        $counter = valid_totp_counter(decrypt_secret($account['mfa_pending_secret']), setting('MFA_CODE'), -1);
        if ($counter === null) { throw new RuntimeException('New authenticator code was not accepted.'); }
        execute('UPDATE users_tb SET mfa_secret = mfa_pending_secret, mfa_pending_secret = NULL, mfa_pending_expires = NULL, mfa_last_counter = ?, auth_version = auth_version + 1 WHERE id = ?', [$counter, $id]);
        audit_event('admin.mfa_enrolled', $id, 'success', 'operator', null);
        return ['codes' => create_recovery_codes('admin', $id, 8)];
    }
    if ($account['mfa_secret']) {
        $counter = valid_totp_counter(decrypt_secret($account['mfa_secret']), setting('MFA_CODE'), (int)$account['mfa_last_counter']);
        if ($counter === null && !consume_recovery_code('admin', $id, setting('MFA_CODE'))) { throw new RuntimeException('Confirm the existing authenticator or an unused recovery code.'); }
        if ($counter !== null) { execute('UPDATE users_tb SET mfa_last_counter = ? WHERE id = ?', [$counter, $id]); }
    } elseif (isset($options['rotate-recovery'])) { throw new RuntimeException('Enroll an authenticator first.'); }
    if (isset($options['rotate-recovery'])) {
        execute('UPDATE users_tb SET auth_version = auth_version + 1 WHERE id = ?', [$id]);
        audit_event('admin.recovery_rotated', $id, 'success', 'operator', null);
        return ['codes' => create_recovery_codes('admin', $id, 8)];
    }
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $otp = \OTPHP\TOTP::generate();
    $otp->setIssuer('MAE Portfolio');
    $otp->setLabel($email);
    execute('UPDATE users_tb SET mfa_pending_secret = ?, mfa_pending_expires = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE) WHERE id = ?', [encrypt_secret($otp->getSecret()), $id]);
    audit_event('admin.mfa_enrollment_started', $id, 'success', 'operator', null);
    return ['secret' => $otp->getSecret(), 'uri' => $otp->getProvisioningUri()];
});
// Private operator output only: never redirect this into web logs or CI artifacts.
if (isset($result['secret'])) {
    echo 'Authenticator setup key: ' . $result['secret'] . "\n";
    echo 'Provisioning URI: ' . $result['uri'] . "\n";
    echo "Add it to your authenticator, then use --confirm with a current MFA_CODE within ten minutes.\n";
} else {
    echo "Save these single-use recovery codes privately. They expire in 30 days and are not displayed again. Existing administrator sessions were revoked.\n";
    foreach ($result['codes'] as $code) { echo $code . "\n"; }
}
