<?php
require_once dirname(__DIR__).'/src/bootstrap.php';
$user=requireUser(); $id=field($_GET,'id');
if (!positiveId($id) || !($reservation=hotel()->request((int)$id))) fail(404,'La reserva no existe.');
$errors=[]; $data=['kind'=>'deposit','amount'=>'','method'=>'cash','paid_on'=>date('Y-m-d'),'note'=>''];
$_SESSION['payment_forms'] ??= [];
foreach ($_SESSION['payment_forms'] as $key=>$form) if ($form['expires']<time()) unset($_SESSION['payment_forms'][$key]);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    foreach ($data as $key=>$value) $data[$key]=field($_POST,$key);
    try {
        if (field($_POST,'action')==='record') {
            $formToken=field($_POST,'payment_token'); $form=$_SESSION['payment_forms'][$formToken] ?? null;
            if (!$form || $form['id']!==(int)$id) fail(403,'El formulario venció o no pertenece a esta reserva. Volvé a abrir Pagos.');
            hotel()->recordPayment((int)$id,$data,$formToken,field($_POST,'acknowledged')==='1',(int)$user['id']);
            flash('Movimiento registrado. El saldo y el resumen de cobros fueron actualizados.');
        } elseif (field($_POST,'action')==='void') {
            if (!positiveId(field($_POST,'payment_id'))) throw new DomainException('Elegí un movimiento válido.');
            hotel()->voidPayment((int)$id,(int)field($_POST,'payment_id'),field($_POST,'void_reason'),field($_POST,'acknowledged')==='1',(int)$user['id']);
            flash('Registro anulado con su motivo. El historial original se conserva.');
        } else throw new DomainException('Operación no válida.');
        redirect('reserva-pagos.php?id='.$id);
    } catch (DomainException $error) { http_response_code(409); $errors['payments']=$error->getMessage(); }
}
if (empty($formToken) || !isset($_SESSION['payment_forms'][$formToken])) {
    while (count($_SESSION['payment_forms'])>=40) array_shift($_SESSION['payment_forms']);
    $formToken=bin2hex(random_bytes(32)); $_SESSION['payment_forms'][$formToken]=['id'=>(int)$id,'expires'=>time()+7200];
}
$balance=hotel()->paymentBalance((int)$id); $entries=hotel()->paymentHistory((int)$id);
pageStart('Pagos de la reserva #'.$id,$user); errorSummary($errors);
?>
<p><a href="<?= e(url('solicitud.php?id='.$id)) ?>">← Volver a la reserva</a> · <a href="<?= e(url('cobros.php')) ?>">Cobros del día</a></p>
<p><?= e($reservation['guest_name']) ?> · Habitación <?= e($reservation['code']) ?> · <?= e(statusLabel($reservation['status'])) ?></p>
<p class="data-note">Registro manual en pesos argentinos. Guardar no realiza un cobro ni envía dinero; registrá únicamente movimientos ya realizados.</p>
<?php require dirname(__DIR__).'/views/payment-balance.php'; ?>
<?php if (in_array($reservation['status'],['confirmed','cancelled'],true)): ?>
<section class="form-panel" id="payments"><h2>Registrar movimiento</h2>
<form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="record"><input type="hidden" name="payment_token" value="<?= e($formToken) ?>">
<label for="kind">Tipo de movimiento</label><select name="kind" id="kind" required>
<?php foreach (Hotel::paymentKinds() as $key=>$label): if ($reservation['status']==='cancelled' && $key!=='refund') continue; ?><option value="<?= e($key) ?>" <?= $data['kind']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select>
<label for="amount">Importe en ARS</label><input id="amount" name="amount" type="text" inputmode="decimal" maxlength="13" required value="<?= e($data['amount']) ?>" aria-describedby="amount-help">
<p class="data-note" id="amount-help">Sin separadores de miles. Ejemplo: 25000,50. El cobro no puede superar el saldo pendiente; la devolución no puede superar los pagos netos.</p>
<label for="method">Medio</label><select name="method" id="method" required><?php foreach (Hotel::paymentMethods() as $key=>$label): ?><option value="<?= e($key) ?>" <?= $data['method']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select>
<label for="paid_on">Fecha del movimiento</label><input id="paid_on" name="paid_on" type="date" min="2000-01-01" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($data['paid_on']) ?>">
<label for="note">Concepto o referencia</label><textarea id="note" name="note" rows="3" maxlength="500" required><?= e($data['note']) ?></textarea>
<p class="data-note">Por ejemplo: seña recibida o devolución acordada. No ingreses números de tarjeta, códigos de seguridad ni claves.</p>
<label class="check-label"><input type="checkbox" name="acknowledged" value="1" required> Confirmo el tipo, importe, medio y fecha, y que el movimiento ya se realizó.</label>
<button class="button">Guardar movimiento</button></form></section>
<?php else: ?><p class="notice">Para registrar cobros, primero confirmá la reserva.</p><?php endif ?>
<section class="spaced-heading"><h2>Historial de movimientos</h2>
<p class="data-note">Para corregir un dato, anulá el registro incorrecto con un motivo y cargá el correcto. Anular un registro no devuelve dinero; una devolución real se registra como otro movimiento.</p>
<?php if (!$entries): ?><p>Todavía no hay movimientos.</p><?php endif ?>
<?php foreach ($entries as $entry): ?>
<article class="form-panel" id="payment-<?= e($entry['id']) ?>"><h3>#<?= e($entry['id']) ?> · <?= e(Hotel::paymentKinds()[$entry['kind']]) ?> · <?= e(Hotel::moneyLabel(Hotel::moneyCents($entry['amount']))) ?></h3>
<p><strong><?= $entry['voided_at']?'Anulado':'Vigente' ?></strong> · <?= e(Hotel::paymentMethods()[$entry['method']]) ?> · Fecha del movimiento: <?= e($entry['paid_on']) ?></p>
<p class="cancellation-reason"><?= e($entry['note']) ?></p><p class="data-note">Registrado por <?= e($entry['recorded_actor']) ?> · <?= e($entry['recorded_at']) ?></p>
<?php if ($entry['voided_at']): ?><p class="cancellation-reason">Motivo de anulación: <?= e($entry['void_reason']) ?></p><p class="data-note">Anulado por <?= e($entry['voided_actor']) ?> · <?= e($entry['voided_at']) ?></p>
<?php else: ?><details><summary>Anular registro #<?= e($entry['id']) ?></summary><form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="void"><input type="hidden" name="payment_id" value="<?= e($entry['id']) ?>">
<label for="void-<?= e($entry['id']) ?>">Motivo de anulación del registro #<?= e($entry['id']) ?></label><textarea name="void_reason" id="void-<?= e($entry['id']) ?>" rows="2" maxlength="500" required></textarea>
<label class="check-label"><input type="checkbox" name="acknowledged" value="1" required> Confirmo que el registro #<?= e($entry['id']) ?> es incorrecto y debe dejar de contar en el saldo.</label><button class="button danger">Confirmar anulación #<?= e($entry['id']) ?></button></form></details><?php endif ?></article>
<?php endforeach ?></section><?php pageEnd(); ?>
