<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$id = field($_GET, 'id');
if (!positiveId($id) || !($request = hotel()->request((int) $id))) fail(404, 'La solicitud no existe.');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = field($_POST, 'action');
        if ($action === 'send_email') {
            $emailId = field($_POST, 'email_id');
            $own = array_filter(reservationEmails()->history((int) $id), fn($row) => (string) $row['id'] === $emailId);
            if (!$own) fail(404, 'El aviso no pertenece a esta reserva.');
            $result = reservationEmails()->send((int) $emailId, field($_POST, 'acknowledged') === '1');
            flash(match ($result) {
                'sent' => 'Correo aceptado por Gmail. La llegada a la bandeja del huésped puede demorar.',
                'disabled' => 'Gmail todavía no está activado. La reserva está guardada.',
                'obsolete' => 'No se envió: la reserva cambió desde que se preparó el aviso.',
                'wait','busy' => 'Hay un envío en curso o muy reciente. Esperá un minuto antes de reintentar.',
                'limit' => 'Se alcanzaron cinco intentos. Revisá la configuración antes de continuar.',
                'uncertain' => 'No se pudo confirmar la entrega. Revisá Enviados en Gmail antes de reintentar.',
                default => 'No se pudo enviar el correo. La reserva se conserva; revisá Gmail y reintentá.',
            });
        } elseif ($action === 'cancel') {
            hotel()->cancelReservation((int) $id, field($_POST, 'reason'), field($_POST, 'acknowledged') === '1', (int) $user['id']);
            flash('Reserva cancelada. Se liberaron sus fechas y se conservó el historial.');
        } elseif (in_array($action, ['checkin','checkout'], true)) {
            hotel()->recordStay((int) $id, $action, (int) $user['id']);
            flash($action === 'checkin' ? 'Llegada registrada.' : 'Salida registrada. La habitación quedó pendiente de limpieza.');
        } else {
            hotel()->resolve((int) $id, $action, (int) $user['id']);
            if ($action === 'confirmed') sendReservationEmails((int) $id);
            flash('Solicitud ' . statusLabel($action) . '.');
        }
        redirect('solicitud.php?id=' . $id);
    } catch (DomainException $error) { http_response_code(409); $errors['actions'] = $error->getMessage(); }
    $request = hotel()->request((int) $id);
}
$stay = hotel()->stay((int) $id);
$cleaning = hotel()->pendingCleaning((int) $request['room_id']);
$balance = hotel()->paymentBalance((int) $id);
pageStart('Solicitud #' . $id, $user);
errorSummary($errors);
$nights = (new DateTimeImmutable($request['check_in']))->diff(new DateTimeImmutable($request['check_out']))->days;
?>
<p><a href="<?= e(url('solicitudes.php')) ?>">← Todas las solicitudes</a></p>
<p><a href="<?= e(url('calendario.php?' . http_build_query(['from'=>$request['check_in'], 'room_id'=>$request['room_id']]))) ?>">Ver en el calendario</a></p>
<dl class="detail-panel"><dt>Estado</dt><dd><?= e(statusLabel($request['status'])) ?></dd>
<dt>Huésped</dt><dd><?= e($request['guest_name']) ?></dd><dt>Correo</dt><dd><?= e($request['email']) ?></dd>
<dt>Habitación</dt><dd><?= e($request['code'] . ' · ' . $request['room_name']) ?> <?= $request['active'] ? '' : '(inactiva)' ?></dd>
<dt>Entrada / salida</dt><dd><?= e($request['check_in'] . ' / ' . $request['check_out']) ?> · <?= e($nights) ?> <?= $nights === 1 ? 'noche' : 'noches' ?></dd>
<dt>Importe registrado</dt><dd>$ <?= e(number_format($nights * (float) $request['nightly_rate'], 2, ',', '.')) ?> ARS</dd>
<dt>Recibida</dt><dd><?= e($request['created_at']) ?></dd>
<?php if ($request['resolved_actor']): ?><dt>Resuelta por</dt><dd><?= e($request['resolved_actor']) ?> · <?= e($request['resolved_at']) ?></dd><?php endif ?></dl>
<?php $reservation=$request; require dirname(__DIR__).'/views/payment-balance.php'; ?>
<?php require dirname(__DIR__) . '/views/reservation-emails.php'; ?>
<p><a class="button" href="<?= e(url('reserva-pagos.php?id='.$id)) ?>">Gestionar señas y pagos</a></p>
<?php if ($request['status'] === 'cancelled'): ?>
<section class="form-panel" aria-labelledby="cancellation-title"><h2 id="cancellation-title">Cancelación registrada</h2>
<p>Esta reserva ya no ocupa fechas en el calendario. Sus datos se conservan para consulta.</p>
<dl class="detail-panel"><dt>Motivo</dt><dd class="cancellation-reason"><?= e($request['cancellation_reason']) ?></dd>
<dt>Cancelada por</dt><dd><?= e($request['cancelled_actor']) ?></dd><dt>Fecha y hora</dt><dd><?= e($request['cancelled_at']) ?></dd></dl></section>
<?php endif ?>
<?php if ($request['status'] === 'pending'): ?><form method="post" id="actions" class="actions"><?php csrfInput() ?><button class="button" name="action" value="confirmed">Confirmar reserva</button><button class="button secondary" name="action" value="rejected">Rechazar solicitud</button></form>
<p class="data-note">Confirmar comprueba disponibilidad. Rechazar no ocupa la habitación.</p><?php endif ?>
<?php if ($request['status'] === 'confirmed'): ?>
<section id="actions" class="form-panel"><h2>Estadía real</h2>
<?php if ($cleaning): ?><p class="notice">Estado actual de la habitación: <strong>Pendiente de limpieza</strong>. No se puede registrar una nueva llegada. <a href="<?= e(url('limpieza.php')) ?>">Gestionar limpieza</a>.</p><?php endif ?>
<?php if ($stay): ?><p>Llegada: <?= e($stay['checked_in_at']) ?> · <?= e($stay['arrival_actor']) ?></p>
<?php if ($stay['checked_out_at']): ?><p>Salida: <?= e($stay['checked_out_at']) ?> · <?= e($stay['departure_actor']) ?></p><?php else: ?><p class="notice">Huésped alojado · sin salida registrada.</p><?php endif ?>
<?php else: ?><p>La llegada todavía no fue registrada.</p><?php endif ?>
<?php if (!$stay || !$stay['checked_out_at']): ?>
<form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="<?= $stay ? 'checkout' : 'checkin' ?>">
<p><?= $stay ? 'Registrá la salida cuando el huésped se retire. Se guarda la hora real, incluso si sale antes o después de lo previsto.' : 'Se admite el ingreso desde la fecha de entrada y antes de la salida prevista. No se admiten ingresos anticipados.' ?></p>
<?php if ($stay && $balance['due']>0): ?><p class="notice">Saldo pendiente registrado: <strong><?= e(Hotel::moneyLabel($balance['due'])) ?></strong>. <a href="<?= e(url('reserva-pagos.php?id='.$id)) ?>">Revisar pagos antes de la salida</a>. Podés registrar la salida y conservar el saldo pendiente.</p><?php endif ?>
<button class="button" <?= !$stay && $cleaning ? 'disabled' : '' ?>><?= $stay ? 'Registrar salida' : 'Registrar llegada' ?></button></form>
<?php endif ?></section><?php endif ?>
<?php if ($request['status'] === 'confirmed' && !$stay): ?>
<p><a class="button" href="<?= e(url('reserva-reprogramar.php?id=' . $id)) ?>">Reprogramar reserva</a></p>
<section class="form-panel" aria-labelledby="cancel-title"><h2 id="cancel-title">Cancelar reserva</h2>
<p>Se liberarán las fechas de esta reserva. El motivo y la persona responsable quedarán registrados. Esta acción no se puede deshacer desde esta pantalla.</p>
<?php if ($balance['net']>0): ?><p class="notice">Hay <?= e(Hotel::moneyLabel($balance['net'])) ?> en pagos netos. Cancelar no devuelve dinero; quedarán a favor del huésped para su seguimiento en Pagos.</p><?php endif ?>
<form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="cancel">
<label for="reason">Motivo de cancelación</label>
<textarea id="reason" name="reason" rows="3" maxlength="500" required aria-describedby="reason-help"><?= e(field($_POST, 'reason')) ?></textarea>
<p id="reason-help" class="data-note">Hasta 500 caracteres. Por ejemplo: el huésped solicitó cancelar su viaje.</p>
<label class="check-label"><input type="checkbox" name="acknowledged" value="1" required> Confirmo que quiero cancelar la reserva #<?= e($id) ?>, habitación <?= e($request['code']) ?>, del <?= e($request['check_in']) ?> al <?= e($request['check_out']) ?>.</label>
<button class="button danger">Confirmar cancelación</button></form></section>
<?php endif ?>
<?php $changes = hotel()->reservationChanges((int) $id); if ($changes): ?>
<section class="spaced-heading" aria-labelledby="history-title"><h2 id="history-title">Historial de reprogramaciones</h2>
<?php foreach ($changes as $change): ?>
<article class="form-panel"><h3><?= e($change['changed_at']) ?> · <?= e($change['changed_actor']) ?></h3>
<dl class="detail-panel"><dt>Antes</dt><dd>Habitación <?= e($change['old_room_code']) ?> · <?= e($change['old_check_in']) ?> a <?= e($change['old_check_out']) ?><br>$ <?= e(number_format(Hotel::reservationTotal($change['old_check_in'], $change['old_check_out'], $change['old_nightly_rate']) / 100, 2, ',', '.')) ?> ARS</dd>
<dt>Después</dt><dd>Habitación <?= e($change['new_room_code']) ?> · <?= e($change['new_check_in']) ?> a <?= e($change['new_check_out']) ?><br>$ <?= e(number_format(Hotel::reservationTotal($change['new_check_in'], $change['new_check_out'], $change['new_nightly_rate']) / 100, 2, ',', '.')) ?> ARS</dd>
<dt>Motivo</dt><dd class="cancellation-reason"><?= e($change['reason']) ?></dd></dl></article>
<?php endforeach ?></section><?php endif; pageEnd(); ?>
