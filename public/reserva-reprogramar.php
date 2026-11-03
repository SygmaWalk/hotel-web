<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$id = field($_GET, 'id');
if (!positiveId($id) || !($reservation = hotel()->request((int) $id))) fail(404, 'La reserva no existe.');
$errors = []; $quote = null; $quoteToken = ''; $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$source = $isPost ? $_POST : $_GET;
$data = [];
foreach (['check_in','check_out','room_id','reason'] as $key) $data[$key] = array_key_exists($key, $source) ? field($source, $key) : (string) ($reservation[$key] ?? '');
if ($isPost) {
    $action = field($_POST, 'action');
    if ($action === 'save') {
        $quoteToken = field($_POST, 'quote_token');
        $stored = $_SESSION['reschedule_quotes'][$quoteToken] ?? null;
        if (!$stored || $stored['id'] !== (int) $id) fail(403, 'La propuesta no es válida. Volvé a abrir Reprogramar reserva.');
        $data = $stored['data'];
        try {
            hotel()->rescheduleReservation($stored, field($_POST, 'acknowledged') === '1', (int) $user['id']);
            unset($_SESSION['reschedule_quotes'][$quoteToken]);
            flash('Reserva reprogramada. El calendario y el importe se actualizaron; el cambio quedó en el historial.');
            redirect('solicitud.php?id=' . $id);
        } catch (DomainException $error) { http_response_code(409); $errors['changes'] = $error->getMessage(); }
    } elseif ($action === 'preview') {
        try {
            $quote = hotel()->previewReschedule((int) $id, $data, (int) $user['id']);
            $quoteToken = bin2hex(random_bytes(32));
            $_SESSION['reschedule_quotes'][$quoteToken] = $quote;
            $_SESSION['reschedule_quotes'] = array_slice($_SESSION['reschedule_quotes'], -20, null, true);
        } catch (DomainException $error) { http_response_code(409); $errors['changes'] = $error->getMessage(); }
    } else { http_response_code(422); $errors['changes'] = 'Acción inválida.'; }
    $reservation = hotel()->request((int) $id);
}
$eligible = $reservation['status'] === 'confirmed' && !hotel()->stay((int) $id);
$rooms = [];
$dateErrors = validateBookingDates($data['check_in'], $data['check_out']);
if ($eligible && !$quote && !$dateErrors) $rooms = hotel()->availableRooms($data['check_in'], $data['check_out'], (int) $id);
if ($dateErrors && !$quote) { $errors += $dateErrors; if (!$isPost) http_response_code(422); }
if (!$eligible) { http_response_code(409); $errors['changes'] = 'Solo se pueden reprogramar reservas confirmadas sin llegada registrada.'; $quote = null; }
$money = fn(int $cents): string => '$ ' . number_format($cents / 100, 2, ',', '.') . ' ARS';
pageStart('Reprogramar reserva #' . $id, $user);
?>
<p><a href="<?= e(url('solicitud.php?id=' . $id)) ?>">← Volver al detalle</a></p>
<div id="changes"><?php errorSummary($errors) ?></div>
<p class="intro"><?= e($reservation['guest_name']) ?> · Habitación <?= e($reservation['code']) ?> · <?= e($reservation['check_in']) ?> a <?= e($reservation['check_out']) ?>.</p>
<?php if ($quote): $original = $quote['original']; $difference = $quote['new_total'] - $quote['old_total']; ?>
<section class="form-panel" aria-labelledby="review-title"><h2 id="review-title">Revisá el cambio</h2>
<dl class="detail-panel"><dt>Antes</dt><dd>Habitación <?= e($original['code']) ?> · <?= e($original['check_in']) ?> a <?= e($original['check_out']) ?><br><?= e($money($quote['old_total'])) ?></dd>
<dt>Después</dt><dd>Habitación <?= e($quote['room_code']) ?> · <?= e($data['check_in']) ?> a <?= e($data['check_out']) ?><br><?= e($money($quote['new_total'])) ?></dd>
<dt>Diferencia total</dt><dd><?= $difference > 0 ? '+' : ($difference < 0 ? '−' : '') ?><?= e($money(abs($difference))) ?><?= $difference === 0 ? ' · Sin diferencia' : '' ?></dd>
<dt>Motivo</dt><dd class="cancellation-reason"><?= e($data['reason']) ?></dd></dl>
<p class="data-note">Misma habitación: se conserva la tarifa por noche acordada. Otra habitación: se aplica su tarifa vigente. El importe no representa un cobro ni un reembolso.</p>
<?php $paymentBalance=hotel()->paymentBalance((int)$id); if ($paymentBalance['net']>0): ?>
<p class="notice">Pagos netos registrados ahora: <?= e(Hotel::moneyLabel($paymentBalance['net'])) ?>. Con este cambio, el saldo pendiente sería <?= e(Hotel::moneyLabel(max(0,$quote['new_total']-$paymentBalance['net']))) ?> y el importe a favor sería <?= e(Hotel::moneyLabel(max(0,$paymentBalance['net']-$quote['new_total']))) ?>. Los movimientos se conservan; el saldo definitivo considera también los pagos que se registren mientras revisás.</p>
<?php endif ?>
<form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="save"><input type="hidden" name="quote_token" value="<?= e($quoteToken) ?>">
<label class="check-label"><input type="checkbox" name="acknowledged" value="1" required> Confirmo las nuevas fechas, la habitación y el importe total de <?= e($money($quote['new_total'])) ?>.</label>
<button class="button">Guardar reprogramación</button></form>
<p><a href="<?= e(url('reserva-reprogramar.php?id=' . $id)) ?>">Volver a editar</a></p>
<p class="data-note">La propuesta vence en 20 minutos. Guardar vuelve a comprobar disponibilidad. Hasta entonces, se mantiene la reserva actual.</p></section>
<?php elseif ($eligible): ?>
<section class="spaced-heading"><h2>1. Consultá las nuevas fechas</h2>
<form method="get" class="filter-form" data-reschedule-dates><input type="hidden" name="id" value="<?= e($id) ?>">
<div><?php inputField('check_in','Nueva entrada',$data,$errors,'date','required min="' . date('Y-m-d') . '" max="' . bookingLimit() . '"') ?></div>
<div><?php inputField('check_out','Nueva salida',$data,$errors,'date','required min="' . date('Y-m-d') . '" max="' . bookingLimit() . '"') ?></div>
<input type="hidden" name="room_id" value="<?= e($data['room_id']) ?>">
<button class="button">Consultar disponibilidad</button></form></section>
<?php if (!$dateErrors): ?>
<section class="form-panel"><h2>2. Elegí habitación y motivo</h2>
<p>Fechas consultadas: <strong><?= e($data['check_in']) ?> a <?= e($data['check_out']) ?></strong>. Si editás las fechas de arriba, volvé a consultar antes de continuar.</p>
<?php if (!$rooms): ?><p class="notice">No hay habitaciones disponibles para todo el intervalo.</p><?php else: ?>
<form method="post" data-reschedule-preview><?php csrfInput() ?><input type="hidden" name="action" value="preview">
<input type="hidden" name="check_in" value="<?= e($data['check_in']) ?>"><input type="hidden" name="check_out" value="<?= e($data['check_out']) ?>">
<label for="room_id">Habitación disponible</label><select id="room_id" name="room_id" required>
<?php foreach ($rooms as $room): $rate = (int) $room['id'] === (int) $reservation['room_id'] ? $reservation['nightly_rate'] : $room['nightly_rate']; ?>
<option value="<?= e($room['id']) ?>" <?= (string) $room['id'] === $data['room_id'] ? 'selected' : '' ?>><?= e($room['code'] . ' · ' . $room['name'] . ' · Hasta ' . $room['capacity'] . ' personas · $ ' . number_format((float) $rate, 2, ',', '.') . ' por noche') ?></option>
<?php endforeach ?></select>
<label for="reason">Motivo del cambio</label><textarea id="reason" name="reason" rows="3" maxlength="500" required><?= e($data['reason']) ?></textarea>
<p class="data-note">Hasta 500 caracteres. Revisar el cambio todavía no modifica la reserva.</p>
<p class="notice" data-reschedule-dirty role="status" hidden>Cambiaste las fechas. Volvé a consultar disponibilidad antes de revisar el cambio.</p>
<button class="button">Revisar cambio e importe</button></form><?php endif ?></section>
<?php endif; endif; pageEnd(); ?>
