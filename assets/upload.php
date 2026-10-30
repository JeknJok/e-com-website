<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
require_post();
$id = positive_int(input('id'));
$file = $_FILES['berkas'] ?? null;
if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size']) || !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    throw new HttpError(422, 'Choose an image that uploaded successfully.');
}
$size = filesize($file['tmp_name']);
if ($size === false || $size < 1 || $size > 2 * 1024 * 1024) { throw new HttpError(422, 'The image must be no larger than 2 MB.'); }
$info = @getimagesize($file['tmp_name']);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!$info || !isset($extensions[$mime]) || $info['mime'] !== $mime || $info[0] * $info[1] > 20000000) { throw new HttpError(422, 'Use a valid JPEG, PNG, or WebP image of at most 20 megapixels.'); }
$relative = 'assets/uploads/product_' . $id . '_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
$destination = dirname(__DIR__) . '/' . $relative;
try {
    transaction(function () use ($id, $file, $relative, $destination): void {
        if (!row('SELECT pcode FROM Products WHERE pcode = ? FOR UPDATE', [$id])) { throw new HttpError(404, 'Product not found.'); }
        if (!move_uploaded_file($file['tmp_name'], $destination)) { throw new RuntimeException('Could not save product image. Check upload directory permissions.'); }
        execute('UPDATE Products SET pimage = ? WHERE pcode = ?', [$relative, $id]);
    });
} catch (Throwable $error) {
    if (is_file($destination)) { unlink($destination); }
    throw $error;
}
flash('Product image updated.');
redirect(url('admin/products_list.php'));
