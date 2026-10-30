<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$admins = rows('SELECT id, firstname, lastname, email, username, mobile FROM users_tb ORDER BY id');
page_start('Administrators', 'admin');
?><a class="btn btn-primary mb-3" href="<?= e(url('admin/user_insert.php')) ?>">Create administrator</a>
<div class="table-wrap" role="region" aria-label="Administrators" tabindex="0"><table class="table table-hover"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($admins as $admin): ?><tr><td><?= e($admin['id']) ?></td><td><?= e($admin['firstname'] . ' ' . $admin['lastname']) ?></td><td><?= e($admin['email']) ?></td><td><?= e($admin['username']) ?></td><td><a class="btn btn-sm btn-secondary" href="<?= e(url('admin/user_update.php', ['id' => $admin['id']])) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= e(url('admin/user_delete.php', ['id' => $admin['id']])) ?>">Delete</a></td></tr><?php endforeach ?>
</tbody></table></div><?php page_end();
