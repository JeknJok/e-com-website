#!/usr/bin/env python3
"""Security regressions against disposable databases; never uses the configured app database."""
import base64
import concurrent.futures
import copy
import json
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import time
import integration as it


def account(base, email, password='demo1234'):
    client = it.Client(base)
    code, body, _ = client.get('admin/Customers_insert.php')
    names = {field.get('name') for field in it.Document(body).inputs}
    it.check(code == 200 and not names.intersection({'CustomerName', 'CAddress', 'City', 'Country', 'PostalCode'}), 'Registration does not collect names or addresses')
    response = client.post('admin/Customers_insert.php', {'ContactName': 'Demo explorer', 'CEmail': email, 'CPassword': password, 'CAddress': 'ignored-private-marker', 'is_active': '0'})
    it.check(response[0] == 303, 'Simple demo credentials create an account')
    body = client.get('admin/account-security.php')[1]
    recovery = re.search(r'class="recovery-code">([a-f0-9]{40})', body).group(1)
    it.check(recovery not in client.get('admin/account-security.php')[1], 'New recovery code is displayed once')
    row = it.sql('SELECT * FROM Customers WHERE CEmail = ?', [email])[0]
    it.check(row['CAddress'] == '' and row['City'] == '' and row['Country'] == '' and row['is_active'] == 1 and row['CPassword'].startswith('$argon2id$'), 'Registration ignores excess personal fields and hashes disposable passwords')
    return client, int(row['CustomerID']), recovery


def session_fixture(client, values):
    cookie = next(c.value for c in client.jar if c.name == 'mae_session')
    code = r'''
require 'app/bootstrap.php';
$fixture=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
session_save_path(private_directory().'/sessions'); session_name('mae_session'); session_id($fixture['id']); session_start();
foreach($fixture['auth'] as $key=>$value){$_SESSION['auth'][$key]=$value;} session_write_close();
'''
    result = subprocess.run([it.PHP, '-r', code], input=json.dumps({'id': cookie, 'auth': values}), env=it.ENV, cwd=it.ROOT, capture_output=True, text=True)
    it.check(result.returncode == 0, 'Private session fixture is prepared')


def run_http(base, admin_email, admin_password, mfa_secret):
    public = it.Client(base)
    for path in ['index.php', 'admin/Customers_insert.php', 'admin/recover.php']:
        status, body, headers = public.get(path)
        it.check(status == 200 and 'Do not enter your real name' in body and 'password you use elsewhere' in body, 'Visitor warning is present: ' + path)
        policy = headers.get('Content-Security-Policy', '')
        it.check("script-src 'self'" in policy and 'unsafe-inline' not in policy and headers.get('Referrer-Policy') == 'no-referrer', 'Strict browser policy and private referrers apply')
    it.check(public.post('admin/Customers_insert.php', {'ContactName': 'Fictional', 'CEmail': 'person@example.com', 'CPassword': 'demo1234'})[0] == 422, 'Registration steers visitors to a reserved fictional email domain')
    client, cid, first_recovery = account(base, 'security@example.test')
    other = it.Client(base); other.login('security@example.test', 'demo1234')
    stolen = it.Client(base)
    for cookie in client.jar: stolen.jar.set_cookie(copy.copy(cookie))
    initial = it.sql('SELECT CEmail, CPassword, auth_version FROM Customers WHERE CustomerID = ?', [cid])[0]
    change = {'ContactName': 'Changed alias', 'CEmail': 'renamed@example.test', 'CPassword': 'new-demo-password-42'}
    it.check(client.post('admin/Customers_profile.php', change)[0] == 422, 'A session and CSRF alone cannot change credentials')
    it.check(client.post('admin/Customers_profile.php', dict(change, current_password='wrong-current-password'))[0] == 422, 'Incorrect current password cannot change credentials')
    it.check(it.sql('SELECT CEmail, CPassword, auth_version FROM Customers WHERE CustomerID = ?', [cid])[0] == initial, 'Rejected credential changes leave all account credentials unchanged')
    it.check(client.post('admin/Customers_profile.php', dict(change, current_password='demo1234'))[0] == 303, 'Correct current proof changes credentials')
    it.check(other.get('admin/catalog.php')[0] == 303 and stolen.get('admin/catalog.php')[0] == 303, 'Credential changes revoke independent sessions and the stolen old cookie')
    it.check(client.get('admin/catalog.php')[0] == 200, 'The confirming customer keeps a rotated valid session')
    it.check(not it.sql("SELECT token_hash FROM RecoveryCodes WHERE account_kind = 'customer' AND account_id = ?", [cid]), 'Credential changes revoke old recovery codes')
    client.get('admin/account-security.php')
    it.check(client.post('admin/account-security.php', {'operation': 'recovery', 'current_password': 'new-demo-password-42'})[0] == 303, 'A customer can rotate a recovery code with current proof')
    recovery = re.search(r'class="recovery-code">([a-f0-9]{40})', client.get('admin/account-security.php')[1]).group(1)
    recover = it.Client(base); recover.get('admin/recover.php')
    bad = recover.post('admin/recover.php', {'email': 'renamed@example.test', 'recovery_code': first_recovery, 'new_password': 'recovered-demo-42'})
    it.check(bad[0] == 422, 'An invalidated recovery code cannot reset a password')
    before = it.sql('SELECT * FROM RecoveryCodes WHERE token_hash = ?', [it.hashlib.sha256(recovery.encode()).hexdigest()])
    recover.get('admin/recover.php')
    it.check(it.sql('SELECT * FROM RecoveryCodes WHERE token_hash = ?', [it.hashlib.sha256(recovery.encode()).hexdigest()]) == before, 'GET does not consume a recovery code')
    contenders = [it.Client(base), it.Client(base)]
    for c in contenders: c.get('admin/recover.php')
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        outcomes = list(pool.map(lambda c: c.post('admin/recover.php', {'email': 'renamed@example.test', 'recovery_code': recovery, 'new_password': 'recovered-demo-42'})[0], contenders))
    it.check(sorted(outcomes) == [303, 422], 'Concurrent recovery consumes a code exactly once')
    it.check(client.get('admin/catalog.php')[0] == 303, 'Recovery revokes a previously active session')
    again = recover.post('admin/recover.php', {'email': 'renamed@example.test', 'recovery_code': recovery, 'new_password': 'reused-demo-42'})
    missing = recover.post('admin/recover.php', {'email': 'nonexistent@example.test', 'recovery_code': recovery, 'new_password': 'reused-demo-42'})
    it.check(again[0] == missing[0] == 422 and 'Those demo recovery details were not accepted.' in again[1] and 'Those demo recovery details were not accepted.' in missing[1], 'Unknown identities and reused codes have the same public recovery failure')
    client.login('renamed@example.test', 'recovered-demo-42')

    clock, _, _ = account(base, 'clock@example.test')
    cookie = next(c.value for c in clock.jar if c.name == 'mae_session')
    session_fixture(clock, {'last_seen': int(time.time()) - 1801})
    it.check(next(c.value for c in clock.jar if c.name == 'mae_session') == cookie and clock.get('admin/catalog.php')[0] == 303, 'Server idle expiry rejects a still-present customer cookie')
    clock.login('clock@example.test', 'demo1234')
    session_fixture(clock, {'issued_at': int(time.time()) - 43201, 'last_seen': int(time.time())})
    it.check(clock.get('admin/catalog.php')[0] == 303, 'Absolute expiry applies despite recent activity')

    # A historical-enrollment fixture allows a current OTP without waiting for the clock.
    admin_id = int(it.sql('SELECT id FROM users_tb WHERE email = ?', [admin_email])[0]['id'])
    it.sql('UPDATE users_tb SET mfa_last_counter = ? WHERE id = ?', [int(time.time()) // 30 - 2, admin_id])
    admin = it.Client(base); admin.get('index.php')
    it.check(admin.post('index.php', {'email': admin_email, 'pass': admin_password, 'submit_admin': '1'})[0] == 303 and admin.get('admin/dashboard.php')[0] == 303, 'Administrator password grants only pending authentication')
    admin.get('admin/mfa.php'); code = it.otp(mfa_secret)
    it.check(admin.post('admin/mfa.php', {'code': code})[0] == 303 and admin.get('admin/dashboard.php')[0] == 200, 'A current authenticator code completes administrator login')
    replay = it.Client(base); replay.get('index.php'); replay.post('index.php', {'email': admin_email, 'pass': admin_password, 'submit_admin': '1'}); replay.get('admin/mfa.php')
    it.check(replay.post('admin/mfa.php', {'code': code})[0] == 422 and replay.get('admin/dashboard.php')[0] == 303, 'An already-used OTP cannot authorize a new session')
    for path in ['admin/user_login.php', 'admin/Customers_login.php']:
        it.check(replay.get(path)[0] == 303 and replay.get('admin/dashboard.php')[0] == 303, 'Legacy login routes cannot bypass MFA')
    session_fixture(admin, {'mfa_at': int(time.time()) - 301})
    it.check(admin.get('admin/Customers_update.php?CustomerID=' + str(cid))[0] == 403, 'Stale MFA cannot read sensitive customer details')
    it.sql('UPDATE users_tb SET mfa_last_counter = ? WHERE id = ?', [int(time.time()) // 30 - 12, admin_id])
    admin.get('admin/security.php')
    it.check(admin.post('admin/security.php', {'current_password': 'wrong-password', 'code': it.otp(mfa_secret)})[0] == 422, 'MFA alone cannot refresh a sensitive-action session')
    it.check(admin.post('admin/security.php', {'current_password': admin_password, 'code': it.otp(mfa_secret)})[0] == 303, 'Current password and new factor refresh administrator confirmation')
    victim, vid, _ = account(base, 'reactivation@example.test')
    admin.get('admin/Customers_update.php?CustomerID=' + str(vid))
    values = {'ContactName': 'Demo explorer', 'CEmail': 'reactivation@example.test', 'CPassword': '', 'is_active': '0'}
    it.check(admin.post('admin/Customers_update.php?CustomerID=' + str(vid), values)[0] == 303, 'Administrator can deactivate an account')
    values['is_active'] = '1'
    it.check(admin.post('admin/Customers_update.php?CustomerID=' + str(vid), values)[0] == 303, 'Administrator can reactivate an unerased account')
    it.check(victim.get('admin/catalog.php')[0] == 303, 'Reactivation cannot revive a cookie that was never used during deactivation')
    it.check('renamed@example.test' not in admin.get('admin/Customers_list.php')[1], 'Customer list does not expose email addresses')

    # Decoder/canonicalizer drops appended metadata while preserving a valid image.
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP438DwHwAFgAI/ScLttAAAAABJRU5ErkJggg==')
    # Generate the fixture with GD, avoiding platform-dependent PNG decoder tolerances.
    generated = subprocess.run([it.PHP, '-r', '$i=imagecreatetruecolor(2,2); ob_start(); imagepng($i); echo base64_encode(ob_get_clean());'], capture_output=True, text=True)
    png = base64.b64decode(generated.stdout)
    pid = int(it.sql('SELECT pcode FROM Products ORDER BY pcode LIMIT 1')[0]['pcode'])
    it.check(admin.upload(pid, png + b'<?php echo "private-metadata-marker"; ?>')[0] == 303, 'A valid image is canonicalized instead of retaining appended content')
    relative = it.sql('SELECT pimage FROM Products WHERE pcode = ?', [pid])[0]['pimage']; path = it.ROOT / relative; it.UPLOADS.append(path)
    it.check(b'private-metadata-marker' not in path.read_bytes() and b'<?php' not in path.read_bytes(), 'Canonical product image contains no appended script metadata')
    it.check(admin.upload(pid, b'<svg xmlns="http://www.w3.org/2000/svg"><script>bad()</script></svg>', 'image/svg+xml', 'image.svg')[0] == 422, 'User SVG is rejected while bundled illustrations remain supported')

    # Audit failure must roll back a write and must not grant a new authenticated session.
    private_marker = 'never-log-this-password-42'
    it.sql('RENAME TABLE SecurityEvents TO SecurityEventsUnavailable')
    try:
        failed = it.Client(base); failed.get('index.php')
        it.check(failed.post('index.php', {'email': 'renamed@example.test', 'pass': 'recovered-demo-42', 'submit_customer': '1'})[0] == 500 and failed.get('admin/catalog.php')[0] == 303, 'Unavailable audit storage cannot grant a new session')
        client.get('admin/Customers_profile.php')
        result = client.post('admin/Customers_profile.php', {'ContactName': 'must-roll-back', 'CEmail': 'renamed@example.test', 'CPassword': ''})
        it.check(result[0] == 500 and it.sql('SELECT ContactName FROM Customers WHERE CustomerID = ?', [cid])[0]['ContactName'] != 'must-roll-back', 'Audit outage rolls back a sensitive account transaction')
    finally: it.sql('RENAME TABLE SecurityEventsUnavailable TO SecurityEvents')
    it.sql('RENAME TABLE RateLimits TO RateLimitsUnavailable')
    try:
        failed = it.Client(base); failed.get('index.php')
        it.check(failed.post('index.php', {'email': 'renamed@example.test', 'pass': private_marker, 'submit_customer': '1'})[0] == 500 and failed.get('admin/catalog.php')[0] == 303, 'Unavailable rate storage fails closed for authentication')
    finally: it.sql('RENAME TABLE RateLimitsUnavailable TO RateLimits')
    logs = (it.ROOT / 'tests/.runtime/php.log').read_text()
    it.check(private_marker not in logs and recovery not in logs and first_recovery not in logs and admin_password not in logs, 'Application logs exclude tested passwords and recovery tokens')

    # Racing independent cookies cannot evade a shared account bucket.
    attempts = [it.Client(base) for _ in range(16)]
    for contender in attempts: contender.get('index.php')
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        outcomes = list(pool.map(lambda c: c.post('index.php', {'email': 'limited@example.test', 'pass': 'wrong-demo-password', 'submit_customer': '1'}), attempts))
    it.check(sum(r[0] == 200 for r in outcomes) == 10 and sum(r[0] == 429 for r in outcomes) == 6, 'Concurrent independent sessions share the exact account attempt budget')
    it.check(all(int(r[2]['Retry-After']) > 0 for r in outcomes if r[0] == 429), 'Throttle responses expose a bounded retry interval')

    # Save a recoverable snapshot, erase data, then prove restoration honors the latest ledger.
    client.get('admin/catalog.php'); client.post('admin/addToOrders.php', {'pcode': pid, 'qty': 2})
    order = it.sql('SELECT OrderID FROM Orders WHERE CustomerID = ?', [cid])[0]['OrderID']
    facts = it.sql('SELECT pprice, qty, pname FROM OrderDetails WHERE OrderID = ?', [order])
    archive = it.PRIVATE_DIRECTORY / 'snapshot.maebackup'
    backup_env = dict(it.ENV, BACKUP_KEY=base64.b64encode(secrets.token_bytes(32)).decode())
    it.cli('scripts/backup.php', backup_env, args=['--create=' + str(archive)])
    it.check(archive.exists() and b'renamed@example.test' not in archive.read_bytes(), 'Database/media backup is encrypted')
    client.get('admin/account-security.php')
    it.check(client.post('admin/account-security.php', {'operation': 'erase', 'current_password': 'recovered-demo-42', 'confirmation': 'wrong'})[0] == 422, 'Erasure requires explicit confirmation and current proof')
    it.check(client.post('admin/account-security.php', {'operation': 'erase', 'current_password': 'recovered-demo-42', 'confirmation': 'ERASE'})[0] == 303, 'A customer can erase a demo identity')
    erased = it.sql('SELECT * FROM Customers WHERE CustomerID = ?', [cid])[0]
    it.check(erased['erased_at'] and erased['is_active'] == 0 and erased['CEmail'] != 'renamed@example.test' and erased['CAddress'] == '', 'Erasure removes identifying fields and disables authentication')
    it.check(it.sql('SELECT pprice, qty, pname FROM OrderDetails WHERE OrderID = ?', [order]) == facts, 'Erasure preserves linked order facts')
    admin.get('admin/Customers_update.php?CustomerID=' + str(cid))
    it.check(admin.post('admin/Customers_update.php?CustomerID=' + str(cid), dict(values, CEmail='renamed@example.test'))[0] == 404, 'An erased account cannot be reactivated')
    ledger = it.PRIVATE_DIRECTORY / 'latest-ledger.json'; ledger.write_text(it.cli('scripts/privacy.php', args=['--export-ledger']).stdout)
    restored = it.ENV['DB_NAME'] + '_restore'; it.create_database(restored)
    media = it.PRIVATE_DIRECTORY / 'restore-media'; media.mkdir()
    restore_env = dict(backup_env, DB_NAME=restored)
    it.cli('scripts/backup.php', restore_env, args=['--restore=' + str(archive), '--uploads=' + str(media)])
    it.check(it.sql('SELECT CEmail FROM Customers WHERE CustomerID = ?', [cid], database=restored)[0]['CEmail'] == 'renamed@example.test', 'An isolated restore recovers the snapshot rather than changing the active database')
    it.cli('scripts/privacy.php', restore_env, args=['--reapply-ledger=' + str(ledger), '--apply'])
    it.check(it.sql('SELECT erased_at FROM Customers WHERE CustomerID = ?', [cid], database=restored)[0]['erased_at'] and it.sql('SELECT pprice, qty, pname FROM OrderDetails WHERE OrderID = ?', [order], database=restored) == facts, 'Latest erasure ledger prevents an old snapshot from reintroducing identity data')
    again = it.cli('scripts/backup.php', restore_env, success=False, args=['--restore=' + str(archive), '--uploads=' + str(media)])
    it.check(again.returncode != 0, 'Restore refuses a nonempty target')

    aged, aged_id, _ = account(base, 'aged@example.test')
    it.sql('UPDATE Customers SET last_seen_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 31 DAY) WHERE CustomerID = ?', [aged_id])
    it.cli('scripts/privacy.php')
    it.check(it.sql('SELECT erased_at FROM Customers WHERE CustomerID = ?', [aged_id])[0]['erased_at'] is None, 'Retention defaults to a non-mutating dry run')
    it.cli('scripts/privacy.php', args=['--apply'])
    it.check(it.sql('SELECT erased_at FROM Customers WHERE CustomerID = ?', [aged_id])[0]['erased_at'], 'Retention erases inactive fictional accounts after the documented period')


def main():
    it.create_database(it.ENV['DB_NAME']); it.cli('scripts/setup.php')
    email, password = 'security-admin@example.test', secrets.token_urlsafe(24)
    it.cli('scripts/create-admin.php', dict(it.ENV, ADMIN_EMAIL=email, ADMIN_PASSWORD=password))
    secret = it.enroll_admin(email, password)
    # Seed just one product; security tests should not depend on the merchandising catalog.
    it.sql("INSERT INTO Products (pname, pcategory, pprice, pimage, pdesc) VALUES ('Security fixture', 'TEST', 12345, 'assets/img/logo-mae.png', 'Fictional fixture')")
    with it.server(it.ENV) as base: run_http(base, email, password, secret)
    it.sql('DELETE FROM RateLimits')
    with it.server(dict(it.ENV, RATE_MUTATION_IP='2')) as base:
        statuses = []
        for i in range(4):
            contender = it.Client(base); contender.get('index.php')
            values = {'csrf': contender.token, 'email': f'ip-{i}@example.test', 'pass': 'wrong-demo-password', 'submit_customer': '1'}
            statuses.append(contender.request('index.php', values=values, headers={'X-Real-IP': f'198.51.100.{i + 1}', 'X-Forwarded-For': f'198.51.100.{i + 1}'})[0])
        it.check(statuses == [200, 200, 429, 429], 'Forged IP headers and new cookies cannot bypass the global mutation budget')
    print(f'PASS: {it.CHECKS} security assertions covering credentials, cookies, MFA, recovery, races, privacy, uploads, audit outages, encryption, and restoration.')


if __name__ == '__main__':
    try: main()
    finally:
        shutil.rmtree(it.FX_DIRECTORY, ignore_errors=True); shutil.rmtree(it.PRIVATE_DIRECTORY, ignore_errors=True)
        for path in it.UPLOADS: path.unlink(missing_ok=True)
        for name in reversed(it.DATABASES): it.sql(f'DROP DATABASE `{name}`', database='')
