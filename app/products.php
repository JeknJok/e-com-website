<?php
declare(strict_types=1);

function save_product(?int $id = null): int
{
    $name = text_value('pname', 100);
    $category = mb_strtoupper(text_value('pcategory', 100), 'UTF-8');
    $price = positive_int(input('pprice'));
    $description = text_value('pdesc', 2000);
    $image = text_value('pimage', 500);
    $clean = preg_replace('#^(\.\./|\./)+#', '', $image);
    if (!(filter_var($image, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true)) && !(str_starts_with($clean, 'assets/') && !str_contains($clean, '..') && !str_contains($clean, '\\'))) {
        throw new HttpError(422, 'Use an HTTP(S) image URL or a path under assets/.');
    }
    $active = input('is_active') === '0' ? 0 : 1;
    if ($id === null) {
        execute('INSERT INTO Products (pname, pcategory, pprice, pdesc, pimage, is_active) VALUES (?, ?, ?, ?, ?, ?)', [$name, $category, $price, $description, $image, $active]);
        return (int)db()->insert_id;
    }
    if (!row('SELECT pcode FROM Products WHERE pcode = ?', [$id])) { throw new HttpError(404, 'Product not found.'); }
    execute('UPDATE Products SET pname = ?, pcategory = ?, pprice = ?, pdesc = ?, pimage = ?, is_active = ? WHERE pcode = ?', [$name, $category, $price, $description, $image, $active, $id]);
    return $id;
}

function product_form(array $product, bool $editing): void
{
    ?><div class="form-panel"><?php form_errors(); ?><form method="post"><?= csrf_field() ?><?php
    foreach (['pname' => ['Product name', 'text', 100], 'pcategory' => ['Category', 'text', 100], 'pprice' => ['Price per item (whole rupiah)', 'number', 10], 'pimage' => ['Image URL or assets/ path', 'text', 500]] as $name => [$label, $type, $length]) {
        field($name, $label, $product[$name] ?? '', $type, $length);
    }
    ?><div class="mb-3"><label class="form-label" for="pdesc">Description</label><textarea class="form-control" id="pdesc" name="pdesc" rows="5" maxlength="2000" required><?= e(submitted_value('pdesc', $product['pdesc'] ?? '')) ?></textarea></div>
<div class="mb-3"><label class="form-label" for="is_active">Availability</label><select class="form-select" id="is_active" name="is_active"><option value="1">Available</option><option value="0" <?= submitted_value('is_active', $product['is_active'] ?? 1) === '0' ? 'selected' : '' ?>>Archived</option></select></div>
<button class="btn btn-primary" type="submit"><?= $editing ? 'Update product' : 'Add product' ?></button></form></div><?php
}
