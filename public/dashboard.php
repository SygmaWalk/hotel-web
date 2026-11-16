<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser();
$date = array_key_exists('date', $_GET) ? field($_GET, 'date') : date('Y-m-d');
$errors = []; $dashboard = null;
try {
    $dashboard = hotel()->dashboard($date);
    $cleaningPending = array_values(array_filter(hotel()->cleaningRooms(), fn($room) => $room['reservation_id'] !== null));
    // Priorizar la llegada más cercana. En caso de empate se conserva el orden de antigüedad de la limpieza.
    usort($cleaningPending, fn($a, $b) => ($a['next_check_in'] ?? '9999-12-31') <=> ($b['next_check_in'] ?? '9999-12-31'));
    $occupants = hotel()->occupants();
}
catch (DomainException $error) { http_response_code(422); $errors['date'] = $error->getMessage(); }
pageStart('Hoy', $user);
?>
<div class="today-toolbar">
    <p>Revisá las tareas del turno y consultá las noches disponibles.</p>
    <div class="today-shortcuts">
        <a class="button" href="<?= e(url('reserva-nueva.php')) ?>">Nueva reserva</a>
        <a href="<?= e(url('calendario.php')) ?>">Abrir calendario</a>
    </div>
</div>
<details class="today-options" <?= ($errors || $date !== date('Y-m-d')) ? 'open' : '' ?>>
    <summary>Fecha de consulta y actualización</summary>
    <form method="get" class="dashboard-filter" data-dashboard-filter>
        <div><label for="date">Fecha de los movimientos y la disponibilidad</label><input id="date" name="date" type="date" required min="2000-01-01" max="2099-12-31" value="<?= e($date) ?>" <?= $errors ? 'aria-invalid="true"' : '' ?>></div>
        <button class="button" type="submit">Actualizar panel</button>
        <a href="<?= e(url('dashboard.php')) ?>">Volver a hoy</a>
    </form>
<?php if ($dashboard): ?>
<div class="live-controls" data-live-controls hidden>
    <label class="check-label"><input type="checkbox" data-auto-refresh> Actualizar cada minuto</label>
    <p role="status" aria-live="polite" data-refresh-status>Datos actualizados al abrir el panel.</p>
    <a href="<?= e(url('dashboard.php?date=' . $date)) ?>" data-refresh-retry hidden>Volver a cargar</a>
</div>
<?php endif ?>
</details>
<?php errorSummary($errors) ?>
<?php if ($dashboard): ?>
<div id="dashboard-content" data-date="<?= e($date) ?>">
<?php require dirname(__DIR__) . '/views/dashboard-content.php'; ?>
</div>
<script src="<?= e(assetUrl('js/dashboard.js')) ?>" defer></script>
<?php endif ?>
<?php pageEnd(); ?>
