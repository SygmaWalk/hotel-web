<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Validation.php';

/** Carga una sola colección de demostración; nunca actualiza ni borra reservas. */
function seedDemo(PDO $pdo, string $date): array
{
    if (!validDate($date) || $date < '2000-01-08' || $date > '2099-12-23') {
        throw new DomainException('Fecha de referencia inválida: usar YYYY-MM-DD entre 2000-01-08 y 2099-12-23.');
    }
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== 'hotel_aurora' && !preg_match('/^hotel_test_[a-f0-9]{10}$/D', $database)) {
        throw new DomainException('La carga solo admite hotel_aurora o una base temporal hotel_test.');
    }
    $cases = [
        ['salida', '101', -2, 0, 'confirmed', 'Demo Salida'],
        ['llegada', '101', 0, 2, 'confirmed', 'Demo Llegada'],
        ['estadia', '102', -1, 1, 'confirmed', 'Demo Estadia'],
        ['futura', '201', 2, 5, 'confirmed', 'Demo Reserva Futura'],
        ['pendiente-hoy', '201', 0, 2, 'pending', 'Demo Pendiente Hoy'],
        ['pendiente-manana', '102', 1, 3, 'pending', 'Demo Pendiente Manana'],
        ['rechazada', '101', 0, 3, 'rejected', 'Demo Rechazada'],
        ['pendiente-futura', '201', 6, 8, 'pending', 'Demo Pendiente Futura'],
    ];
    $tokens = array_map(fn(array $case): string => hash('sha256', 'hotel-aurora-demo-v1:' . $case[0]), $cases);
    $pdo->beginTransaction();
    try {
        // Mismo orden de bloqueo que la aplicación: habitación antes de reserva.
        $rooms = $pdo->query("SELECT id, code, active, nightly_rate FROM rooms WHERE code IN ('101','102','201') ORDER BY id FOR UPDATE")->fetchAll();
        $q = $pdo->prepare('SELECT id, submission_token, check_in FROM reservations WHERE submission_token IN (' . implode(',', array_fill(0, count($tokens), '?')) . ') FOR UPDATE');
        $q->execute($tokens); $existing = $q->fetchAll();
        if ($existing) {
            if (count($existing) !== count($cases)) throw new DomainException('La colección demo está incompleta. No se cambia nada; revisar sus registros.');
            $originalDate = array_values(array_filter($existing, fn(array $row): bool => $row['submission_token'] === $tokens[1]))[0]['check_in'];
            $pdo->commit();
            return ['inserted' => 0, 'existing' => count($existing), 'reference_date' => $originalDate];
        }
        $byCode = array_column($rooms, null, 'code');
        foreach (['101','102','201'] as $code) {
            if (!isset($byCode[$code]) || !$byCode[$code]['active']) throw new DomainException('Se requieren las habitaciones 101, 102 y 201 activas. No se cambia nada.');
        }
        $staffId = $pdo->query("SELECT id FROM users WHERE role IN ('staff','admin') ORDER BY CASE role WHEN 'staff' THEN 0 ELSE 1 END, id LIMIT 1")->fetchColumn();
        if (!$staffId) throw new DomainException('Se requiere una cuenta de personal existente.');
        $base = new DateTimeImmutable($date);
        $day = fn(int $offset): string => $base->modify(sprintf('%+d days', $offset))->format('Y-m-d');
        $overlap = $pdo->prepare("SELECT id FROM reservations WHERE room_id = ? AND status = 'confirmed' AND check_in < ? AND check_out > ? LIMIT 1 FOR UPDATE");
        $insert = $pdo->prepare('INSERT INTO reservations (room_id, guest_name, email, check_in, check_out, nightly_rate, status, submission_token, created_at, resolved_by, resolved_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($cases as $i => [$key, $code, $from, $to, $status, $name]) {
            $room = $byCode[$code];
            if ($status === 'confirmed') {
                $overlap->execute([$room['id'], $day($to), $day($from)]);
                if ($overlap->fetchColumn()) throw new DomainException('Hay una reserva confirmada incompatible en la habitación ' . $code . '. Se cancela toda la carga demo.');
            }
            $created = $base->modify('-7 days')->modify('+' . $i . ' hours');
            $insert->execute([$room['id'], $name, $key . '@demo.example', $day($from), $day($to), $room['nightly_rate'], $status, $tokens[$i], $created->format('Y-m-d H:i:s'), $status === 'pending' ? null : $staffId, $status === 'pending' ? null : $created->modify('+30 minutes')->format('Y-m-d H:i:s')]);
        }
        $pdo->commit();
        return ['inserted' => count($cases), 'existing' => 0, 'reference_date' => $date];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    date_default_timezone_set('America/Argentina/Buenos_Aires');
    if (count($argv) > 2) { fwrite(STDERR, "Uso: php scripts/seed-demo.php [YYYY-MM-DD]\n"); exit(1); }
    try {
        $result = seedDemo(db(), $argv[1] ?? date('Y-m-d'));
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    } catch (DomainException $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);
    } catch (Throwable $error) {
        fwrite(STDERR, "No se pudo cargar la demo. Revisá la conexión y el esquema; no se confirmó la transacción.\n"); exit(1);
    }
}
