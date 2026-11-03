<?php
require_once dirname(__DIR__).'/src/bootstrap.php'; $user=requireUser();
$date=array_key_exists('date',$_GET)?field($_GET,'date'):date('Y-m-d');
try { $report=hotel()->dailyPayments($date); } catch (DomainException $error) { fail(422,$error->getMessage()); }
pageStart('Cobros del día',$user);
?>
<p>Consultá los movimientos por su fecha declarada. Los importes reflejan las correcciones actuales: los registros anulados se muestran, pero no suman.</p>
<form method="get" class="filter-form"><label for="date">Fecha del movimiento</label><input id="date" name="date" type="date" min="2000-01-01" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($date) ?>"><button class="button">Consultar cobros</button></form>
<section class="form-panel"><h2>Resumen del <?= e($date) ?></h2><dl class="detail-panel">
<dt>Cobros</dt><dd><?= e(Hotel::moneyLabel($report['charged'])) ?></dd><dt>Devoluciones</dt><dd><?= e(Hotel::moneyLabel($report['refunded'])) ?></dd><dt>Neto registrado</dt><dd><strong><?= e(Hotel::moneyLabel($report['net'])) ?></strong></dd></dl>
<p class="data-note">Incluye señas y pagos. Es un resumen de registros manuales; no representa la ocupación, facturación ni un arqueo de caja.</p></section>
<h2 class="spaced-heading">Por medio</h2><div class="table-wrap"><table><thead><tr><th>Medio</th><th>Cobros</th><th>Devoluciones</th><th>Neto</th></tr></thead><tbody>
<?php foreach ($report['methods'] as $method=>$totals): ?><tr><th><?= e(Hotel::paymentMethods()[$method]) ?></th><td><?= e(Hotel::moneyLabel($totals['charged'])) ?></td><td><?= e(Hotel::moneyLabel($totals['refunded'])) ?></td><td><?= e(Hotel::moneyLabel($totals['net'])) ?></td></tr><?php endforeach ?>
</tbody></table></div><h2 class="spaced-heading">Movimientos del día</h2>
<?php if (!$report['entries']): ?><p>No hay movimientos para esta fecha.</p><?php endif ?>
<?php foreach ($report['entries'] as $entry): ?><article class="form-panel"><h3><a href="<?= e(url('reserva-pagos.php?id='.$entry['reservation_id'].'#payment-'.$entry['id'])) ?>">Reserva #<?= e($entry['reservation_id']) ?> · Movimiento #<?= e($entry['id']) ?></a></h3>
<p><?= e($entry['guest_name']) ?> · Habitación actual <?= e($entry['code']) ?></p><p><strong><?= $entry['voided_at']?'Anulado':'Vigente' ?></strong> · <?= e(Hotel::paymentKinds()[$entry['kind']]) ?> · <?= e(Hotel::moneyLabel(Hotel::moneyCents($entry['amount']))) ?> · <?= e(Hotel::paymentMethods()[$entry['method']]) ?></p>
<p class="cancellation-reason"><?= e($entry['note']) ?></p><p class="data-note">Registrado por <?= e($entry['recorded_actor']) ?> · <?= e($entry['recorded_at']) ?></p>
<?php if ($entry['voided_at']): ?><p class="cancellation-reason">Anulación: <?= e($entry['void_reason']) ?> · <?= e($entry['voided_actor']) ?> · <?= e($entry['voided_at']) ?></p><?php endif ?></article><?php endforeach; pageEnd(); ?>
