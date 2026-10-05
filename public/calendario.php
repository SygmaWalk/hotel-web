<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$from = array_key_exists('from', $_GET) ? field($_GET, 'from') : date('Y-m-d');
$defaultTo = validDate($from) && $from >= '2000-01-01' && $from <= bookingLimit()
    ? min(bookingLimit(), (new DateTimeImmutable($from))->modify('+6 days')->format('Y-m-d')) : '';
$to = array_key_exists('to', $_GET) ? field($_GET, 'to') : $defaultTo;
$roomValue = field($_GET, 'room_id');
$errors = []; $calendar = null; $allRooms = [];
if (isset($_GET['room_id']) && (!is_string($_GET['room_id']) || ($roomValue !== '' && !positiveId($roomValue)))) {
    $errors['room_id'] = 'Elegí una habitación válida.';
}
try {
    if (!$errors) {
        $calendar = hotel()->calendar($from, $to, $roomValue === '' ? null : (int) $roomValue);
        $allRooms = $calendar['allRooms'];
    }
} catch (DomainException $error) { $errors['from'] = $error->getMessage(); }
if ($errors) { http_response_code(422); $allRooms = hotel()->rooms(true); }
$formatDate = fn(string $date): string => (new DateTimeImmutable($date))->format('d/m/Y');
$calendarUrl = fn(string $start, string $end): string => url('calendario.php?' . http_build_query(['from'=>$start, 'to'=>$end, 'room_id'=>$roomValue]));
$labels = ['free'=>'Libre', 'reserved'=>'Reservada', 'expected'=>'Ocupada prevista', 'inactive'=>'Inactiva', 'occupied'=>'Estadía registrada'];
pageStart('Calendario de disponibilidad', $user);
?>
<p class="intro">Consultá las noches de cada habitación hasta el <?= e(bookingLimit()) ?>, un año desde hoy. Podés consultar fechas pasadas.</p>
<form method="get" class="filter-form calendar-filter">
    <div><label for="from">Primera noche</label><input id="from" name="from" type="date" required min="2000-01-01" max="<?= e(bookingLimit()) ?>" value="<?= e($from) ?>" <?= isset($errors['from']) ? 'aria-invalid="true"' : '' ?>></div>
    <div><label for="to">Última noche</label><input id="to" name="to" type="date" required min="2000-01-01" max="<?= e(bookingLimit()) ?>" value="<?= e($to) ?>"></div>
    <div><label for="room_id">Habitación</label><select id="room_id" name="room_id" <?= isset($errors['room_id']) ? 'aria-invalid="true"' : '' ?>><option value="">Todas las habitaciones</option>
        <?php foreach ($allRooms as $room): ?><option value="<?= (int) $room['id'] ?>" <?= $roomValue === (string) $room['id'] ? 'selected' : '' ?>><?= e($room['code'] . ' · ' . $room['name'] . ($room['active'] ? '' : ' · Inactiva')) ?></option><?php endforeach ?>
    </select></div>
    <button class="button" type="submit">Consultar calendario</button>
    <a href="<?= e(url('calendario.php')) ?>">Hoy y próximas noches</a>
</form>
<?php errorSummary($errors); ?>
<?php if ($calendar): ?>
<section class="calendar-section" aria-labelledby="calendar-range">
    <div class="panel-heading calendar-heading">
        <div><p class="eyebrow">NOCHES CONSULTADAS</p><h2 id="calendar-range"><?= e($formatDate($from)) ?> al <?= e($formatDate($to)) ?></h2><p class="panel-description"><?= count($calendar['rooms']) ?> <?= count($calendar['rooms']) === 1 ? 'habitación' : 'habitaciones' ?> · <?= count($calendar['days']) ?> <?= count($calendar['days']) === 1 ? 'noche' : 'noches' ?> · Máximo 31 por consulta</p></div>
        <nav class="calendar-pagination" aria-label="Cambiar rango del calendario">
        <?php $length = count($calendar['days']);
        $previousStart = (new DateTimeImmutable($from))->modify('-' . $length . ' days')->format('Y-m-d');
        $previousEnd = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
        $nextStart = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
        $nextEnd = (new DateTimeImmutable($to))->modify('+' . $length . ' days')->format('Y-m-d'); ?>
        <?php if ($previousStart >= '2000-01-01'): ?><a href="<?= e($calendarUrl($previousStart, $previousEnd)) ?>">← Anteriores</a><?php endif ?>
        <?php if ($nextStart <= bookingLimit()): ?><a href="<?= e($calendarUrl($nextStart, min($nextEnd, bookingLimit()))) ?>">Siguientes →</a><?php endif ?>
        </nav>
    </div>
    <ul class="calendar-legend" aria-label="Estados del calendario">
        <?php foreach ($labels as $state => $label): ?><li class="calendar-state <?= e($state) ?>"><?= e($label) ?></li><?php endforeach ?>
    </ul>
    <p class="panel-description" id="calendar-help">Las reservas confirmadas bloquean desde la entrada hasta la noche anterior a la salida. Pendientes y rechazadas no bloquean. «Ocupada prevista» identifica noches confirmadas de hoy o anteriores; no acredita un check-in real. Las noches futuras figuran como reservadas. «Estadía registrada» corresponde a movimientos reales. Una salida anticipada no cancela las noches previstas de la reserva.</p>
    <?php if (!$calendar['rooms']): ?>
        <p class="notice">Todavía no hay habitaciones para mostrar.</p>
    <?php else: ?>
        <p class="list-note">Desplazá la tabla horizontalmente para recorrer las fechas. Con teclado, enfocá la tabla y usá las flechas.</p>
        <div class="table-wrap calendar-scroll" tabindex="0" role="region" aria-label="Disponibilidad por habitación y noche" aria-describedby="calendar-help">
            <table class="calendar-table">
                <caption>Noches del <?= e($formatDate($from)) ?> al <?= e($formatDate($to)) ?>. Las entradas y salidas son previstas.</caption>
                <thead><tr><th scope="col">Habitación</th><?php foreach ($calendar['days'] as $day): ?><th scope="col" <?= $day === $calendar['today'] ? 'aria-current="date"' : '' ?>><time datetime="<?= e($day) ?>"><?= e((new DateTimeImmutable($day))->format('d/m')) ?></time><span class="calendar-weekday"><?= ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'][(int) (new DateTimeImmutable($day))->format('w')] ?><?= $day === $calendar['today'] ? ' · Hoy' : '' ?></span></th><?php endforeach ?></tr></thead>
                <tbody><?php foreach ($calendar['rooms'] as $room): ?><tr>
                    <th scope="row"><strong><?= e($room['code']) ?></strong><span class="calendar-room-name"><?= e($room['name']) ?></span><?php if (!$room['active']): ?><span class="calendar-inactive-note">Inactiva · no disponible</span><?php endif ?></th>
                    <?php foreach ($calendar['days'] as $day): $cell = $calendar['cells'][$room['id']][$day]; ?>
                    <td class="calendar-cell <?= e($cell['state']) ?>">
                        <span class="calendar-state <?= e($cell['state']) ?>"><?= e($labels[$cell['state']]) ?></span>
                        <?php foreach ($cell['actuals'] as $actual): ?><a class="calendar-movement" href="<?= e(url('solicitud.php?id=' . $actual['id'])) ?>">Registro real #<?= (int) $actual['id'] ?> · Entrada <?= e($actual['checked_in_at']) ?><?= $actual['checked_out_at'] ? ' · Salida ' . e($actual['checked_out_at']) : ' · Sin salida registrada' ?></a><?php endforeach ?>
                        <?php foreach ($cell['stays'] as $stay): ?><a class="calendar-stay" href="<?= e(url('solicitud.php?id=' . $stay['id'])) ?>"><strong>#<?= (int) $stay['id'] ?></strong> <?= e($stay['guest_name']) ?></a><?php endforeach ?>
                        <?php foreach (['departures'=>'Salida', 'arrivals'=>'Entrada'] as $movement => $label): foreach ($cell[$movement] as $reservation): ?><a class="calendar-movement" href="<?= e(url('solicitud.php?id=' . $reservation['id'])) ?>"><?= e($label) ?> #<?= (int) $reservation['id'] ?></a><?php endforeach; endforeach ?>
                    </td><?php endforeach ?>
                </tr><?php endforeach ?></tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?php endif; pageEnd(); ?>
