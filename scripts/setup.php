<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/Database.php';
$root = dirname(__DIR__);
if (is_file($root . '/config/local.php')) exit("Ya existe config/local.php. No se modifica la instalación.\n");
$pdo = new PDO(getenv('HOTEL_SETUP_DSN') ?: 'mysql:host=127.0.0.1;charset=utf8mb4', getenv('HOTEL_SETUP_USER') ?: 'root', getenv('HOTEL_SETUP_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
// CREATE sin IF NOT EXISTS evita intervenir una base preexistente accidentalmente.
$pdo->exec('CREATE DATABASE hotel_aurora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE hotel_aurora');
$pdo->exec(file_get_contents($root . '/database/schema.sql'));
$dbPassword = bin2hex(random_bytes(24));
$pdo->exec("CREATE USER 'hotel_aurora_app'@'localhost' IDENTIFIED BY " . $pdo->quote($dbPassword));
$pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON hotel_aurora.* TO 'hotel_aurora_app'@'localhost'");
$config = ['dsn'=>'mysql:host=127.0.0.1;port=3306;dbname=hotel_aurora;charset=utf8mb4', 'user'=>'hotel_aurora_app','password'=>$dbPassword];
file_put_contents($root . '/config/local.php', "<?php\nreturn " . var_export($config, true) . ";\n");
$rooms = [
    ['101', 'Doble del lago', 2, '65000.00'],
    ['102', 'Doble del jardín', 2, '58000.00'],
    ['201', 'Familiar Patagonia', 4, '92000.00'],
];
$insert = $pdo->prepare('INSERT INTO rooms (code, name, capacity, nightly_rate) VALUES (?, ?, ?, ?)');
foreach ($rooms as $room) $insert->execute($room);
$credentials = "Acceso local de demostración. No publicar este archivo.\n\n";
$insert = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
foreach (['admin','staff'] as $role) {
    $email = $role . '@hotelaurora.example';
    $password = bin2hex(random_bytes(12));
    $insert->execute([$email, password_hash($password, PASSWORD_DEFAULT), $role]);
    $credentials .= "$role: $email\nContraseña: $password\n\n";
}
file_put_contents($root . '/config/access.local.txt', $credentials);
echo "Base hotel_aurora creada con 3 habitaciones ficticias.\nCuentas locales disponibles en config/access.local.txt (excluido de Git y bloqueado por HTTP).\n";
