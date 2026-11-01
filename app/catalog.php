<?php
declare(strict_types=1);

function catalog_page(bool $asAdmin = false): void
{
    $actor = $asAdmin ? require_admin() : require_customer();
    $cid = $asAdmin ? positive_int(input('cid', $_GET)) : (int)$actor['CustomerID'];
    $customer = row('SELECT CustomerID, ContactName FROM Customers WHERE CustomerID = ? AND is_active = 1', [$cid]);
    if (!$customer) { throw new HttpError(404, 'Active customer not found.'); }
    $order = active_order($cid, input('oid', $_GET));
    $search = trim(input('search', $_GET));
    $category = trim(input('category', $_GET));
    $legacyFilter = input('filterby', $_GET);
    if ($legacyFilter !== '') {
        $filters = ['0' => '', '1' => 'ELECTRONICS', '2' => 'TOYS'];
        if (!array_key_exists($legacyFilter, $filters)) { throw new HttpError(422, 'Unknown category filter.'); }
        $category = $filters[$legacyFilter];
    }
    if (mb_strlen($search) > 100 || mb_strlen($category) > 100) { throw new HttpError(422, 'Search or category is too long.'); }
    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
    $products = rows("SELECT * FROM Products WHERE is_active = 1 AND pname LIKE ? ESCAPE '!'" . ($category === '' ? '' : ' AND pcategory = ?') . ' ORDER BY pcode', $category === '' ? ['%' . $escaped . '%'] : ['%' . $escaped . '%', $category]);
    $categories = rows('SELECT DISTINCT pcategory FROM Products WHERE is_active = 1 ORDER BY pcategory');
    $params = ($asAdmin ? ['cid' => $cid] : []) + ($order ? ['oid' => $order['OrderID']] : []);
    $context = ['search' => $search, 'category' => $category] + $params;
    $target = $asAdmin ? 'admin/catalog_admin.php' : 'admin/catalog.php';
    page_start($asAdmin ? 'Shop as ' . $customer['ContactName'] : 'Shop', $asAdmin ? 'admin' : 'customer', $context);
    ?><p class="page-intro">The most affordable finds, known to mankind.</p>
<?php if ($asAdmin): ?><p class="shop-as-notice">You are placing orders for <strong><?= e($customer['ContactName']) ?></strong>. <a href="<?= e(url('admin/Shop_admin.php')) ?>">Choose another customer</a></p><?php endif ?>
<?php if ($order): $summary = row('SELECT COALESCE(SUM(qty), 0) AS items, COALESCE(SUM(pprice * qty), 0) AS total FROM OrderDetails WHERE OrderID = ?', [$order['OrderID']]); ?>
<aside class="cart-summary" aria-label="Current order">
<div><strong>Open invoice #<?= e($order['OrderID']) ?></strong><span><?= e($summary['items']) ?> <?= (int)$summary['items'] === 1 ? 'item' : 'items' ?> · <?= price_html($summary['total']) ?></span><small>Payment window ends <?= e($order['InvoiceExpiresAt']) ?> UTC.</small></div>
<a class="btn btn-primary" href="<?= e(url('admin/OrderDetails_list.php', ['OrderID' => $order['OrderID']])) ?>">View order</a>
</aside>
<?php else: ?><p class="invoice-note">Adding your first item opens an invoice. Unpaid invoices expire after 24 hours.</p><?php endif ?>
<div class="catalog-toolbar">
<form class="catalog-filter" method="get" action="<?= e(url($target)) ?>">
<?php foreach ($params as $name => $value): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>"><?php endforeach ?>
<?php if ($asAdmin): ?><div class="filter-field"><label class="form-label" for="adminCatalogSearch">Search catalog</label><input class="form-control" type="search" id="adminCatalogSearch" name="search" value="<?= e($search) ?>" maxlength="100" placeholder="Search products"></div><?php else: ?><input type="hidden" name="search" value="<?= e($search) ?>"><?php endif ?>
<div class="filter-field"><label class="form-label" for="categoryFilter">Category</label>
<select class="form-select" id="categoryFilter" name="category"><option value="">All categories</option>
<?php $knownCategory = $category === ''; foreach ($categories as $entry): $knownCategory = $knownCategory || $category === $entry['pcategory']; ?><option value="<?= e($entry['pcategory']) ?>" <?= $category === $entry['pcategory'] ? 'selected' : '' ?>><?= e($entry['pcategory']) ?></option><?php endforeach ?>
<?php if (!$knownCategory): ?><option selected value="<?= e($category) ?>"><?= e($category) ?></option><?php endif ?>
</select></div><button class="btn btn-secondary" type="submit">Apply</button>
</form><p class="result-count" role="status"><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?></p>
</div>
<?php if ($search !== '' || $category !== ''): ?><div class="applied-filters" aria-label="Applied filters">
<?php if ($search !== ''): ?><span>Search: <strong><?= e($search) ?></strong></span><?php endif ?>
<?php if ($category !== ''): ?><span>Category: <strong><?= e($category) ?></strong></span><?php endif ?>
<a href="<?= e(url($target, $params)) ?>">Clear filters</a></div><?php endif ?>
<?php if (!$products): ?><div class="empty-state"><h2><?= $search !== '' || $category !== '' ? 'No matching products' : 'The catalog is waiting for its next finds' ?></h2><p><?= $search !== '' || $category !== '' ? 'Try another search or clear your filters to see the full catalog.' : 'There are no available products right now. Please check back soon.' ?></p><?php if ($search !== '' || $category !== ''): ?><a class="btn btn-secondary" href="<?= e(url($target, $params)) ?>">See all products</a><?php endif ?></div><?php endif ?>
<div class="product-grid">
<?php foreach ($products as $product): ?><article class="product-card">
<div class="product-visual"><img class="catalog-image" src="<?= e(image_url($product['pimage'])) ?>" data-fallback="<?= e(url('assets/img/logo-mae.png')) ?>" alt="<?= e($product['pname']) ?>" width="600" height="450" loading="lazy"></div>
<div class="product-body"><p class="product-category"><?= e($product['pcategory']) ?></p><h2 class="product-title"><?= e($product['pname']) ?></h2>
<p class="product-price"><?= price_html($product['pprice']) ?> <span>per item</span></p>
<details class="product-description"><summary>Product description</summary><p><?= nl2br(e($product['pdesc'])) ?></p></details>
<form class="purchase-form" method="post" action="<?= e(url($asAdmin ? 'admin/addToOrders_admin.php' : 'admin/addToOrders.php')) ?>">
<?= csrf_field() ?><input type="hidden" name="pcode" value="<?= e($product['pcode']) ?>"><input type="hidden" name="oid" value="<?= e($order['OrderID'] ?? '') ?>">
<input type="hidden" name="search" value="<?= e($search) ?>"><input type="hidden" name="category" value="<?= e($category) ?>">
<?php if ($asAdmin): ?><input type="hidden" name="cid" value="<?= $cid ?>"><?php endif ?>
<div class="quantity-field"><label for="quantity-<?= e($product['pcode']) ?>">Qty<span class="visually-hidden"> for <?= e($product['pname']) ?></span></label><input class="form-control" type="number" id="quantity-<?= e($product['pcode']) ?>" name="qty" value="1" min="1" max="9999" step="1" required></div><button class="btn btn-primary" type="submit">Add to cart</button></form></div>
</article><?php endforeach ?></div><?php
    page_end();
}

function add_from_request(bool $asAdmin = false): never
{
    $actor = $asAdmin ? require_admin() : require_customer();
    require_post();
    $cid = $asAdmin ? positive_int(input('cid')) : (int)$actor['CustomerID'];
    $orderId = add_order_item($cid, positive_int(input('pcode')), positive_int(input('qty'), 9999), input('oid'));
    flash('Item added to order #' . $orderId . '.');
    $params = ['oid' => $orderId, 'search' => mb_substr(input('search'), 0, 100), 'category' => mb_substr(input('category'), 0, 100)];
    if ($asAdmin) { $params['cid'] = $cid; }
    redirect(url($asAdmin ? 'admin/catalog_admin.php' : 'admin/catalog.php', $params));
}
