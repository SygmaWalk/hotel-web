<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$source = $isPost ? $_POST : $_GET;
$data = [];
foreach (['check_in', 'check_out', 'room_id', 'guest_name', 'email'] as $key) $data[$key] = field($source, $key);
if (!$isPost && !array_key_exists('check_in', $_GET)) $data['check_in'] = date('Y-m-d');
if (!$isPost && !array_key_exists('check_out', $_GET) && validDate($data['check_in'])) {
    $data['check_out'] = (new DateTimeImmutable($data['check_in']))->modify('+1 day')->format('Y-m-d');
}
$errors = validateBookingDates($data['check_in'], $data['check_out']);
$token = field($_POST, 'submission_token');
if ($isPost) {
    if (!isset($_SESSION['staff_submissions'][$token])) fail(403, 'El formulario venció. Abrí Nueva reserva.');
    [$data, $errors] = validateRequest($_POST);
    http_response_code(422);
    if (!$errors) {
        try {
            $id = hotel()->createReservation($data, $token, (int) $user['id']);
            sendReservationEmails($id);
            flash('Reserva #' . $id . ' confirmada. Ya figura en el calendario; abrí su detalle para registrar la llegada cuando corresponda.');
            redirect('calendario.php?' . http_build_query(['from'=>$data['check_in'], 'room_id'=>$data['room_id']]));
        } catch (DomainException $error) { http_response_code(409); $errors['room_id'] = $error->getMessage(); }
    }
} else {
    $token = bin2hex(random_bytes(32));
    $_SESSION['staff_submissions'][$token] = time();
    $_SESSION['staff_submissions'] = array_slice($_SESSION['staff_submissions'], -20, null, true);
    if ($errors) http_response_code(422);
}
$rooms = [];
$datesValid = !validateBookingDates($data['check_in'], $data['check_out']);
if ($datesValid) $rooms = hotel()->availableRooms($data['check_in'], $data['check_out']);
$selectedAvailable = in_array($data['room_id'], array_map(fn($r) => (string) $r['id'], $rooms), true);
$formatDate = fn(string $date): string => (new DateTimeImmutable($date))->format('d/m/Y');
pageStart('Nueva reserva', $user);
?>
<p class="intro">Registrá una reserva de mostrador o por teléfono. Al guardarla quedará confirmada y ocupará las noches elegidas.</p>
<p><a href="<?= e(url('calendario.php')) ?>">← Volver al calendario</a></p>
<?php errorSummary($errors) ?>
<section class="spaced-heading" aria-labelledby="dates-title">
<h2 id="dates-title">1. Consultá las fechas</h2>
<form method="get" action="<?= e(url('reserva-nueva.php')) ?>" class="filter-form calendar-filter" data-booking-dates>
<div><?php inputField('check_in', 'Entrada', $data, $errors, 'date', 'required min="' . date('Y-m-d') . '" max="' . (new DateTimeImmutable(bookingLimit()))->modify('-1 day')->format('Y-m-d') . '"') ?></div>
<div><?php inputField('check_out', 'Salida', $data, $errors, 'date', 'required min="' . date('Y-m-d') . '" max="' . bookingLimit() . '"') ?></div>
<input type="hidden" name="room_id" value="<?= e($data['room_id']) ?>">
<button class="button" type="submit">Consultar disponibilidad</button>
</form>
<p class="data-note">La salida libera esa noche. Máximo un año de anticipación. Para ingresar hoy, la habitación debe tener registrada la salida del huésped anterior.</p>
</section>
<?php if ($datesValid): ?>
<section class="spaced-heading" aria-labelledby="guest-title">
<h2 id="guest-title">2. Completá la reserva</h2>
<?php if (!$rooms): ?><p class="notice">No hay habitaciones disponibles para todo ese intervalo. Probá otras fechas.</p><?php endif ?>
<?php $nights = (int) (new DateTimeImmutable($data['check_in']))->diff(new DateTimeImmutable($data['check_out']))->days; ?>
<p>Del <strong><?= e($formatDate($data['check_in'])) ?></strong> al <strong><?= e($formatDate($data['check_out'])) ?></strong> · <?= $nights ?> <?= $nights === 1 ? 'noche' : 'noches' ?>.</p>
<?php if ($data['room_id'] !== '' && !$selectedAvailable): ?><p class="notice">La habitación elegida no está disponible para estas fechas. Seleccioná otra de la lista.</p><?php endif ?>
<form method="post" action="<?= e(url('reserva-nueva.php')) ?>" class="form-panel" data-reservation-form>
<?php csrfInput() ?><input type="hidden" name="submission_token" value="<?= e($token) ?>">
<input type="hidden" name="check_in" value="<?= e($data['check_in']) ?>"><input type="hidden" name="check_out" value="<?= e($data['check_out']) ?>">
<?php inputField('guest_name', 'Nombre y apellido del huésped', $data, $errors, 'text', 'required maxlength="100" autocomplete="off"') ?>
<?php inputField('email', 'Correo electrónico del huésped', $data, $errors, 'email', 'required maxlength="190" autocomplete="off"') ?>
<label for="room_id">Habitación disponible</label>
<select id="room_id" name="room_id" required <?= isset($errors['room_id']) ? 'aria-invalid="true" aria-describedby="room_id-error"' : '' ?>>
<option value="">Elegí una habitación</option>
<?php foreach ($rooms as $room): ?><option value="<?= (int) $room['id'] ?>" data-rate="<?= e($room['nightly_rate']) ?>" <?= (string) $room['id'] === $data['room_id'] ? 'selected' : '' ?>><?= e($room['code'] . ' · ' . $room['name'] . ' · Hasta ' . $room['capacity'] . ' personas · $ ' . number_format((float) $room['nightly_rate'], 2, ',', '.') . ' por noche') ?></option><?php endforeach ?>
</select>
<?php if (isset($errors['room_id'])): ?><p id="room_id-error" class="field-error"><?= e($errors['room_id']) ?></p><?php endif ?>
<p id="estimate" class="notice" aria-live="polite">El importe se calcula con la tarifa vigente de la habitación al guardar.</p>
<p class="data-note">Guardar confirma la reserva. La llegada del huésped se registra por separado. <?= reservationEmails()->enabled() ? 'Se intentará enviar la confirmación al correo del huésped; podés revisar el resultado en el detalle de la reserva.' : 'El aviso por correo quedará pendiente hasta configurar Gmail.' ?></p>
<p class="notice" data-dates-changed role="status" hidden>Cambiaste las fechas. Volvé a consultar disponibilidad antes de guardar.</p>
<button class="button" type="submit" <?= !$rooms ? 'disabled' : '' ?>>Guardar reserva confirmada</button>
</form>
</section><?php endif; pageEnd(); ?>
