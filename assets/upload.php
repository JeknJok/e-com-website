<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
require_post();
$id = positive_int(input('id'));
rate_limit('upload', (string)$id);
$file = $_FILES['berkas'] ?? null;
if (!is_array($file) || !isset($file['error'], $file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    throw new HttpError(422, 'Choose an image of no more than 2 MB that uploaded successfully.');
}
$size = filesize($file['tmp_name']);
if (!$size || $size > 2 * 1024 * 1024) { throw new HttpError(422, 'The image must be no larger than 2 MB.'); }
$info = @getimagesize($file['tmp_name']);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!$info || !isset($extensions[$mime]) || $info['mime'] !== $mime || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 8000000) {
    throw new HttpError(422, 'Use a JPEG, PNG, or WebP image of at most 8 megapixels and 4096 pixels per side.');
}
$decoded = @imagecreatefromstring(file_get_contents($file['tmp_name']));
if (!$decoded) { throw new HttpError(422, 'This image could not be decoded safely.'); }
$canonical = imagecreatetruecolor($info[0], $info[1]);
imagealphablending($canonical, false);
imagesavealpha($canonical, true);
imagecopy($canonical, $decoded, 0, 0, 0, 0, $info[0], $info[1]);
imagedestroy($decoded);
$temporary = tempnam(private_directory(), 'image-');
try {
    $encoded = match ($mime) {
        'image/jpeg' => imagejpeg($canonical, $temporary, 85),
        'image/png' => imagepng($canonical, $temporary, 6),
        'image/webp' => imagewebp($canonical, $temporary, 85),
    };
} finally { imagedestroy($canonical); }
if (!$encoded || !filesize($temporary) || filesize($temporary) > 2 * 1024 * 1024) {
    @unlink($temporary);
    throw new HttpError(422, 'The canonical image is too large. Choose a smaller image.');
}
$lock = fopen(media_lock_path(), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    @unlink($temporary);
    header('Retry-After: 2');
    throw new HttpError(429, 'Another upload is finishing. Try again in a moment.');
}
$relative = 'assets/uploads/product_' . $id . '_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
$destination = dirname(__DIR__) . '/' . $relative;
$partial = $destination . '.part';
$old = null;
try {
    $used = $count = 0;
    foreach (new DirectoryIterator(__DIR__ . '/uploads') as $entry) {
        if ($entry->isFile() && !$entry->isLink()) { $used += $entry->getSize(); $count++; }
    }
    if ($used + filesize($temporary) > 100 * 1024 * 1024 || $count >= 500) { throw new HttpError(422, 'Demo media storage is full. Ask the operator to clean unused images.'); }
    transaction(function () use ($id, $temporary, $partial, $destination, $relative, &$old): void {
        $product = row('SELECT pimage FROM Products WHERE pcode = ? FOR UPDATE', [$id]);
        if (!$product) { throw new HttpError(404, 'Product not found.'); }
        if (!copy($temporary, $partial) || !chmod($partial, 0644) || !rename($partial, $destination)) { throw new RuntimeException('Could not save canonical product image.'); }
        execute('UPDATE Products SET pimage = ? WHERE pcode = ?', [$relative, $id]);
        audit_event('product.image_updated', $id);
        $old = $product['pimage'];
    });
    if ($old && preg_match('#^assets/uploads/product_[0-9]+_[a-f0-9]{24}\.(?:jpg|png|webp)$#D', $old) && !row('SELECT pcode FROM Products WHERE pimage = ? LIMIT 1', [$old])) {
        $oldPath = dirname(__DIR__) . '/' . $old;
        if (is_file($oldPath) && !is_link($oldPath)) { @unlink($oldPath); }
    }
} catch (Throwable $error) {
    @unlink($destination);
    throw $error;
} finally {
    @unlink($partial);
    @unlink($temporary);
    flock($lock, LOCK_UN);
    fclose($lock);
}
flash('Product image updated. Metadata was removed during image processing.');
redirect(url('admin/products_list.php'));
