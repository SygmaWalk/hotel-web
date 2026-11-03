<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = field($_POST, 'room_id');
    $reservationId = field($_POST, 'reservation_id');
    if (field($_POST, 'action') !== 'complete' || !positiveId($roomId) || !positiveId($reservationId)) {
        http_response_code(422); $errors['cleaning-list'] = 'Elegí una limpieza pendiente válida.';
    } else {
        try {
            hotel()->completeCleaning((int) $roomId, (int) $reservationId, (int) $user['id']);
            flash('Limpieza registrada. La habitación quedó lista; se conservan sus reservas.');
            redirect('limpieza.php');
        } catch (DomainException $error) { http_response_code(409); $errors['cleaning-list'] = $error->getMessage(); }
    }
}
$rooms = hotel()->cleaningRooms();
$pendingCount = count(array_filter($rooms, fn($r) => $r['reservation_id'] !== null));
pageStart('Limpieza de habitaciones', $user);
?>
<p class="intro">Después de cada salida registrada, la habitación queda pendiente de limpieza. Marcala como lista cuando haya terminado la limpieza y esté preparada para recibir al próximo huésped.</p>
<p class="data-note">Este estado es actual. No cancela reservas ni bloquea fechas futuras; impide registrar una nueva llegada hasta que la habitación esté lista.</p>
<?php errorSummary($errors); ?>
<section class="dashboard-panel spaced-heading" id="cleaning-list" tabindex="-1" aria-labelledby="cleaning-title">
<div class="panel-heading"><h2 id="cleaning-title">Estado actual</h2><span class="count-pill"><?= $pendingCount ?> <?= $pendingCount === 1 ? 'pendiente' : 'pendientes' ?></span></div>
<?php if (!$rooms): ?><p>No hay habitaciones cargadas.</p><?php else: ?>
<?php if (!$pendingCount): ?><p class="notice success">No hay limpiezas pendientes.</p><?php endif ?>
<ul class="cleaning-list">
<?php foreach ($rooms as $room): ?>
<li><div><h3><?= e($room['code'] . ' · ' . $room['name']) ?></h3>
<span class="calendar-state <?= $room['reservation_id'] ? 'cleaning' : ($room['occupied'] ? 'occupied' : 'free') ?>"><?= $room['reservation_id'] ? 'Pendiente de limpieza' : ($room['occupied'] ? 'En uso · sin limpieza pendiente' : 'Lista · sin limpieza pendiente') ?></span>
<?php if (!$room['active']): ?><p class="data-note">Habitación inactiva. Marcarla como lista no la reactiva.</p><?php endif ?>
<?php if ($room['reservation_id']): ?><p>Pendiente desde <?= e($room['pending_at']) ?> · <a href="<?= e(url('solicitud.php?id=' . $room['reservation_id'])) ?>">Ver salida de la reserva #<?= (int) $room['reservation_id'] ?></a></p><?php endif ?>
</div>
<?php if ($room['reservation_id'] && !$room['occupied']): ?><form method="post" action="<?= e(url('limpieza.php')) ?>">
<?php csrfInput(); ?><input type="hidden" name="action" value="complete"><input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>"><input type="hidden" name="reservation_id" value="<?= (int) $room['reservation_id'] ?>">
<button class="button" aria-label="<?= e('Marcar habitación ' . $room['code'] . ' como lista') ?>">Marcar como lista</button>
</form><?php elseif ($room['reservation_id']): ?><p class="notice">Revisá la estadía: hay un huésped sin salida registrada.</p><?php endif ?>
</li><?php endforeach ?></ul><?php endif ?>
</section>
<section class="dashboard-panel spaced-heading" aria-labelledby="cleaning-history-title"><h2 id="cleaning-history-title">Últimas limpiezas registradas</h2>
<?php $recent = hotel()->recentCleanings(); if (!$recent): ?><p>Todavía no se registraron limpiezas terminadas.</p><?php else: ?>
<ul class="request-list"><?php foreach ($recent as $item): ?><li><div><strong>Habitación <?= e($item['code']) ?></strong><p><?= e($item['completed_at']) ?> · <?= e($item['completed_actor']) ?></p></div><a href="<?= e(url('solicitud.php?id=' . $item['reservation_id'])) ?>">Reserva #<?= (int) $item['reservation_id'] ?></a></li><?php endforeach ?></ul><p class="list-note">Hasta 10 registros recientes.</p>
<?php endif ?></section>
<?php pageEnd(); ?>
