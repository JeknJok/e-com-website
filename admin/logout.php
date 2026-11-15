<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_post();
if (current_admin() || current_customer()) { audit_event('auth.logout'); }
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
redirect(url('index.php'));
