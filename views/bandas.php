<?php
// Cada columna representa una noche. El día de salida pertenece al tramo siguiente.
?>
<div class="planning-scroll" role="region" aria-label="Disponibilidad por habitación" tabindex="0">
<table class="planning"><thead><tr><th scope="col">Habitación</th>
<?php foreach ($calendar['days'] as $day): $night = new DateTimeImmutable($day); ?>
<th scope="col"><small><?= ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'][(int)$night->format('w')] ?></small><?= $night->format('d/m') ?></th>
<?php endforeach ?></tr></thead><tbody>
<?php foreach ($calendar['rooms'] as $room): ?>
<tr><th scope="row"><?= e($room['name']) ?><small><?= e($room['code']) ?><?= !$room['active'] ? ' · Inactiva' : '' ?></small></th>
<?php
$segments = [];
foreach ($calendar['days'] as $day) {
    $cell = $calendar['cells'][$room['id']][$day];
    $reservation = $cell['stays'][0] ?? null;
    $actual = $cell['actuals'][0] ?? null;
    $block = $cell['maintenance'][0] ?? null;
    $state = $cell['state'];
    $href = null;
    if ($reservation) {
        $state = 'reserved';
        $label = 'Reserva #' . $reservation['id'];
        $href = 'solicitud.php?id=' . $reservation['id'];
    } elseif ($state === 'occupied' && $actual) {
        $label = 'Estadía #' . $actual['id'];
        $href = 'solicitud.php?id=' . $actual['id'];
    } elseif ($block) {
        $label = 'Mantenimiento';
        $href = 'mantenimiento.php?block_id=' . $block['id'] . '#block-' . $block['id'];
    } else {
        $label = $labels[$state];
    }
    $bookable = $state === 'free' && $day >= $calendar['today'] && $day < bookingLimit();
    $key = [$state, $href, $bookable];
    $last = array_key_last($segments);
    if ($last !== null && $segments[$last]['key'] === $key) {
        $segments[$last]['length']++;
        $segments[$last]['end'] = $day;
        continue;
    }
    if ($bookable) $href = 'reserva-nueva.php?' . http_build_query(['room_id'=>$room['id'], 'check_in'=>$day]);
    $segments[] = ['key'=>$key,'length'=>1,'start'=>$day,'end'=>$day,'label'=>$label,'href'=>$href,'state'=>$state];
}
foreach ($segments as $band):
    $range = substr($formatDate($band['start']),0,5);
    if ($band['start'] !== $band['end']) $range .= ' – ' . substr($formatDate($band['end']),0,5);
?>
<td colspan="<?= $band['length'] ?>"><div class="planning-band <?= e($band['state']) ?>">
<?php if ($band['href']): ?><a href="<?= e(url($band['href'])) ?>"><?= e($band['label']) ?><small><?= e($range) ?></small></a>
<?php else: ?><span><?= e($band['label']) ?><small><?= e($range) ?></small></span><?php endif ?>
</div></td><?php endforeach ?></tr>
<?php endforeach ?></tbody></table></div>
