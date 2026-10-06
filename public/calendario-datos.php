<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
function calendarReply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
try {
    if (!currentUser()) calendarReply(401, ['error'=>'La sesión venció. Ingresá nuevamente.']);
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { header('Allow: GET'); calendarReply(405, ['error'=>'Método no permitido.']); }
    session_write_close();
    $month = field($_GET, 'month');
    if (!preg_match('/^\d{4}-\d{2}$/D', $month) || !validDate($month . '-01')) throw new DomainException('Elegí un mes válido.');
    $from = $month . '-01';
    if ($from < '2000-01-01' || $from > bookingLimit()) throw new DomainException('El mes debe estar entre enero de 2000 y el límite de un año.');
    $room = field($_GET, 'room_id');
    if (isset($_GET['room_id']) && (!is_string($_GET['room_id']) || ($room !== '' && !positiveId($room)))) throw new DomainException('Elegí una habitación válida.');
    $to = min(bookingLimit(), (new DateTimeImmutable($from))->format('Y-m-t'));
    $data = hotel()->calendar($from, $to, $room === '' ? null : (int) $room);
    // Solo los campos necesarios para dibujar el calendario; nunca correos o tokens.
    foreach (['rooms', 'allRooms'] as $key) $data[$key] = array_map(fn($r) => array_intersect_key($r, array_flip(['id','code','name','active'])), $data[$key]);
    $data['limit'] = bookingLimit();
    calendarReply(200, $data);
} catch (DomainException $error) {
    calendarReply(422, ['error'=>$error->getMessage()]);
} catch (Throwable $error) {
    error_log('Calendario JSON: ' . get_class($error));
    calendarReply(503, ['error'=>'No se pudo consultar el calendario. Intentá nuevamente.']);
}
