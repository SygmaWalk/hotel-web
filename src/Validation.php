<?php
declare(strict_types=1);

function field(array $input, string $key): string
{
    // Un cliente HTTP también puede enviar arrays donde esperábamos texto.
    return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
}

function validDate(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function positiveId(string $value): bool
{
    return ctype_digit($value) && strlen($value) <= 10 && (int) $value > 0 && (int) $value <= 4294967295;
}

function validateRequest(array $input): array
{
    $data = [];
    foreach (['guest_name', 'email', 'check_in', 'check_out', 'room_id'] as $key) {
        $data[$key] = field($input, $key);
    }
    $errors = [];
    if ($data['guest_name'] === '' || mb_strlen($data['guest_name']) > 100) {
        $errors['guest_name'] = 'Ingresá un nombre de hasta 100 caracteres.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 190) {
        $errors['email'] = 'Ingresá un correo válido de hasta 190 caracteres.';
    }
    if (!validDate($data['check_in']) || $data['check_in'] < date('Y-m-d') || $data['check_in'] > '2099-12-30') {
        $errors['check_in'] = 'La entrada debe ser una fecha válida desde hoy y anterior a 2100.';
    }
    if (!validDate($data['check_out']) || $data['check_out'] <= $data['check_in'] || $data['check_out'] > '2099-12-31') {
        $errors['check_out'] = 'La salida debe ser posterior a la entrada y anterior a 2100.';
    }
    if (!positiveId($data['room_id'])) {
        $errors['room_id'] = 'Seleccioná una habitación válida.';
    }
    return [$data, $errors];
}

function validateRoom(array $input): array
{
    $data = [];
    foreach (['code', 'name', 'capacity', 'nightly_rate'] as $key) {
        $data[$key] = field($input, $key);
    }
    $data['code'] = strtoupper($data['code']);
    $errors = [];
    if (!preg_match('/^[A-Z0-9-]{1,20}$/D', $data['code'])) {
        $errors['code'] = 'Usá un código de 1 a 20 letras, números o guiones.';
    }
    if ($data['name'] === '' || mb_strlen($data['name']) > 100) {
        $errors['name'] = 'Ingresá un nombre de hasta 100 caracteres.';
    }
    if (!ctype_digit($data['capacity']) || (int) $data['capacity'] < 1 || (int) $data['capacity'] > 100) {
        $errors['capacity'] = 'La capacidad debe ser un entero entre 1 y 100.';
    }
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $data['nightly_rate'])) {
        $errors['nightly_rate'] = 'Ingresá una tarifa no negativa, hasta 99999999,99 y con hasta dos decimales.';
    }
    return [$data, $errors];
}
