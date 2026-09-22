<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/Database.php';
$email = $argv[1] ?? '';
$role = $argv[2] ?? 'staff';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || !in_array($role, ['admin','staff'], true)) exit("Uso: php scripts/create-user.php correo admin|staff\n");
$password = bin2hex(random_bytes(12));
$query = db()->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
$query->execute([$email, password_hash($password, PASSWORD_DEFAULT), $role]);
file_put_contents(dirname(__DIR__) . '/config/access.local.txt', "\n$role: $email\nContraseña: $password\n", FILE_APPEND);
echo "Usuario creado. Contraseña generada en config/access.local.txt.\n";
