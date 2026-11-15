<?php
declare(strict_types=1);

function erase_customer(int $id, ?int $expectedVersion = null): void
{
    transaction(function () use ($id, $expectedVersion): void {
        $customer = row('SELECT CustomerID, erased_at, auth_version FROM Customers WHERE CustomerID = ? FOR UPDATE', [$id]);
        if (!$customer || $customer['erased_at'] !== null) { return; }
        if ($expectedVersion !== null && (int)$customer['auth_version'] !== $expectedVersion) { throw new HttpError(409, 'This account changed. Sign in and confirm erasure again.'); }
        audit_event('privacy.erasure', $id);
        execute('INSERT IGNORE INTO ErasureLedger (customer_id) VALUES (?)', [$id]);
        execute("UPDATE Customers SET CustomerName = 'Deleted demo account', ContactName = 'Deleted demo account', CAddress = '', City = '', PostalCode = '', Country = '', CEmail = ?, CPassword = ?, is_active = 0, erased_at = CURRENT_TIMESTAMP, auth_version = auth_version + 1 WHERE CustomerID = ?", ['erased-' . $id . '-' . bin2hex(random_bytes(8)) . '@example.test', hash_password(bin2hex(random_bytes(32))), $id]);
        execute("DELETE FROM RecoveryCodes WHERE account_kind = 'customer' AND account_id = ?", [$id]);
        execute("UPDATE Orders SET OrderStatus = 'CANCELLED', InvoiceExpiresAt = NULL WHERE CustomerID = ? AND OrderStatus = 'INVOICE'", [$id]);
    });
}
