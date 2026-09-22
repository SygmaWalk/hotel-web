<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$status = field($_GET, 'status'); $date = field($_GET, 'date'); $errors = []; $requests = [];
try { $requests = hotel()->requests($status, $date); }
catch (DomainException $error) { http_response_code(422); $errors['status'] = $error->getMessage(); }
pageStart('Solicitudes de estadía', $user);
?>
<p class="intro">Revisá los pedidos y organizá las reservas. Las solicitudes pendientes todavía no ocupan una habitación.</p>
<?php errorSummary($errors) ?>
<form method="get" class="filter-form">
<div><label for="status">Estado</label><select id="status" name="status"><option value="">Todos</option>
<?php foreach (['pending','confirmed','rejected'] as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(statusLabel($option)) ?></option><?php endforeach ?></select></div>
<div><label for="date">Fecha de entrada</label><input type="date" id="date" name="date" value="<?= e($date) ?>"></div><button class="button">Filtrar</button><a href="<?= e(url('solicitudes.php')) ?>">Limpiar filtros</a></form>
<?php if (!$errors && !$requests): ?><p class="notice">No hay solicitudes para estos filtros.</p><?php endif ?>
<?php if ($requests): ?><div class="table-wrap"><table><caption><?= count($requests) ?> solicitudes</caption><thead><tr><th>Referencia</th><th>Huésped</th><th>Habitación</th><th>Entrada / salida</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
<?php foreach ($requests as $request): ?><tr><td>#<?= e($request['id']) ?></td><td><?= e($request['guest_name']) ?></td><td><?= e($request['code']) ?></td><td><?= e($request['check_in']) ?><br><?= e($request['check_out']) ?></td><td><span class="badge <?= e($request['status']) ?>"><?= e(statusLabel($request['status'])) ?></span></td><td><a href="<?= e(url('solicitud.php?id=' . $request['id'])) ?>">Ver detalle #<?= e($request['id']) ?></a></td></tr><?php endforeach ?>
</tbody></table></div><?php endif ?>
<?php pageEnd(); ?>
