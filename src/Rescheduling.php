<?php
declare(strict_types=1);
trait HotelRescheduling
{
    public static function reservationTotal(string $from, string $to, string $rate): int
    {
        // Centavos enteros: evita redondeos de coma flotante al comparar importes.
        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '0');
        $cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        return (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days * $cents;
    }

    private function scheduleSnapshot(array $reservation): string
    {
        $values = [];
        foreach (['id','room_id','check_in','check_out','nightly_rate','status'] as $key) $values[] = (string) $reservation[$key];
        // El último cambio detecta también ida y vuelta a las mismas fechas (ABA).
        // Al guardar, ya se bloqueó la reserva. Esta primera lectura consistente de la
        // transacción ve su historial confirmado sin bloquear huecos de otras reservas.
        $values[] = (string) ($this->query('SELECT id FROM reservation_changes WHERE reservation_id = ? ORDER BY id DESC LIMIT 1', [$reservation['id']])->fetchColumn() ?: 0);
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    private function assertReschedulable(?array $reservation, bool $lock = false): void
    {
        if (!$reservation) throw new DomainException('La reserva no existe.');
        if ($reservation['status'] !== 'confirmed') throw new DomainException('Solo se pueden reprogramar reservas confirmadas.');
        if ($this->query('SELECT reservation_id FROM stays WHERE reservation_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$reservation['id']])->fetchColumn()) {
            throw new DomainException('La reserva ya tiene una llegada registrada y no se puede reprogramar.');
        }
    }

    public function previewReschedule(int $id, array $input, int $actor): array
    {
        $this->actor($actor);
        $original = $this->request($id);
        $this->assertReschedulable($original);
        $data = [];
        foreach (['room_id','check_in','check_out','reason'] as $key) $data[$key] = field($input, $key);
        $errors = validateBookingDates($data['check_in'], $data['check_out']);
        if (!positiveId($data['room_id'])) $errors[] = 'Elegí una habitación válida.';
        if ($data['reason'] === '' || mb_strlen($data['reason']) > 500) $errors[] = 'Ingresá un motivo de entre 1 y 500 caracteres.';
        if ($errors) throw new DomainException(implode(' ', $errors));
        if ((int) $original['room_id'] === (int) $data['room_id'] && $original['check_in'] === $data['check_in'] && $original['check_out'] === $data['check_out']) {
            throw new DomainException('Elegí otras fechas o una habitación diferente. La reserva no cambió.');
        }
        $available = $this->availableRooms($data['check_in'], $data['check_out'], $id);
        $room = null;
        foreach ($available as $candidate) if ((int) $candidate['id'] === (int) $data['room_id']) $room = $candidate;
        if (!$room) throw new DomainException('La habitación no está disponible para todo el intervalo. Consultá otras fechas o elegí otra.');
        $rate = (int) $original['room_id'] === (int) $room['id'] ? $original['nightly_rate'] : $room['nightly_rate'];
        return ['id'=>$id, 'actor'=>$actor, 'expires'=>time()+1200, 'snapshot'=>$this->scheduleSnapshot($original),
            'original'=>$original, 'data'=>$data, 'room_code'=>$room['code'], 'rate'=>$rate,
            'old_total'=>self::reservationTotal($original['check_in'], $original['check_out'], $original['nightly_rate']),
            'new_total'=>self::reservationTotal($data['check_in'], $data['check_out'], $rate)];
    }

    // La propuesta procede de la sesión del servidor, nunca de campos de precio del navegador.
    public function rescheduleReservation(array $quote, bool $acknowledged, int $actor): void
    {
        $this->actor($actor);
        if (!$acknowledged) throw new DomainException('Confirmá las fechas, la habitación y el importe antes de guardar.');
        if ($quote['actor'] !== $actor || $quote['expires'] < time()) throw new DomainException('La propuesta venció o pertenece a otra sesión. Volvé a revisar el cambio.');
        $id = (int) $quote['id']; $data = $quote['data'];
        $initial = $this->request($id);
        if (!$initial) throw new DomainException('La reserva no existe.');
        $this->pdo->beginTransaction();
        try {
            // Orden numérico para que dos cambios entre habitaciones no inviertan bloqueos.
            $roomIds = array_unique([(int) $initial['room_id'], (int) $data['room_id']]); sort($roomIds, SORT_NUMERIC);
            $lockedRooms = [];
            foreach ($roomIds as $roomId) $lockedRooms[$roomId] = $this->query('SELECT * FROM rooms WHERE id = ? FOR UPDATE', [$roomId])->fetch();
            $current = $this->query('SELECT * FROM reservations WHERE id = ? FOR UPDATE', [$id])->fetch();
            if (!$current || (int) $current['room_id'] !== (int) $initial['room_id'] || !hash_equals($quote['snapshot'], $this->scheduleSnapshot($current))) {
                throw new DomainException('La reserva cambió desde que revisaste la propuesta. Volvé a abrir Reprogramar reserva.');
            }
            $this->assertReschedulable($current, true);
            $destination = $lockedRooms[(int) $data['room_id']];
            $this->assertBookable($destination ?: null, $data, $id);
            $rate = (int) $current['room_id'] === (int) $data['room_id'] ? $current['nightly_rate'] : $destination['nightly_rate'];
            if ($rate !== $quote['rate'] || $destination['code'] !== $quote['room_code']) throw new DomainException('La habitación o su tarifa cambió. Volvé a revisar el importe antes de confirmar.');
            $this->query('INSERT INTO reservation_changes (reservation_id, old_room_id, new_room_id, old_room_code, new_room_code,
                old_check_in, old_check_out, new_check_in, new_check_out, old_nightly_rate, new_nightly_rate, reason, changed_at, changed_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $current['room_id'], $data['room_id'], $lockedRooms[(int) $current['room_id']]['code'], $destination['code'],
                 $current['check_in'], $current['check_out'], $data['check_in'], $data['check_out'], $current['nightly_rate'], $rate,
                 $data['reason'], date('Y-m-d H:i:s'), $actor]);
            $this->query('UPDATE reservations SET room_id = ?, check_in = ?, check_out = ?, nightly_rate = ? WHERE id = ?',
                [$data['room_id'], $data['check_in'], $data['check_out'], $rate, $id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function reservationChanges(int $id): array
    {
        return $this->query('SELECT c.*, u.email AS changed_actor FROM reservation_changes c JOIN users u ON u.id = c.changed_by
            WHERE c.reservation_id = ? ORDER BY c.id DESC', [$id])->fetchAll();
    }
}
