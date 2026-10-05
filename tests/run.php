<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Hotel.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$root = dirname(__DIR__);
$runtime = __DIR__ . '/.runtime';
if (!is_dir($runtime)) mkdir($runtime, 0700, true);
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FALLÓ: ' . $label);
    $checks++;
}
function rejected(callable $action, string $label): void {
    try { $action(); } catch (DomainException $e) { check(true, $label); return; }
    check(false, $label);
}
function token(string $html, string $name = 'csrf'): string {
    if (!preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]+)"/', $html, $match)) throw new RuntimeException('Falta token ' . $name);
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}
function request(string $page, array $post = [], string $client = 'guest', string $method = 'GET', bool $multipart = false): array {
    global $base, $runtime;
    $curl = curl_init($base . '/' . $page);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>true, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_COOKIEFILE=>$runtime . '/' . $client . '.cookies', CURLOPT_COOKIEJAR=>$runtime . '/' . $client . '.cookies', CURLOPT_TIMEOUT=>10]);
    if ($method === 'POST') { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $post : http_build_query($post)); }
    elseif ($method !== 'GET') curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    $response = curl_exec($curl);
    if ($response === false) throw new RuntimeException('HTTP: ' . curl_error($curl));
    $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $result = [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), substr($response, $size), substr($response, 0, $size)];
    curl_setopt($curl, CURLOPT_COOKIELIST, 'FLUSH');
    curl_close($curl);
    return $result;
}
$schema = 'hotel_test_' . bin2hex(random_bytes(5));
$dsn = getenv('HOTEL_TEST_DSN') ?: 'mysql:host=127.0.0.1;charset=utf8mb4';
$rootUser = getenv('HOTEL_TEST_USER') ?: 'root';
$rootPassword = getenv('HOTEL_TEST_PASSWORD') ?: '';
$control = new PDO($dsn, $rootUser, $rootPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server = null; $pdo = null; $hotel = null; $created = false; $workers = [];
try {
    $control->exec('CREATE DATABASE ' . $schema . ' CHARACTER SET utf8mb4');
    $created = true;
    $config = ['dsn'=>$dsn . ';dbname=' . $schema,'user'=>$rootUser,'password'=>$rootPassword];
    $configPath = $runtime . '/config.php';
    file_put_contents($configPath, '<?php return ' . var_export($config, true) . ';');
    $pdo = connectDatabase($config);
    $pdo->exec(file_get_contents($root . '/database/schema.sql'));
    $hotel = new Hotel($pdo);
    $adminPassword = bin2hex(random_bytes(16)); $staffPassword = bin2hex(random_bytes(16));
    $insert = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
    $insert->execute(['admin@example.test', password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']); $adminId = (int) $pdo->lastInsertId();
    $insert->execute(['staff@example.test', password_hash($staffPassword, PASSWORD_DEFAULT), 'staff']);
    check(password_verify($adminPassword, $pdo->query('SELECT password_hash FROM users LIMIT 1')->fetchColumn()), 'hash de contraseña');
    check($hotel->rooms() === [], 'catálogo vacío');
    $emptyDashboard = $hotel->dashboard(date('Y-m-d'));
    check($emptyDashboard['summary']['active'] === 0 && $emptyDashboard['summary']['percentage'] === 0 && $emptyDashboard['rooms'] === [], 'panel sin habitaciones ni división por cero');
    check(count($emptyDashboard['week']) === 7 && array_sum(array_column($emptyDashboard['week'], 'reserved')) === 0, 'semana vacía del panel');
    check($hotel->calendar('2026-10-05', '2026-10-11')['rooms'] === [], 'calendario sin habitaciones');
    foreach ([['2027-02-30','2027-03-01'], ['','2026-10-05'], ['1999-12-31','2000-01-01'], ['2099-12-31','2100-01-01'], ['2026-10-06','2026-10-05'], ['2026-10-01','2026-11-01']] as [$badFrom, $badTo]) {
        rejected(fn() => $hotel->calendar($badFrom, $badTo), 'calendario rechaza fecha o rango inválido');
    }
    check(count($hotel->calendar('2024-02-28', '2024-03-01')['days']) === 3, 'calendario incluye día bisiesto y cambio de mes');
    check(count($hotel->calendar((new DateTimeImmutable(bookingLimit()))->modify('-30 days')->format('Y-m-d'), bookingLimit())['days']) === 31, 'calendario admite 31 noches y límite superior');
    foreach (['2027-02-30', '', '1999-12-31', '2100-01-01', "2026-10-05' OR 1=1"] as $badDate) {
        rejected(fn() => $hotel->dashboard($badDate), 'fecha inválida del panel');
    }
    $room = ['code'=>'T-101', 'name'=>'Habitación de prueba', 'capacity'=>'2', 'nightly_rate'=>'65000.00'];
    $roomId = $hotel->saveRoom($room, null);
    rejected(fn() => $hotel->saveRoom($room, null), 'código único');
    rejected(fn() => $hotel->saveRoom([...$room, 'capacity'=>'0'], null), 'capacidad positiva');
    rejected(fn() => $hotel->saveRoom([...$room, 'nightly_rate'=>'-1'], null), 'tarifa no negativa');
    rejected(fn() => $hotel->saveRoom([...$room, 'nightly_rate'=>'1.234'], null), 'precisión monetaria');
    $hotel->saveRoom([...$room, 'name'=>'Nombre editado'], $roomId);
    check($hotel->room($roomId)['name'] === 'Nombre editado', 'edición');
    $today = new DateTimeImmutable('today');
    $day = fn(int $offset): string => $today->modify(sprintf('%+d days', $offset))->format('Y-m-d');
    $valid = ['guest_name'=>'Huésped ficticio', 'email'=>'guest@example.test', 'room_id'=>(string) $roomId, 'check_in'=>$day(20), 'check_out'=>$day(23)];
    foreach ([['guest_name'=>''], ['email'=>'malo'], ['check_in'=>'2027-02-30'], ['check_in'=>chr(0)], ['check_out'=>$day(19)], ['room_id'=>['1']], ['check_in'=>$day(-1)]] as $invalid) {
        check(count(validateRequest([...$valid, ...$invalid])[1]) > 0, 'validación servidor: ' . json_encode($invalid));
    }
    $sameToken = bin2hex(random_bytes(32));
    $id = $hotel->submit($valid, $sameToken);
    check($hotel->request($id)['status'] === 'pending', 'estado inicial pendiente');
    check($hotel->submit($valid, $sameToken) === $id && count($hotel->requests()) === 1, 'reenvío idempotente');
    $hotel->resolve($id, 'confirmed', $adminId);
    rejected(fn() => $hotel->resolve($id, 'rejected', $adminId), 'solo pendientes');
    $overlap = $hotel->submit([...$valid, 'check_in'=>$day(21), 'check_out'=>$day(24)], bin2hex(random_bytes(32)));
    rejected(fn() => $hotel->resolve($overlap, 'confirmed', $adminId), 'solapamiento');
    $hotel->resolve($overlap, 'rejected', $adminId);
    $adjacent = $hotel->submit([...$valid, 'check_in'=>$day(23), 'check_out'=>$day(24)], bin2hex(random_bytes(32)));
    $hotel->resolve($adjacent, 'confirmed', $adminId);
    check($hotel->request($adjacent)['status'] === 'confirmed', 'salida y entrada el mismo día y rechazo sin ocupación');
    check(count($hotel->requests('confirmed', $day(20))) === 1, 'filtros fecha y estado');
    rejected(fn() => $hotel->requests('inventado'), 'filtro estado inválido');
    rejected(fn() => $hotel->requests('', '2027-02-30'), 'filtro fecha inválido');
    rejected(fn() => $hotel->deactivate($roomId, false), 'advertencia de futuras reservas');
    $hotel->deactivate($roomId, true);
    check($hotel->room($roomId)['active'] === 0 && count($hotel->requests()) === 3, 'baja lógica conserva historial');
    rejected(fn() => $hotel->submit($valid, bin2hex(random_bytes(32))), 'no solicitudes para inactiva');
    $room2 = $hotel->saveRoom([...$room, 'code'=>'T-102'], null);
    $inactiveRequest = $hotel->submit([...$valid, 'room_id'=>(string) $room2], bin2hex(random_bytes(32)));
    $hotel->deactivate($room2, false);
    rejected(fn() => $hotel->resolve($inactiveRequest, 'confirmed', $adminId), 'no confirmación de inactiva');
    $raceRoom = $hotel->saveRoom([...$room, 'code'=>'T-RACE'], null);
    $raceIds = [];
    for ($i=0; $i<2; $i++) $raceIds[] = $hotel->submit([...$valid,'room_id'=>(string) $raceRoom], bin2hex(random_bytes(32)));
    $pdo->beginTransaction();
    $pdo->query('SELECT id FROM rooms WHERE id = ' . $raceRoom . ' FOR UPDATE');
    foreach ($raceIds as $i=>$raceId) {
        $ready = $runtime . '/ready-' . $i;
        if (is_file($ready)) unlink($ready);
        $workers[] = proc_open([PHP_BINARY, __DIR__ . '/confirm-worker.php', $configPath, (string) $raceId, (string) $adminId, $ready],
            [0=>['pipe','r'],1=>['file',$runtime . '/worker.log','a'],2=>['file',$runtime . '/worker.log','a']], $pipes);
        fclose($pipes[0]);
    }
    $deadline = microtime(true) + 10;
    while ((!is_file($runtime.'/ready-0') || !is_file($runtime.'/ready-1')) && microtime(true)<$deadline) usleep(30000);
    check(is_file($runtime.'/ready-0') && is_file($runtime.'/ready-1'), 'dos procesos concurrentes listos');
    usleep(100000);
    $pdo->commit();
    $codes = [];
    foreach ($workers as $worker) $codes[] = proc_close($worker);
    $workers = [];
    sort($codes);
    check($codes === [0,2], 'solo una confirmación concurrente');
    $q = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE room_id = ? AND status = 'confirmed'");
    $q->execute([$raceRoom]);
    check((int) $q->fetchColumn() === 1, 'sin doble reserva en base');
    $overview = $hotel->dashboard($day(20));
    check($overview['summary']['arrivals'] === 2 && $overview['summary']['departures'] === 0, 'llegadas incluyen reserva en habitación inactiva');
    check($overview['summary']['pending'] === 2 && count($overview['pending']) === 2, 'pendientes globales excluyen confirmadas y rechazadas');
    check($overview['summary']['active'] === 1 && $overview['summary']['reserved'] === 1 && $overview['summary']['available'] === 0, 'disponibilidad excluye habitaciones inactivas');
    check($overview['summary']['percentage'] === 100 && count($overview['rooms']) === 3, 'inventario y porcentaje del panel');
    check(array_map('intval', array_column($overview['week'], 'reserved')) === [1,1,1,0,0,0,0], 'siete noches con salida exclusiva');
    $turnover = $hotel->dashboard($day(23));
    check($turnover['summary']['arrivals'] === 1 && $turnover['summary']['departures'] === 2 && $turnover['summary']['available'] === 1, 'entrada y salida el mismo día');
    check($hotel->dashboard($day(19))['summary']['reserved'] === 0, 'fecha anterior a la llegada libre');

    $calendar = $hotel->calendar($day(19), $day(24));
    check(count($calendar['rooms']) === 3 && count($calendar['days']) === 6, 'calendario incluye habitaciones inactivas y noches extremas');
    check($calendar['cells'][$raceRoom][$day(19)]['state'] === 'free', 'noche anterior a entrada libre');
    check($calendar['cells'][$raceRoom][$day(20)]['state'] === 'reserved', 'noche futura confirmada reservada');
    check($calendar['cells'][$raceRoom][$day(23)]['state'] === 'free' && count($calendar['cells'][$raceRoom][$day(23)]['departures']) === 1, 'salida libera noche y conserva movimiento');
    check($calendar['cells'][$room2][$day(20)]['state'] === 'inactive' && $calendar['cells'][$room2][$day(20)]['stays'] === [], 'pendiente no bloquea habitación inactiva');
    check(count($calendar['cells'][$roomId][$day(21)]['stays']) === 1, 'rechazada no aparece como estadía');
    check(count($calendar['cells'][$roomId][$day(23)]['arrivals']) === 1 && count($calendar['cells'][$roomId][$day(23)]['departures']) === 1, 'calendario distingue entrada y salida el mismo día');
    check($calendar['cells'][$roomId][$day(23)]['stays'][0]['id'] === $adjacent, 'salida exclusiva conserva solo siguiente reserva');
    check($calendar['cells'][$roomId][$day(20)]['stays'][0]['id'] === $id, 'reserva de habitación inactiva permanece visible');
    $filteredCalendar = $hotel->calendar($day(21), $day(21), $roomId);
    check(count($filteredCalendar['rooms']) === 1 && count($filteredCalendar['allRooms']) === 3 && count($filteredCalendar['days']) === 1, 'filtro por habitación y rango de una noche');
    check($filteredCalendar['cells'][$roomId][$day(21)]['stays'][0]['id'] === $id, 'reserva iniciada antes del rango incluida');
    check(count($hotel->calendar($day(24), $day(24), $roomId)['cells'][$roomId][$day(24)]['departures']) === 1, 'salida en inicio del rango incluida');
    rejected(fn() => $hotel->calendar($day(20), $day(21), 999999), 'habitación inexistente rechazada');
    $historical = $pdo->prepare("INSERT INTO reservations (room_id, guest_name, email, check_in, check_out, nightly_rate, submission_token, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed')");
    $historical->execute([$raceRoom, '<script>calendarTest()</script>', 'private@example.test', $day(-2), $day(1), '10', bin2hex(random_bytes(32))]);
    $historyId = (int) $pdo->lastInsertId();
    $historyCalendar = $hotel->calendar($day(-1), $day(1), $raceRoom);
    check($historyCalendar['cells'][$raceRoom][$day(-1)]['state'] === 'expected' && $historyCalendar['cells'][$raceRoom][$day(0)]['state'] === 'expected', 'pasado y hoy se distinguen como ocupación prevista');
    check($historyCalendar['cells'][$raceRoom][$day(1)]['state'] === 'free', 'salida de ocupación prevista no bloquea');

    // Servidor HTTP aislado: mismo código, otra base, ningún dato del hotel real.
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $base = 'http://' . $address;
    foreach (glob($runtime . '/*.cookies') as $cookie) unlink($cookie);
    $env = getenv(); $env['HOTEL_CONFIG_PATH'] = $configPath; $env['HOTEL_UPLOAD_DIR'] = $runtime . '/uploads-' . $schema;
    $server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $runtime, '-S', $address, '-t', $root . '/public'],
        [0=>['pipe','r'],1=>['file',$runtime.'/server.log','a'],2=>['file',$runtime.'/server.log','a']], $pipes, $root, $env);
    fclose($pipes[0]);
    $started = false;
    for ($i=0;$i<50;$i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $errstr, .1);
        if ($connection) { fclose($connection); $started=true; break; }
        usleep(50000);
    }
    check($started, 'servidor HTTP iniciado');
    [$code,$body] = request('habitaciones.php');
    check($code === 200 && str_contains($body, 'T-RACE') && !str_contains($body, 'T-101'), 'catálogo SQL activo');
    [$code,$body,$headers] = request('solicitudes.php');
    check($code === 303 && str_contains($headers, 'login.php'), 'panel privado');
    check(request('dashboard.php')[0] === 303, 'dashboard requiere sesión');
    check(request('calendario.php')[0] === 303, 'calendario requiere sesión');
    check(request('logout.php')[0] === 405, 'logout requiere POST');
    check(request('reservar.php', [], 'guest', 'PUT')[0] === 405, 'método inválido');
    $body = request('reservar.php?room_id='.$raceRoom)[1];
    $form = [...$valid, 'room_id'=>(string)$raceRoom, 'csrf'=>token($body), 'submission_token'=>token($body,'submission_token')];
    check(request('reservar.php', [...$form,'csrf'=>'incorrecto'], 'guest','POST')[0] === 403, 'CSRF requerido');
    [$code,$body] = request('reservar.php', [...$form, 'email'=>'mal','guest_name'=>'<script>alert(1)</script>'], 'guest','POST');
    check($code === 422 && str_contains($body,'&lt;script&gt;') && !str_contains($body,'<script>alert'), 'XSS escapado y datos conservados');
    $before = count($hotel->requests());
    check(request('reservar.php', [...$form,'check_out'=>$day(19)],'guest','POST')[0] === 422 && count($hotel->requests()) === $before, 'POST inválido no inserta');
    [$code,$body,$headers] = request('reservar.php', $form,'guest','POST');
    check($code === 303 && str_contains($headers,'solicitud-enviada.php'), 'POST INSERT redirección');
    check(request('solicitud-enviada.php')[0] === 200 && count($hotel->requests()) === $before+1, 'GET comprobante');
    check(request('reservar.php',$form,'guest','POST')[0] === 303 && count($hotel->requests()) === $before+1, 'POST repetido sin duplicado');
    $publicReceipt = request('solicitud-enviada.php')[1];
    check(!str_contains($publicReceipt, $valid['email']) && !str_contains($publicReceipt, $valid['guest_name']), 'comprobante sin datos personales');
    $login = request('login.php', [],'admin')[1]; $csrf = token($login);
    check(request('login.php',['csrf'=>$csrf,'email'=>'admin@example.test','password'=>'mala'],'admin','POST')[0] === 422, 'credenciales inválidas');
    $oldCookie = file_get_contents($runtime.'/admin.cookies');
    check(request('login.php',['csrf'=>$csrf,'email'=>'admin@example.test','password'=>$adminPassword],'admin','POST')[0] === 303, 'login correcto');
    check($oldCookie !== file_get_contents($runtime.'/admin.cookies'), 'regeneración sesión');
    [$code,$body] = request('solicitudes.php',[],'admin'); $adminCsrf = token($body);
    check($code === 200 && str_contains($body, $valid['guest_name']), 'personal consulta solicitudes');
    [$code,$body] = request('dashboard.php?date='.$day(20),[],'admin');
    check($code === 200 && str_contains($body,'id="dashboard-content"') && str_contains($body,'data-date="'.$day(20).'"'), 'dashboard administrador y fecha consultada');
    check(str_contains($body,'aria-current="page">Panel del día') && str_contains($body,'solicitud.php?id='.$id), 'navegación activa y enlaces a detalle');
    [$calendarCode, $calendarBody] = request('calendario.php?from='.$day(20).'&to='.$day(24).'&room_id='.$roomId, [], 'admin');
    check($calendarCode === 200 && str_contains($calendarBody, 'aria-current="page">Calendario') && str_contains($calendarBody, 'solicitud.php?id='.$id), 'calendario privado integrado con navegación y detalle');
    check(str_contains($calendarBody, '1 habitación · 5 noches') && str_contains($calendarBody, 'room_id='.$roomId), 'calendario muestra rango y conserva filtro en navegación');
    check(!str_contains($calendarBody, $sameToken) && !str_contains($calendarBody, $valid['email']), 'calendario no expone tokens ni correos');
    foreach (['from=2027-02-30', 'from[]=2026-10-05', 'to[]=2026-10-05', 'from=2026-10-01&to=2026-11-01', 'room_id[]=1', 'room_id=0', 'room_id=999999', 'from=2026-10-06&to=2026-10-05'] as $badQuery) {
        check(request('calendario.php?'.$badQuery, [], 'admin')[0] === 422, 'filtro inválido HTTP calendario');
    }
    $calendarBody = request('calendario.php?from='.$day(0).'&to='.$day(0), [], 'admin')[1];
    check(str_contains($calendarBody, '&lt;script&gt;calendarTest()&lt;/script&gt;') && !str_contains($calendarBody, '<script>calendarTest()'), 'calendario escapa nombres de reservas');
    $calendarBody = request('calendario.php?from='.bookingLimit(), [], 'admin')[1];
    check(str_contains($calendarBody, '1 noche') && !str_contains($calendarBody, 'Siguientes →'), 'calendario no navega fuera del límite superior');
    check(!str_contains(request('calendario.php?from=2000-01-01', [], 'admin')[1], '← Anteriores'), 'calendario no navega fuera del límite inferior');
    check(!str_contains($body,$sameToken) && !str_contains($body,$valid['email']), 'panel no expone tokens de envío ni correo del huésped');
    foreach (['2027-02-30','', '2100-01-01'] as $badDate) check(request('dashboard.php?date='.rawurlencode($badDate),[],'admin')[0] === 422, 'fecha inválida HTTP panel');
    check(request('dashboard.php?date[]=2026-10-05',[],'admin')[0] === 422, 'fecha array inválida HTTP panel');
    [$code,$body] = request('dashboard.php?date='.$day(200),[],'admin');
    check($code === 200 && str_contains($body,'No hay llegadas previstas'), 'panel cambia fecha y muestra estado vacío');
    $pdo->prepare('UPDATE reservations SET guest_name = ? WHERE id = ?')->execute(['<script>alert(1)</script>', $inactiveRequest]);
    $body = request('dashboard.php',[],'admin')[1];
    check(str_contains($body,'&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($body,'<script>alert(1)</script>'), 'panel escapa nombre del huésped');
    check(request('solicitudes.php?status=inventado',[],'admin')[0] === 422, 'filtro inválido HTTP');
    check(str_contains(request('solicitudes.php?date='.$day(200),[],'admin')[1], 'No hay solicitudes'), 'estado vacío panel');
    check(request('solicitud.php?id=999999',[],'admin')[0] === 404, 'detalle inexistente');
    $httpId = (int) $pdo->query('SELECT MAX(id) FROM reservations')->fetchColumn();
    check(request('solicitud.php?id='.$httpId,['csrf'=>$adminCsrf,'action'=>'confirmed'],'admin','POST')[0] === 409, 'conflicto fechas HTTP');
    check(request('solicitud.php?id='.$httpId,['csrf'=>$adminCsrf,'action'=>'rejected'],'admin','POST')[0] === 303, 'rechazo HTTP');
    $staffForm = request('login.php',[],'staff')[1];
    check(request('login.php',['csrf'=>token($staffForm),'email'=>'staff@example.test','password'=>$staffPassword],'staff','POST')[0] === 303, 'login recepción');
    $staffBody = request('solicitudes.php',[],'staff')[1]; $staffCsrf=token($staffBody);
    [$code,$body] = request('dashboard.php',[],'staff');
    check($code === 200 && !str_contains($body,'habitaciones-admin.php'), 'dashboard recepción sin enlace administrativo');
    [$code,$body] = request('calendario.php', [], 'staff');
    check($code === 200 && str_contains($body, 'Calendario de disponibilidad') && !str_contains($body, 'habitaciones-admin.php'), 'recepción accede al calendario sin administrar habitaciones');
    check(str_contains(request('habitaciones.php',[],'staff')[1],'Panel del día'), 'catálogo conserva navegación de sesión');
    check(request('habitaciones-admin.php',[],'staff')[0] === 403, 'recepción no administra');
    check(request('habitaciones-admin.php',['csrf'=>$staffCsrf,'action'=>'save',...$room],'staff','POST')[0] === 403, 'autorización POST servidor');
    check(request('habitaciones-admin.php',['csrf'=>$adminCsrf,'action'=>'save',...$room,'code'=>'T-HTTP'],'admin','POST')[0] === 303, 'alta HTTP');
    $httpRoom = (int)$pdo->query("SELECT id FROM rooms WHERE code='T-HTTP'")->fetchColumn();
    check(request('habitaciones-admin.php?id='.$httpRoom,['csrf'=>$adminCsrf,'action'=>'save',...$room,'code'=>'T-HTTP','nightly_rate'=>'0'],'admin','POST')[0] === 303, 'edición tarifa cero');
    check(request('habitaciones-admin.php?id='.$raceRoom,['csrf'=>$adminCsrf,'action'=>'deactivate'],'admin','POST')[0] === 422, 'advertencia HTTP reservas futuras');
    check(request('habitaciones-admin.php?id='.$raceRoom,['csrf'=>$adminCsrf,'action'=>'deactivate','acknowledged'=>'1'],'admin','POST')[0] === 303, 'baja aceptada HTTP');
    require __DIR__ . '/operations-cases.php';
    check(request('logout.php',['csrf'=>$adminCsrf],'admin','POST')[0] === 303 && request('solicitudes.php',[],'admin')[0] === 303, 'logout invalida acceso');
    $lockForm=request('login.php',[],'locked')[1]; $lockCsrf=token($lockForm);
    for($i=0;$i<5;$i++) request('login.php',['csrf'=>$lockCsrf,'email'=>'locked@example.test','password'=>'wrong'],'locked','POST');
    check(request('login.php',['csrf'=>$lockCsrf,'email'=>'locked@example.test','password'=>'wrong'],'locked','POST')[0] === 429,'límite intentos login');
    $pdo->exec('UPDATE rooms SET active = 0');
    check(str_contains(request('habitaciones.php')[1],'Por el momento no hay habitaciones'), 'estado vacío público');
    $badConfig = $config; $badConfig['password'] = 'invalid-test-password';
    file_put_contents($configPath, '<?php return ' . var_export($badConfig,true) . ';');
    [$code,$body] = request('habitaciones.php');
    check($code === 503 && !str_contains($body,'SQLSTATE') && !str_contains($body,'invalid-test-password'), 'error DB sin detalles');
    echo "OK: $checks verificaciones de reglas, concurrencia y HTTP.\n";
} finally {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    foreach ($workers as $worker) if (is_resource($worker)) { proc_terminate($worker); proc_close($worker); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $hotel = null; $pdo = null;
    foreach (glob($runtime . '/uploads-' . $schema . '/*') ?: [] as $upload) unlink($upload);
    if (is_dir($runtime . '/uploads-' . $schema)) rmdir($runtime . '/uploads-' . $schema);
    // Solo la base efímera creada por ESTA ejecución. Nunca hotel_aurora.
    if ($created && preg_match('/^hotel_test_[a-f0-9]{10}$/D', $schema)) $control->exec('DROP DATABASE ' . $schema);
    if (is_file($runtime . '/config.php')) unlink($runtime . '/config.php');
    foreach (glob($runtime . '/*.cookies') as $cookie) unlink($cookie);
}
