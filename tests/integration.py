#!/usr/bin/env python3
"""HTTP/database regressions. Creates and drops only uniquely named test databases.

Requires PHP 8.2+ (mysqli, mbstring, fileinfo), Python 3, and a MySQL/MariaDB
account with CREATE/DROP DATABASE permission. Connection uses DB_* environment.
"""
import base64
import concurrent.futures
import contextlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import signal
import shutil
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')
ENV = os.environ.copy()
ENV['DB_NAME'] = 'mae_test_' + secrets.token_hex(6)
ENV['APP_BASE_PATH'] = ''
ENV['COOKIE_SECURE'] = '0'
FX_DIRECTORY = ROOT / 'tests/.runtime' / ('fx_' + secrets.token_hex(6))
FX_DIRECTORY.mkdir(parents=True, exist_ok=True)
ENV['FX_CACHE_DIR'] = str(FX_DIRECTORY)
DATABASES = []
UPLOADS = []
CHECKS = 0


def check(condition, label):
    global CHECKS
    if not condition:
        raise AssertionError(label)
    CHECKS += 1


def cli(script, env=None, success=True):
    result = subprocess.run([PHP, str(ROOT / script)], env=env or ENV,
                            cwd=ROOT, capture_output=True, text=True)
    if success and result.returncode:
        raise AssertionError(f'{script}: {result.stdout}\n{result.stderr}')
    return result


def sql(statement, params=(), database=None):
    env = ENV.copy()
    if database is not None:
        env['DB_NAME'] = database
    code = r'''
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$d = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$c = new mysqli(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', getenv('DB_NAME'), (int)(getenv('DB_PORT') ?: 3306), getenv('DB_SOCKET') ?: null);
$c->set_charset('utf8mb4'); $c->query("SET time_zone = '+00:00'");
$s = $c->prepare($d['sql']); $s->execute($d['params']); $r = $s->get_result();
echo json_encode($r ? $r->fetch_all(MYSQLI_ASSOC) : [], JSON_THROW_ON_ERROR);
'''
    result = subprocess.run([PHP, '-r', code], env=env, input=json.dumps({'sql': statement, 'params': list(params)}),
                            capture_output=True, text=True)
    if result.returncode:
        raise AssertionError(result.stderr)
    return json.loads(result.stdout)


def create_database(name):
    assert re.fullmatch(r'mae_test_[a-z0-9_]+', name)
    sql(f'CREATE DATABASE `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', database='')
    DATABASES.append(name)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self, base):
        self.base = base
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())
        self.token = ''

    def request(self, path, values=None, data=None, headers=None):
        if values is not None:
            data = urllib.parse.urlencode(values).encode()
        req = urllib.request.Request(self.base + '/' + path.lstrip('/'), data=data, headers=headers or {})
        try:
            response = self.opener.open(req, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read().decode('utf-8', 'replace')
            matches = re.findall(r'name="csrf" value="([a-f0-9]+)"', body)
            if matches:
                self.token = matches[0]
            return response.status, body, response.headers

    def get(self, path):
        return self.request(path)

    def post(self, path, values=None, csrf=True):
        values = dict(values or {})
        if csrf:
            values.setdefault('csrf', self.token)
        return self.request(path, values)

    def login(self, email, password, admin=False):
        self.get('index.php')
        response = self.post('index.php', {'email': email, 'pass': password,
                                         'submit_admin' if admin else 'submit_customer': '1'})
        check(response[0] == 303, 'Valid credentials log in and redirect')
        destination = 'admin/dashboard.php' if admin else 'admin/catalog.php'
        check(self.get(destination)[0] == 200, 'Authenticated destination loads')

    def upload(self, product, content, mime='image/png', name='picture.png', csrf=True):
        boundary = 'mae' + secrets.token_hex(12)
        parts = []
        for key, value in {'id': str(product), **({'csrf': self.token} if csrf else {})}.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="berkas"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + b'\r\n')
        parts.append(f'--{boundary}--\r\n'.encode())
        return self.request('assets/upload.php', data=b''.join(parts), headers={'Content-Type': f'multipart/form-data; boundary={boundary}'})


class Document(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.ids = []
        self.links = []
        self.inputs = []
        self.prices = []
        self.feed(body)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if 'id' in attrs:
            self.ids.append(attrs['id'])
        if tag == 'a' and 'href' in attrs:
            self.links.append(attrs['href'])
        if tag == 'input':
            self.inputs.append(attrs)
        if 'data-money-idr' in attrs:
            self.prices.append(int(attrs['data-money-idr']))


def seed_rates(rows, fetched_at=None, fail=False):
    """Populate an isolated cache through the production service and an injected provider."""
    code = r'''
require 'app/currency.php';
$v=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$clock=$v['now'];
$fetch=function() use($v): string { if ($v['fail']) { throw new RuntimeException('Test provider unavailable'); } return json_encode($v['rows'],JSON_THROW_ON_ERROR); };
try { echo json_encode((new ExchangeRates(null,$fetch,fn()=>$clock))->latest(),JSON_THROW_ON_ERROR); }
catch (RuntimeException $e) { echo json_encode(['unavailable'=>true]); }
'''
    response = subprocess.run([PHP, '-r', code], cwd=ROOT, env=ENV,
                              input=json.dumps({'rows': rows, 'now': fetched_at or int(time.time()), 'fail': fail}),
                              capture_output=True, text=True)
    check(response.returncode == 0, 'Exchange-rate fixture seeds through the production cache service: ' + response.stderr)
    return json.loads(response.stdout)


@contextlib.contextmanager
def server(env):
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    runtime = ROOT / 'tests/.runtime'
    runtime.mkdir(exist_ok=True)
    log = open(runtime / 'php.log', 'w')
    server_env = dict(env, PHP_CLI_SERVER_WORKERS='4')
    process = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(ROOT), str(ROOT / 'scripts/dev-router.php')],
                               env=server_env, cwd=ROOT, stdout=log, stderr=log, start_new_session=True)
    base = f'http://127.0.0.1:{port}' + env.get('APP_BASE_PATH', '')
    try:
        for _ in range(100):
            if process.poll() is not None:
                raise AssertionError((runtime / 'php.log').read_text())
            try:
                if Client(base).get('index.php')[0] == 200:
                    break
            except (OSError, urllib.error.URLError):
                pass
            time.sleep(.05)
        else:
            raise AssertionError('PHP server did not become ready')
        yield base
    finally:
        os.killpg(process.pid, signal.SIGTERM)
        process.wait(timeout=10)
        log.close()


def customer_data(email, name='Customer'):
    return {'CustomerName': name, 'ContactName': name, 'CAddress': "12 O'Reilly Road", 'City': 'Vancouver',
            'PostalCode': 'V0V0V0', 'Country': 'Canada', 'CEmail': email, 'CPassword': 'Customer-pass-42'}


def register(base, email, name='Customer'):
    client = Client(base)
    check(client.get('admin/Customers_insert.php')[0] == 200, 'Registration form loads')
    response = client.post('admin/Customers_insert.php', customer_data(email, name))
    check(response[0] == 303 and '/admin/catalog.php' in response[2]['Location'], 'Registration creates a logged-in customer')
    check(client.get('admin/catalog.php')[0] == 200, 'New customer can shop immediately')
    return client, int(sql('SELECT CustomerID FROM Customers WHERE CEmail = ?', [email])[0]['CustomerID'])


def run_http(base, admin_email, admin_password):
    public = Client(base)
    code, body, headers = public.get('index.php')
    document = Document(body)
    check(code == 200 and len(document.ids) == len(set(document.ids)), 'Landing page has unique IDs')
    check('id="contact"' in body and 'href="#services"' not in body, 'Landing-page anchors exist')
    cookie = headers.get('Set-Cookie', '')
    check('HttpOnly' in cookie and 'SameSite=Lax' in cookie, 'Session cookies are protected')
    check('data-currency-select' in body and all(f'value="{code}"' in body for code in ['IDR', 'USD', 'EUR', 'GBP', 'CAD', 'JPY']),
          'Landing page offers exactly the supported currency choices')
    check(Document(body).prices and all(value > 0 for value in Document(body).prices),
          'Public prices retain exact original IDR amounts for conversion')
    for filename in FX_DIRECTORY.glob('rates.json*'):
        filename.unlink()
    date = time.strftime('%Y-%m-%d', time.gmtime())
    rate_rows = [{'base': 'IDR', 'quote': code, 'rate': rate, 'date': date}
                 for code, rate in [('USD', .00006), ('EUR', .000055), ('GBP', .000048), ('CAD', .000083), ('JPY', .009)]]
    seed_rates(rate_rows)
    code, rates_body, rate_headers = public.get('api/exchange-rates.php')
    rates = json.loads(rates_body)
    check(code == 200 and rates['base'] == 'IDR' and set(rates['rates']) == {'IDR', 'USD', 'EUR', 'GBP', 'CAD', 'JPY'},
          'Public currency endpoint returns all six IDR-based rates')
    check('application/json' in rate_headers.get('Content-Type', '') and rate_headers.get('Set-Cookie') is None,
          'Rate endpoint has JSON content and creates no session cookie')
    check(not rates['stale'] and rates['dates']['CAD'] == date, 'Public rate response exposes its publication date and freshness')
    check(json.loads(public.get('api/exchange-rates.php?base=USD&url=http://example.test')[1])['base'] == 'IDR',
          'Query parameters cannot alter the fixed rate provider or base currency')
    status, _, headers = public.post('api/exchange-rates.php')
    check(status == 405 and headers.get('Allow') == 'GET', 'Exchange rates accept only GET requests')
    (FX_DIRECTORY / 'rates.json').unlink()
    old_time = int(time.time()) - 7200
    old_rows = [dict(row, date=time.strftime('%Y-%m-%d', time.gmtime(old_time))) for row in rate_rows]
    seed_rates(old_rows, old_time)
    seed_rates(rate_rows, fail=True)
    check(json.loads(public.get('api/exchange-rates.php')[1])['stale'], 'API outage serves marked saved rates with the original date')
    (FX_DIRECTORY / 'rates.json').unlink()
    seed_rates(rate_rows, fail=True)
    code, error_body, _ = public.get('api/exchange-rates.php')
    check(code == 503 and 'Prices remain in IDR' in json.loads(error_body)['error'], 'Cold outage returns a safe IDR fallback message')
    legacy_upload = ROOT / ('assets/uploads/product_legacy_' + secrets.token_hex(6) + '.php')
    UPLOADS.append(legacy_upload)
    legacy_upload.write_text('<?php echo "legacy-upload-executed";')
    code, upload_body, _ = public.get(str(legacy_upload.relative_to(ROOT)))
    check(code == 404 and 'legacy-upload-executed' not in upload_body, 'Legacy PHP uploads cannot execute through the development router')
    for path in ['admin/dashboard.php', 'admin/user_list.php', 'admin/Customers_list.php', 'admin/products_list.php',
                 'admin/Orders_list.php', 'admin/Shop_admin.php', 'admin/catalog_admin.php?cid=1', 'assets/upload.php']:
        check(public.get(path)[0] == 303, f'Unauthenticated access is blocked: {path}')
    for path in ['app/bootstrap.php', 'database/schema.sql', 'scripts/setup.php', 'config.example.php', '.git/config',
                 'admin/create_Customers_tb.php', 'admin/create_users_tb.php', 'admin/orders_tb_create.php',
                 'admin/ordersDetails_tb_create%20copy.php', 'admin/cart_tb_create.php', 'admin/products_tb_create.php']:
        check(public.get(path)[0] == 404, f'Private/installer path is not executable: {path}')
    check(public.post('index.php', {'email': admin_email, 'pass': admin_password, 'submit_admin': '1'}, csrf=False)[0] == 403,
          'Login requires CSRF token')
    check(public.post('index.php', {'email': "' OR 1=1 -- ", 'pass': 'anything', 'submit_admin': '1'})[0] == 200,
          'SQL input cannot bypass login')
    check(public.get('admin/dashboard.php')[0] == 303, 'Failed login creates no admin session')

    admin = Client(base)
    admin.login(admin_email, admin_password, admin=True)
    alice, aid = register(base, 'alice@example.test', "Alice <script>alert('x')</script>")
    bob, bid = register(base, 'bob@example.test', 'Bob')
    check(alice.get('admin/user_list.php')[0] == 403, 'Customer cannot use admin routes')
    check(alice.post('admin/user_insert.php', {'firstName': 'Fake'})[0] == 403, 'Customer cannot create admins')
    profile = alice.get('admin/Customers_profile.php')[1]
    check('&lt;script&gt;' in profile and '<script>alert' not in profile, 'Profile values are HTML-escaped')
    original_hash = sql('SELECT CPassword FROM Customers WHERE CustomerID = ?', [aid])[0]['CPassword']
    check(original_hash != 'Customer-pass-42' and original_hash.startswith('$'), 'Customer passwords are hashed')
    values = customer_data('alice@example.test', "Alice <script>alert('x')</script>")
    values.update(City='Burnaby', ContactName='Alice updated', CPassword='')
    check(alice.post('admin/Customers_profile.php', values)[0] == 303, 'Profile update succeeds')
    profile_row = sql('SELECT City, ContactName, CPassword FROM Customers WHERE CustomerID = ?', [aid])[0]
    check(profile_row['City'] == 'Burnaby' and profile_row['CPassword'] == original_hash, 'City changes and blank password is preserved')
    alice.get('admin/catalog.php')
    check(alice.post('admin/Customers_profile.php', values, csrf=False)[0] == 403, 'Profile update requires CSRF')
    duplicate = Client(base)
    duplicate.get('admin/Customers_insert.php')
    code, body, _ = duplicate.post('admin/Customers_insert.php', customer_data('ALICE@example.test'))
    check(code == 409, 'Duplicate email is rejected')
    inputs = {field.get('name'): field.get('value') for field in Document(body).inputs}
    check(inputs['CEmail'] == 'ALICE@example.test' and inputs['City'] == 'Vancouver' and
          inputs['CPassword'] == '' and 'id="form-error"' in body, 'Failed registration preserves non-password input and shows an accessible error')
    invalid_profile = dict(values, CEmail='bob@example.test', City='Retain this city', CPassword='Another-pass-42')
    code, body, _ = alice.post('admin/Customers_profile.php', invalid_profile)
    inputs = {field.get('name'): field.get('value') for field in Document(body).inputs}
    check(code == 409 and inputs['City'] == 'Retain this city' and inputs['CPassword'] == '', 'Failed profile edit retains typed values without echoing a password')
    check(sql('SELECT CPassword FROM Customers WHERE CustomerID = ?', [aid])[0]['CPassword'] == original_hash,
          'Duplicate email rolls back profile password changes')

    product = sql('SELECT pcode, pprice, pname FROM Products ORDER BY pcode LIMIT 1')[0]
    pid = int(product['pcode'])
    for qty in ['0', '-1', '1.5', '1e2', '10000', '999999999999999999999']:
        check(alice.post('admin/addToOrders.php', {'pcode': pid, 'qty': qty})[0] == 422, 'Invalid quantity rejected: ' + qty)
    check(alice.post('admin/addToOrders.php', {'pcode': pid, 'qty': 1}, csrf=False)[0] == 403, 'Cart update requires CSRF')
    response = alice.post('admin/addToOrders.php', {'pcode': pid, 'qty': 2, 'oid': 'new', 'pprice': 1, 'pname': 'Forged'})
    check(response[0] == 303, 'Product can be added to cart')
    oid = int(sql('SELECT OrderID FROM Orders WHERE CustomerID = ?', [aid])[0]['OrderID'])
    item = sql('SELECT * FROM OrderDetails WHERE OrderID = ?', [oid])[0]
    check(int(product['pprice']) in Document(alice.get('admin/catalog.php')[1]).prices and
          2 * int(product['pprice']) in Document(alice.get(f'admin/OrderDetails_list.php?OrderID={oid}')[1]).prices,
          'Catalog, order subtotals, and totals carry immutable IDR values for conversion')
    check(int(item['pprice']) == int(product['pprice']) and item['pname'] == product['pname'] and int(item['qty']) == 2,
          'Order uses server-side product name and price')
    for path in ['admin/catalog.php?filterby=2', 'admin/catalog.php?search=Mimikyu', 'admin/catalog.php?category=TOYS&search=Mimikyu']:
        code, body, _ = alice.get(path)
        check(code == 200 and f'name="oid" value="{oid}"' in body, 'Search/filter keeps the active order')
        ids = Document(body).ids
        check(len(ids) == len(set(ids)), 'Catalog has unique IDs')
    check(alice.get('admin/catalog.php?filterby=999')[0] == 422, 'Unknown filter is handled')
    check(alice.get("admin/catalog.php?search=" + urllib.parse.quote("' OR 1=1 --"))[0] == 200, 'Search safely handles SQL punctuation')
    check(alice.post('admin/addToOrders.php', {'pcode': pid, 'qty': 1, 'oid': ''})[0] == 303, 'Adding after navigation succeeds')
    check(len(sql('SELECT OrderID FROM Orders WHERE CustomerID = ?', [aid])) == 1, 'Navigation does not create another invoice')
    check(int(sql('SELECT qty FROM OrderDetails WHERE OrderID = ?', [oid])[0]['qty']) == 3, 'Repeated adds combine quantities')
    sql('UPDATE OrderDetails SET qty = 9999 WHERE OrderID = ?', [oid])
    check(alice.post('admin/addToOrders.php', {'pcode': pid, 'qty': 1, 'oid': oid})[0] == 422 and
          int(sql('SELECT qty FROM OrderDetails WHERE OrderID = ?', [oid])[0]['qty']) == 9999,
          'Combined quantity limit rolls back the complete update')
    sql('UPDATE OrderDetails SET qty = 3 WHERE OrderID = ?', [oid])
    for path in [f'admin/OrderDetails_list.php?OrderID={oid}', f'admin/catalog.php?oid={oid}']:
        check(bob.get(path)[0] == 404, 'Other customer cannot read/use order ID')
    check(bob.post('admin/addToOrders.php', {'pcode': pid, 'qty': 1, 'oid': oid})[0] == 404, 'Other customer cannot modify order ID')
    check(bob.post('admin/Customers_cancelOrder.php', {'OrderID': oid})[0] == 404, 'Other customer cannot cancel order ID')
    check(alice.get(f'admin/Customers_cancelOrder.php?OrderID={oid}')[0] == 405, 'GET cannot cancel orders')
    check(alice.post('admin/check_paid.php', {'OrderID': oid})[0] == 403, 'Customer cannot mark payment status')
    check(admin.get(f'admin/check_paid.php?OrderID={oid}')[0] == 405, 'GET cannot mark orders paid')
    check(admin.post('admin/check_paid.php', {'OrderID': oid})[0] == 303, 'Admin can mark invoice paid')
    check(alice.post('admin/addToOrders.php', {'pcode': pid, 'qty': 1, 'oid': oid})[0] == 409, 'Paid orders cannot be edited')
    check(alice.post('admin/Customers_cancelOrder.php', {'OrderID': oid})[0] == 409, 'Paid order cannot be cancelled')
    check(alice.post('admin/addToOrders.php', {'pcode': pid, 'qty': 1})[0] == 303, 'Shopping after payment opens a separate invoice')
    other_invoice = int(sql("SELECT OrderID FROM Orders WHERE CustomerID = ? AND OrderStatus = 'INVOICE'", [aid])[0]['OrderID'])
    check(admin.post('admin/uncheck_paid.php', {'OrderID': oid})[0] == 409 and
          sql('SELECT OrderStatus FROM Orders WHERE OrderID = ?', [oid])[0]['OrderStatus'] == 'PAID',
          'Reopening cannot create competing active invoices')
    check(alice.post('admin/Customers_cancelOrder.php', {'OrderID': other_invoice})[0] == 303, 'Other invoice can be closed before reopening')
    check(admin.post('admin/uncheck_paid.php', {'OrderID': oid})[0] == 303, 'Admin can reopen a paid invoice')
    check(sql('SELECT InvoiceExpiresAt > CURRENT_TIMESTAMP AS future FROM Orders WHERE OrderID = ?', [oid])[0]['future'] == 1,
          'Reopened invoice gets a new payment window')
    check(alice.post('admin/Customers_cancelOrder.php', {'OrderID': oid})[0] == 303, 'Owner can cancel unpaid order')
    check(sql('SELECT OrderStatus FROM Orders WHERE OrderID = ?', [oid])[0]['OrderStatus'] == 'CANCELLED' and
          len(sql('SELECT OrderID FROM OrderDetails WHERE OrderID = ?', [oid])) == 1, 'Cancellation retains linked order history')
    check('CANCELLED' in alice.get('admin/Customers_history.php')[1], 'Cancelled order appears in history')

    # Independent sessions and worker processes exercise database concurrency, not just PHP session serialization.
    sessions = []
    for _ in range(4):
        session = Client(base)
        session.login('bob@example.test', 'Customer-pass-42')
        sessions.append(session)
    def add(session):
        return session.post('admin/addToOrders.php', {'pcode': pid, 'qty': 1, 'oid': 'new'})[0]
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        check(all(code == 303 for code in pool.map(add, sessions)), 'Concurrent cart requests all succeed')
    bobs_orders = sql('SELECT OrderID FROM Orders WHERE CustomerID = ?', [bid])
    check(len(bobs_orders) == 1, 'Concurrent sessions create exactly one invoice per customer')
    boid = int(bobs_orders[0]['OrderID'])
    check(int(sql('SELECT qty FROM OrderDetails WHERE OrderID = ?', [boid])[0]['qty']) == 4, 'Concurrent adds lose no quantity')

    # Start independent PHP processes as well: this remains a real database race
    # even on hosts whose development HTTP server serializes incoming requests.
    race_ids = []
    for label in ['race-one', 'race-two']:
        _, race_id = register(base, label + '@example.test', label)
        race_ids.append(race_id)
    runner = 'require getcwd() . "/app/bootstrap.php"; $v=json_decode(stream_get_contents(STDIN),true); echo add_order_item((int)$v[0],(int)$v[1],1,"new");'
    jobs = []
    for race_id in race_ids:
        for _ in range(4):
            process = subprocess.Popen([PHP, '-r', runner], cwd=ROOT, env=ENV, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            process.stdin.write(json.dumps([race_id, pid]))
            process.stdin.close()
            jobs.append((race_id, process))
    for race_id, process in jobs:
        output = process.stdout.read()
        error = process.stderr.read()
        check(process.wait(timeout=20) == 0, 'Independent database writer completes: ' + error)
        check(sql('SELECT CustomerID FROM Orders WHERE OrderID = ?', [int(output)])[0]['CustomerID'] == race_id,
              'Concurrent insert ID belongs to the correct customer')
    for race_id in race_ids:
        race_orders = sql('SELECT OrderID FROM Orders WHERE CustomerID = ?', [race_id])
        check(len(race_orders) == 1 and
              int(sql('SELECT qty FROM OrderDetails WHERE OrderID = ?', [race_orders[0]['OrderID']])[0]['qty']) == 4,
              'Independent processes share one invoice and preserve every item')
    sql('UPDATE Orders SET InvoiceExpiresAt = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 SECOND) WHERE OrderID = ?', [boid])
    bob.get('admin/Customers_orders.php')
    check(sql('SELECT OrderStatus FROM Orders WHERE OrderID = ?', [boid])[0]['OrderStatus'] == 'CANCELLED', 'Request-time expiry cancels overdue invoice')
    check(admin.post('admin/check_paid.php', {'OrderID': boid})[0] == 409, 'Expired invoice cannot be marked paid')
    bob.post('admin/addToOrders.php', {'pcode': pid, 'qty': 1})
    next_oid = int(sql("SELECT OrderID FROM Orders WHERE CustomerID = ? AND OrderStatus = 'INVOICE'", [bid])[0]['OrderID'])
    sql('UPDATE Orders SET InvoiceExpiresAt = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 SECOND) WHERE OrderID = ?', [next_oid])
    cli('scripts/expire-orders.php')
    check(sql('SELECT OrderStatus FROM Orders WHERE OrderID = ?', [next_oid])[0]['OrderStatus'] == 'CANCELLED', 'CLI expiry works without a page visit')

    check(admin.get(f'admin/catalog_admin.php?cid={aid}')[0] == 200, 'Shop As renders for selected customer')
    check(admin.post('admin/addToOrders_admin.php', {'cid': aid, 'pcode': pid, 'qty': 2})[0] == 303, 'Admin order uses selected customer')
    admin_oid = int(sql("SELECT OrderID FROM Orders WHERE CustomerID = ? AND OrderStatus = 'INVOICE'", [aid])[0]['OrderID'])
    check(admin.get(f'admin/OrderDetails_list.php?OrderID={admin_oid}')[0] == 200, 'Admin can view order details without customer login')
    check(admin.get('admin/shop_admin.php')[0] == 200, 'Lowercase Shop As compatibility path works')

    product_data = {'pname': "O'Reilly <script>safe</script>", 'pprice': 5000, 'pcategory': 'ELECTRONICS',
                    'pimage': 'assets/img/portfolio/mimikyu.jpg', 'pdesc': "Quote's description <b>text</b>", 'is_active': 1}
    check(admin.post('admin/products_insert.php', product_data)[0] == 303, 'Product creation handles apostrophes')
    new_pid = int(sql('SELECT pcode FROM Products WHERE pname = ?', [product_data['pname']])[0]['pcode'])
    product_data['pprice'] = 6000
    check(admin.post(f'admin/products_update.php?pcode={new_pid}', product_data)[0] == 303, 'Product editing works')
    highlights = public.get('index.php')[1]
    check('Rp. 6.000' in highlights and 'O&#039;Reilly &lt;script&gt;safe&lt;/script&gt;' in highlights and
          '<script>safe</script>' not in highlights, 'Public highlights use current catalog prices and safely escaped product content')
    invalid_product = dict(product_data, pprice='-1', pname='Keep this product name')
    code, body, _ = admin.post(f'admin/products_update.php?pcode={new_pid}', invalid_product)
    inputs = {field.get('name'): field.get('value') for field in Document(body).inputs}
    check(code == 422 and inputs['pname'] == 'Keep this product name' and '&lt;b&gt;text&lt;/b&gt;' in body,
          'Invalid product edit preserves fields and safely renders its description')
    check('&lt;script&gt;' in admin.get('admin/products_list.php')[1], 'Product output is escaped')
    bad_product = dict(product_data, pimage='javascript:alert(1)')
    check(admin.post(f'admin/products_update.php?pcode={new_pid}', bad_product)[0] == 422, 'Unsafe image URL is rejected')
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/R5sAAAAASUVORK5CYII=')
    check(admin.upload(new_pid, b'<?php echo "not an image";', 'image/jpeg', 'test.jpg')[0] == 422, 'Spoofed image content is rejected')
    check(admin.upload(new_pid, png, csrf=False)[0] == 403, 'Upload requires CSRF')
    check(admin.upload(new_pid, png)[0] == 303, 'Valid image upload succeeds')
    uploaded = sql('SELECT pimage FROM Products WHERE pcode = ?', [new_pid])[0]['pimage']
    UPLOADS.append(ROOT / uploaded)
    check(uploaded.startswith('assets/uploads/product_') and (ROOT / uploaded).exists(), 'Upload updates product image and stores randomized filename')
    check(admin.upload(new_pid, b'x' * (2 * 1024 * 1024 + 1))[0] == 422, 'Oversized upload rejected')
    check(admin.get(f'admin/products_delete.php?pcode={new_pid}')[0] == 200 and
          sql('SELECT is_active FROM Products WHERE pcode = ?', [new_pid])[0]['is_active'] == 1, 'Delete GET only asks for confirmation')
    check(admin.post(f'admin/products_delete.php?pcode={new_pid}')[0] == 303, 'Product deletion works')
    check(bob.post('admin/addToOrders.php', {'pcode': new_pid, 'qty': 1})[0] == 404, 'Archived product cannot be ordered')
    check(sql('SELECT is_active FROM Products WHERE pcode = ?', [new_pid])[0]['is_active'] == 0, 'Product archive retains the database record')
    check('O&#039;Reilly' not in public.get('index.php')[1], 'Archived products disappear from public highlights')

    values = customer_data('alice@example.test', 'Alice admin-edited')
    values.update(City='Richmond', CPassword='', is_active='1')
    check(admin.post(f'admin/Customers_update.php?CustomerID={aid}', values)[0] == 303, 'Admin customer editing works')
    check(sql('SELECT City FROM Customers WHERE CustomerID = ?', [aid])[0]['City'] == 'Richmond', 'Admin customer edit saves city')
    admin.get('admin/user_insert.php')
    new_admin = {'firstName': 'Second', 'lastName': "O'Reilly", 'email': 'second-admin@example.test',
                 'pass': 'Second-pass-42', 'username': '', 'mobile': ''}
    check(admin.post('admin/user_insert.php', new_admin)[0] == 303, 'Admin creation supplies fields required by legacy schema')
    second_id = int(sql('SELECT id FROM users_tb WHERE email = ?', [new_admin['email']])[0]['id'])
    hash_before = sql('SELECT pass FROM users_tb WHERE id = ?', [second_id])[0]['pass']
    new_admin.update(firstName='Updated', **{'pass': ''})
    check(admin.post(f'admin/user_update.php?id={second_id}', new_admin)[0] == 303 and
          sql('SELECT pass FROM users_tb WHERE id = ?', [second_id])[0]['pass'] == hash_before, 'Admin editing preserves blank password')
    check(admin.post(f'admin/user_delete.php?id={second_id}')[0] == 303, 'Administrator deletion works')
    own_id = int(sql('SELECT id FROM users_tb WHERE email = ?', [admin_email])[0]['id'])
    check(admin.post(f'admin/user_delete.php?id={own_id}')[0] == 409, 'Cannot delete current/last administrator')

    # Crawl actual navigation, including case-sensitive filenames and confirmation pages.
    for client, start in [(admin, 'admin/dashboard.php'), (alice, 'admin/catalog.php')]:
        body = client.get(start)[1]
        for href in Document(body).links:
            parsed = urllib.parse.urlparse(href)
            if not parsed.netloc and parsed.path.endswith('.php'):
                code, linked_body, _ = client.get(parsed.path.lstrip('/') + ('?' + parsed.query if parsed.query else ''))
                check(code in (200, 303), 'Navigation target exists: ' + href)
                ids = Document(linked_body).ids
                check(len(ids) == len(set(ids)), 'Linked page has no duplicate IDs: ' + href)

    check(admin.get(f'admin/Customers_delete.php?CustomerID={bid}')[0] == 200 and
          len(sql('SELECT CustomerID FROM Customers WHERE CustomerID = ?', [bid])) == 1, 'Customer delete GET is non-mutating')
    check(bob.post('admin/addToOrders.php', {'pcode': pid, 'qty': 1})[0] == 303, 'Customer can create another invoice before deactivation')
    paid_id = int(sql("SELECT OrderID FROM Orders WHERE CustomerID = ? AND OrderStatus = 'INVOICE'", [bid])[0]['OrderID'])
    check(admin.post('admin/check_paid.php', {'OrderID': paid_id})[0] == 303, 'Customer has paid history to protect')
    before = sql('SELECT * FROM OrderDetails WHERE OrderID IN (SELECT OrderID FROM Orders WHERE CustomerID = ?) ORDER BY OrderDetailsID', [bid])
    check(admin.post(f'admin/Customers_delete.php?CustomerID={bid}', {'operation': 'delete'})[0] == 409,
          'Permanent deletion is refused for a customer with orders')
    check(admin.post(f'admin/Customers_delete.php?CustomerID={bid}', {'operation': 'deactivate'})[0] == 303,
          'Customer with history is deactivated')
    check(sql('SELECT is_active FROM Customers WHERE CustomerID = ?', [bid])[0]['is_active'] == 0 and
          sql('SELECT OrderStatus FROM Orders WHERE OrderID = ?', [paid_id])[0]['OrderStatus'] == 'PAID' and
          sql('SELECT * FROM OrderDetails WHERE OrderID IN (SELECT OrderID FROM Orders WHERE CustomerID = ?) ORDER BY OrderDetailsID', [bid]) == before,
          'Deactivation preserves paid orders and every historical line')
    check(not sql('SELECT d.OrderDetailsID FROM OrderDetails d LEFT JOIN Orders o ON o.OrderID = d.OrderID WHERE o.OrderID IS NULL'),
          'Order operations leave no orphan details')
    check(bob.get('admin/catalog.php')[0] == 303, 'Deactivated customer session is invalidated')
    check(admin.get(f'admin/catalog_admin.php?cid={bid}')[0] == 404 and
          admin.post('admin/addToOrders_admin.php', {'cid': bid, 'pcode': pid, 'qty': 1})[0] == 404,
          'Admin cannot shop for a deactivated customer')
    blocked = Client(base)
    blocked.get('index.php')
    check(blocked.post('index.php', {'email': 'bob@example.test', 'pass': 'Customer-pass-42', 'submit_customer': 1})[0] == 200 and
          blocked.get('admin/catalog.php')[0] == 303, 'Deactivated credentials cannot log in')
    restored = dict(customer_data('bob@example.test', 'Bob'), CPassword='', is_active='1')
    check(admin.post(f'admin/Customers_update.php?CustomerID={bid}', restored)[0] == 303,
          'Admin can reactivate a customer')
    check(bob.get('admin/catalog.php')[0] == 303, 'Reactivation does not restore an already-invalidated session')
    blocked.login('bob@example.test', 'Customer-pass-42')
    check('Paid' in blocked.get('admin/Customers_history.php')[1], 'Reactivated customer retains access to paid history')
    _, empty_id = register(base, 'empty@example.test')
    check(admin.post(f'admin/Customers_delete.php?CustomerID={empty_id}', {'operation': 'delete'})[0] == 303 and
          not sql('SELECT CustomerID FROM Customers WHERE CustomerID = ?', [empty_id]),
          'An account without orders can still be permanently deleted')
    alice.get('admin/Customers_profile.php')
    check(alice.get('admin/logout.php')[0] == 405, 'GET cannot log a customer out')
    check(alice.post('admin/logout.php')[0] == 303 and alice.get('admin/catalog.php')[0] == 303, 'POST logout invalidates customer session')


LEGACY = [
    "CREATE TABLE Customers (CustomerID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, CustomerName VARCHAR(30) NOT NULL, ContactName VARCHAR(30) NOT NULL, CAddress VARCHAR(500) NOT NULL, City VARCHAR(20) NOT NULL, PostalCode VARCHAR(10), Country VARCHAR(20) NOT NULL, CPassword VARCHAR(16) NOT NULL, CEmail VARCHAR(20) NOT NULL, reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
    "CREATE TABLE users_tb (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, firstname VARCHAR(30) NOT NULL, lastname VARCHAR(30), email VARCHAR(50) NOT NULL, username VARCHAR(10) NOT NULL, pass VARCHAR(10) NOT NULL, mobile VARCHAR(13) NOT NULL, reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
    "CREATE TABLE Products (pcode INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pcategory TEXT NOT NULL, pprice INT NOT NULL, pimage VARCHAR(500) NOT NULL, pname VARCHAR(30) NOT NULL, pdesc VARCHAR(2000) NOT NULL, reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
    "CREATE TABLE Orders (OrderID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, OrderDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP, CustomerID INT NOT NULL, OrderStatus VARCHAR(10) NOT NULL, reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
    "CREATE TABLE OrderDetails (OrderDetailsID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, OrderID INT NOT NULL, pcode INT NOT NULL, pprice INT NOT NULL, qty INT NOT NULL, pname VARCHAR(100) NOT NULL, reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
    "INSERT INTO Customers (CustomerName, ContactName, CAddress, City, PostalCode, Country, CEmail, CPassword) VALUES ('Legacy', 'Legacy', 'Street', 'City', NULL, 'Canada', 'OLD@example.test', 'old-pass-42')",
    "INSERT INTO users_tb (firstname, lastname, email, username, mobile, pass) VALUES ('Legacy', NULL, 'legacy-admin@example.test', 'legacy', '', 'old-admin')",
    "INSERT INTO Products (pcategory, pprice, pimage, pname, pdesc) VALUES ('TOYS', 10, 'assets/img/logo-mae.png', 'Legacy toy', 'Description')",
    "INSERT INTO Orders (CustomerID, OrderStatus, OrderDate) VALUES (1, 'INVOICE', DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 25 HOUR))",
    "INSERT INTO OrderDetails (OrderID, pcode, pprice, qty, pname) VALUES (1, 1, 10, 1, 'Legacy toy'), (999, 1, 10, 1, 'Pre-existing orphan')",
]


def run_migration():
    name = ENV['DB_NAME'] + '_legacy'
    create_database(name)
    for statement in LEGACY:
        sql(statement, database=name)
    env = dict(ENV, DB_NAME=name)
    result = cli('scripts/migrate.php', env)
    check('Pre-existing orphan detail rows retained for manual review: 1' in result.stdout, 'Migration reports and preserves pre-existing orphan data')
    customer = sql('SELECT * FROM Customers', database=name)[0]
    check(customer['CPassword'].startswith('$') and customer['CEmail'] == 'old@example.test' and customer['PostalCode'] == '',
          'Legacy migration hashes password, normalizes email, handles nullable postal code')
    check(sql('SELECT OrderStatus FROM Orders WHERE OrderID = 1', database=name)[0]['OrderStatus'] == 'CANCELLED', 'Migration applies expiry to old invoices')
    check(len(sql('SELECT OrderID FROM OrderDetails', database=name)) == 2, 'Migration does not silently delete old detail records')
    check('already current' in cli('scripts/migrate.php', env).stdout and
          sql('SELECT CPassword FROM Customers', database=name)[0]['CPassword'] == customer['CPassword'], 'Migration is idempotent and does not hash a hash')
    with server(env) as base:
        client = Client(base)
        client.login('old@example.test', 'old-pass-42')
        admin = Client(base)
        admin.login('legacy-admin@example.test', 'old-admin', admin=True)
        values = customer_data('old@example.test', 'Updated legacy')
        values['CPassword'] = ''
        check(client.post('admin/Customers_profile.php', values)[0] == 303, 'Migrated customer can update profile')
        check(admin.post('admin/addToOrders_admin.php', {'cid': 1, 'pcode': 1, 'qty': 1})[0] == 303, 'Migrated database supports new order flow')

    duplicate_name = ENV['DB_NAME'] + '_duplicate'
    create_database(duplicate_name)
    for statement in LEGACY[:6]:
        sql(statement, database=duplicate_name)
    sql("INSERT INTO Customers SELECT 2, CustomerName, ContactName, CAddress, City, PostalCode, Country, CPassword, CEmail, reg_date FROM Customers WHERE CustomerID = 1", database=duplicate_name)
    result = cli('scripts/migrate.php', dict(ENV, DB_NAME=duplicate_name), success=False)
    check(result.returncode != 0 and 'Duplicate email' in result.stderr and
          sql('SELECT CPassword FROM Customers LIMIT 1', database=duplicate_name)[0]['CPassword'] == 'old-pass-42',
          'Migration preflight refuses duplicate identities before changing data')

    # Upgrade a database installed by the earlier repair draft, not just the original schema.
    version_one = ENV['DB_NAME'] + '_v1'
    create_database(version_one)
    for statement in (ROOT / 'database/schema.sql').read_text().split(';'):
        if statement.strip():
            sql(statement, database=version_one)
    sql("INSERT INTO Customers (CustomerName, ContactName, CAddress, City, Country, CEmail, CPassword) VALUES ('Existing', 'Existing', 'Street', 'City', 'Canada', 'existing@example.test', ?)", [customer['CPassword']], database=version_one)
    sql('ALTER TABLE Customers DROP COLUMN is_active', database=version_one)
    sql('DELETE FROM MaeSchema', database=version_one)
    sql('INSERT INTO MaeSchema VALUES (1)', database=version_one)
    cli('scripts/migrate.php', dict(ENV, DB_NAME=version_one))
    upgraded = sql('SELECT CPassword, is_active FROM Customers', database=version_one)[0]
    check(upgraded['CPassword'] == customer['CPassword'] and upgraded['is_active'] == 1 and
          sql('SELECT version FROM MaeSchema WHERE version = 2', database=version_one),
          'Version 1 upgrade adds account availability without changing password hashes')
    check('already current' in cli('scripts/migrate.php', dict(ENV, DB_NAME=version_one)).stdout,
          'Version 1 upgrade remains idempotent')


def main():
    for path in ROOT.rglob('*.php'):
        result = subprocess.run([PHP, '-l', str(path)], capture_output=True, text=True)
        check(result.returncode == 0, 'PHP syntax: ' + str(path.relative_to(ROOT)))
    create_database(ENV['DB_NAME'])
    seed = subprocess.run([PHP, str(ROOT / 'scripts/setup.php'), '--seed'], env=ENV, capture_output=True, text=True)
    check(seed.returncode == 0, 'Fresh setup: ' + seed.stderr)
    admin_email, admin_password = 'admin@example.test', secrets.token_urlsafe(20)
    cli('scripts/create-admin.php', dict(ENV, ADMIN_EMAIL=admin_email, ADMIN_PASSWORD=admin_password))
    with server(ENV) as base:
        run_http(base, admin_email, admin_password)
    with server(dict(ENV, APP_BASE_PATH='/mae')) as base:
        admin = Client(base)
        admin.login(admin_email, admin_password, admin=True)
        check('/mae/admin/products_list.php' in admin.get('admin/dashboard.php')[1], 'Subdirectory navigation preserves base path')
        body = admin.get('admin/dashboard.php')[1]
        check('content="/mae/api/exchange-rates.php"' in body and 'src="/mae/js/currency.js"' in body,
              'Currency assets and endpoint respect subdirectory installations')
    run_migration()
    print(f'PASS: {CHECKS} assertions covering syntax, HTTP flows, permissions, orders, uploads, concurrency, and legacy migration.')


if __name__ == '__main__':
    try:
        main()
    finally:
        shutil.rmtree(FX_DIRECTORY, ignore_errors=True)
        for path in UPLOADS:
            path.unlink(missing_ok=True)
        for name in reversed(DATABASES):
            sql(f'DROP DATABASE `{name}`', database='')
