#!/usr/bin/env python3
"""Disposable Docker deployment checks. Uses fictional data, local TLS, and generated test keys."""
import argparse
import base64
import hashlib
import hmac
import http.cookiejar
import http.cookies
import json
import os
from pathlib import Path
import re
import secrets
import ssl
import struct
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
STATE = ROOT / 'tests/.runtime/production-state.json'
PROJECT = 'mae-security-test'
CHECKS = 0


def check(value, label):
    global CHECKS
    if not value: raise AssertionError(label)
    CHECKS += 1


def compose(args, env, private=False, success=True):
    result = subprocess.run(['docker', 'compose', '-p', PROJECT, '-f', 'compose.production.yaml', *args], cwd=ROOT, env=env, capture_output=True, text=True)
    if success and result.returncode:
        detail = '[private operator output omitted]' if private else (result.stdout + result.stderr)[-5000:]
        raise RuntimeError('Disposable deployment command failed: ' + detail)
    return result


def otp(secret):
    key = base64.b32decode(secret + '=' * ((8 - len(secret) % 8) % 8))
    digest = hmac.new(key, struct.pack('>Q', int(time.time()) // 30), hashlib.sha1).digest()
    offset = digest[-1] & 15
    return f'{(struct.unpack(">I", digest[offset:offset + 4])[0] & 0x7fffffff) % 1000000:06}'


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None


class Client:
    def __init__(self, context):
        self.token = ''
        self.opener = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPSHandler(context=context), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, values=None):
        body = urllib.parse.urlencode(values).encode() if values is not None else None
        request = urllib.request.Request('https://mae.localhost/' + path, data=body)
        try: response = self.opener.open(request, timeout=20)
        except urllib.error.HTTPError as error: response = error
        with response:
            text = response.read().decode('utf-8', 'replace')
            tokens = re.findall(r'name="csrf" value="([a-f0-9]+)"', text)
            if tokens: self.token = tokens[0]
            return response.status, text, response.headers

    def post(self, path, values): return self.request(path, dict(values, csrf=self.token))


def run(env, work):
    compose(['--profile', 'operator', 'build', '--quiet'], env)
    compose(['up', '-d', 'db'], env)
    compose(['run', '--rm', 'provision'], env)
    compose(['run', '--rm', 'operator', 'php', 'scripts/setup.php', '--seed'], env)
    compose(['run', '--rm', 'provision'], env)
    compose(['up', '-d', 'web', 'proxy', 'expiry', 'maintenance'], env)
    certificate = work / 'root.crt'
    for _ in range(100):
        result = compose(['cp', 'proxy:/data/caddy/pki/authorities/local/root.crt', str(certificate)], env, success=False)
        if result.returncode == 0: break
        time.sleep(.3)
    check(certificate.exists(), 'Local TLS authority is generated')
    context = ssl.create_default_context(cafile=str(certificate))
    visitor = Client(context)
    for _ in range(100):
        try:
            if visitor.request('index.php')[0] == 200: break
        except (OSError, urllib.error.URLError): pass
        time.sleep(.3)
    status, body, headers = visitor.request('index.php')
    check(status == 200 and 'use made-up details only' in body, 'Actual HTTPS deployment renders the portfolio warning')
    check(visitor.request('')[0] == 200 and 'use made-up details only' in visitor.request('')[1], 'The root URL serves the MAE homepage')
    cookie = headers.get('Set-Cookie', '')
    # A previous readiness request may already have issued this cookie; use a new browser jar.
    status, body, headers = Client(context).request('index.php'); cookie = headers.get('Set-Cookie', '')
    parsed_cookie = http.cookies.SimpleCookie(); parsed_cookie.load(cookie)
    protected = parsed_cookie.get('mae_session')
    check(protected is not None and bool(protected['secure']) and bool(protected['httponly']) and protected['samesite'].lower() == 'lax', 'Production session cookie has all required attributes')
    check("script-src 'self'" in headers.get('Content-Security-Policy', '') and headers.get('Strict-Transport-Security') == 'max-age=31536000', 'CSP and configured HSTS apply under TLS')
    for path in ['app/bootstrap.php', 'database/schema.sql', 'vendor/autoload.php', 'config.example.php', '.env.example', 'compose.production.yaml', 'docker/Caddyfile']:
        check(visitor.request(path)[0] in (403, 404), 'Apache denies internal path: ' + path)
    for path in ['assets/vendor/bootstrap.min.css', 'assets/vendor/bootstrap.bundle.min.js', 'assets/vendor/fonts/fonts.css']:
        response = visitor.request(path)
        check(response[0] == 200 and response[2].get('Strict-Transport-Security') == 'max-age=31536000', 'Bundled browser dependency is available with edge HTTPS policy')
    missing = visitor.request('robots.txt')
    check(missing[0] == 404 and missing[2].get('Strict-Transport-Security') == 'max-age=31536000' and missing[2].get('Server') is None and 'Apache/' not in missing[1], 'Error responses retain HSTS and omit server-version banners')
    uid = compose(['exec', '-T', 'web', 'id', '-u'], env).stdout.strip()
    check(uid != '0', 'Apache application runs without root')
    code = "$ok=@file_put_contents('/var/www/html/source-write-probe','bad'); if($ok!==false){exit(1);} echo 'denied';"
    check(compose(['exec', '-T', 'web', 'php', '-r', code], env).stdout.strip() == 'denied', 'Application source is read-only')
    code = "require 'app/bootstrap.php'; try{execute('ALTER TABLE Products ADD forbidden INT'); exit(1);}catch(mysqli_sql_exception $e){if($e->getCode()!==1142){throw $e;} echo 'denied';}"
    check(compose(['exec', '-T', 'web', 'php', '-r', code], env).stdout.strip() == 'denied', 'Runtime credentials cannot change schema')
    code = "require 'app/bootstrap.php'; try{rows('SELECT * FROM SecurityEvents'); exit(1);}catch(mysqli_sql_exception $e){if($e->getCode()!==1142){throw $e;} echo 'denied';}"
    check(compose(['exec', '-T', 'web', 'php', '-r', code], env).stdout.strip() == 'denied', 'Web credentials cannot read the audit trail')
    code = "$names=array_map('basename',glob('/run/secrets/*')); sort($names); echo json_encode($names);"
    check(json.loads(compose(['exec', '-T', 'web', 'php', '-r', code], env).stdout) == ['app_key', 'db_runtime'], 'Web service receives only its two required secrets')
    code = "define('MAE_NO_AUTH_JOB',true);require 'app/bootstrap.php'; expire_orders(); try{rows('SELECT CEmail FROM Customers'); exit(1);}catch(mysqli_sql_exception $e){if($e->getCode()!==1142){throw $e;} echo 'restricted';}"
    check(compose(['exec', '-T', 'expiry', 'php', '-r', code], env).stdout.strip() == 'restricted', 'Scheduled expiry cannot read customer data')
    code = "$c=stream_context_create(['http'=>['ignore_errors'=>true,'header'=>\"Host: mae.localhost\r\nX-Forwarded-Proto: https\r\nX-Real-IP: 198.51.100.1\r\n\"]]);file_get_contents('http://127.0.0.1:8080/index.php',false,$c);echo json_encode($http_response_header);"
    response = json.loads(compose(['exec', '-T', 'web', 'php', '-r', code], env).stdout)
    check('400' in response[0] and not any('Set-Cookie:' in h for h in response), 'Forged forwarding headers from an untrusted connection cannot create a production session')

    # Immutable Apache rules remain authoritative even if the upload volume policy is replaced.
    code = "file_put_contents('assets/uploads/.htaccess','Require all granted');file_put_contents('assets/uploads/probe.php','<?php echo 123456789;');file_put_contents('assets/uploads/probe.php.jpg','<?php echo 123456789;');"
    compose(['exec', '-T', 'web', 'php', '-r', code], env)
    for path in ['assets/uploads/probe.php', 'assets/uploads/probe.php.jpg', 'assets/uploads/probe.php/extra.jpg']:
        response = visitor.request(path)
        check(response[0] in (403, 404) and '123456789' not in response[1], 'Upload execution is denied independently of writable .htaccess')
    compose(['exec', '-T', 'web', 'php', '-r', "unlink('assets/uploads/probe.php');unlink('assets/uploads/probe.php.jpg');"], env)

    # The complete administrator flow also runs through the real production stack.
    operator = dict(env, ADMIN_EMAIL='operator@example.test', ADMIN_PASSWORD=secrets.token_urlsafe(24))
    compose(['run', '--rm', '-e', 'ADMIN_EMAIL', '-e', 'ADMIN_PASSWORD', 'operator', 'php', 'scripts/create-admin.php'], operator, private=True)
    begin = compose(['run', '--rm', '-e', 'ADMIN_PASSWORD', 'operator', 'php', 'scripts/admin-mfa.php', '--email=operator@example.test', '--begin'], operator, private=True)
    secret = re.search(r'Authenticator setup key: ([A-Z2-7]+)', begin.stdout).group(1)
    operator['MFA_CODE'] = otp(secret)
    confirmed = compose(['run', '--rm', '-e', 'ADMIN_PASSWORD', '-e', 'MFA_CODE', 'operator', 'php', 'scripts/admin-mfa.php', '--email=operator@example.test', '--confirm'], operator, private=True)
    codes = re.findall(r'^[a-f0-9]{40}$', confirmed.stdout, re.M)
    admin = Client(context); admin.request('index.php')
    check(admin.post('index.php', {'email': operator['ADMIN_EMAIL'], 'pass': operator['ADMIN_PASSWORD'], 'submit_admin': '1'})[0] == 303 and admin.request('admin/dashboard.php')[0] == 303, 'Production admin password grants no administrator access alone')
    admin.request('admin/mfa.php')
    check(admin.post('admin/mfa.php', {'code': codes.pop()})[0] == 303 and admin.request('admin/dashboard.php')[0] == 200, 'Production MFA completes a legitimate admin login')

    customer = Client(context); customer.request('admin/Customers_insert.php')
    check(customer.post('admin/Customers_insert.php', {'ContactName': 'TLS explorer', 'CEmail': 'tls@example.test', 'CPassword': 'demo1234'})[0] == 303, 'Runtime grants support real registration transactions')
    customer.request('admin/catalog.php')
    check(customer.post('admin/addToOrders.php', {'pcode': '1', 'qty': '2'})[0] == 303, 'Runtime grants support an order transaction')
    compose(['run', '--rm', 'backup', 'php', 'scripts/backup.php', '--create=/backups/test.maebackup'], env)
    code = "define('MAE_NO_AUTH_JOB',true);require 'app/bootstrap.php'; try{execute(\"UPDATE Customers SET ContactName='forbidden'\");exit(1);}catch(mysqli_sql_exception $e){if($e->getCode()!==1142){throw $e;}echo 'read-only';}"
    check(compose(['run', '--rm', 'backup', 'php', '-r', code], env).stdout.strip() == 'read-only', 'Backup identity cannot modify customer data')
    customer.request('admin/account-security.php')
    check(customer.post('admin/account-security.php', {'operation': 'erase', 'current_password': 'demo1234', 'confirmation': 'ERASE'})[0] == 303, 'Runtime grants support private account erasure')
    compose(['run', '--rm', 'operator', 'sh', '-c', 'php scripts/privacy.php --export-ledger > /backups/latest-ledger.json'], env)
    # Create a new isolated restore target using the operator-only root mount.
    code = "require 'app/bootstrap.php';$c=new mysqli('db','root',setting('DB_ROOT_PASSWORD'));$c->query('CREATE DATABASE MaeRestore CHARACTER SET utf8mb4');$c->query(\"GRANT ALL PRIVILEGES ON MaeRestore.* TO 'mae_migration'@'%'\");"
    compose(['run', '--rm', 'provision', 'php', '-r', code], env)
    compose(['run', '--rm', 'operator', 'php', '-r', "mkdir('/backups/restored-media',0700);"], env)
    compose(['run', '--rm', '-e', 'DB_NAME=MaeRestore', 'operator', 'php', 'scripts/backup.php', '--restore=/backups/test.maebackup', '--uploads=/backups/restored-media'], env)
    compose(['run', '--rm', '-e', 'DB_NAME=MaeRestore', 'operator', 'php', 'scripts/privacy.php', '--reapply-ledger=/backups/latest-ledger.json', '--apply'], env)
    code = "require 'app/bootstrap.php';$r=row('SELECT is_active,erased_at FROM Customers WHERE CustomerID=1');if(!$r||$r['is_active']||!$r['erased_at']){exit(1);}if(!row('SELECT OrderDetailsID FROM OrderDetails LIMIT 1')){exit(1);}echo 'restored';"
    check(compose(['run', '--rm', '-e', 'DB_NAME=MaeRestore', 'operator', 'php', '-r', code], env).stdout.strip() == 'restored', 'Production backup restores isolated order facts and reapplies newer erasures')
    state = json.loads(STATE.read_text())
    state['image_id'] = compose(['images', '-q', 'web'], env).stdout.strip().splitlines()[0]
    STATE.write_text(json.dumps(state))
    print(f'PASS: {CHECKS} production deployment assertions covering TLS, Apache, immutable uploads, non-root source, grants, secret isolation, MFA, and backup restoration.')


def main():
    parser = argparse.ArgumentParser(); parser.add_argument('--keep', action='store_true'); parser.add_argument('--stop', action='store_true'); options = parser.parse_args()
    if options.stop:
        if STATE.exists():
            env = dict(os.environ, **json.loads(STATE.read_text())['environment'])
            compose(['down', '-v', '--remove-orphans'], env)
            STATE.unlink()
        return
    work = Path(tempfile.mkdtemp(prefix='mae-production-test-')); secret_dir = work / 'secrets'; backup_dir = work / 'backups'; backup_dir.mkdir(); backup_dir.chmod(0o777)
    # The private 0700 parent protects the writable fixture bind mount across host/container UIDs.
    init = subprocess.run([os.getenv('PHP_BIN', 'php'), 'scripts/init-secrets.php', '--directory=' + str(secret_dir)], cwd=ROOT, capture_output=True, text=True)
    check(init.returncode == 0, 'Generated test secrets initialize without printing values')
    settings = {'MAE_HOST': 'mae.localhost', 'SECRETS_DIRECTORY': str(secret_dir), 'BACKUP_DIRECTORY': str(backup_dir), 'HSTS_ENABLED': '1'}
    env = dict(os.environ, **settings)
    STATE.parent.mkdir(parents=True, exist_ok=True); STATE.write_text(json.dumps({'environment': settings, 'fixture_directory': str(work)}))
    try: run(env, work)
    finally:
        if not options.keep: compose(['down', '-v', '--remove-orphans'], env, success=False); STATE.unlink(missing_ok=True)


if __name__ == '__main__': main()
