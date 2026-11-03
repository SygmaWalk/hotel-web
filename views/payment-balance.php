<section class="form-panel" aria-labelledby="balance-title"><h2 id="balance-title">Saldo de la reserva</h2>
<dl class="detail-panel"><dt>Total de la estadía<?= $reservation['status']==='cancelled'?' original':'' ?></dt><dd><?= e(Hotel::moneyLabel($balance['total'])) ?></dd>
<dt>Cobros registrados</dt><dd><?= e(Hotel::moneyLabel($balance['charged'])) ?></dd><dt>Devoluciones registradas</dt><dd><?= e(Hotel::moneyLabel($balance['refunded'])) ?></dd>
<dt>Pagos netos</dt><dd><?= e(Hotel::moneyLabel($balance['net'])) ?></dd><dt>Saldo pendiente</dt><dd><strong><?= e(Hotel::moneyLabel($balance['due'])) ?></strong></dd>
<dt>A favor del huésped</dt><dd><?= e(Hotel::moneyLabel($balance['credit'])) ?></dd></dl>
<?php if ($reservation['status']==='cancelled'): ?><p class="notice">La reserva está cancelada. Los pagos no se devolvieron automáticamente. El importe neto queda a favor del huésped; este módulo no aplica penalidades de cancelación.</p>
<?php elseif ($balance['credit']>0): ?><p class="notice">Hay un importe a favor del huésped. Revisá si corresponde registrar una devolución ya realizada.</p><?php endif ?>
</section>
