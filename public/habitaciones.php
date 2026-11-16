<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$rooms = hotel()->rooms();
$illustrations = ['101'=>'aurora-doble.jpg', '102'=>'aurora-standard.jpg', '201'=>'aurora-familiar.jpg'];
pageStart('Nuestras habitaciones');
?>
<p class="intro">Compará la capacidad y el precio por noche. Elegí una habitación para consultar el importe de tu estadía según las fechas.</p>
<?php if (!$rooms): ?><p class="notice">Por el momento no hay habitaciones disponibles para solicitar.</p><?php endif ?>
<div class="room-grid guest-catalog">
<?php foreach ($rooms as $room): ?>
<article class="room-card"><?php roomGallery((int) $room['id'], $room['name'], $illustrations[$room['code']] ?? 'hotel-interior.jpg'); ?>
<p class="eyebrow">HABITACIÓN <?= e($room['code']) ?></p><h2><?= e($room['name']) ?></h2>
<p>Hasta <?= (int)$room['capacity'] ?> <?= (int)$room['capacity'] === 1 ? 'persona' : 'personas' ?></p>
<p class="rate"><small>Precio por noche</small><strong>ARS <?= e(number_format((float)$room['nightly_rate'], 2, ',', '.')) ?></strong></p>
<a class="button guest-action" aria-label="<?= e('Elegir fechas para ' . $room['name']) ?>" href="<?= e(url('reservar.php?room_id=' . $room['id'])) ?>">Elegir fechas <span aria-hidden="true">→</span></a></article>
<?php endforeach ?>
</div>
<?php pageEnd(); ?>
