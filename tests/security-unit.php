<?php
declare(strict_types=1);
putenv('APP_ENV=development');
putenv('APP_KEY=' . base64_encode(random_bytes(32)));
$directory = sys_get_temp_dir() . '/mae-security-unit-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
putenv('RUNTIME_DIR=' . $directory);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/backup.php';
$checks = 0;
function check_security(bool $value, string $label): void
{
    global $checks;
    if (!$value) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++;
}
function rejects(callable $callback, string $label): void
{
    try { $callback(); } catch (Throwable) { check_security(true, $label); return; }
    check_security(false, $label);
}
try {
    foreach ([['127.0.0.1', '127.0.0.1/32', true], ['172.30.30.10', '172.30.30.10/32', true], ['172.30.30.11', '172.30.30.10/32', false], ['172.30.31.1', '172.30.30.0/24', false], ['::1', '::1/128', true], ['::2', '::1/128', false], ['127.0.0.1', '::1/128', false]] as [$ip, $range, $expected]) {
        check_security(ip_in_range($ip, $range) === $expected, 'Exact proxy address/CIDR boundary');
    }
    putenv('TRUSTED_PROXIES=172.30.30.10/32');
    $_SERVER = ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_REAL_IP' => '198.51.100.1'];
    check_security(!request_https() && client_ip() === '203.0.113.5', 'Untrusted forwarding headers do not change transport or IP');
    $_SERVER['REMOTE_ADDR'] = '172.30.30.10';
    check_security(request_https() && client_ip() === '198.51.100.1', 'Only the configured proxy supplies transport and client IP');
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https,http';
    check_security(!request_https(), 'Ambiguous forwarding chains are rejected');
    $long = str_repeat('😀', 128); $hash = new_password($long);
    check_security(verify_password($long, $hash) && str_starts_with($hash, '$argon2id$'), '128-character Unicode passwords work with Argon2id');
    rejects(fn() => new_password(str_repeat('😀', 129)), 'Password character limit');
    rejects(fn() => new_password("demo\0password"), 'NUL is rejected');
    rejects(fn() => new_password('short'), 'Demo minimum applies');
    rejects(fn() => new_password('demo1234', true), 'Administrator policy remains stronger than the demo policy');
    $legacy = password_hash(str_repeat('a', 72), PASSWORD_BCRYPT);
    check_security(verify_password(str_repeat('a', 72), $legacy) && !verify_password(str_repeat('a', 72) . 'x', $legacy), 'Legacy bcrypt works without long-input truncation');
    $cipher = encrypt_secret('fictional-authenticator');
    check_security(decrypt_secret($cipher) === 'fictional-authenticator', 'Authenticator encryption round trip');
    $bytes = base64_decode($cipher); $bytes[strlen($bytes) - 1] = chr(ord($bytes[strlen($bytes) - 1]) ^ 1);
    rejects(fn() => decrypt_secret(base64_encode($bytes)), 'Modified authenticator ciphertext is rejected');
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; $otp = totp($secret); $otp->setDigits(8); // gitleaks:allow -- public RFC 6238 test vector, never an account secret
    foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $time => $expected) {
        check_security($otp->at($time) === $expected, 'RFC 6238 SHA-1 vector');
    }
    $code = totp($secret)->now(); $counter = intdiv(time(), 30);
    check_security(valid_totp_counter($secret, $code, $counter - 2) !== null && valid_totp_counter($secret, $code, $counter + 1) === null, 'Application enforces TOTP replay boundaries');
    check_security(local_image_path('https://tracker.example.test/photo.png') === null && local_image_path('assets/uploads/evil.svg') === null && local_image_path('assets/img/catalog/mug.svg') !== null, 'Only local trusted image locations are accepted');
    $source = $directory . '/plain'; file_put_contents($source, random_bytes(1048577));
    $key = base64_encode(random_bytes(32)); putenv('BACKUP_KEY=' . $key);
    encrypt_backup($source, $directory . '/encrypted');
    decrypt_backup($directory . '/encrypted', $directory . '/restored');
    check_security(hash_file('sha256', $source) === hash_file('sha256', $directory . '/restored'), 'Multi-block encrypted backup round trip');
    $bytes = file_get_contents($directory . '/encrypted'); $bytes[100] = chr(ord($bytes[100]) ^ 1); file_put_contents($directory . '/tampered', $bytes);
    rejects(fn() => decrypt_backup($directory . '/tampered', $directory . '/bad-restore'), 'Tampered backup is rejected');
    file_put_contents($directory . '/truncated', substr(file_get_contents($directory . '/encrypted'), 0, -1));
    rejects(fn() => decrypt_backup($directory . '/truncated', $directory . '/truncated-restore'), 'Truncated final backup block is rejected');
    putenv('BACKUP_KEY=' . base64_encode(random_bytes(32)));
    rejects(fn() => decrypt_backup($directory . '/encrypted', $directory . '/wrong-key'), 'A wrong backup key cannot restore data');
    putenv('BACKUP_KEY=' . base64_encode(app_key()));
    rejects(fn() => backup_key(), 'Backup and application keys must be separate');
    file_put_contents($directory . '/secret', "toy-secret\n"); putenv('UNIT_SECRET_FILE=' . $directory . '/secret');
    check_security(setting('UNIT_SECRET') === 'toy-secret', 'Secret files are supported without echoing values');
    putenv('UNIT_SECRET=conflict'); rejects(fn() => setting('UNIT_SECRET'), 'Ambiguous environment/file secrets fail closed');
    putenv('APP_ENV=production'); putenv('APP_ORIGIN=http://mae.example.test'); putenv('DB_USER=mae_runtime'); putenv('DB_PASSWORD=fictional-database-secret'); putenv('COOKIE_SECURE=1');
    rejects(fn() => validate_security_configuration(), 'Production refuses HTTP origins');
    putenv('APP_ORIGIN=https://mae.example.test'); putenv('DB_USER=root');
    rejects(fn() => validate_security_configuration(), 'Production refuses root database credentials');
    putenv('DB_USER=mae_runtime'); validate_security_configuration(); check_security(true, 'Complete production transport configuration validates');
    echo "PASS: $checks security unit assertions (proxy trust, Unicode/bcrypt, RFC TOTP vectors, secret files, authenticated encryption, and production validation).\n";
} finally {
    foreach (glob($directory . '/*') as $file) { unlink($file); }
    rmdir($directory);
}
