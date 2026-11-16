<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$status = field($_GET, 'status'); $date = field($_GET, 'date'); $errors = []; $requests = [];
try { $requests = hotel()->requests($status, $date); }
catch (DomainException $error) { http_response_code(422); $errors['status'] = $error->getMessage(); }
pageStart('Solicitudes y reservas', $user);
?>
<p class="intro">Revisá los pedidos y organizá las reservas. Las solicitudes pendientes todavía no ocupan una habitación.</p>
<?php errorSummary($errors) ?>
<form method="get" class="filter-form">
<div><label for="status">Estado</label><select id="status" name="status"><option value="">Todos</option>
<?php foreach (['pending','confirmed','rejected','cancelled'] as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(statusLabel($option)) ?></option><?php endforeach ?></select></div>
<div><label for="date">Fecha de entrada</label><input type="date" id="date" name="date" value="<?= e($date) ?>"></div><button class="button">Filtrar</button><a href="<?= e(url('solicitudes.php')) ?>">Limpiar filtros</a></form>
<?php if (!$errors && !$requests): ?><p class="notice">No hay solicitudes para estos filtros.</p><?php endif ?>
<?php if ($requests): ?>
<div class="admin-section-heading"><h2>Resultados</h2><span class="count-pill"><?= count($requests) ?> solicitudes</span></div>
<div class="admin-request-grid">
<?php foreach ($requests as $request): ?><article class="admin-request-card">
<div class="admin-card-top"><span class="data-note"><?= in_array($request['status'], ['confirmed', 'cancelled'], true) ? 'Reserva' : 'Solicitud' ?> #<?= e($request['id']) ?></span><span class="badge <?= e($request['status']) ?>"><?= e(statusLabel($request['status'])) ?></span></div>
<h3><?= e($request['guest_name']) ?></h3><p>Habitación <?= e($request['code']) ?></p>
<dl class="admin-dates"><div><dt>Entrada</dt><dd><time datetime="<?= e($request['check_in']) ?>"><?= e((new DateTimeImmutable($request['check_in']))->format('d/m/Y')) ?></time></dd></div><div><dt>Salida</dt><dd><time datetime="<?= e($request['check_out']) ?>"><?= e((new DateTimeImmutable($request['check_out']))->format('d/m/Y')) ?></time></dd></div></dl>
<a class="button secondary" href="<?= e(url('solicitud.php?id=' . $request['id'])) ?>">Ver detalle #<?= e($request['id']) ?></a>
</article><?php endforeach ?></div><?php endif ?>
<?php pageEnd(); ?>
