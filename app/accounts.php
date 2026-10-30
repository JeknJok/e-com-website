<?php
declare(strict_types=1);

function account_write(callable $callback): mixed
{
    try {
        return $callback();
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() === 1062) {
            throw new HttpError(409, 'That email address is already registered.');
        }
        throw $error;
    }
}

function save_customer(?int $id = null, bool $asAdmin = false): int
{
    $values = [text_value('CustomerName', 100), text_value('ContactName', 100), text_value('CAddress', 500), text_value('City', 100), text_value('PostalCode', 20), text_value('Country', 100), email_value('CEmail')];
    $password = input('CPassword');
    $hash = $id !== null && $password === '' ? null : new_password($password);
    $availability = $asAdmin ? input('is_active') : '';
    if ($asAdmin && !in_array($availability, ['0', '1'], true)) {
        throw new HttpError(422, 'Choose an account status.');
    }
    return account_write(function () use ($id, $values, $hash, $asAdmin, $availability): int {
        if ($id === null) {
            execute('INSERT INTO Customers (CustomerName, ContactName, CAddress, City, PostalCode, Country, CEmail, CPassword) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [...$values, $hash]);
            return (int)db()->insert_id;
        }
        return transaction(function () use ($id, $values, $hash, $asAdmin, $availability): int {
            if (!row('SELECT CustomerID FROM Customers WHERE CustomerID = ? FOR UPDATE', [$id])) {
                throw new HttpError(404, 'Customer not found.');
            }
            execute('UPDATE Customers SET CustomerName = ?, ContactName = ?, CAddress = ?, City = ?, PostalCode = ?, Country = ?, CEmail = ? WHERE CustomerID = ?', [...$values, $id]);
            if ($hash !== null) {
                execute('UPDATE Customers SET CPassword = ? WHERE CustomerID = ?', [$hash, $id]);
            }
            if ($asAdmin) {
                execute('UPDATE Customers SET is_active = ? WHERE CustomerID = ?', [(int)$availability, $id]);
            }
            return $id;
        });
    });
}

function customer_form(array $customer, bool $editing, bool $asAdmin = false): void
{
    ?><div class="form-panel"><?php form_errors(); ?><form method="post"><?= csrf_field() ?><?php
    foreach (['CustomerName' => ['Full name', 100], 'ContactName' => ['Username', 100], 'CAddress' => ['Address', 500], 'CEmail' => ['Email', 254], 'Country' => ['Country', 100], 'City' => ['City', 100], 'PostalCode' => ['Postal code', 20]] as $name => [$label, $maximum]) {
        field($name, $label, $customer[$name] ?? '', $name === 'CEmail' ? 'email' : 'text', $maximum);
    }
    field('CPassword', $editing ? 'New password (leave blank to keep your password)' : 'Password (8–72 bytes)', '', 'password', 72, !$editing);
    if ($asAdmin && $editing) {
        ?><div class="mb-3"><label class="form-label" for="is_active">Account status</label><select class="form-select" id="is_active" name="is_active"><option value="1">Active</option><option value="0" <?= submitted_value('is_active', $customer['is_active'] ?? 1) === '0' ? 'selected' : '' ?>>Deactivated</option></select><p class="form-text">Deactivated customers cannot sign in or place orders. Their order history is preserved.</p></div><?php
    }
    ?><button class="btn btn-primary" type="submit"><?= $editing ? 'Update data' : 'Register' ?></button></form></div><?php
}

function save_admin(?int $id = null): int
{
    $values = [text_value('firstName', 100), text_value('lastName', 100, false), email_value('email'), text_value('username', 100, false), text_value('mobile', 30, false)];
    $password = input('pass');
    $hash = $id !== null && $password === '' ? null : new_password($password);
    return account_write(function () use ($id, $values, $hash): int {
        if ($id === null) {
            execute('INSERT INTO users_tb (firstname, lastname, email, username, mobile, pass) VALUES (?, ?, ?, ?, ?, ?)', [...$values, $hash]);
            return (int)db()->insert_id;
        }
        return transaction(function () use ($id, $values, $hash): int {
            if (!row('SELECT id FROM users_tb WHERE id = ? FOR UPDATE', [$id])) { throw new HttpError(404, 'Administrator not found.'); }
            execute('UPDATE users_tb SET firstname = ?, lastname = ?, email = ?, username = ?, mobile = ? WHERE id = ?', [...$values, $id]);
            if ($hash !== null) { execute('UPDATE users_tb SET pass = ? WHERE id = ?', [$hash, $id]); }
            return $id;
        });
    });
}

function admin_form(array $admin, bool $editing): void
{
    ?><div class="form-panel"><?php form_errors(); ?><form method="post"><?= csrf_field() ?><?php
    foreach (['firstName' => ['First name', 'firstname', 100], 'lastName' => ['Last name', 'lastname', 100], 'email' => ['Email', 'email', 254], 'username' => ['Username', 'username', 100], 'mobile' => ['Mobile', 'mobile', 30]] as $name => [$label, $column, $maximum]) {
        field($name, $label, $admin[$column] ?? '', $name === 'email' ? 'email' : 'text', $maximum, in_array($name, ['firstName', 'email'], true));
    }
    field('pass', $editing ? 'New password (leave blank to keep current password)' : 'Password (8–72 bytes)', '', 'password', 72, !$editing);
    ?><button class="btn btn-primary" type="submit"><?= $editing ? 'Update administrator' : 'Create administrator' ?></button></form></div><?php
}
