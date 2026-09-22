<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$rooms = hotel()->rooms();
pageStart('Elegí tu próxima estadía');
?>
<p class="intro">Habitaciones de ejemplo. Tarifas en pesos argentinos por noche. La disponibilidad se revisa al confirmar cada solicitud.</p>
<?php if (!$rooms): ?><p class="notice">Por el momento no hay habitaciones disponibles para solicitar.</p><?php endif ?>
<div class="room-grid">
<?php foreach ($rooms as $room): ?>
<article class="room-card"><p class="eyebrow">HABITACIÓN <?= e($room['code']) ?></p><h2><?= e($room['name']) ?></h2>
<p>Hasta <?= e($room['capacity']) ?> personas</p><p class="rate">$ <?= e(number_format((float) $room['nightly_rate'], 2, ',', '.')) ?> <small>/ noche</small></p>
<a class="button" href="<?= e(url('reservar.php?room_id=' . $room['id'])) ?>">Solicitar estadía</a></article>
<?php endforeach ?>
</div>
<?php pageEnd(); ?>
