<?php
declare(strict_types=1);

function account_write(callable $callback): mixed
{
    try { return $callback(); }
    catch (mysqli_sql_exception $error) {
        if ($error->getCode() === 1062) { throw new HttpError(409, 'This demo email is unavailable. Choose another made-up address ending in .test.'); }
        throw $error;
    }
}

function demo_email(): string
{
    $email = email_value('CEmail');
    if (!str_ends_with(substr($email, strrpos($email, '@') + 1), '.test')) {
        throw new HttpError(422, 'Use a fictional address ending in .test, such as explorer@example.test. No email is sent.');
    }
    return $email;
}

function save_customer(?int $id = null, bool $asAdmin = false): int
{
    $alias = text_value('ContactName', 100);
    $email = demo_email();
    $password = input('CPassword');
    $old = $id === null ? null : row('SELECT CustomerID, CEmail, CPassword, auth_version, is_active, erased_at FROM Customers WHERE CustomerID = ?', [$id]);
    if ($id !== null && (!$old || $old['erased_at'] !== null)) { throw new HttpError(404, 'Customer not found.'); }
    $sensitive = $old && ($old['CEmail'] !== $email || $password !== '');
    if ($id === null) { rate_limit('register', $email); }
    if ($asAdmin) { require_fresh_admin(); }
    elseif ($sensitive) { confirm_current_password($old, input('current_password')); }
    $hash = $id !== null && $password === '' ? null : new_password($password);
    $availability = $asAdmin && $id !== null ? input('is_active') : '1';
    if (!in_array($availability, ['0', '1'], true)) { throw new HttpError(422, 'Choose an account status.'); }
    $changed = null;
    $result = account_write(function () use ($id, $alias, $email, $hash, $old, $sensitive, $asAdmin, $availability, &$changed): int {
        return transaction(function () use ($id, $alias, $email, $hash, $old, $sensitive, $asAdmin, $availability, &$changed): int {
            if ($id === null) {
                execute('INSERT INTO Customers (CustomerName, ContactName, CAddress, City, PostalCode, Country, CEmail, CPassword) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$alias, $alias, '', '', '', '', $email, $hash]);
                $newId = (int)db()->insert_id;
                audit_event('account.register', $newId);
                return $newId;
            }
            $locked = row('SELECT auth_version, erased_at FROM Customers WHERE CustomerID = ? FOR UPDATE', [$id]);
            if (!$locked || $locked['erased_at'] !== null || (int)$locked['auth_version'] !== (int)$old['auth_version']) { throw new HttpError(409, 'This account changed. Reload and confirm your credentials again.'); }
            $revoke = $sensitive || ($asAdmin && (int)$old['is_active'] !== (int)$availability);
            execute('UPDATE Customers SET CustomerName = ?, ContactName = ?, CEmail = ? WHERE CustomerID = ?', [$alias, $alias, $email, $id]);
            if ($hash !== null) { execute('UPDATE Customers SET CPassword = ? WHERE CustomerID = ?', [$hash, $id]); }
            if ($asAdmin) { execute('UPDATE Customers SET is_active = ? WHERE CustomerID = ?', [(int)$availability, $id]); }
            if ($revoke) {
                execute('UPDATE Customers SET auth_version = auth_version + 1 WHERE CustomerID = ?', [$id]);
                execute("DELETE FROM RecoveryCodes WHERE account_kind = 'customer' AND account_id = ?", [$id]);
                $changed = (int)$old['auth_version'] + 1;
            }
            audit_event($revoke ? 'account.credentials_changed' : 'account.profile_updated', $id);
            return $id;
        });
    });
    if ($changed !== null && !$asAdmin) { refresh_customer_session($result, $changed); }
    return $result;
}

function customer_form(array $customer, bool $editing, bool $asAdmin = false): void
{
    ?><div class="form-panel"><?php form_errors(); ?><form method="post"><?= csrf_field() ?><?php
    field('ContactName', 'Demo username', $customer['ContactName'] ?? '', 'text', 100);
    field('CEmail', 'Fictional email ending in .test', $customer['CEmail'] ?? '', 'email', 254);
    ?><p class="form-text">For example: explorer@example.test. This is only a demo login name; no mailbox or email verification is needed.</p><?php
    field('CPassword', $editing ? 'New demo password (leave blank to keep it)' : 'Demo password (8–128 characters)', '', 'password', 256, !$editing);
    if ($editing && !$asAdmin) { field('current_password', 'Current demo password (required when changing email or password)', '', 'password', 256, false); }
    if ($asAdmin && $editing) {
        ?><div class="mb-3"><label class="form-label" for="is_active">Account status</label><select class="form-select" id="is_active" name="is_active"><option value="1">Active</option><option value="0" <?= submitted_value('is_active', $customer['is_active'] ?? 1) === '0' ? 'selected' : '' ?>>Deactivated</option></select><p class="form-text">Deactivated accounts cannot sign in. Erased accounts cannot be reactivated.</p></div><?php
    }
    ?><button class="btn btn-primary" type="submit"><?= $editing ? 'Update demo account' : 'Create demo account' ?></button></form></div><?php
}

function save_admin(?int $id = null): int
{
    require_fresh_admin();
    $values = [text_value('firstName', 100), text_value('lastName', 100, false), email_value('email'), text_value('username', 100, false), ''];
    $password = input('pass');
    $hash = $id !== null && $password === '' ? null : new_password($password, true);
    return account_write(function () use ($id, $values, $hash): int {
        return transaction(function () use ($id, $values, $hash): int {
            if ($id === null) {
                execute('INSERT INTO users_tb (firstname, lastname, email, username, mobile, pass) VALUES (?, ?, ?, ?, ?, ?)', [...$values, $hash]);
                $id = (int)db()->insert_id;
                audit_event('admin.created', $id);
                return $id;
            }
            $old = row('SELECT id, email FROM users_tb WHERE id = ? FOR UPDATE', [$id]);
            if (!$old) { throw new HttpError(404, 'Administrator not found.'); }
            execute('UPDATE users_tb SET firstname = ?, lastname = ?, email = ?, username = ?, mobile = ? WHERE id = ?', [...$values, $id]);
            if ($hash !== null) { execute('UPDATE users_tb SET pass = ? WHERE id = ?', [$hash, $id]); }
            if ($hash !== null || $old['email'] !== $values[2]) {
                execute('UPDATE users_tb SET auth_version = auth_version + 1 WHERE id = ?', [$id]);
                execute("DELETE FROM RecoveryCodes WHERE account_kind = 'admin' AND account_id = ?", [$id]);
            }
            audit_event('admin.updated', $id);
            return $id;
        });
    });
}

function admin_form(array $admin, bool $editing): void
{
    ?><div class="form-panel"><?php form_errors(); ?><form method="post"><?= csrf_field() ?><?php
    foreach (['firstName' => ['Display name', 'firstname', 100], 'lastName' => ['Last name', 'lastname', 100], 'email' => ['Administrator login email', 'email', 254], 'username' => ['Username', 'username', 100]] as $name => [$label, $column, $maximum]) {
        field($name, $label, $admin[$column] ?? '', $name === 'email' ? 'email' : 'text', $maximum, in_array($name, ['firstName', 'email'], true));
    }
    field('pass', $editing ? 'New administrator passphrase (leave blank to keep it)' : 'Administrator passphrase (15–128 characters)', '', 'password', 256, !$editing);
    ?><p class="form-text">New administrators must enroll an authenticator using the operator command before signing in. Changing administrator credentials signs that account out everywhere.</p><button class="btn btn-primary" type="submit"><?= $editing ? 'Update administrator' : 'Create administrator' ?></button></form></div><?php
}
