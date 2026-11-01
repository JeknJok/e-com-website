<?php
declare(strict_types=1);

function page_start(string $title, string $role = 'public', array $context = []): void
{
    $links = $role === 'admin'
        ? ['Dashboard' => 'admin/dashboard.php', 'Users' => 'admin/user_list.php', 'Shop As' => 'admin/Shop_admin.php', 'Products' => 'admin/products_list.php', 'Orders' => 'admin/Orders_list.php', 'Customers' => 'admin/Customers_list.php']
        : ($role === 'customer' ? ['Shop' => 'admin/catalog.php', 'Profile' => 'admin/Customers_profile.php', 'Active Orders' => 'admin/Customers_orders.php', 'Order History' => 'admin/Customers_history.php'] : ['Home / Login' => 'index.php#login', 'Register' => 'admin/Customers_insert.php']);
    $currentPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · M-A-E-Commerce</title>
<?php theme_head(); ?>
</head><body class="mae-app">
<a class="skip-link" href="#main-content">Skip to content</a>
<nav class="navbar navbar-expand-xl navbar-dark bg-dark app-navbar" aria-label="Main navigation"><div class="container-fluid app-container">
<a class="navbar-brand mae-brand" href="<?= e(url('index.php')) ?>"><img src="<?= e(url('assets/img/logo-mae.png')) ?>" width="44" height="42" alt=""><span>M-A-E-Commerce</span></a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNavigation" aria-controls="siteNavigation" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="siteNavigation"><ul class="navbar-nav me-auto">
<?php foreach ($links as $label => $path): $active = $currentPath === parse_url(url($path), PHP_URL_PATH); ?><li class="nav-item"><a class="nav-link <?= $active ? 'active' : '' ?>" <?= $active ? 'aria-current="page"' : '' ?> href="<?= e(url($path)) ?>"><?= e($label) ?></a></li><?php endforeach ?>
</ul>
<?php if ($role === 'customer'): ?>
<form class="d-flex nav-search" role="search" method="get" action="<?= e(url('admin/catalog.php')) ?>">
<?php if (isset($context['oid'])): ?><input type="hidden" name="oid" value="<?= e($context['oid']) ?>"><?php endif ?>
<?php if (!empty($context['category'])): ?><input type="hidden" name="category" value="<?= e($context['category']) ?>"><?php endif ?>
<label class="visually-hidden" for="catalogSearch">Search catalog</label><input class="form-control" type="search" id="catalogSearch" name="search" value="<?= e($context['search'] ?? '') ?>" placeholder="Search catalog" maxlength="100"><button class="btn btn-outline-light" type="submit">Search</button>
</form><?php endif ?>
<?php if ($role !== 'public'): ?><form class="logout-form" method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-outline-light" type="submit">Logout</button></form><?php endif ?>
</div></div></nav><main id="main-content" class="app-container app-main" tabindex="-1">
<?php if (isset($_SESSION['flash'])): ?><div class="alert alert-success" role="status"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif ?>
<div class="page-heading"><h1 class="page-title"><?= e($title) ?></h1><?php currency_selector(); ?></div>
<?php
}

function theme_head(): void
{
    ?><link rel="icon" href="<?= e(url('assets/favicon.ico')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto+Slab:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/lux/bootstrap.min.css">
<link rel="stylesheet" href="<?= e(url('css/app.css')) ?>">
<meta name="mae-exchange-rates" content="<?= e(url('api/exchange-rates.php')) ?>">
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script defer src="<?= e(url('js/app.js')) ?>"></script>
<script defer src="<?= e(url('js/currency.js')) ?>"></script>
<?php
}

function currency_selector(): void
{
    ?><div class="currency-tools">
<div class="currency-control"><label for="display-currency">Display currency</label><select class="form-select" id="display-currency" data-currency-select aria-describedby="currency-status" disabled>
<?php foreach (ExchangeRates::CURRENCIES as $code => $name): ?><option value="<?= e($code) ?>"><?= e($code . ' · ' . $name) ?></option><?php endforeach ?>
</select></div><p id="currency-status" data-currency-status role="status" aria-live="polite">Prices shown in IDR.</p><a class="currency-source" data-currency-source href="https://frankfurter.dev/" target="_blank" rel="noopener noreferrer" hidden>Rates: Frankfurter</a>
<noscript><p>Currency conversion requires JavaScript. Prices are shown in IDR.</p></noscript>
</div><?php
}

function page_end(): void
{
    echo '</main><footer class="app-footer"><div class="app-container">M-A-E-Commerce <span>Most affordable, known to mankind.</span></div></footer></body></html>';
}

function handle_form(callable $callback): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return; }
    require_post();
    try {
        $callback();
    } catch (HttpError $error) {
        if (!in_array($error->status, [409, 422], true)) { throw $error; }
        $GLOBALS['form_error'] = $error->getMessage();
        http_response_code($error->status);
    }
}

function form_errors(): void
{
    if (isset($GLOBALS['form_error'])) {
        echo '<div class="alert alert-danger" id="form-error" role="alert" tabindex="-1"><strong>Please check the form.</strong> ' . e($GLOBALS['form_error']) . '</div>';
    }
}

function submitted_value(string $name, mixed $fallback = ''): string
{
    return (string)(isset($GLOBALS['form_error']) && is_string($_POST[$name] ?? null) ? $_POST[$name] : $fallback);
}

function field(string $name, string $label, mixed $value = '', string $type = 'text', int $maxlength = 100, bool $required = true): void
{
    $value = $type === 'password' ? '' : submitted_value($name, $value);
    ?><div class="mb-3"><label class="form-label" for="<?= e($name) ?>"><?= e($label) ?><?= $required ? '' : ' <span class="text-muted">(optional)</span>' ?></label><input class="form-control" type="<?= e($type) ?>" id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" <?= $type === 'number' ? 'min="1" step="1"' : '' ?> maxlength="<?= $maxlength ?>" <?= $required ? 'required' : '' ?> <?= $type === 'password' ? 'autocomplete="new-password"' : '' ?>></div><?php
}

function action_form(string $path, array $values, string $label, string $style = 'secondary'): void
{
    ?><form class="d-inline" method="post" action="<?= e(url('admin/' . $path)) ?>"><?= csrf_field() ?><?php foreach ($values as $name => $value): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>"><?php endforeach ?><button class="btn btn-sm btn-<?= e($style) ?> my-1" type="submit"><?= e($label) ?></button></form><?php
}

function order_status(string $status): string
{
    $labels = ['INVOICE' => 'Unpaid invoice', 'PAID' => 'Paid', 'CANCELLED' => 'Cancelled'];
    $styles = ['INVOICE' => 'invoice', 'PAID' => 'paid', 'CANCELLED' => 'cancelled'];
    return '<span class="order-status status-' . ($styles[$status] ?? 'cancelled') . '" data-status="' . e($status) . '">' . e($labels[$status] ?? $status) . '</span>';
}

function render_orders(array $orders, bool $asAdmin = false): void
{
    if (!$orders) {
        echo '<div class="empty-state"><h2>No orders to show</h2><p>Your orders will appear here when they are available.</p></div>';
        return;
    }
    ?><div class="table-wrap" role="region" aria-label="Orders" tabindex="0"><table class="table table-hover"><thead><tr><th scope="col">Order</th><th scope="col">Ordered at (UTC)</th><?php if ($asAdmin): ?><th scope="col">Customer</th><?php endif ?><th scope="col">Status</th><th scope="col">Payment deadline (UTC)</th><th scope="col" class="order-total">Total</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach ($orders as $order): ?><tr><th scope="row">#<?= e($order['OrderID']) ?></th><td><?= e($order['OrderDate']) ?></td><?php if ($asAdmin): ?><td><?= e($order['ContactName']) ?> (#<?= e($order['CustomerID']) ?>)</td><?php endif ?><td><?= order_status($order['OrderStatus']) ?></td><td><?= $order['OrderStatus'] === 'INVOICE' ? e($order['InvoiceExpiresAt']) : '—' ?></td><td class="order-total"><?= price_html($order['total']) ?></td><td><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/OrderDetails_list.php', ['OrderID' => $order['OrderID']])) ?>">Details</a>
<?php if ($order['OrderStatus'] === 'INVOICE'): if ($asAdmin): action_form('check_paid.php', ['OrderID' => $order['OrderID']], 'Mark paid', 'success'); endif; action_form('Customers_cancelOrder.php', ['OrderID' => $order['OrderID']], 'Cancel order', 'outline-danger'); elseif ($asAdmin && $order['OrderStatus'] === 'PAID'): action_form('uncheck_paid.php', ['OrderID' => $order['OrderID']], 'Reopen invoice'); endif ?>
</td></tr><?php endforeach ?></tbody></table></div><?php
}
