<?php
declare(strict_types=1);
require_once __DIR__ . '/Validation.php';

final class Hotel
{
    public function __construct(private PDO $pdo) {}

    private function query(string $sql, array $params = []): PDOStatement
    {
        // prepare separa la instrucción SQL de los valores ingresados por el usuario.
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public function rooms(bool $includeInactive = false): array
    {
        return $this->query('SELECT * FROM rooms' . ($includeInactive ? '' : ' WHERE active = 1') . ' ORDER BY code')->fetchAll();
    }

    public function room(int $id): ?array
    {
        return $this->query('SELECT * FROM rooms WHERE id = ?', [$id])->fetch() ?: null;
    }

    public function dashboard(string $date): array
    {
        if (!validDate($date) || $date < '2000-01-01' || $date > '2099-12-31') {
            throw new DomainException('Elegí una fecha válida entre 2000 y 2099.');
        }
        $summary = $this->query("SELECT
            COALESCE(SUM(status = 'pending'), 0) AS pending,
            COALESCE(SUM(status = 'confirmed' AND check_in = ?), 0) AS arrivals,
            COALESCE(SUM(status = 'confirmed' AND check_out = ?), 0) AS departures
            FROM reservations", [$date, $date])->fetch();
        $rooms = $this->query("SELECT h.id, h.code, h.name, h.active,
            EXISTS(SELECT 1 FROM reservations r WHERE r.room_id = h.id
                AND r.status = 'confirmed' AND r.check_in <= ? AND r.check_out > ?) AS reserved
            FROM rooms h ORDER BY h.code", [$date, $date])->fetchAll();
        $active = count(array_filter($rooms, fn(array $room): bool => (bool) $room['active']));
        $reserved = count(array_filter($rooms, fn(array $room): bool => $room['active'] && $room['reserved']));
        $summary = array_map('intval', $summary);
        $summary += ['active' => $active, 'reserved' => $reserved, 'available' => $active - $reserved,
            'inactive' => count($rooms) - $active, 'percentage' => $active ? (int) round(100 * $reserved / $active) : 0];
        // Las llegadas y salidas incluyen habitaciones desactivadas: requieren atención igualmente.
        $select = 'SELECT r.id, r.guest_name, r.check_in, r.check_out, h.code, h.active FROM reservations r JOIN rooms h ON h.id = r.room_id';
        $arrivals = $this->query($select . " WHERE r.status = 'confirmed' AND r.check_in = ? ORDER BY h.code, r.id LIMIT 20", [$date])->fetchAll();
        $departures = $this->query($select . " WHERE r.status = 'confirmed' AND r.check_out = ? ORDER BY h.code, r.id LIMIT 20", [$date])->fetchAll();
        $pending = $this->query($select . " WHERE r.status = 'pending' ORDER BY r.created_at, r.id LIMIT 8")->fetchAll();
        $days = []; $params = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = 'SELECT CAST(? AS DATE) AS day';
            $params[] = (new DateTimeImmutable($date))->modify('+' . $i . ' days')->format('Y-m-d');
        }
        $week = $this->query("SELECT dates.day, COUNT(DISTINCT h.id) AS reserved FROM (" . implode(' UNION ALL ', $days) . ") dates
            LEFT JOIN reservations r ON r.status = 'confirmed' AND r.check_in <= dates.day AND r.check_out > dates.day
            LEFT JOIN rooms h ON h.id = r.room_id AND h.active = 1
            GROUP BY dates.day ORDER BY dates.day", $params)->fetchAll();
        return compact('date', 'summary', 'rooms', 'arrivals', 'departures', 'pending', 'week');
    }

    public function calendar(string $from, string $to, ?int $roomId = null): array
    {
        foreach ([$from, $to] as $date) {
            if (!validDate($date) || $date < '2000-01-01' || $date > '2099-12-31') {
                throw new DomainException('Elegí fechas válidas entre 2000 y 2099.');
            }
        }
        $start = new DateTimeImmutable($from);
        $length = (int) $start->diff(new DateTimeImmutable($to))->format('%r%a') + 1;
        if ($length < 1 || $length > 31) throw new DomainException('Consultá entre 1 y 31 noches, con la fecha final igual o posterior a la inicial.');
        $allRooms = $this->rooms(true);
        $rooms = $roomId === null ? $allRooms : array_values(array_filter($allRooms, fn(array $room): bool => (int) $room['id'] === $roomId));
        if ($roomId !== null && !$rooms) throw new DomainException('Elegí una habitación existente.');
        $days = [];
        for ($i = 0; $i < $length; $i++) $days[] = $start->modify('+' . $i . ' days')->format('Y-m-d');
        // Incluye las salidas del primer día, aunque ya no ocupen esa noche.
        $sql = "SELECT id, room_id, guest_name, check_in, check_out FROM reservations
            WHERE status = 'confirmed' AND check_in <= ? AND check_out >= ?";
        $params = [$to, $from];
        if ($roomId !== null) { $sql .= ' AND room_id = ?'; $params[] = $roomId; }
        $reservations = $this->query($sql . ' ORDER BY check_in, id', $params)->fetchAll();
        $today = date('Y-m-d');
        $cells = [];
        foreach ($rooms as $room) {
            foreach ($days as $day) {
                $cells[$room['id']][$day] = ['state' => $room['active'] ? 'free' : 'inactive', 'stays' => [], 'arrivals' => [], 'departures' => []];
            }
        }
        foreach ($reservations as $reservation) {
            if (!isset($cells[$reservation['room_id']])) continue;
            foreach ($days as $day) {
                $cell =& $cells[$reservation['room_id']][$day];
                if ($reservation['check_in'] === $day) $cell['arrivals'][] = $reservation;
                if ($reservation['check_out'] === $day) $cell['departures'][] = $reservation;
                if ($reservation['check_in'] <= $day && $reservation['check_out'] > $day) {
                    $cell['stays'][] = $reservation;
                    $cell['state'] = $day <= $today ? 'expected' : 'reserved';
                }
                unset($cell);
            }
        }
        return compact('from', 'to', 'today', 'days', 'rooms', 'allRooms', 'cells');
    }

    public function submit(array $input, string $token): int
    {
        [$data, $errors] = validateRequest($input);
        if ($errors || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new DomainException('Revisá los datos de la solicitud.');
        }
        $this->pdo->beginTransaction();
        try {
            $room = $this->query('SELECT * FROM rooms WHERE id = ? FOR UPDATE', [$data['room_id']])->fetch();
            // El índice UNIQUE más el bloqueo hacen idempotente el reenvío del mismo formulario.
            $existing = $this->query('SELECT id FROM reservations WHERE submission_token = ? FOR UPDATE', [$token])->fetchColumn();
            if ($existing) {
                $this->pdo->commit();
                return (int) $existing;
            }
            if (!$room || !$room['active']) {
                throw new DomainException('Esta habitación ya no está disponible para solicitudes. Elegí otra.');
            }
            $this->query('INSERT INTO reservations (room_id, guest_name, email, check_in, check_out, nightly_rate, submission_token) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                $data['room_id'], $data['guest_name'], $data['email'], $data['check_in'], $data['check_out'], $room['nightly_rate'], $token,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            // Dos envíos concurrentes con el mismo token y distinta habitación tampoco duplican.
            if ($error instanceof PDOException && ($error->errorInfo[1] ?? 0) === 1062) {
                $id = $this->query('SELECT id FROM reservations WHERE submission_token = ?', [$token])->fetchColumn();
                if ($id) return (int) $id;
            }
            throw $error;
        }
    }

    public function requests(string $status = '', string $date = ''): array
    {
        if ($status !== '' && !in_array($status, ['pending', 'confirmed', 'rejected'], true)) {
            throw new DomainException('Elegí un estado válido.');
        }
        if ($date !== '' && !validDate($date)) throw new DomainException('Ingresá una fecha válida.');
        $sql = 'SELECT r.*, h.code, h.name AS room_name FROM reservations r JOIN rooms h ON h.id = r.room_id WHERE 1 = 1';
        $params = [];
        if ($status !== '') { $sql .= ' AND r.status = ?'; $params[] = $status; }
        if ($date !== '') { $sql .= ' AND r.check_in = ?'; $params[] = $date; }
        return $this->query($sql . ' ORDER BY r.created_at DESC, r.id DESC', $params)->fetchAll();
    }

    public function request(int $id): ?array
    {
        return $this->query('SELECT r.*, h.code, h.name AS room_name, h.active FROM reservations r JOIN rooms h ON h.id = r.room_id WHERE r.id = ?', [$id])->fetch() ?: null;
    }

    public function resolve(int $id, string $action, int $staffId): void
    {
        if (!in_array($action, ['confirmed', 'rejected'], true)) throw new DomainException('Acción inválida.');
        $initial = $this->request($id);
        if (!$initial) throw new DomainException('La solicitud no existe.');
        $this->pdo->beginTransaction();
        try {
            // Todas las confirmaciones y bajas toman primero el mismo bloqueo de habitación.
            // Así dos empleados no pueden confirmar fechas superpuestas simultáneamente.
            $room = $this->query('SELECT * FROM rooms WHERE id = ? FOR UPDATE', [$initial['room_id']])->fetch();
            $request = $this->query('SELECT * FROM reservations WHERE id = ? FOR UPDATE', [$id])->fetch();
            if ($request['status'] !== 'pending') throw new DomainException('La solicitud ya fue resuelta.');
            if ($action === 'confirmed') {
                if (!$room['active']) throw new DomainException('La habitación está inactiva.');
                if ($request['check_in'] < date('Y-m-d')) throw new DomainException('La fecha de entrada ya pasó.');
                // Intervalos [entrada, salida): salir y entrar el mismo día NO se solapan.
                $overlap = $this->query("SELECT id FROM reservations WHERE room_id = ? AND status = 'confirmed' AND check_in < ? AND check_out > ? LIMIT 1 FOR UPDATE", [$room['id'], $request['check_out'], $request['check_in']])->fetchColumn();
                if ($overlap) throw new DomainException('La habitación ya está ocupada en esas fechas.');
            }
            $this->query('UPDATE reservations SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?', [$action, $staffId, $id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function saveRoom(array $input, ?int $id): int
    {
        [$data, $errors] = validateRoom($input);
        if ($errors) throw new DomainException(implode(' ', $errors));
        try {
            if ($id !== null) {
                if (!$this->room($id)) throw new DomainException('La habitación no existe.');
                $this->query('UPDATE rooms SET code = ?, name = ?, capacity = ?, nightly_rate = ? WHERE id = ?', [...array_values($data), $id]);
                return $id;
            }
            $this->query('INSERT INTO rooms (code, name, capacity, nightly_rate) VALUES (?, ?, ?, ?)', array_values($data));
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? 0) === 1062) throw new DomainException('Ya existe una habitación con ese código.');
            throw $error;
        }
    }

    public function futureReservations(int $roomId): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM reservations WHERE room_id = ? AND status = 'confirmed' AND check_out > ?", [$roomId, date('Y-m-d')])->fetchColumn();
    }

    public function deactivate(int $id, bool $acknowledged): void
    {
        $this->pdo->beginTransaction();
        try {
            $room = $this->query('SELECT id FROM rooms WHERE id = ? FOR UPDATE', [$id])->fetch();
            if (!$room) throw new DomainException('La habitación no existe.');
            if ($this->futureReservations($id) > 0 && !$acknowledged) {
                throw new DomainException('Hay reservas confirmadas vigentes o futuras. Revisalas y aceptá la advertencia antes de desactivar.');
            }
            $this->query('UPDATE rooms SET active = 0 WHERE id = ?', [$id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}
