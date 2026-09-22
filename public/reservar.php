<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$errors = [];
$data = ['room_id' => field($_GET, 'room_id')];
$rooms = hotel()->rooms();
$token = field($_POST, 'submission_token');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$data, $errors] = validateRequest($_POST);
    if (!isset($_SESSION['submissions'][$token])) fail(403, 'El formulario venció. Abrí una nueva solicitud.');
    if (!$errors) {
        try {
            $id = hotel()->submit($data, $token);
            // PRG: tras el INSERT, redirigir a GET evita reenviar POST al actualizar.
            $_SESSION['receipt'] = $id;
            redirect('solicitud-enviada.php');
        } catch (DomainException $error) { $errors['room_id'] = $error->getMessage(); }
    }
    http_response_code(422);
} else {
    $token = bin2hex(random_bytes(32));
    $_SESSION['submissions'][$token] = time();
    // Permitir varias pestañas y reintentos; limitar el tamaño de la sesión.
    $_SESSION['submissions'] = array_slice($_SESSION['submissions'], -20, null, true);
}
pageStart('Solicitá tu estadía');
?>
<p class="intro">Enviar una solicitud no confirma una reserva. El personal revisará las fechas y la habitación elegida. Demo: usá datos ficticios.</p>
<?php errorSummary($errors) ?>
<?php if (!$rooms): ?><p class="notice">No hay habitaciones activas. Intentá nuevamente más adelante.</p><?php else: ?>
<form class="form-panel" method="post" data-reservation-form>
<?php csrfInput() ?><input type="hidden" name="submission_token" value="<?= e($token) ?>">
<?php inputField('guest_name', 'Nombre y apellido', $data, $errors, 'text', 'required maxlength="100" autocomplete="name"') ?>
<?php inputField('email', 'Correo electrónico', $data, $errors, 'email', 'required maxlength="190" autocomplete="email"') ?>
<div class="form-grid"><div><?php inputField('check_in', 'Entrada', $data, $errors, 'date', 'required min="' . date('Y-m-d') . '" max="2099-12-30"') ?></div>
<div><?php inputField('check_out', 'Salida', $data, $errors, 'date', 'required max="2099-12-31"') ?></div></div>
<label for="room_id">Habitación</label><select id="room_id" name="room_id" required <?= isset($errors['room_id']) ? 'aria-invalid="true" aria-describedby="room_id-error"' : '' ?>>
<option value="">Elegí una habitación</option>
<?php foreach ($rooms as $room): ?><option value="<?= e($room['id']) ?>" data-rate="<?= e($room['nightly_rate']) ?>" <?= (string) $room['id'] === ($data['room_id'] ?? '') ? 'selected' : '' ?>><?= e($room['code'] . ' · ' . $room['name'] . ' · Hasta ' . $room['capacity'] . ' personas') ?></option><?php endforeach ?></select>
<?php if (isset($errors['room_id'])): ?><p id="room_id-error" class="field-error"><?= e($errors['room_id']) ?></p><?php endif ?>
<p id="estimate" class="notice" aria-live="polite">Elegí las fechas y la habitación para calcular un importe orientativo.</p>
<button class="button" type="submit">Enviar solicitud</button>
</form><?php endif ?>
<?php pageEnd(); ?>
