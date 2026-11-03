<?php
declare(strict_types=1);
require_once __DIR__ . '/MailTransport.php';

final class ReservationEmails
{
    public function __construct(private PDO $pdo, private HotelMailTransport $transport) {}

    private static function snapshot(array $r): string
    {
        return json_encode(array_intersect_key($r, array_flip(['room_id','guest_name','email','check_in','check_out','nightly_rate'])), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function queue(PDO $pdo, int $id, string $kind): void
    {
        if (!$pdo->inTransaction()) throw new LogicException('El aviso debe guardarse en la transacción de la reserva.');
        if (!in_array($kind, ['received','confirmed'], true)) throw new InvalidArgumentException('Tipo de aviso inválido.');
        $q = $pdo->prepare('SELECT r.*, h.code, h.name AS room_name FROM reservations r JOIN rooms h ON h.id = r.room_id WHERE r.id = ?');
        $q->execute([$id]); $r = $q->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new LogicException('Reserva inexistente.');
        $confirmed = $kind === 'confirmed';
        $subject = 'Hotel Aurora · ' . ($confirmed ? 'Reserva confirmada' : 'Solicitud recibida') . ' #' . $id;
        $fmt = fn(string $date): string => (new DateTimeImmutable($date))->format('d/m/Y');
        $nights = (int) (new DateTimeImmutable($r['check_in']))->diff(new DateTimeImmutable($r['check_out']))->days;
        $body = 'Hola, ' . $r['guest_name'] . ".\n\n" . ($confirmed
            ? "Tu reserva está confirmada.\n" : "Recibimos tu solicitud y está pendiente de revisión. Este correo NO confirma la reserva.\n")
            . "\nReferencia: #$id\nHabitación: " . $r['code'] . ' · ' . $r['room_name']
            . "\nEntrada: " . $fmt($r['check_in']) . "\nSalida: " . $fmt($r['check_out']) . "\nNoches: $nights\n"
            . ($confirmed ? 'Importe de alojamiento: ' : 'Importe orientativo: ')
            . number_format($nights * (float) $r['nightly_rate'], 2, ',', '.') . " ARS\n\n"
            . "Este mensaje no acredita un pago. Para consultas, respondé a este correo.\n"
            . "Hotel Aurora · Proyecto académico de demostración.\n";
        $q = $pdo->prepare('INSERT INTO reservation_emails (reservation_id,kind,recipient,subject,body,snapshot,message_key,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $q->execute([$id,$kind,$r['email'],$subject,$body,self::snapshot($r),bin2hex(random_bytes(16))]);
    }

    public function enabled(): bool { return $this->transport->enabled(); }

    public function history(int $reservationId): array
    {
        $q = $this->pdo->prepare('SELECT id,kind,recipient,status,attempts,created_at,attempted_at,sent_at,error_code FROM reservation_emails WHERE reservation_id = ? ORDER BY id');
        $q->execute([$reservationId]); return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sendPending(int $reservationId): void
    {
        // Solo los nuevos pendientes de ESTA reserva; nunca dispara un envío masivo histórico.
        foreach ($this->history($reservationId) as $row) if ($row['status'] === 'pending') $this->send((int) $row['id']);
    }

    public function send(int $emailId, bool $acknowledgeUncertain = false): string
    {
        if (!$this->enabled()) return 'disabled';
        if ($this->pdo->inTransaction()) throw new LogicException('Enviar solo después de confirmar la transacción.');
        $lock = 'hotel_mail_' . hash('sha256', (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn() . ':' . $emailId);
        $lock = substr($lock, 0, 64);
        $q = $this->pdo->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int) $q->fetchColumn() !== 1) return 'busy';
        try {
            $q = $this->pdo->prepare('SELECT *, TIMESTAMPDIFF(SECOND, attempted_at, NOW()) AS age_seconds FROM reservation_emails WHERE id = ?'); $q->execute([$emailId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) return 'missing';
            if (in_array($row['status'], ['sent','obsolete'], true)) return $row['status'];
            if (in_array($row['status'], ['sending','uncertain'], true) && !$acknowledgeUncertain) return 'uncertain';
            if ($row['age_seconds'] !== null && (int) $row['age_seconds'] < 60) return 'wait';
            if ((int) $row['attempts'] >= 5) return 'limit';
            $q = $this->pdo->prepare('SELECT * FROM reservations WHERE id = ?'); $q->execute([$row['reservation_id']]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            $expected = $row['kind'] === 'received' ? 'pending' : 'confirmed';
            if (!$r || $r['status'] !== $expected || self::snapshot($r) !== $row['snapshot']) {
                $q = $this->pdo->prepare("UPDATE reservation_emails SET status='obsolete',error_code=NULL WHERE id=?"); $q->execute([$emailId]);
                return 'obsolete';
            }
            $q = $this->pdo->prepare("UPDATE reservation_emails SET status='sending',attempts=attempts+1,attempted_at=NOW(),error_code=NULL WHERE id=?"); $q->execute([$emailId]);
            try {
                $this->transport->send($row);
            } catch (Throwable $error) {
                $status = $error instanceof MailDeliveryException && !$error->uncertain ? 'failed' : 'uncertain';
                $q = $this->pdo->prepare('UPDATE reservation_emails SET status=?,error_code=? WHERE id=?');
                $q->execute([$status, $status === 'failed' ? 'smtp_failed' : 'delivery_unknown', $emailId]);
                return $status;
            }
            // Si el proceso se interrumpe aquí queda sending: exige revisión, no reenvío automático.
            $q = $this->pdo->prepare("UPDATE reservation_emails SET status='sent',sent_at=NOW(),error_code=NULL WHERE id=?"); $q->execute([$emailId]);
            return 'sent';
        } finally { $q = $this->pdo->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
    }
}
