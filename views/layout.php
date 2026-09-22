<?php
declare(strict_types=1);
function pageStart(string $title, ?array $user = null): void { ?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Hotel Aurora</title><link rel="stylesheet" href="<?= e(url('css/styles.css')) ?>">
<script src="<?= e(url('js/forms.js')) ?>" defer></script></head><body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<div class="demo-notice">Proyecto académico · Hotel ficticio e información de ejemplo</div>
<header class="site-header"><div class="container app-header">
<a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-monogram" aria-hidden="true">A</span><span>HOTEL <strong>AURORA</strong></span></a>
<nav class="app-nav" aria-label="Principal"><a href="<?= e(url('habitaciones.php')) ?>">Habitaciones</a><a href="<?= e(url('reservar.php')) ?>">Solicitar estadía</a>
<?php if ($user): ?><a href="<?= e(url('solicitudes.php')) ?>">Solicitudes</a>
<?php if ($user['role'] === 'admin'): ?><a href="<?= e(url('habitaciones-admin.php')) ?>">Administrar habitaciones</a><?php endif ?>
<form method="post" action="<?= e(url('logout.php')) ?>"><?php csrfInput() ?><button class="text-button">Cerrar sesión</button></form>
<?php else: ?><a href="<?= e(url('login.php')) ?>">Personal</a><?php endif ?></nav></div></header>
<main id="contenido" class="container app-main" tabindex="-1"><p class="eyebrow">HOTEL AURORA</p><h1><?= e($title) ?></h1>
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
