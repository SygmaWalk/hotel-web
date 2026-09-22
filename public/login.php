<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
if (currentUser()) redirect('solicitudes.php');
$errors = []; $data = ['email' => field($_POST, 'email')];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = hash('sha256', strtolower($data['email']));
    $statement = db()->prepare('SELECT failures FROM login_attempts WHERE identity_hash = ? AND last_failure > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $statement->execute([$identity]);
    if ((int) $statement->fetchColumn() >= 5) {
        http_response_code(429);
        $errors['email'] = 'Demasiados intentos. Esperá 15 minutos antes de volver a intentar.';
    } else {
        $statement = db()->prepare('SELECT * FROM users WHERE email = ?');
        $statement->execute([$data['email']]);
        $user = $statement->fetch();
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $valid = password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if ($user && $valid) {
            session_regenerate_id(true);
            $_SESSION = ['user_id' => $user['id'], 'csrf' => bin2hex(random_bytes(32))];
            $statement = db()->prepare('DELETE FROM login_attempts WHERE identity_hash = ?');
            $statement->execute([$identity]);
            redirect('solicitudes.php');
        }
        $statement = db()->prepare('INSERT INTO login_attempts (identity_hash, failures, last_failure) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE failures = IF(last_failure < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, failures + 1), last_failure = NOW()');
        $statement->execute([$identity]);
        http_response_code(422);
        $errors['email'] = 'Correo o contraseña incorrectos.';
    }
}
pageStart('Acceso del personal');
errorSummary($errors);
?>
<form method="post" class="form-panel narrow"><?php csrfInput() ?>
<?php inputField('email', 'Correo del personal', $data, $errors, 'email', 'required maxlength="190" autocomplete="username"') ?>
<label for="password">Contraseña</label><input type="password" id="password" name="password" required autocomplete="current-password" maxlength="200">
<button class="button">Ingresar</button></form>
<?php pageEnd(); ?>
