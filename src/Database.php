<?php
declare(strict_types=1);

function connectDatabase(array $config): PDO
{
    // PDO abre la conexión. Los errores se manejan en el límite HTTP, sin mostrar secretos.
    return new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function db(): PDO
{
    static $connection;
    if (!$connection) {
        $path = getenv('HOTEL_CONFIG_PATH') ?: dirname(__DIR__) . '/config/local.php';
        if (!is_file($path)) {
            throw new RuntimeException('Configuración local ausente.');
        }
        $connection = connectDatabase(require $path);
    }
    return $connection;
}
