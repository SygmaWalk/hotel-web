<?php
declare(strict_types=1);
trait HotelMaintenance
{
    public function maintenanceDuring(int $roomId, string $from, string $until): array
    {
        // Fin exclusivo; al levantar se conserva el bloqueo de noches anteriores a hoy.
        return $this->query('SELECT id, room_id, start_date, end_date,
            LEAST(end_date, COALESCE(DATE(released_at), end_date)) AS effective_end
            FROM room_maintenance WHERE room_id = ? AND start_date < ?
            AND LEAST(end_date, COALESCE(DATE(released_at), end_date)) > ?
            AND start_date < LEAST(end_date, COALESCE(DATE(released_at), end_date)) ORDER BY start_date, id',
            [$roomId, $until, $from])->fetchAll();
    }

    private function assertNoMaintenance(int $roomId, string $from, string $until): void
    {
        if ($this->maintenanceDuring($roomId, $from, $until)) throw new DomainException('La habitación tiene un bloqueo por mantenimiento en esas fechas. Elegí otra habitación o intervalo.');
    }

    public function inspectMaintenance(array $input): array
    {
        $data = [];
        foreach (['room_id','start_date','end_date','reason'] as $key) $data[$key] = field($input, $key);
        $errors = validateBookingDates($data['start_date'], $data['end_date']);
        if (!positiveId($data['room_id'])) $errors[] = 'Elegí una habitación válida.';
        if ($data['reason'] === '' || mb_strlen($data['reason']) > 500) $errors[] = 'Ingresá un motivo de entre 1 y 500 caracteres.';
        if ($errors) throw new DomainException(implode(' ', $errors));
        $room = $this->room((int) $data['room_id']);
        if (!$room) throw new DomainException('La habitación no existe.');
        $reservations = $this->query("SELECT id, guest_name, check_in, check_out FROM reservations
            WHERE room_id = ? AND status = 'confirmed' AND check_in < ? AND check_out > ? ORDER BY check_in, id",
            [$room['id'], $data['end_date'], $data['start_date']])->fetchAll();
        $occupants = $data['start_date'] === date('Y-m-d') ? $this->query('SELECT r.id, r.guest_name, r.check_out FROM stays s
            JOIN reservations r ON r.id = s.reservation_id WHERE r.room_id = ? AND s.checked_out_at IS NULL', [$room['id']])->fetchAll() : [];
        $blocks = $this->maintenanceDuring((int) $room['id'], $data['start_date'], $data['end_date']);
        return compact('data','room','reservations','occupants','blocks');
    }

    public function createMaintenance(array $input, string $token, bool $acknowledged, int $actor): int
    {
        $this->actor($actor);
        if (!$acknowledged) throw new DomainException('Confirmá la habitación y las fechas antes de bloquear.');
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new DomainException('El formulario no es válido. Volvé a revisar el bloqueo.');
        $review = $this->inspectMaintenance($input); $data = $review['data'];
        $this->pdo->beginTransaction();
        try {
            // Misma puerta de entrada que crear, confirmar, reprogramar y registrar llegada.
            $this->query('SELECT * FROM rooms WHERE id = ? FOR UPDATE', [$data['room_id']])->fetch();
            $existing = $this->query('SELECT * FROM room_maintenance WHERE submission_token = ? FOR UPDATE', [$token])->fetch();
            if ($existing) {
                foreach (['room_id','start_date','end_date','reason'] as $key) if ((string) $existing[$key] !== $data[$key]) throw new DomainException('Este formulario ya se usó para otro bloqueo.');
                if ((int) $existing['created_by'] !== $actor) throw new DomainException('Este formulario pertenece a otro empleado.');
                $this->pdo->commit(); return (int) $existing['id'];
            }
            // Primera lectura consistente después del bloqueo: incluye lo confirmado mientras esperábamos.
            $review = $this->inspectMaintenance($data);
            if ($review['reservations'] || $review['occupants'] || $review['blocks']) throw new DomainException('No se guardó el bloqueo. Resolvé las reservas, las estadías abiertas o los bloqueos superpuestos y volvé a revisar.');
            $this->query('INSERT INTO room_maintenance (room_id, start_date, end_date, reason, created_at, created_by, submission_token) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$data['room_id'], $data['start_date'], $data['end_date'], $data['reason'], date('Y-m-d H:i:s'), $actor, $token]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit(); return $id;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function releaseMaintenance(int $id, string $reason, bool $acknowledged, int $actor): void
    {
        $this->actor($actor); $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) throw new DomainException('Ingresá un motivo de entre 1 y 500 caracteres para levantar el bloqueo.');
        if (!$acknowledged) throw new DomainException('Confirmá que querés liberar las noches pendientes de este bloqueo.');
        $initial = $this->query('SELECT room_id FROM room_maintenance WHERE id = ?', [$id])->fetch();
        if (!$initial) throw new DomainException('El bloqueo no existe.');
        $this->pdo->beginTransaction();
        try {
            $this->query('SELECT * FROM rooms WHERE id = ? FOR UPDATE', [$initial['room_id']])->fetch();
            $block = $this->query('SELECT * FROM room_maintenance WHERE id = ? FOR UPDATE', [$id])->fetch();
            if ($block['released_at'] !== null || $block['end_date'] <= date('Y-m-d')) throw new DomainException('El bloqueo ya terminó o fue levantado. Actualizá la página.');
            $this->query('UPDATE room_maintenance SET released_at = ?, released_by = ?, release_reason = ? WHERE id = ?', [date('Y-m-d H:i:s'), $actor, $reason, $id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function maintenanceList(bool $history = false, ?int $blockId = null): array
    {
        return $this->query('SELECT m.*, h.code, h.active, c.email AS created_actor, u.email AS released_actor
            FROM room_maintenance m JOIN rooms h ON h.id = m.room_id JOIN users c ON c.id = m.created_by
            LEFT JOIN users u ON u.id = m.released_by WHERE ' . ($blockId !== null ? 'm.id = ?' : ($history ? '(m.released_at IS NOT NULL OR m.end_date <= ?)' : '(m.released_at IS NULL AND m.end_date > ?)')) .
            ($history ? ' ORDER BY COALESCE(m.released_at, m.end_date) DESC, m.id DESC LIMIT 30' : ' ORDER BY m.start_date, h.code, m.id'), [$blockId ?? date('Y-m-d')])->fetchAll();
    }
}
