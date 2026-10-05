<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$date = array_key_exists('date', $_GET) ? field($_GET, 'date') : date('Y-m-d');
$errors = []; $dashboard = null;
try { $dashboard = hotel()->dashboard($date); }
catch (DomainException $error) { http_response_code(422); $errors['date'] = $error->getMessage(); }
pageStart('El hotel, de un vistazo', $user);
?>
<div class="dashboard-toolbar">
    <p class="intro">Organizá las llegadas, revisá los pedidos y prepará el día.</p>
    <form method="get" class="dashboard-filter" data-dashboard-filter>
        <div><label for="date">Fecha de consulta</label><input id="date" name="date" type="date" required min="2000-01-01" max="2099-12-31" value="<?= e($date) ?>" <?= $errors ? 'aria-invalid="true"' : '' ?>></div>
        <button class="button" type="submit">Actualizar panel</button>
        <a href="<?= e(url('dashboard.php')) ?>">Hoy</a>
    </form>
</div>
<?php errorSummary($errors) ?>
<?php if ($dashboard): ?>
<div class="live-controls" data-live-controls hidden>
    <label class="check-label"><input type="checkbox" data-auto-refresh> Actualizar cada minuto</label>
    <p role="status" aria-live="polite" data-refresh-status>Datos actualizados al abrir el panel.</p>
    <a href="<?= e(url('dashboard.php?date=' . $date)) ?>" data-refresh-retry hidden>Volver a cargar</a>
</div>
<div id="dashboard-content" data-date="<?= e($date) ?>">
<?php require dirname(__DIR__) . '/views/dashboard-content.php'; ?>
</div>
<script src="<?= e(assetUrl('js/dashboard.js')) ?>" defer></script>
<?php endif ?>
<?php pageEnd(); ?>
