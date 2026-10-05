<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$id = field($_GET, 'id');
if (!positiveId($id) || !($request = hotel()->request((int) $id))) fail(404, 'La solicitud no existe.');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = field($_POST, 'action');
        if (in_array($action, ['checkin','checkout'], true)) {
            hotel()->recordStay((int) $id, $action, (int) $user['id']);
            flash($action === 'checkin' ? 'Llegada registrada.' : 'Salida registrada.');
        } else {
            hotel()->resolve((int) $id, $action, (int) $user['id']);
            flash('Solicitud ' . statusLabel($action) . '.');
        }
        redirect('solicitud.php?id=' . $id);
    } catch (DomainException $error) { http_response_code(409); $errors['actions'] = $error->getMessage(); }
    $request = hotel()->request((int) $id);
}
$stay = hotel()->stay((int) $id);
pageStart('Solicitud #' . $id, $user);
errorSummary($errors);
$nights = (new DateTimeImmutable($request['check_in']))->diff(new DateTimeImmutable($request['check_out']))->days;
?>
<p><a href="<?= e(url('solicitudes.php')) ?>">← Todas las solicitudes</a></p>
<dl class="detail-panel"><dt>Estado</dt><dd><?= e(statusLabel($request['status'])) ?></dd>
<dt>Huésped</dt><dd><?= e($request['guest_name']) ?></dd><dt>Correo</dt><dd><?= e($request['email']) ?></dd>
<dt>Habitación</dt><dd><?= e($request['code'] . ' · ' . $request['room_name']) ?> <?= $request['active'] ? '' : '(inactiva)' ?></dd>
<dt>Entrada / salida</dt><dd><?= e($request['check_in'] . ' / ' . $request['check_out']) ?> · <?= e($nights) ?> noches</dd>
<dt>Importe registrado</dt><dd>$ <?= e(number_format($nights * (float) $request['nightly_rate'], 2, ',', '.')) ?> ARS</dd>
<dt>Recibida</dt><dd><?= e($request['created_at']) ?></dd></dl>
<?php if ($request['status'] === 'pending'): ?><form method="post" id="actions" class="actions"><?php csrfInput() ?><button class="button" name="action" value="confirmed">Confirmar reserva</button><button class="button secondary" name="action" value="rejected">Rechazar solicitud</button></form>
<p class="data-note">Confirmar comprueba disponibilidad. Rechazar no ocupa la habitación.</p><?php endif ?>
<?php if ($request['status'] === 'confirmed'): ?>
<section id="actions" class="form-panel"><h2>Estadía real</h2>
<?php if ($stay): ?><p>Llegada: <?= e($stay['checked_in_at']) ?> · <?= e($stay['arrival_actor']) ?></p>
<?php if ($stay['checked_out_at']): ?><p>Salida: <?= e($stay['checked_out_at']) ?> · <?= e($stay['departure_actor']) ?></p><?php else: ?><p class="notice">Huésped alojado · sin salida registrada.</p><?php endif ?>
<?php else: ?><p>La llegada todavía no fue registrada.</p><?php endif ?>
<?php if (!$stay || !$stay['checked_out_at']): ?>
<form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="<?= $stay ? 'checkout' : 'checkin' ?>">
<p><?= $stay ? 'Registrá la salida cuando el huésped se retire. Se guarda la hora real, incluso si sale antes o después de lo previsto.' : 'Se admite el ingreso desde la fecha de entrada y antes de la salida prevista. No se admiten ingresos anticipados.' ?></p>
<button class="button"><?= $stay ? 'Registrar salida' : 'Registrar llegada' ?></button></form>
<?php endif ?></section><?php endif ?>
<?php pageEnd(); ?>
