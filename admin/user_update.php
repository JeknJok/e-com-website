<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/accounts.php';
require_admin();
$id = positive_int(input('id', $_GET));
$admin = row('SELECT id, firstname, lastname, email, username, mobile FROM users_tb WHERE id = ?', [$id]);
if (!$admin) { throw new HttpError(404, 'Administrator not found.'); }
handle_form(function () use ($id): void {
    save_admin($id);
    flash('Administrator updated.');
    redirect(url('admin/user_list.php'));
});
page_start('Update administrator', 'admin');
admin_form($admin, true);
page_end();
