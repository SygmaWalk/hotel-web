<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once dirname(__DIR__) . '/scripts/seed-demo.php';
require_once dirname(__DIR__) . '/src/Hotel.php';
$schema = 'hotel_test_' . bin2hex(random_bytes(5));
$pdo = new PDO(getenv('HOTEL_TEST_DSN') ?: 'mysql:host=127.0.0.1;charset=utf8mb4', getenv('HOTEL_TEST_USER') ?: 'root', getenv('HOTEL_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
$created = false; $checks = 0;
function demoCheck(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FALLÓ: ' . $label); $checks++; }
function demoReject(callable $action, string $label): void { try { $action(); } catch (DomainException $e) { demoCheck(true, $label); return; } demoCheck(false, $label); }
try {
    $pdo->exec('CREATE DATABASE ' . $schema . ' CHARACTER SET utf8mb4'); $created = true;
    $pdo->exec('USE ' . $schema);
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    $pdo->exec("INSERT INTO rooms (code,name,capacity,nightly_rate) VALUES ('101','Prueba 101',2,65000),('102','Prueba 102',2,58000),('201','Prueba 201',4,92000)");
    $pdo->prepare("INSERT INTO users (email,password_hash,role) VALUES (?,?, 'staff')")->execute(['staff@demo.example', password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT)]);
    demoReject(fn() => seedDemo($pdo,'2026-02-30'), 'fecha imposible rechazada');
    demoReject(fn() => seedDemo($pdo,'2100-01-01'), 'rango de fecha rechazado');
    $pdo->exec("UPDATE rooms SET active=0 WHERE code='201'");
    demoReject(fn() => seedDemo($pdo,'2026-10-05'), 'habitación inactiva rechazada');
    demoCheck((int)$pdo->query('SELECT COUNT(*) FROM reservations')->fetchColumn()===0, 'fallo no deja filas');
    $pdo->exec('UPDATE rooms SET active=1');
    // Reserva ajena que interfiere con el cuarto caso: los tres primeros INSERT deben revertirse.
    $pdo->exec("INSERT INTO reservations (room_id,guest_name,email,check_in,check_out,nightly_rate,status,submission_token) SELECT id,'Ajena','ajena@example.test','2026-10-07','2026-10-10',12345,'confirmed',REPEAT('f',64) FROM rooms WHERE code='201'");
    $before = $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll();
    demoReject(fn() => seedDemo($pdo,'2026-10-05'), 'conflicto cancela toda la colección');
    demoCheck($before === $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll(), 'rollback conserva reserva ajena');
    $pdo->exec("UPDATE reservations SET check_in='2026-11-10',check_out='2026-11-12'");
    $untouched = $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll();
    $result = seedDemo($pdo, '2026-10-05');
    demoCheck($result['inserted']===8, 'ocho casos insertados');
    demoCheck((int)$pdo->query('SELECT COUNT(*) FROM reservations')->fetchColumn()===9, 'reserva ajena conservada');
    demoCheck($untouched[0] === $pdo->query("SELECT * FROM reservations WHERE email='ajena@example.test'")->fetch(), 'datos ajenos sin cambios');
    $hotel = new Hotel($pdo); $panel = $hotel->dashboard('2026-10-05');
    demoCheck($panel['summary']['arrivals']===1 && $panel['summary']['departures']===1, 'llegada y salida en fecha base');
    demoCheck($panel['summary']['pending']===3 && $panel['summary']['available']===1 && $panel['summary']['percentage']===67, 'pendientes y disponibilidad esperados');
    demoCheck(array_map('intval',array_column($panel['week'],'reserved'))===[2,1,1,1,1,0,0], 'previsión de siete noches');
    $snapshot = $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll();
    $repeat = seedDemo($pdo, '2026-10-06');
    demoCheck($repeat['inserted']===0 && $repeat['existing']===8 && $repeat['reference_date']==='2026-10-05', 'repetir conserva fecha original');
    demoCheck($snapshot === $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll(), 'repetir no modifica filas');
    $pdo->exec("UPDATE reservations SET status='rejected' WHERE email='pendiente-hoy@demo.example'");
    seedDemo($pdo, '2026-10-05');
    demoCheck($pdo->query("SELECT status FROM reservations WHERE email='pendiente-hoy@demo.example'")->fetchColumn()==='rejected', 'preserva decisiones del personal');
    $pdo->exec("DELETE FROM reservations WHERE email='pendiente-futura@demo.example'");
    $partial = $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll();
    demoReject(fn() => seedDemo($pdo,'2026-10-05'), 'colección parcial exige revisión');
    demoCheck($partial === $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll(), 'colección parcial no se altera');
    echo "OK: $checks verificaciones de carga demo.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($created && preg_match('/^hotel_test_[a-f0-9]{10}$/D', $schema)) $pdo->exec('DROP DATABASE ' . $schema);
}
