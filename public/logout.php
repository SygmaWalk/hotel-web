<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); fail(405, 'Cerrá la sesión desde el botón del menú.'); }
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', ['expires'=>time()-3600, 'path'=>$params['path'], 'secure'=>$params['secure'], 'httponly'=>true, 'samesite'=>'Lax']);
session_destroy();
redirect('login.php');
