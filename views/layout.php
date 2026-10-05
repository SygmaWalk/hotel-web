<?php
declare(strict_types=1);
function assetUrl(string $path): string {
    return url($path) . '?v=' . filemtime(dirname(__DIR__) . '/public/' . $path);
}
function navLink(string $page, string $label): void {
    $current = basename($_SERVER['SCRIPT_NAME']);
    $active = $current === $page || ($current === 'solicitud.php' && $page === 'solicitudes.php');
    echo '<a href="' . e(url($page)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
}
function pageStart(string $title, ?array $user = null): void {
    $user ??= currentUser();
    ?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Hotel Aurora</title><link rel="stylesheet" href="<?= e(assetUrl('css/styles.css')) ?>">
<script src="<?= e(assetUrl('js/forms.js')) ?>" defer></script><script src="<?= e(assetUrl('js/navigation.js')) ?>" defer></script></head><body class="app-body">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<div class="demo-notice">Proyecto académico · Hotel ficticio e información de ejemplo</div>
<header class="site-header"><div class="container app-header">
<a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-monogram" aria-hidden="true">A</span><span>HOTEL <strong>AURORA</strong></span></a>
<button class="menu-toggle app-menu-toggle" type="button" aria-controls="navegacion" aria-expanded="false" hidden>Menú</button>
<nav id="navegacion" class="app-nav" aria-label="Principal">
<?php if ($user): ?><?php navLink('dashboard.php', 'Panel del día'); navLink('calendario.php', 'Calendario'); navLink('solicitudes.php', 'Solicitudes'); ?>
<?php if ($user['role'] === 'admin'): ?><?php navLink('habitaciones-admin.php', 'Habitaciones'); ?><?php endif ?>
<?php navLink('habitaciones.php', 'Ver catálogo'); ?>
<form method="post" action="<?= e(url('logout.php')) ?>"><?php csrfInput() ?><button class="text-button">Cerrar sesión</button></form>
<?php else: ?><?php navLink('habitaciones.php', 'Habitaciones'); navLink('reservar.php', 'Solicitar estadía'); navLink('login.php', 'Personal'); ?><?php endif ?></nav></div></header>
<main id="contenido" class="container app-main" tabindex="-1"><p class="eyebrow"><?= $user ? 'GESTIÓN DEL HOTEL · ' . ($user['role'] === 'admin' ? 'ADMINISTRACIÓN' : 'RECEPCIÓN') : 'HOTEL AURORA' ?></p><h1><?= e($title) ?></h1>
<?php if (isset($_SESSION['flash'])): ?><p class="notice success" role="status"><?= e($_SESSION['flash']) ?></p><?php unset($_SESSION['flash']); endif;
}
function pageEnd(): void { ?>
</main><footer class="site-footer"><div class="container"><p>Hotel Aurora · Demo académica. Usá datos ficticios.</p></div></footer></body></html>
<?php }
function errorSummary(array $errors): void {
    if (!$errors) return;
    echo '<div class="notice error" role="alert"><p>Revisá lo siguiente:</p><ul>';
    foreach ($errors as $key => $error) echo '<li><a href="#' . e($key) . '">' . e($error) . '</a></li>';
    echo '</ul></div>';
}
function inputField(string $name, string $label, array $data, array $errors, string $type = 'text', string $attrs = ''): void { ?>
<label for="<?= e($name) ?>"><?= e($label) ?></label>
<input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= e($data[$name] ?? '') ?>" <?= $attrs ?> <?= isset($errors[$name]) ? 'aria-invalid="true" aria-describedby="' . e($name) . '-error"' : '' ?>>
<?php if (isset($errors[$name])): ?><p class="field-error" id="<?= e($name) ?>-error"><?= e($errors[$name]) ?></p><?php endif;
}
