<?php
declare(strict_types=1);
trait HotelOperations
{
    private function actor(int $id, bool $admin = false): void
    {
        $role = $this->query('SELECT role FROM users WHERE id = ?', [$id])->fetchColumn();
        if (!$role || ($admin && $role !== 'admin')) throw new DomainException('No tenés permisos para realizar esta operación.');
    }

    public function recordStay(int $id, string $action, int $actor): void
    {
        $this->actor($actor);
        if (!in_array($action, ['checkin','checkout'], true)) throw new DomainException('Movimiento inválido.');
        $initial = $this->request($id);
        if (!$initial) throw new DomainException('La reserva no existe.');
        $this->pdo->beginTransaction();
        try {
            $room = $this->query('SELECT * FROM rooms WHERE id = ? FOR UPDATE', [$initial['room_id']])->fetch();
            $reservation = $this->query('SELECT * FROM reservations WHERE id = ? FOR UPDATE', [$id])->fetch();
            $stay = $this->query('SELECT * FROM stays WHERE reservation_id = ? FOR UPDATE', [$id])->fetch();
            if ($reservation['status'] !== 'confirmed') throw new DomainException('Solo se registran movimientos de reservas confirmadas.');
            $now = date('Y-m-d H:i:s');
            if ($action === 'checkin') {
                if ($stay) throw new DomainException('La llegada ya está registrada.');
                if (!$room['active']) throw new DomainException('La habitación está inactiva.');
                if (date('Y-m-d') < $reservation['check_in'] || date('Y-m-d') >= $reservation['check_out']) {
                    throw new DomainException('La llegada debe registrarse desde la fecha de entrada y antes de la salida prevista.');
                }
                $occupied = $this->query('SELECT s.reservation_id FROM stays s JOIN reservations r ON r.id = s.reservation_id WHERE r.room_id = ? AND s.checked_out_at IS NULL LIMIT 1 FOR UPDATE', [$room['id']])->fetchColumn();
                if ($occupied) throw new DomainException('La habitación tiene un huésped sin salida registrada. Registrá primero su salida.');
                $this->query('INSERT INTO stays (reservation_id, checked_in_at, checked_in_by) VALUES (?, ?, ?)', [$id, $now, $actor]);
            } else {
                if (!$stay) throw new DomainException('Primero debe registrarse la llegada.');
                if ($stay['checked_out_at'] !== null) throw new DomainException('La salida ya está registrada.');
                $this->query('UPDATE stays SET checked_out_at = ?, checked_out_by = ? WHERE reservation_id = ?', [$now, $actor, $id]);
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function stay(int $id): ?array
    {
        return $this->query('SELECT s.*, a.email AS arrival_actor, b.email AS departure_actor FROM stays s JOIN users a ON a.id = s.checked_in_by LEFT JOIN users b ON b.id = s.checked_out_by WHERE s.reservation_id = ?', [$id])->fetch() ?: null;
    }

    public function occupants(): array
    {
        return $this->query('SELECT r.id, r.room_id, r.guest_name, r.check_out, h.code, s.checked_in_at FROM stays s JOIN reservations r ON r.id = s.reservation_id JOIN rooms h ON h.id = r.room_id WHERE s.checked_out_at IS NULL ORDER BY h.code')->fetchAll();
    }

    public function actualStays(string $from, string $to): array
    {
        return $this->query('SELECT r.id, r.room_id, r.guest_name, s.checked_in_at, s.checked_out_at FROM stays s JOIN reservations r ON r.id = s.reservation_id WHERE s.checked_in_at < ? AND (s.checked_out_at IS NULL OR s.checked_out_at >= ?) ORDER BY s.checked_in_at', [(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d'), $from])->fetchAll();
    }

    public function createStaff(string $email, string $password, string $confirmation, int $actor): int
    {
        $this->actor($actor, true);
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) throw new DomainException('Ingresá un correo válido de hasta 190 caracteres.');
        if (strlen($password) < 12 || trim($password) === '' || strlen($password) > 72 || str_contains($password, "\0")) throw new DomainException('La contraseña debe tener entre 12 y 72 bytes, sin caracteres nulos.');
        if ($password !== $confirmation) throw new DomainException('Las contraseñas no coinciden.');
        try {
            // El rol es fijo en el servidor; nunca se toma del formulario.
            $this->query("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'staff')", [$email, password_hash($password, PASSWORD_DEFAULT)]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? 0) === 1062) throw new DomainException('Ya existe un usuario con ese correo.');
            throw $error;
        }
    }

    public function staffUsers(int $actor): array
    {
        $this->actor($actor, true);
        return $this->query("SELECT id, email FROM users WHERE role = 'staff' ORDER BY email")->fetchAll();
    }

    public function images(int $roomId): array
    {
        return $this->query('SELECT id, filename, mime_type FROM room_images WHERE room_id = ? ORDER BY id', [$roomId])->fetchAll();
    }

    public function image(int $id): ?array
    {
        return $this->query('SELECT i.*, r.active FROM room_images i JOIN rooms r ON r.id = i.room_id WHERE i.id = ?', [$id])->fetch() ?: null;
    }

    public function saveRoomImages(array $data, ?int $id, array $uploads, int $actor): int
    {
        $this->actor($actor, true);
        require_once __DIR__ . '/RoomImages.php';
        $files = RoomImages::validate($uploads);
        $stored = [];
        $this->pdo->beginTransaction();
        try {
            if ($id !== null) $this->query('SELECT id FROM rooms WHERE id = ? FOR UPDATE', [$id])->fetch();
            $roomId = $this->saveRoom($data, $id);
            if (count($this->images($roomId)) + count($files) > 5) throw new DomainException('Se permiten hasta cinco imágenes por habitación.');
            foreach ($files as $file) {
                $filename = RoomImages::store($file);
                $stored[] = $filename;
                $this->query('INSERT INTO room_images (room_id, filename, mime_type) VALUES (?, ?, ?)', [$roomId, $filename, $file['mime']]);
            }
            $this->pdo->commit();
            return $roomId;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            foreach ($stored as $filename) unlink(RoomImages::directory() . '/' . $filename);
            throw $error;
        }
    }
}
