<?php
$summary = $dashboard['summary'];
$formatDate = fn(string $day): string => (new DateTimeImmutable($day))->format('d/m/Y');
$displayDate = $formatDate($date);
$oldest = $dashboard['pending'][0] ?? null;
$firstCleaning = $cleaningPending[0] ?? null;
?>
<section class="today-attention" aria-labelledby="attention-title">
    <div class="today-section-head">
        <h2 id="attention-title">Requiere atención</h2>
        <span>Estado actual del hotel</span>
    </div>
    <div class="today-tasks">
        <section class="today-task" id="pending" aria-labelledby="pending-title">
            <div class="panel-heading"><h3 id="pending-title">Solicitudes pendientes</h3><span class="count-pill"><?= (int) $summary['pending'] ?></span></div>
            <?php if ($oldest): ?>
                <p class="today-task-label">Solicitud más antigua</p>
                <p class="today-task-value"><?= e($oldest['guest_name']) ?> · #<?= (int) $oldest['id'] ?></p>
                <p class="today-task-note">Habitación <?= e($oldest['code']) ?> · <?= e($formatDate($oldest['check_in'])) ?> al <?= e($formatDate($oldest['check_out'])) ?></p>
                <a class="today-task-link" href="<?= e(url('solicitud.php?id=' . $oldest['id'])) ?>">Revisar solicitud #<?= (int) $oldest['id'] ?> <span aria-hidden="true">→</span></a>
            <?php else: ?>
                <p class="today-clear">No hay solicitudes pendientes.</p>
            <?php endif ?>
            <a class="today-all" href="<?= e(url('solicitudes.php?status=pending')) ?>">Ver solicitudes pendientes</a>
        </section>
        <section class="today-task" aria-labelledby="cleaning-now-title">
            <div class="panel-heading"><h3 id="cleaning-now-title">Limpieza ahora</h3><span class="count-pill"><?= count($cleaningPending) ?></span></div>
            <?php if ($firstCleaning): ?>
                <p class="today-task-label">Pendiente de limpieza</p>
                <p class="today-task-value">Habitación <?= e($firstCleaning['code']) ?> · <?= e($firstCleaning['name']) ?></p>
                <p class="today-task-note"><?= $firstCleaning['next_check_in'] ? 'Llegada prevista el ' . e($formatDate($firstCleaning['next_check_in'])) . '.' : 'Preparar antes de recibir al próximo huésped.' ?><?= $firstCleaning['active'] ? '' : ' Habitación inactiva.' ?></p>
                <a class="today-task-link" href="<?= e(url('limpieza.php#limpieza-habitacion-' . $firstCleaning['id'])) ?>">Revisar habitación <?= e($firstCleaning['code']) ?> <span aria-hidden="true">→</span></a>
            <?php else: ?>
                <p class="today-clear">No hay limpiezas pendientes.</p>
            <?php endif ?>
            <a class="today-all" href="<?= e(url('limpieza.php#cleaning-list')) ?>">Ver toda la limpieza</a>
        </section>
    </div>
    <?php if (count($dashboard['pending']) > 1 || count($cleaningPending) > 1): ?>
    <details class="today-more" id="other-pending-tasks">
        <summary>Otras tareas pendientes</summary>
        <ul class="request-list">
        <?php foreach (array_slice($dashboard['pending'], 1) as $request): ?>
            <li><a href="<?= e(url('solicitud.php?id=' . $request['id'])) ?>">Solicitud #<?= (int) $request['id'] ?> · <?= e($request['guest_name']) ?> · Habitación <?= e($request['code']) ?></a></li>
        <?php endforeach ?>
        <?php foreach (array_slice($cleaningPending, 1) as $room): ?>
            <li><a href="<?= e(url('limpieza.php#limpieza-habitacion-' . $room['id'])) ?>">Limpiar habitación <?= e($room['code']) ?> · <?= e($room['name']) ?><?= $room['next_check_in'] ? ' · Llegada prevista el ' . e($formatDate($room['next_check_in'])) : '' ?><?= $room['active'] ? '' : ' · Inactiva' ?></a></li>
        <?php endforeach ?>
        </ul>
        <?php if ($summary['pending'] > count($dashboard['pending'])): ?><p class="list-note">Se muestran las <?= count($dashboard['pending']) ?> solicitudes más antiguas. <a href="<?= e(url('solicitudes.php?status=pending')) ?>">Ver todas (<?= (int) $summary['pending'] ?>)</a></p><?php endif ?>
    </details>
    <?php endif ?>
    <details class="today-more" id="current-occupants">
        <summary>Huéspedes alojados ahora · <?= count($occupants) ?></summary>
        <?php if (!$occupants): ?><p>No hay huéspedes con llegada registrada y salida pendiente.</p>
        <?php else: ?><ul class="request-list">
            <?php foreach ($occupants as $occupant): ?><li><a href="<?= e(url('solicitud.php?id=' . $occupant['id'])) ?>"><?= e($occupant['guest_name']) ?> · Habitación <?= e($occupant['code']) ?></a><span><?= $occupant['check_out'] <= date('Y-m-d') ? 'Salida prevista alcanzada: revisar' : 'Alojado' ?></span></li><?php endforeach ?>
        </ul><?php endif ?>
    </details>
</section>
<div class="today-section-head today-plan">
    <h2>Movimientos y disponibilidad</h2>
    <time datetime="<?= e($date) ?>">Fecha consultada · <?= e($displayDate) ?></time>
</div>
<section class="metrics-grid" aria-label="Indicadores del día">
    <a class="metric-card" href="#arrivals"><span class="metric-label">Llegadas previstas</span><strong><?= e($summary['arrivals']) ?></strong><span>Reservas que comienzan este día <span aria-hidden="true">↗</span></span></a>
    <a class="metric-card" href="#departures"><span class="metric-label">Salidas previstas</span><strong><?= e($summary['departures']) ?></strong><span>Reservas que finalizan este día <span aria-hidden="true">↗</span></span></a>
    <a class="metric-card metric-availability" href="#availability"><span class="metric-label">Disponibles para reservar</span><strong><?= e($summary['available']) ?><small> / <?= e($summary['active']) ?></small></strong><span>Activas, sin reserva ni mantenimiento esa noche <span aria-hidden="true">↗</span></span></a>
</section>
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
<div class="dashboard-grid today-forecast">
<section class="dashboard-panel" id="availability" aria-labelledby="availability-title">
    <div class="panel-heading"><div><p class="eyebrow">NOCHE DEL <?= e($displayDate) ?></p><h2 id="availability-title">Disponibilidad prevista por habitación</h2></div><span class="occupancy-number"><?= e($summary['percentage']) ?>% <small>reservado</small></span></div>
    <p class="panel-description">La fecha de salida libera la noche. Las solicitudes pendientes no bloquean disponibilidad.</p>
    <p class="panel-description"><strong><?= e($summary['maintenance']) ?></strong> habitaciones activas en mantenimiento. <a href="<?= e(url('mantenimiento.php')) ?>">Gestionar mantenimiento</a>.</p>
    <?php if (!$dashboard['rooms']): ?><p class="empty-inline">Todavía no hay habitaciones cargadas. Un administrador puede agregarlas al inventario.</p><?php else: ?>
    <ul class="room-status-grid">
    <?php foreach ($dashboard['rooms'] as $room): ?><li><span class="room-code"><?= e($room['code']) ?></span><div><h3><?= e($room['name']) ?></h3><span class="room-state <?= !$room['active'] ? 'inactive' : ($room['maintenance'] ? 'maintenance' : ($room['reserved'] ? 'reserved' : 'available')) ?>"><?= !$room['active'] ? 'Inactiva' . ($room['reserved'] ? ' · Con reserva' : '') : ($room['maintenance'] ? 'En mantenimiento' : ($room['reserved'] ? 'Reservada' : 'Libre')) ?></span></div></li><?php endforeach ?>
    </ul><?php endif ?>
</section>
<section class="dashboard-panel occupancy-panel" aria-labelledby="week-title">
    <p class="eyebrow">PRÓXIMAS SIETE NOCHES</p><h2 id="week-title">Reservas previstas</h2>
    <p class="panel-description">Habitaciones activas con reserva confirmada. No indica check-in realizado.</p>
    <ul class="week-list">
    <?php foreach ($dashboard['week'] as $day): ?>
        <li><time datetime="<?= e($day['day']) ?>"><?= e((new DateTimeImmutable($day['day']))->format('d/m')) ?></time><meter min="0" max="<?= max(1, $summary['active']) ?>" value="<?= e($day['reserved']) ?>" aria-label="Habitaciones reservadas el <?= e($day['day']) ?>"><?= e($day['reserved']) ?></meter><span><?= e($day['reserved']) ?> / <?= e($summary['active']) ?></span></li>
    <?php endforeach ?>
    </ul><p class="list-note"><?= e($summary['inactive']) ?> habitaciones inactivas excluidas del total.</p>
    <?php if ($date <= bookingLimit()): ?><a class="today-all" href="<?= e(url('calendario.php?from=' . $date)) ?>">Abrir estas noches en el calendario</a><?php endif ?>
</section>
</div>
