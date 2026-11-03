<?php $emailHistory = reservationEmails()->history((int) $id); ?>
<section class="form-panel" aria-labelledby="emails-title"><h2 id="emails-title">Correos al huésped</h2>
<?php if (!reservationEmails()->enabled()): ?><p class="notice">Gmail no está activado. Los avisos quedan guardados; la reserva conserva su estado.</p><?php endif ?>
<?php if (!$emailHistory): ?><p>Esta reserva no tiene avisos registrados. Las reservas anteriores a la incorporación de correos no generan envíos retroactivos.</p><?php endif ?>
<?php foreach ($emailHistory as $email):
    $uncertain = in_array($email['status'], ['sending','uncertain'], true);
    $label = ['pending'=>'Pendiente de envío','sending'=>'Envío iniciado: revisar resultado','sent'=>'Aceptado por Gmail','failed'=>'No enviado: requiere reintento','uncertain'=>'Entrega sin confirmar','obsolete'=>'Omitido: la reserva cambió'][$email['status']]; ?>
<article><h3><?= $email['kind'] === 'received' ? 'Solicitud recibida' : 'Reserva confirmada' ?></h3>
<p><strong><?= e($label) ?></strong> · <?= e($email['recipient']) ?><br>Intentos: <?= (int) $email['attempts'] ?><?= $email['sent_at'] ? ' · ' . e($email['sent_at']) : '' ?></p>
<?php if (!in_array($email['status'], ['sent','obsolete'], true) && (int) $email['attempts'] < 5 && reservationEmails()->enabled()): ?>
<form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="send_email"><input type="hidden" name="email_id" value="<?= (int) $email['id'] ?>">
<?php if ($uncertain): ?><p>Puede haberse enviado aunque no tengamos confirmación. Revisá la carpeta Enviados de Gmail para evitar un duplicado.</p><label class="check-label"><input type="checkbox" name="acknowledged" value="1" required> Revisé el envío y quiero volver a intentarlo.</label><?php endif ?>
<button class="button secondary"><?= $email['attempts'] ? 'Reintentar correo' : 'Enviar aviso pendiente' ?></button></form>
<?php endif ?></article>
<?php endforeach ?>
<p class="data-note">Gmail puede aceptar un mensaje que termine en Spam o sea rechazado más tarde. Los cambios de fechas, cancelaciones y pagos no generan correos en esta primera versión.</p>
</section>
