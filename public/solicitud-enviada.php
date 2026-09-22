<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
if (empty($_SESSION['receipt'])) redirect('reservar.php');
pageStart('Solicitud recibida');
?>
<div class="notice success"><p>Guardamos tu solicitud con referencia <strong>#<?= e($_SESSION['receipt']) ?></strong>.</p><p>Se recibió como pendiente de revisión. Este comprobante no implica una reserva confirmada.</p></div>
<p>El envío repetido del mismo formulario conserva la misma referencia.</p><a class="button" href="<?= e(url('habitaciones.php')) ?>">Volver a habitaciones</a>
<?php pageEnd(); ?>
