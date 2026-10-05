<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$pdo = new PDO(getenv('HOTEL_SETUP_DSN') ?: 'mysql:host=127.0.0.1;dbname=hotel_aurora;charset=utf8mb4', getenv('HOTEL_SETUP_USER') ?: 'root', getenv('HOTEL_SETUP_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'hotel_aurora') exit("Seleccioná hotel_aurora. No se cambió nada.\n");
$pdo->exec(file_get_contents(dirname(__DIR__) . '/database/operations.sql'));
echo "Tablas de estadías e imágenes disponibles. Se conservaron los registros existentes.\n";
