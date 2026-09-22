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
function request(string $page, array $post = [], string $client = 'guest', string $method = 'GET'): array {
    global $base, $runtime;
    $curl = curl_init($base . '/' . $page);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>true, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_COOKIEFILE=>$runtime . '/' . $client . '.cookies', CURLOPT_COOKIEJAR=>$runtime . '/' . $client . '.cookies', CURLOPT_TIMEOUT=>10]);
    if ($method === 'POST') { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post)); }
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

    // Servidor HTTP aislado: mismo código, otra base, ningún dato del hotel real.
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $base = 'http://' . $address;
    foreach (glob($runtime . '/*.cookies') as $cookie) unlink($cookie);
    $env = getenv(); $env['HOTEL_CONFIG_PATH'] = $configPath;
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
    check(request('solicitudes.php?status=inventado',[],'admin')[0] === 422, 'filtro inválido HTTP');
    check(str_contains(request('solicitudes.php?date='.$day(200),[],'admin')[1], 'No hay solicitudes'), 'estado vacío panel');
    check(request('solicitud.php?id=999999',[],'admin')[0] === 404, 'detalle inexistente');
    $httpId = (int) $pdo->query('SELECT MAX(id) FROM reservations')->fetchColumn();
    check(request('solicitud.php?id='.$httpId,['csrf'=>$adminCsrf,'action'=>'confirmed'],'admin','POST')[0] === 409, 'conflicto fechas HTTP');
    check(request('solicitud.php?id='.$httpId,['csrf'=>$adminCsrf,'action'=>'rejected'],'admin','POST')[0] === 303, 'rechazo HTTP');
    $staffForm = request('login.php',[],'staff')[1];
    check(request('login.php',['csrf'=>token($staffForm),'email'=>'staff@example.test','password'=>$staffPassword],'staff','POST')[0] === 303, 'login recepción');
    $staffBody = request('solicitudes.php',[],'staff')[1]; $staffCsrf=token($staffBody);
    check(request('habitaciones-admin.php',[],'staff')[0] === 403, 'recepción no administra');
    check(request('habitaciones-admin.php',['csrf'=>$staffCsrf,'action'=>'save',...$room],'staff','POST')[0] === 403, 'autorización POST servidor');
    check(request('habitaciones-admin.php',['csrf'=>$adminCsrf,'action'=>'save',...$room,'code'=>'T-HTTP'],'admin','POST')[0] === 303, 'alta HTTP');
    $httpRoom = (int)$pdo->query("SELECT id FROM rooms WHERE code='T-HTTP'")->fetchColumn();
    check(request('habitaciones-admin.php?id='.$httpRoom,['csrf'=>$adminCsrf,'action'=>'save',...$room,'code'=>'T-HTTP','nightly_rate'=>'0'],'admin','POST')[0] === 303, 'edición tarifa cero');
    check(request('habitaciones-admin.php?id='.$raceRoom,['csrf'=>$adminCsrf,'action'=>'deactivate'],'admin','POST')[0] === 422, 'advertencia HTTP reservas futuras');
    check(request('habitaciones-admin.php?id='.$raceRoom,['csrf'=>$adminCsrf,'action'=>'deactivate','acknowledged'=>'1'],'admin','POST')[0] === 303, 'baja aceptada HTTP');
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
    // Solo la base efímera creada por ESTA ejecución. Nunca hotel_aurora.
    if ($created && preg_match('/^hotel_test_[a-f0-9]{10}$/D', $schema)) $control->exec('DROP DATABASE ' . $schema);
    if (is_file($runtime . '/config.php')) unlink($runtime . '/config.php');
    foreach (glob($runtime . '/*.cookies') as $cookie) unlink($cookie);
}
