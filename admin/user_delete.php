<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
$actor = require_admin();
$id = positive_int(input('id', $_GET));
$admin = row('SELECT id, firstname, email FROM users_tb WHERE id = ?', [$id]);
if (!$admin) { throw new HttpError(404, 'Administrator not found.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    transaction(function () use ($id, $actor): void {
        $admins = rows('SELECT id FROM users_tb ORDER BY id FOR UPDATE');
        if ($id === (int)$actor['id'] || count($admins) <= 1) {
            throw new HttpError(409, 'You cannot delete your own account or the last administrator.');
        }
        execute('DELETE FROM users_tb WHERE id = ?', [$id]);
    });
    flash('Administrator deleted.');
    redirect(url('admin/user_list.php'));
}
page_start('Delete administrator', 'admin');
?><p>Permanently delete <?= e($admin['firstname']) ?> (<?= e($admin['email']) ?>)?</p><form method="post"><?= csrf_field() ?><button class="btn btn-danger" type="submit">Confirm deletion</button> <a class="btn btn-secondary" href="<?= e(url('admin/user_list.php')) ?>">Keep administrator</a></form><?php page_end();
