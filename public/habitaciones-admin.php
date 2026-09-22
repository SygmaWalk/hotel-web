<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser(true);
$errors = []; $data = [];
$idText = field($_GET, 'id');
$id = $idText === '' ? null : (positiveId($idText) ? (int) $idText : 0);
if ($id !== null) {
    $data = hotel()->room($id);
    if (!$data) fail(404, 'La habitación no existe.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = field($_POST, 'action');
    try {
        if ($action === 'save') {
            [$data, $errors] = validateRoom($_POST);
            if (!$errors) {
                hotel()->saveRoom($data, $id);
                flash('Datos de la habitación guardados.');
                redirect('habitaciones-admin.php');
            }
            http_response_code(422);
        } elseif ($action === 'deactivate' && $id !== null) {
            hotel()->deactivate($id, field($_POST, 'acknowledged') === '1');
            flash('Habitación desactivada. Se conserva el historial y las reservas existentes.');
            redirect('habitaciones-admin.php');
        } else { throw new DomainException('Acción inválida.'); }
    } catch (DomainException $error) { http_response_code(422); $errors['room-form'] = $error->getMessage(); }
}
$rooms = hotel()->rooms(true);
$future = $id !== null ? hotel()->futureReservations($id) : 0;
pageStart('Administrar habitaciones', $user);
errorSummary($errors);
?>
<div class="admin-grid"><section><h2><?= $id === null ? 'Nueva habitación' : 'Editar habitación' ?></h2>
<form method="post" id="room-form" class="form-panel"><?php csrfInput() ?><input type="hidden" name="action" value="save">
<?php inputField('code', 'Código único', $data, $errors, 'text', 'required maxlength="20"') ?>
<?php inputField('name', 'Nombre', $data, $errors, 'text', 'required maxlength="100"') ?>
<?php inputField('capacity', 'Capacidad (personas)', $data, $errors, 'number', 'required min="1" max="100" step="1"') ?>
<?php inputField('nightly_rate', 'Tarifa por noche (ARS)', $data, $errors, 'number', 'required min="0" max="99999999.99" step="0.01"') ?>
<button class="button">Guardar habitación</button> <a href="<?= e(url('habitaciones-admin.php')) ?>">Nueva / cancelar</a></form>
<?php if ($id !== null && hotel()->room($id)['active']): ?>
<form method="post" class="form-panel danger-zone"><?php csrfInput() ?><input type="hidden" name="action" value="deactivate">
<h3>Desactivar habitación</h3><p>Dejará de ofrecerse. Las reservas y solicitudes existentes se conservarán.</p>
<?php if ($future): ?><p class="notice error">Hay <?= e($future) ?> reservas confirmadas vigentes o futuras. Coordiná su atención antes de desactivar.</p><label class="check-label"><input type="checkbox" name="acknowledged" value="1" required> Revisé las reservas y entiendo que no se cancelan automáticamente.</label><?php endif ?>
<button class="button secondary">Desactivar habitación</button></form><?php endif ?>
</section><section><h2>Inventario</h2><?php if (!$rooms): ?><p class="notice">Todavía no hay habitaciones. Creá la primera.</p><?php endif ?>
<div class="inventory"><?php foreach ($rooms as $room): ?><article class="room-card"><h3><?= e($room['code'] . ' · ' . $room['name']) ?></h3><p><?= e($room['capacity']) ?> personas · $ <?= e(number_format((float) $room['nightly_rate'], 2, ',', '.')) ?></p><p><?= $room['active'] ? 'Activa' : 'Inactiva' ?></p><a href="<?= e(url('habitaciones-admin.php?id=' . $room['id'])) ?>">Editar <?= e($room['code']) ?></a></article><?php endforeach ?></div></section></div>
<?php pageEnd(); ?>
