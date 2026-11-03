<?php
declare(strict_types=1);
trait HotelPayments
{
    public static function paymentKinds(): array { return ['deposit'=>'Seña','payment'=>'Pago','refund'=>'Devolución']; }
    public static function paymentMethods(): array { return ['cash'=>'Efectivo','transfer'=>'Transferencia','card'=>'Tarjeta','other'=>'Otro']; }

    public static function moneyCents(string $amount): int
    {
        $amount = str_replace(',', '.', trim($amount));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amount)) throw new DomainException('Ingresá un importe sin separadores de miles y con hasta dos decimales.');
        [$whole,$fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return (int)$whole * 100 + (int)str_pad($fraction, 2, '0');
    }

    public static function moneyDecimal(int $cents): string { return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT); }
    public static function moneyLabel(int $cents): string { return '$ '.number_format($cents / 100,2,',','.').' ARS'; }

    public function paymentHistory(int $id): array
    {
        return $this->query('SELECT p.*, u.email AS recorded_actor, v.email AS voided_actor
            FROM reservation_payments p JOIN users u ON u.id=p.recorded_by LEFT JOIN users v ON v.id=p.voided_by
            WHERE p.reservation_id=? ORDER BY p.id DESC',[$id])->fetchAll();
    }

    private function balanceFor(array $reservation, array $entries): array
    {
        $charged = 0; $refunded = 0;
        foreach ($entries as $entry) {
            if ($entry['voided_at'] !== null) continue;
            $amount = self::moneyCents($entry['amount']);
            if ($entry['kind'] === 'refund') $refunded += $amount; else $charged += $amount;
        }
        $total = self::reservationTotal($reservation['check_in'],$reservation['check_out'],$reservation['nightly_rate']);
        $net = $charged - $refunded;
        // Cancelar no devuelve dinero: el saldo registrado pasa a quedar a favor del huésped.
        $payable = $reservation['status'] === 'confirmed' ? $total : 0;
        return ['total'=>$total,'charged'=>$charged,'refunded'=>$refunded,'net'=>$net,
            'due'=>max(0,$payable-$net),'credit'=>max(0,$net-$payable)];
    }

    public function paymentBalance(int $id): array
    {
        // Un SELECT evita mezclar estado/tarifa anterior con pagos de una versión posterior.
        $rows = $this->query('SELECT r.check_in,r.check_out,r.nightly_rate,r.status,p.id,p.amount,p.kind,p.voided_at
            FROM reservations r LEFT JOIN reservation_payments p ON p.reservation_id=r.id WHERE r.id=?',[$id])->fetchAll();
        if (!$rows) throw new DomainException('La reserva no existe.');
        return $this->balanceFor($rows[0],array_values(array_filter($rows,fn(array $row): bool => $row['id'] !== null)));
    }

    public function recordPayment(int $id, array $input, string $token, bool $acknowledged, int $actor): int
    {
        $this->actor($actor);
        if (!$acknowledged) throw new DomainException('Confirmá que el movimiento ya se realizó antes de registrarlo.');
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new DomainException('El formulario no es válido. Volvé a abrir Pagos.');
        $data = [];
        foreach (['kind','amount','method','paid_on','note'] as $key) $data[$key]=field($input,$key);
        if (!isset(self::paymentKinds()[$data['kind']]) || !isset(self::paymentMethods()[$data['method']])) throw new DomainException('Elegí un tipo de movimiento y un medio válidos.');
        $cents=self::moneyCents($data['amount']);
        if ($cents<=0) throw new DomainException('El importe debe ser mayor que cero.');
        $data['amount']=self::moneyDecimal($cents);
        if (!validDate($data['paid_on']) || $data['paid_on']<'2000-01-01' || $data['paid_on']>date('Y-m-d')) throw new DomainException('La fecha del movimiento debe estar entre el año 2000 y hoy.');
        if ($data['note']==='' || mb_strlen($data['note'])>500) throw new DomainException('Ingresá un concepto de entre 1 y 500 caracteres.');
        $this->pdo->beginTransaction();
        try {
            // Todas las altas y anulaciones toman esta misma fila. Reprogramar/cancelar también la bloquean.
            $reservation=$this->query('SELECT * FROM reservations WHERE id = ? FOR UPDATE',[$id])->fetch();
            if (!$reservation) throw new DomainException('La reserva no existe.');
            $existing=$this->query('SELECT * FROM reservation_payments WHERE submission_token=?',[$token])->fetch();
            if ($existing) {
                if ((int)$existing['reservation_id']!==$id || (int)$existing['recorded_by']!==$actor) throw new DomainException('El formulario ya se usó en otra operación.');
                foreach ($data as $key=>$value) if ($existing[$key]!==$value) throw new DomainException('El formulario ya se usó con otros datos. Abrí uno nuevo.');
                $this->pdo->commit(); return (int)$existing['id'];
            }
            $balance=$this->balanceFor($reservation,$this->paymentHistory($id));
            if ($data['kind']==='refund') {
                if (!in_array($reservation['status'],['confirmed','cancelled'],true) || $cents>$balance['net']) throw new DomainException('La devolución no puede superar los pagos netos registrados.');
            } else {
                if ($reservation['status']!=='confirmed') throw new DomainException('Solo se registran cobros en reservas confirmadas.');
                if ($cents>$balance['due']) throw new DomainException('El importe supera el saldo pendiente actual. Revisá los pagos o la reprogramación.');
            }
            $this->query('INSERT INTO reservation_payments (reservation_id,kind,amount,method,paid_on,note,recorded_at,recorded_by,submission_token) VALUES (?,?,?,?,?,?,?,?,?)',
                [$id,$data['kind'],$data['amount'],$data['method'],$data['paid_on'],$data['note'],date('Y-m-d H:i:s'),$actor,$token]);
            $paymentId=(int)$this->pdo->lastInsertId(); $this->pdo->commit(); return $paymentId;
        } catch (Throwable $error) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $error; }
    }

    public function voidPayment(int $reservationId, int $paymentId, string $reason, bool $acknowledged, int $actor): void
    {
        $this->actor($actor); $reason=trim($reason);
        if (!$acknowledged) throw new DomainException('Confirmá la anulación del registro.');
        if ($reason==='' || mb_strlen($reason)>500) throw new DomainException('Ingresá un motivo de entre 1 y 500 caracteres.');
        $this->pdo->beginTransaction();
        try {
            $reservation=$this->query('SELECT * FROM reservations WHERE id = ? FOR UPDATE',[$reservationId])->fetch();
            if (!$reservation) throw new DomainException('La reserva no existe.');
            $entry=$this->query('SELECT * FROM reservation_payments WHERE id=? AND reservation_id=?',[$paymentId,$reservationId])->fetch();
            if (!$entry || $entry['voided_at']!==null) throw new DomainException('El movimiento no existe en esta reserva o ya fue anulado.');
            $balance=$this->balanceFor($reservation,$this->paymentHistory($reservationId));
            if ($entry['kind']!=='refund' && self::moneyCents($entry['amount'])>$balance['net']) throw new DomainException('No se puede anular este cobro porque dejaría devoluciones sin respaldo. Revisá primero los registros de devolución.');
            $this->query('UPDATE reservation_payments SET voided_at=?,voided_by=?,void_reason=? WHERE id=?', [date('Y-m-d H:i:s'),$actor,$reason,$paymentId]);
            $this->pdo->commit();
        } catch (Throwable $error) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $error; }
    }

    public function dailyPayments(string $date): array
    {
        if (!validDate($date) || $date<'2000-01-01' || $date>date('Y-m-d')) throw new DomainException('Elegí una fecha válida entre el año 2000 y hoy.');
        $entries=$this->query('SELECT p.*,r.guest_name,h.code,u.email AS recorded_actor,v.email AS voided_actor
            FROM reservation_payments p JOIN reservations r ON r.id=p.reservation_id JOIN rooms h ON h.id=r.room_id
            JOIN users u ON u.id=p.recorded_by LEFT JOIN users v ON v.id=p.voided_by
            WHERE p.paid_on=? ORDER BY p.id DESC',[$date])->fetchAll();
        $methods=[]; $charged=0; $refunded=0;
        foreach (self::paymentMethods() as $key=>$label) $methods[$key]=['charged'=>0,'refunded'=>0,'net'=>0];
        foreach ($entries as $entry) {
            if ($entry['voided_at']!==null) continue;
            $amount=self::moneyCents($entry['amount']); $key=$entry['kind']==='refund'?'refunded':'charged';
            $methods[$entry['method']][$key]+=$amount;
            if ($key==='charged') $charged+=$amount; else $refunded+=$amount;
        }
        foreach ($methods as &$method) $method['net']=$method['charged']-$method['refunded']; unset($method);
        return ['entries'=>$entries,'methods'=>$methods,'charged'=>$charged,'refunded'=>$refunded,'net'=>$charged-$refunded];
    }
}
