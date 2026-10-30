<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/accounts.php';
require_admin();
handle_form(function (): void {
    save_admin();
    flash('Administrator created.');
    redirect(url('admin/user_list.php'));
});
page_start('Create administrator', 'admin');
admin_form([], false);
page_end();
