<?php
declare(strict_types=1);

/** Insert missing demo products without overwriting edits or reactivating archived items. */
function seed_catalog(): array
{
    $products = require dirname(__DIR__) . '/database/catalog.php';
    $lock = 'mae:catalog:' . substr(hash('sha256', setting('DB_NAME', 'NealleDB')), 0, 40);
    if ((int)(row('SELECT GET_LOCK(?, 10) AS acquired', [$lock])['acquired'] ?? 0) !== 1) {
        throw new RuntimeException('Another catalog import is running. Please try again shortly.');
    }
    try {
        return transaction(function () use ($products): array {
            $added = $skipped = 0;
            foreach ($products as [$name, $category, $price, $image, $description]) {
                // Match either identity so renamed items and custom images keep their existing data.
                if (row('SELECT pcode FROM Products WHERE pname = ? OR pimage = ? LIMIT 1', [$name, $image])) {
                    $skipped++;
                    continue;
                }
                execute('INSERT INTO Products (pname, pcategory, pprice, pimage, pdesc) VALUES (?, ?, ?, ?, ?)', [$name, $category, $price, $image, $description]);
                $added++;
            }
            return ['added' => $added, 'skipped' => $skipped];
        });
    } finally {
        query('SELECT RELEASE_LOCK(?)', [$lock])->close();
    }
}
