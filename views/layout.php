<?php
declare(strict_types=1);
function assetUrl(string $path): string {
    return url($path) . '?v=' . filemtime(dirname(__DIR__) . '/public/' . $path);
}
function navLink(string $page, string $label): void {
    $current = basename($_SERVER['SCRIPT_NAME']);
    $active = $current === $page || (in_array($current, ['solicitud.php','reserva-reprogramar.php','reserva-pagos.php'], true) && $page === 'solicitudes.php') || ($current === 'reserva-nueva.php' && $page === 'calendario.php');
    echo '<a href="' . e(url($page)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
}
function pageStart(string $title, ?array $user = null): void {
    $user ??= currentUser();
    ?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Hotel Aurora</title><link rel="stylesheet" href="<?= e(assetUrl('css/styles.css')) ?>">
<?php if ($user): ?><link rel="stylesheet" href="<?= e(assetUrl('css/staff-nav.css')) ?>"><?php endif ?>
<?php if ($user && basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php'): ?><link rel="stylesheet" href="<?= e(assetUrl('css/today.css')) ?>"><?php endif ?>
<link rel="stylesheet" href="<?= e(assetUrl('css/interface.css')) ?>">
<script src="<?= e(assetUrl('js/accessibility.js')) ?>" defer></script>
<script src="<?= e(assetUrl('js/forms.js')) ?>" defer></script><script src="<?= e(assetUrl('js/navigation.js')) ?>" defer></script></head><body class="app-body<?= $user ? ' staff-area' : '' ?>">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<div class="demo-notice">Proyecto académico · Hotel ficticio e información de ejemplo</div>
<header class="site-header"><div class="container app-header">
<a class="brand" href="<?= e(url($user ? 'dashboard.php' : 'index.php')) ?>" aria-label="<?= $user ? 'Hotel Aurora · Volver a Hoy' : 'Hotel Aurora · Inicio' ?>"><span class="brand-monogram" aria-hidden="true">A</span><span>HOTEL <strong>AURORA</strong></span></a>
<button class="menu-toggle app-menu-toggle" type="button" aria-controls="navegacion" aria-expanded="false" hidden>Menú</button>
<nav id="navegacion" class="app-nav" aria-label="<?= $user ? 'Gestión del hotel' : 'Principal' ?>">
<?php if ($user): ?>
<div class="staff-group" role="group" aria-labelledby="nav-recepcion">
    <p class="staff-group-label" id="nav-recepcion">Recepción</p>
    <?php navLink('dashboard.php', 'Hoy'); navLink('calendario.php', 'Calendario'); navLink('solicitudes.php', 'Solicitudes'); ?>
</div>
<div class="staff-group" role="group" aria-labelledby="nav-operacion">
    <p class="staff-group-label" id="nav-operacion">Operación</p>
    <?php navLink('limpieza.php', 'Limpieza'); navLink('mantenimiento.php', 'Mantenimiento'); navLink('cobros.php', 'Cobros'); ?>
</div>
<?php if ($user['role'] === 'admin'): ?>
<div class="staff-group" role="group" aria-labelledby="nav-administracion">
    <p class="staff-group-label" id="nav-administracion">Administración</p>
    <?php navLink('habitaciones-admin.php', 'Habitaciones'); navLink('usuarios.php', 'Usuarios'); ?>
</div>
<?php endif ?>
<div class="staff-group staff-account" role="group" aria-labelledby="nav-sesion">
    <p class="staff-group-label" id="nav-sesion">Sitio y sesión</p>
    <?php navLink('habitaciones.php', 'Ver catálogo'); ?>
    <form method="post" action="<?= e(url('logout.php')) ?>"><?php csrfInput() ?><button class="text-button">Cerrar sesión</button></form>
</div>
<?php else: ?><?php navLink('habitaciones.php', 'Habitaciones'); navLink('reservar.php', 'Solicitar estadía'); navLink('login.php', 'Personal'); ?><?php endif ?></nav></div></header>
<main id="contenido" class="container app-main" tabindex="-1"><p class="eyebrow"><?= $user ? 'GESTIÓN DEL HOTEL · ' . ($user['role'] === 'admin' ? 'ADMINISTRACIÓN' : 'RECEPCIÓN') : 'HOTEL AURORA' ?></p><h1><?= e($title) ?></h1>
<?php if (isset($_SESSION['flash'])): ?><p class="notice success" role="status"><?= e($_SESSION['flash']) ?></p><?php unset($_SESSION['flash']); endif;
}
function roomGallery(int $roomId, string $name, ?string $illustration = null): void {
    $images = hotel()->images($roomId);
    if (!$images) {
        if ($illustration !== null) {
            echo '<figure class="room-cover"><img src="' . e(url('img/' . $illustration)) . '" alt="" width="600" height="400"><figcaption>Imagen ilustrativa</figcaption></figure>';
        }
        return;
    }
    echo '<div class="room-gallery" role="group" aria-label="Fotos de ' . e($name) . '">';
    foreach ($images as $i => $photo) {
        echo '<a href="' . e(url('imagen.php?id=' . $photo['id'])) . '" target="_blank" rel="noopener"><img src="' . e(url('imagen.php?id=' . $photo['id'])) . '" alt="' . e($name . ' · Foto ' . ($i+1)) . '" loading="lazy" width="480" height="320"></a>';
    }
    echo '</div>';
}
function pageEnd(): void { ?>
</main><footer class="site-footer"><div class="container"><p>Hotel Aurora · Demo académica. Usá datos ficticios.</p></div></footer></body></html>
<?php }
function errorSummary(array $errors): void {
    if (!$errors) return;
    echo '<div class="notice error" role="alert" tabindex="-1" data-error-summary><p>Revisá lo siguiente:</p><ul>';
    foreach ($errors as $key => $error) echo '<li><a id="summary-' . e($key) . '" data-error-target="' . e($key) . '" href="#' . e($key) . '">' . e($error) . '</a></li>';
    echo '</ul></div>';
}
function inputField(string $name, string $label, array $data, array $errors, string $type = 'text', string $attrs = ''): void { ?>
<label for="<?= e($name) ?>"><?= e($label) ?></label>
<input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= e($data[$name] ?? '') ?>" <?= $attrs ?> <?= isset($errors[$name]) ? 'aria-invalid="true" aria-describedby="' . e($name) . '-error"' : '' ?>>
<?php if (isset($errors[$name])): ?><p class="field-error" id="<?= e($name) ?>-error"><?= e($errors[$name]) ?></p><?php endif;
}
