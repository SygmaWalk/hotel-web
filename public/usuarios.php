<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
$user = requireUser(true);
$errors = [];
$data = ['email'=>field($_POST, 'email')];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['confirmation'] ?? null) ? $_POST['confirmation'] : '';
    try {
        hotel()->createStaff($data['email'], $password, $confirmation, (int) $user['id']);
        flash('Usuario de recepción creado. Ya puede ingresar con su correo y contraseña.');
        redirect('usuarios.php');
    } catch (DomainException $error) { http_response_code(422); $errors['email'] = $error->getMessage(); }
}
$staff = hotel()->staffUsers((int) $user['id']);
pageStart('Usuarios de recepción', $user);
?>
<p class="intro">Creá cuentas para consultar el panel, gestionar reservas y registrar llegadas y salidas. Estas cuentas no pueden administrar habitaciones ni crear usuarios.</p>
<?php errorSummary($errors) ?>
<form method="post" class="form-panel narrow">
<?php csrfInput(); inputField('email', 'Correo del nuevo usuario', $data, $errors, 'email', 'required maxlength="190" autocomplete="off"'); ?>
<label for="password">Contraseña</label><input id="password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="password-help">
<p id="password-help" class="data-note">Usá una contraseña de al menos 12 caracteres. Máximo 72 bytes; las letras acentuadas pueden ocupar más de uno.</p>
<label for="confirmation">Repetir contraseña</label><input id="confirmation" name="confirmation" type="password" required autocomplete="new-password" maxlength="72">
<button class="button">Crear usuario de recepción</button>
</form>
<h2 class="spaced-heading">Cuentas de recepción</h2>
<?php if (!$staff): ?><p class="notice">Todavía no hay cuentas de recepción.</p><?php else: ?><ul><?php foreach ($staff as $account): ?><li><?= e($account['email']) ?></li><?php endforeach ?></ul><?php endif ?>
<?php pageEnd(); ?>
