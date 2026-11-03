<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
if (empty($_SESSION['receipt'])) redirect('reservar.php');
pageStart('Solicitud recibida');
?>
<div class="notice success"><p>Guardamos tu solicitud con referencia <strong>#<?= e($_SESSION['receipt']) ?></strong>.</p><p>Se recibió como pendiente de revisión. Este comprobante no implica una reserva confirmada.</p></div>
<p>El envío repetido del mismo formulario conserva la misma referencia.</p><a class="button" href="<?= e(url('habitaciones.php')) ?>">Volver a habitaciones</a>
<?php $mailRows = reservationEmails()->history((int) $_SESSION['receipt']);
$receivedMail = array_values(array_filter($mailRows, fn($row) => $row['kind'] === 'received'));
$mailSent = $receivedMail && $receivedMail[0]['status'] === 'sent'; ?>
<p><?= $mailSent ? 'El aviso fue aceptado por Gmail. Revisá tu bandeja de entrada y Spam; la reserva sigue pendiente hasta su confirmación.' : 'No tenemos confirmación de envío del aviso por correo. Tu solicitud sí quedó guardada; conservá esta referencia.' ?></p>
<?php pageEnd(); ?>
