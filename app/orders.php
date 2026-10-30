<?php
declare(strict_types=1);

function list_orders(?int $customerId = null, bool $history = false): array
{
    return rows('SELECT o.*, c.ContactName, COALESCE(t.total, 0) AS total FROM Orders o JOIN Customers c ON c.CustomerID = o.CustomerID LEFT JOIN (SELECT OrderID, SUM(pprice * qty) AS total FROM OrderDetails GROUP BY OrderID) t ON t.OrderID = o.OrderID'
        . ($customerId === null ? '' : " WHERE o.CustomerID = ? AND o.OrderStatus " . ($history ? "IN ('PAID', 'CANCELLED')" : "= 'INVOICE'"))
        . ' ORDER BY o.OrderID DESC', $customerId === null ? [] : [$customerId]);
}

function expire_orders(?int $customerId = null): void
{
    $sql = "UPDATE Orders SET OrderStatus = 'CANCELLED', InvoiceExpiresAt = NULL WHERE OrderStatus = 'INVOICE' AND InvoiceExpiresAt <= CURRENT_TIMESTAMP";
    execute($sql . ($customerId === null ? '' : ' AND CustomerID = ?'), $customerId === null ? [] : [$customerId]);
}

function accessible_order(int $orderId, ?int $customerId = null, bool $lock = false): array
{
    $order = row('SELECT * FROM Orders WHERE OrderID = ?' . ($lock ? ' FOR UPDATE' : ''), [$orderId]);
    if (!$order || ($customerId !== null && (int)$order['CustomerID'] !== $customerId)) {
        throw new HttpError(404, 'Order not found.');
    }
    return $order;
}

function active_order(int $customerId, string $requested = ''): ?array
{
    expire_orders($customerId);
    if ($requested !== '' && $requested !== 'new') {
        $order = accessible_order(positive_int($requested), $customerId);
        if ($order['OrderStatus'] !== 'INVOICE') {
            throw new HttpError(409, 'This order is no longer open. Return to Shop to start another order.');
        }
    } else {
        $id = $_SESSION['active_orders'][$customerId] ?? null;
        $order = $id ? row("SELECT * FROM Orders WHERE OrderID = ? AND CustomerID = ? AND OrderStatus = 'INVOICE'", [$id, $customerId]) : null;
        $order ??= row("SELECT * FROM Orders WHERE CustomerID = ? AND OrderStatus = 'INVOICE' ORDER BY OrderID DESC LIMIT 1", [$customerId]);
    }
    if ($order) {
        $_SESSION['active_orders'][$customerId] = (int)$order['OrderID'];
    } else {
        unset($_SESSION['active_orders'][$customerId]);
    }
    return $order;
}

function add_order_item(int $customerId, int $productId, int $quantity, string $requested = ''): int
{
    return transaction(function () use ($customerId, $productId, $quantity, $requested): int {
        // A customer-row lock serializes cart creation across sessions/connections.
        if (!row('SELECT CustomerID FROM Customers WHERE CustomerID = ? AND is_active = 1 FOR UPDATE', [$customerId])) {
            throw new HttpError(404, 'Active customer not found.');
        }
        $order = active_order($customerId, $requested);
        $product = row('SELECT pcode, pname, pprice FROM Products WHERE pcode = ? AND is_active = 1 LOCK IN SHARE MODE', [$productId]);
        if (!$product) {
            throw new HttpError(404, 'Product is no longer available.');
        }
        if (!$order) {
            execute("INSERT INTO Orders (CustomerID, OrderStatus, InvoiceExpiresAt) VALUES (?, 'INVOICE', DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 24 HOUR))", [$customerId]);
            $orderId = (int)db()->insert_id;
        } else {
            $orderId = (int)$order['OrderID'];
            $order = accessible_order($orderId, $customerId, true);
            if ($order['OrderStatus'] !== 'INVOICE' || strtotime($order['InvoiceExpiresAt'] . ' UTC') <= time()) {
                throw new HttpError(409, 'This order is no longer open.');
            }
        }
        $item = row('SELECT OrderDetailsID, qty FROM OrderDetails WHERE OrderID = ? AND pcode = ? FOR UPDATE', [$orderId, $productId]);
        if ($item) {
            $quantity += (int)$item['qty'];
            if ($quantity > 9999) {
                throw new HttpError(422, 'An order may contain at most 9999 units of one product.');
            }
            execute('UPDATE OrderDetails SET qty = ? WHERE OrderDetailsID = ?', [$quantity, $item['OrderDetailsID']]);
        } else {
            execute('INSERT INTO OrderDetails (OrderID, pcode, pprice, qty, pname) VALUES (?, ?, ?, ?, ?)', [$orderId, $productId, $product['pprice'], $quantity, $product['pname']]);
        }
        $_SESSION['active_orders'][$customerId] = $orderId;
        return $orderId;
    }, 3);
}

function cancel_order(int $orderId, ?int $customerId): void
{
    transaction(function () use ($orderId, $customerId): void {
        $order = accessible_order($orderId, $customerId, true);
        if ($order['OrderStatus'] !== 'INVOICE') {
            throw new HttpError(409, 'Only unpaid orders can be cancelled.');
        }
        // Retain both the order and its items as linked cancellation history.
        execute("UPDATE Orders SET OrderStatus = 'CANCELLED', InvoiceExpiresAt = NULL WHERE OrderID = ?", [$orderId]);
        unset($_SESSION['active_orders'][(int)$order['CustomerID']]);
    }, 3);
}

function change_payment_status(int $orderId, bool $paid): void
{
    transaction(function () use ($orderId, $paid): void {
        $owner = accessible_order($orderId);
        // Use the same lock order as additions, so reopening cannot race cart creation.
        row('SELECT CustomerID FROM Customers WHERE CustomerID = ? FOR UPDATE', [$owner['CustomerID']]);
        expire_orders((int)$owner['CustomerID']);
        $order = accessible_order($orderId, null, true);
        $expected = $paid ? 'INVOICE' : 'PAID';
        if ($order['OrderStatus'] !== $expected || ($paid && strtotime($order['InvoiceExpiresAt'] . ' UTC') <= time())) {
            throw new HttpError(409, 'The order status changed or its invoice expired. Reload the order list.');
        }
        if ($paid) {
            if (!row('SELECT OrderDetailsID FROM OrderDetails WHERE OrderID = ? LIMIT 1', [$orderId])) {
                throw new HttpError(409, 'An empty order cannot be marked paid.');
            }
            execute("UPDATE Orders SET OrderStatus = 'PAID', InvoiceExpiresAt = NULL WHERE OrderID = ?", [$orderId]);
        } else {
            if (row("SELECT OrderID FROM Orders WHERE CustomerID = ? AND OrderStatus = 'INVOICE' AND OrderID <> ? LIMIT 1 FOR UPDATE", [$order['CustomerID'], $orderId])) {
                throw new HttpError(409, 'This customer already has an open invoice. Close it before reopening another.');
            }
            execute("UPDATE Orders SET OrderStatus = 'INVOICE', InvoiceExpiresAt = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 24 HOUR) WHERE OrderID = ?", [$orderId]);
        }
        unset($_SESSION['active_orders'][(int)$order['CustomerID']]);
    }, 3);
}
