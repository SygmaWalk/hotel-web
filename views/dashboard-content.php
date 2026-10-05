<?php
$summary = $dashboard['summary'];
$displayDate = (new DateTimeImmutable($date))->format('d/m/Y');
?>
<div class="dashboard-date"><span class="eyebrow">RESUMEN DE OPERACIONES</span><time datetime="<?= e($date) ?>"><?= e($displayDate) ?></time></div>
<section class="dashboard-panel"><h2>Huéspedes alojados ahora</h2>
<p class="panel-description">Registro actual de llegadas sin salida, independiente de la fecha consultada. Las reservas previstas se mantienen hasta su fecha de salida; una salida anticipada no las cancela.</p>
<?php $occupants = hotel()->occupants(); ?>
<?php if (!$occupants): ?><p>No hay huéspedes con llegada registrada y salida pendiente.</p><?php else: ?><ul class="request-list"><?php foreach ($occupants as $occupant): ?><li><a href="<?= e(url('solicitud.php?id=' . $occupant['id'])) ?>"><?= e($occupant['guest_name']) ?> · Habitación <?= e($occupant['code']) ?></a><span><?= $occupant['check_out'] <= date('Y-m-d') ? 'Salida prevista alcanzada: revisar' : 'Alojado' ?></span></li><?php endforeach ?></ul><?php endif ?>
</section>
<section class="metrics-grid" aria-label="Indicadores del día">
    <a class="metric-card" href="#arrivals"><span class="metric-label">Llegadas previstas</span><strong><?= e($summary['arrivals']) ?></strong><span>Reservas que comienzan este día <span aria-hidden="true">↗</span></span></a>
    <a class="metric-card" href="#departures"><span class="metric-label">Salidas previstas</span><strong><?= e($summary['departures']) ?></strong><span>Reservas que finalizan este día <span aria-hidden="true">↗</span></span></a>
    <a class="metric-card metric-pending" href="#pending"><span class="metric-label">Por responder</span><strong><?= e($summary['pending']) ?></strong><span>Solicitudes pendientes · todas las fechas <span aria-hidden="true">↗</span></span></a>
    <a class="metric-card metric-availability" href="#availability"><span class="metric-label">Libres según reservas</span><strong><?= e($summary['available']) ?><small> / <?= e($summary['active']) ?></small></strong><span>Activas y sin reserva para esa noche <span aria-hidden="true">↗</span></span></a>
</section>
<div class="dashboard-grid">
<section class="dashboard-panel" id="pending" aria-labelledby="pending-title">
    <div class="panel-heading"><div><p class="eyebrow">ATENCIÓN DEL PERSONAL</p><h2 id="pending-title">Solicitudes pendientes</h2></div><a href="<?= e(url('solicitudes.php?status=pending')) ?>">Ver todas <span aria-hidden="true">→</span></a></div>
    <p class="panel-description">Las más antiguas primero. Confirmá la disponibilidad desde el detalle.</p>
    <?php if (!$dashboard['pending']): ?>
    <div class="empty-state"><span class="empty-symbol" aria-hidden="true">✓</span><h3>Todo al día</h3><p>No hay solicitudes pendientes. Los nuevos pedidos aparecerán acá.</p><a href="<?= e(url('solicitudes.php')) ?>">Consultar todas las solicitudes</a></div>
    <?php else: ?>
    <ul class="request-list">
    <?php foreach ($dashboard['pending'] as $request): ?>
        <li><div><a class="request-title" href="<?= e(url('solicitud.php?id=' . $request['id'])) ?>"><?= e($request['guest_name']) ?> <span>#<?= e($request['id']) ?></span></a><p>Habitación <?= e($request['code']) ?> · <?= e((new DateTimeImmutable($request['check_in']))->format('d/m/Y')) ?> al <?= e((new DateTimeImmutable($request['check_out']))->format('d/m/Y')) ?></p></div><span class="badge pending">Pendiente</span></li>
    <?php endforeach ?>
    </ul><p class="list-note">Mostrando <?= count($dashboard['pending']) ?> de <?= e($summary['pending']) ?> solicitudes.</p>
    <?php endif ?>
</section>
<section class="dashboard-panel occupancy-panel" aria-labelledby="week-title">
    <p class="eyebrow">PRÓXIMAS SIETE NOCHES</p><h2 id="week-title">Reservas previstas</h2>
    <p class="panel-description">Habitaciones activas con reserva confirmada. No indica check-in realizado.</p>
    <ul class="week-list">
    <?php foreach ($dashboard['week'] as $day): ?>
        <li><time datetime="<?= e($day['day']) ?>"><?= e((new DateTimeImmutable($day['day']))->format('d/m')) ?></time><meter min="0" max="<?= max(1, $summary['active']) ?>" value="<?= e($day['reserved']) ?>" aria-label="Habitaciones reservadas el <?= e($day['day']) ?>"><?= e($day['reserved']) ?></meter><span><?= e($day['reserved']) ?> / <?= e($summary['active']) ?></span></li>
    <?php endforeach ?>
    </ul><p class="list-note"><?= e($summary['inactive']) ?> habitaciones inactivas excluidas del total.</p>
</section>
</div>
<div class="dashboard-grid equal-columns">
<?php foreach (['arrivals' => 'Llegadas previstas', 'departures' => 'Salidas previstas'] as $kind => $label): ?>
<section class="dashboard-panel" id="<?= e($kind) ?>" aria-labelledby="<?= e($kind) ?>-title">
    <div class="panel-heading"><h2 id="<?= e($kind) ?>-title"><?= e($label) ?></h2><span class="count-pill"><?= e($summary[$kind]) ?></span></div>
    <?php if (!$dashboard[$kind]): ?><p class="empty-inline">No hay <?= $kind === 'arrivals' ? 'llegadas' : 'salidas' ?> previstas para el <?= e($displayDate) ?>.</p>
    <?php else: ?><ul class="request-list">
    <?php foreach ($dashboard[$kind] as $request): ?><li><div><a class="request-title" href="<?= e(url('solicitud.php?id=' . $request['id'])) ?>"><?= e($request['guest_name']) ?></a><p>Habitación <?= e($request['code']) ?> · #<?= e($request['id']) ?><?= $request['active'] ? '' : ' · Habitación inactiva: revisar' ?></p></div><span class="badge confirmed">Confirmada</span></li><?php endforeach ?>
    </ul><?php if ($summary[$kind] > 20): ?><p class="list-note">Primeros 20 movimientos. <a href="<?= e(url('solicitudes.php?status=confirmed')) ?>">Ver todas las reservas confirmadas</a>.</p><?php endif ?><?php endif ?>
</section>
<?php endforeach ?>
</div>
<section class="dashboard-panel" id="availability" aria-labelledby="availability-title">
    <div class="panel-heading"><div><p class="eyebrow">NOCHE DEL <?= e($displayDate) ?></p><h2 id="availability-title">Disponibilidad prevista por habitación</h2></div><span class="occupancy-number"><?= e($summary['percentage']) ?>% <small>reservado</small></span></div>
    <p class="panel-description">La fecha de salida libera la noche. Las solicitudes pendientes no bloquean disponibilidad.</p>
    <?php if (!$dashboard['rooms']): ?><p class="empty-inline">Todavía no hay habitaciones cargadas. Un administrador puede agregarlas al inventario.</p><?php else: ?>
    <ul class="room-status-grid">
    <?php foreach ($dashboard['rooms'] as $room): ?><li><span class="room-code"><?= e($room['code']) ?></span><div><h3><?= e($room['name']) ?></h3><span class="room-state <?= !$room['active'] ? 'inactive' : ($room['reserved'] ? 'reserved' : 'available') ?>"><?= !$room['active'] ? 'Inactiva' . ($room['reserved'] ? ' · Con reserva' : '') : ($room['reserved'] ? 'Reservada' : 'Libre') ?></span></div></li><?php endforeach ?>
    </ul><?php endif ?>
</section>
