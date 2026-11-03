<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser(); $errors = []; $review = null; $proposalToken = '';
$data = ['room_id'=>field($_GET,'room_id'),'start_date'=>date('Y-m-d'),'end_date'=>(new DateTimeImmutable('tomorrow'))->format('Y-m-d'),'reason'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = field($_POST,'action');
    if ($action === 'create') {
        $proposalToken = field($_POST,'proposal_token');
        $proposal = $_SESSION['maintenance_proposals'][$proposalToken] ?? null;
        if (!$proposal || $proposal['expires'] < time()) fail(403,'La revisión venció o no es válida. Volvé a revisar el bloqueo.');
        $data = $proposal['data'];
    } elseif ($action === 'review') {
        foreach (array_keys($data) as $key) $data[$key] = field($_POST,$key);
    }
    try {
        if ($action === 'release') {
            $blockId = field($_POST,'block_id');
            if (!positiveId($blockId)) throw new DomainException('Elegí un bloqueo válido.');
            hotel()->releaseMaintenance((int)$blockId,field($_POST,'release_reason'),field($_POST,'acknowledged')==='1',(int)$user['id']);
            flash('Bloqueo levantado. Se liberaron sus noches pendientes y se conservó el historial.');
            redirect('mantenimiento.php#block-'.$blockId);
        } elseif ($action === 'create') {
            $blockId = hotel()->createMaintenance($data,$proposalToken,field($_POST,'acknowledged')==='1',(int)$user['id']);
            flash('Bloqueo #'.$blockId.' registrado. Consultá su estado en la lista.');
            redirect('mantenimiento.php#block-'.$blockId);
        } elseif ($action !== 'review') { throw new DomainException('Acción inválida.'); }
    } catch (DomainException $error) { http_response_code(409); $errors['review'] = $error->getMessage(); }
    if (in_array($action,['review','create'],true)) {
        try {
            $review = hotel()->inspectMaintenance($data);
            if (!$review['reservations'] && !$review['occupants'] && !$review['blocks']) {
                $proposalToken = bin2hex(random_bytes(32));
                $_SESSION['maintenance_proposals'][$proposalToken] = ['data'=>$review['data'],'expires'=>time()+1200];
                $_SESSION['maintenance_proposals'] = array_slice($_SESSION['maintenance_proposals'],-20,null,true);
            }
        } catch (DomainException $error) { http_response_code(422); $errors['review'] = $error->getMessage(); }
    }
}
$rooms = hotel()->rooms(true); $blocks = hotel()->maintenanceList(); $history = hotel()->maintenanceList(true);
$focusedId = field($_GET,'block_id');
if (array_key_exists('block_id',$_GET)) {
    if (!positiveId($focusedId) || !($focused = hotel()->maintenanceList(true,(int)$focusedId))) fail(404,'El bloqueo no existe.');
    if (!in_array((int)$focusedId,array_map('intval',array_column([...$blocks,...$history],'id')),true)) $history[] = $focused[0];
}
pageStart('Mantenimiento', $user);
?>
<p class="intro">Bloqueá una habitación por fechas para realizar reparaciones. Primero revisá las reservas afectadas; guardar vuelve a comprobar los conflictos.</p>
<p><a href="<?= e(url('calendario.php')) ?>">Ver calendario</a></p>
<div id="review"><?php errorSummary($errors) ?></div>
<?php if ($review): ?>
<section class="form-panel" aria-labelledby="review-title"><h2 id="review-title">Revisión del bloqueo</h2>
<p>Habitación <strong><?= e($review['room']['code']) ?></strong> · Desde <?= e($data['start_date']) ?> hasta <?= e($data['end_date']) ?>, sin incluir esa última noche.</p>
<p class="cancellation-reason">Motivo: <?= e($data['reason']) ?></p>
<?php if ($review['reservations']): ?><h3>Reservas afectadas</h3><p>Reprogramá o cancelá las reservas que correspondan y volvé a revisar. No se modifican automáticamente.</p><ul>
<?php foreach ($review['reservations'] as $reservation): ?><li><a href="<?= e(url('solicitud.php?id='.$reservation['id'])) ?>">Reserva #<?= e($reservation['id']) ?> · <?= e($reservation['guest_name']) ?></a> · <?= e($reservation['check_in']) ?> a <?= e($reservation['check_out']) ?></li><?php endforeach ?></ul><?php endif ?>
<?php if ($review['occupants']): ?><h3>Huéspedes sin salida registrada</h3><p>Antes de bloquear desde hoy, registrá la salida real del huésped.</p><ul><?php foreach ($review['occupants'] as $occupant): ?><li><a href="<?= e(url('solicitud.php?id='.$occupant['id'])) ?>">Reserva #<?= e($occupant['id']) ?> · <?= e($occupant['guest_name']) ?></a></li><?php endforeach ?></ul><?php endif ?>
<?php if ($review['blocks']): ?><h3>Bloqueos superpuestos</h3><ul><?php foreach ($review['blocks'] as $block): ?><li><a href="#block-<?= e($block['id']) ?>">Bloqueo #<?= e($block['id']) ?></a> · <?= e($block['start_date']) ?> a <?= e($block['effective_end']) ?></li><?php endforeach ?></ul><?php endif ?>
<?php if (!$review['reservations'] && !$review['occupants'] && !$review['blocks']): ?>
<p class="notice success">No hay conflictos. Las solicitudes pendientes no ocupan noches; no podrán confirmarse durante el bloqueo.</p>
<form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="create"><input type="hidden" name="proposal_token" value="<?= e($proposalToken) ?>">
<label class="check-label"><input type="checkbox" name="acknowledged" value="1" required> Confirmo el bloqueo de la habitación <?= e($review['room']['code']) ?> del <?= e($data['start_date']) ?> al <?= e($data['end_date']) ?>.</label>
<button class="button">Guardar bloqueo</button></form><p class="data-note">Esta revisión vence en 20 minutos. Hasta guardar, la habitación sigue disponible.</p>
<?php else: ?><p class="notice error">No se puede guardar el bloqueo mientras existan estos conflictos.</p><?php endif ?></section><?php endif ?>
<section class="form-panel"><h2>Nuevo bloqueo</h2>
<form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="review">
<label for="room_id">Habitación</label><select id="room_id" name="room_id" required><option value="">Elegí una habitación</option>
<?php foreach ($rooms as $room): ?><option value="<?= e($room['id']) ?>" <?= $data['room_id']===(string)$room['id']?'selected':'' ?>><?= e($room['code'].' · '.$room['name'].(!$room['active']?' · Inactiva':'')) ?></option><?php endforeach ?></select>
<?php inputField('start_date','Primera noche bloqueada',$data,[],'date','required min="'.date('Y-m-d').'" max="'.bookingLimit().'"'); inputField('end_date','Disponible nuevamente desde',$data,[],'date','required min="'.date('Y-m-d').'" max="'.bookingLimit().'"'); ?>
<p class="data-note">Ejemplo: del 23 al 25 bloquea las noches del 23 y 24. El 25 vuelve a estar disponible si no tiene otras restricciones. Máximo un año de anticipación.</p>
<label for="reason">Motivo del mantenimiento</label><textarea id="reason" name="reason" rows="3" maxlength="500" required><?= e($data['reason']) ?></textarea>
<button class="button">Revisar conflictos</button></form></section>
<section class="spaced-heading"><h2>Bloqueos vigentes y programados</h2>
<?php if (!$blocks): ?><p>No hay bloqueos vigentes ni programados.</p><?php endif ?>
<?php foreach ($blocks as $block): ?>
<article class="form-panel" id="block-<?= e($block['id']) ?>"><h3>Habitación <?= e($block['code']) ?> · Bloqueo #<?= e($block['id']) ?></h3>
<p><span class="calendar-state maintenance"><?= $block['start_date']<=date('Y-m-d')?'En mantenimiento':'Programado' ?></span><?= !$block['active']?' · Habitación inactiva':'' ?></p>
<p><?= e($block['start_date']) ?> a <?= e($block['end_date']) ?> · Fin exclusivo.</p>
<p class="cancellation-reason"><?= e($block['reason']) ?></p><p class="data-note">Creado por <?= e($block['created_actor']) ?> · <?= e($block['created_at']) ?></p>
<details><summary>Levantar bloqueo #<?= e($block['id']) ?></summary>
<form method="post"><?php csrfInput() ?><input type="hidden" name="action" value="release"><input type="hidden" name="block_id" value="<?= e($block['id']) ?>">
<label for="release-<?= e($block['id']) ?>">Motivo para levantarlo</label><textarea id="release-<?= e($block['id']) ?>" name="release_reason" rows="2" maxlength="500" required></textarea>
<label class="check-label"><input type="checkbox" name="acknowledged" value="1" required> Confirmo que se pueden liberar las noches pendientes de este bloqueo desde hoy.</label>
<p class="data-note">Se conserva el historial de noches anteriores. La limpieza y la activación de la habitación se gestionan por separado.</p>
<button class="button secondary">Levantar bloqueo #<?= e($block['id']) ?></button></form></details></article><?php endforeach ?></section>
<section class="spaced-heading"><h2>Historial de mantenimiento</h2><p class="data-note">Últimos 30 bloqueos terminados o levantados, más el seleccionado desde el calendario. Las fechas originales se conservan.</p>
<?php if (!$history): ?><p>Todavía no hay bloqueos terminados.</p><?php endif ?>
<?php foreach ($history as $block): ?><article class="form-panel" id="block-<?= e($block['id']) ?>"><h3>Habitación <?= e($block['code']) ?> · Bloqueo #<?= e($block['id']) ?> · <?= $block['released_at']?'Levantado':'Finalizado por fecha' ?></h3>
<p><?= e($block['start_date']) ?> a <?= e($block['end_date']) ?> · Fin exclusivo.</p><p class="cancellation-reason"><?= e($block['reason']) ?></p>
<p>Creado por <?= e($block['created_actor']) ?> · <?= e($block['created_at']) ?></p>
<?php if ($block['released_at']): ?><p>Levantado por <?= e($block['released_actor']) ?> · <?= e($block['released_at']) ?></p><p class="cancellation-reason">Motivo: <?= e($block['release_reason']) ?></p><?php endif ?></article><?php endforeach ?></section>
<?php pageEnd(); ?>
