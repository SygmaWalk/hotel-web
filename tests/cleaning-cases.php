<?php
// Solo la base temporal creada por run.php.
$cleanRoom = $hotel->saveRoom([...$room, 'code'=>'T-CLEAN'], null);
$cleanActor = (int)$pdo->query("SELECT id FROM users WHERE email='staff@example.test'")->fetchColumn();
check($hotel->pendingCleaning($cleanRoom) === null, 'habitación nueva sin limpieza pendiente');
$historical->execute([$cleanRoom, 'Prueba limpieza', 'clean@example.test', $day(-2), $day(0), '10', bin2hex(random_bytes(32))]);
$departing = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO stays (reservation_id, checked_in_at, checked_in_by) VALUES (?, ?, ?)')->execute([$departing,$day(-2).' 12:00:00',$cleanActor]);
// Una falla al crear la tarea debe revertir también la salida.
$pdo->exec("CREATE TRIGGER test_cleaning_failure BEFORE INSERT ON room_cleanings FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo de prueba aislado'");
try {
    $rolledBack = false;
    try { $hotel->recordStay($departing, 'checkout', $cleanActor); } catch (PDOException $error) { $rolledBack = true; }
    check($rolledBack && $hotel->stay($departing)['checked_out_at'] === null && $hotel->pendingCleaning($cleanRoom) === null, 'salida y limpieza se revierten juntas ante fallo');
} finally { $pdo->exec('DROP TRIGGER test_cleaning_failure'); }
check(request('solicitud.php?id='.$departing,['csrf'=>$staffCsrf,'action'=>'checkout'],'staff','POST')[0] === 303, 'salida HTTP crea limpieza');
$task = $hotel->pendingCleaning($cleanRoom);
check((int)$task['reservation_id'] === $departing && $task['pending_at'] === $hotel->stay($departing)['checked_out_at'], 'tarea vinculada a salida y hora real');
check(str_contains(request('solicitud.php?id='.$departing,[],'staff')[1], 'quedó pendiente de limpieza'), 'salida informa tarea pendiente');
rejected(fn() => $hotel->recordStay($departing,'checkout',$cleanActor), 'salida repetida no crea otra tarea');
check((int)$pdo->query('SELECT COUNT(*) FROM room_cleanings WHERE reservation_id='.$departing)->fetchColumn() === 1, 'una tarea por salida');
$nextCleanData = [...$valid, 'room_id'=>(string)$cleanRoom, 'check_in'=>$day(0), 'check_out'=>$day(1)];
$nextCleanId = $hotel->createReservation($nextCleanData,bin2hex(random_bytes(32)),$cleanActor);
$futureCleanId = $hotel->createReservation([...$nextCleanData,'check_in'=>$day(10),'check_out'=>$day(11)],bin2hex(random_bytes(32)),$cleanActor);
check($hotel->request($futureCleanId)['status'] === 'confirmed', 'pendiente de limpieza admite reserva futura');
check(in_array($cleanRoom,array_column($hotel->availableRooms($day(12),$day(13)),'id')), 'limpieza no bloquea consulta futura');
rejected(fn() => $hotel->recordStay($nextCleanId,'checkin',$cleanActor), 'servidor bloquea llegada sin limpieza');
check($hotel->stay($nextCleanId) === null, 'llegada rechazada no deja estadía');
check(request('solicitud.php?id='.$nextCleanId,['csrf'=>$staffCsrf,'action'=>'checkin'],'staff','POST')[0] === 409, 'POST directo no evita bloqueo de limpieza');
$nextHtml = request('solicitud.php?id='.$nextCleanId,[],'staff')[1];
check(str_contains($nextHtml,'disabled>Registrar llegada') && str_contains($nextHtml,'Gestionar limpieza'), 'detalle deshabilita llegada y permite gestionar limpieza');
$cleanOverview = array_values(array_filter($hotel->cleaningRooms(),fn($r)=>(int)$r['id']===$cleanRoom));
check(count($cleanOverview) === 1 && (int)$cleanOverview[0]['reservation_id'] === $departing, 'una fila actual por habitación');
check($cleanOverview[0]['next_check_in'] === $day(0), 'limpieza identifica la primera llegada pendiente de registro');
$cleanDashboard = request('dashboard.php?date='.$day(40),[],'staff')[1];
check(str_contains($cleanDashboard,'T-CLEAN') && str_contains($cleanDashboard,'Limpieza ahora'), 'panel muestra limpieza actual aun al consultar futuro');
$roomAnchor = 'limpieza-habitacion-' . $cleanRoom;
check(str_contains($cleanDashboard,'limpieza.php#'.$roomAnchor.'"') && str_contains(request('limpieza.php',[],'staff')[1],'id="'.$roomAnchor.'"'), 'desde Hoy se llega a la habitación que requiere limpieza');
$cleanCalendar = $hotel->calendar($day(10),$day(12),$cleanRoom);
check((int)$cleanCalendar['rooms'][0]['cleaning_pending'] === 1 && $cleanCalendar['cells'][$cleanRoom][$day(10)]['state'] === 'reserved' && $cleanCalendar['cells'][$cleanRoom][$day(12)]['state'] === 'free', 'calendario separa limpieza actual de disponibilidad futura');
[$code,$jsonText] = request('calendario-datos.php?month='.substr($day(10),0,7).'&room_id='.$cleanRoom,[],'staff');
$cleanJson = json_decode($jsonText,true,512,JSON_THROW_ON_ERROR);
check($code === 200 && (int)$cleanJson['rooms'][0]['cleaning_pending'] === 1 && !str_contains($jsonText,'completed_actor'), 'JSON expone solo estado operativo sin empleado');
check(str_contains(request('calendario.php?room_id='.$cleanRoom,[],'staff')[1],'Ahora: pendiente de limpieza'), 'tabla sin JavaScript muestra estado actual');
check(request('limpieza.php')[0] === 303, 'limpieza requiere sesión');
$guestCleaningCsrf = token(request('login.php')[1]);
$cleanPost = ['action'=>'complete','room_id'=>(string)$cleanRoom,'reservation_id'=>(string)$departing,'csrf'=>$staffCsrf];
check(request('limpieza.php',[...$cleanPost,'csrf'=>$guestCleaningCsrf],'guest','POST')[0] === 303 && $hotel->pendingCleaning($cleanRoom)!==null, 'visitante no puede completar limpieza');
foreach (['staff','admin'] as $role) check(request('limpieza.php',[],$role)[0] === 200, 'pantalla limpieza para '.$role);
check(request('limpieza.php',[...$cleanPost,'csrf'=>'bad'],'staff','POST')[0] === 403, 'CSRF limpieza obligatorio');
check(request('limpieza.php',[...$cleanPost,'room_id'=>['1']],'staff','POST')[0] === 422, 'identificador array rechazado');
check(request('limpieza.php',[...$cleanPost,'action'=>'inventada'],'staff','POST')[0] === 422, 'acción inválida rechazada');
check(request('limpieza.php',[...$cleanPost,'room_id'=>(string)$receptionRoom],'staff','POST')[0] === 409, 'tarea no puede completarse para otra habitación');
check(request('limpieza.php?'.http_build_query($cleanPost),[],'staff')[0] === 200 && $hotel->pendingCleaning($cleanRoom)!==null, 'GET nunca modifica limpieza');
rejected(fn() => $hotel->completeCleaning($cleanRoom,$departing,999999), 'actor inexistente no completa limpieza');
rejected(fn() => $hotel->completeCleaning(999999,$departing,$cleanActor), 'habitación inexistente rechazada');
$reservationsBeforeCleaning = $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll();
check(request('limpieza.php',[...$cleanPost,'completed_by'=>(string)$adminId],'staff','POST')[0] === 303, 'recepción marca lista por POST');
$completed = $pdo->query('SELECT * FROM room_cleanings WHERE reservation_id='.$departing)->fetch();
check($hotel->pendingCleaning($cleanRoom) === null && $completed['completed_at'] !== null && (int)$completed['completed_by'] === $cleanActor, 'limpieza guarda fecha y actor de sesión');
check($pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll() === $reservationsBeforeCleaning, 'limpieza conserva todas las reservas');
check(request('limpieza.php',$cleanPost,'staff','POST')[0] === 409 && $pdo->query('SELECT * FROM room_cleanings WHERE reservation_id='.$departing)->fetch() === $completed, 'doble envío no sobrescribe auditoría');
check(str_contains(request('limpieza.php',[],'staff')[1],'staff@example.test'), 'historial muestra responsable');
check(request('solicitud.php?id='.$nextCleanId,['csrf'=>$staffCsrf,'action'=>'checkin'],'staff','POST')[0] === 303, 'habitación lista permite llegada');
$hotel->recordStay($nextCleanId,'checkout',$cleanActor);
check((int)$hotel->pendingCleaning($cleanRoom)['reservation_id'] === $nextCleanId, 'siguiente salida genera nueva tarea');
check(request('limpieza.php',$cleanPost,'staff','POST')[0] === 409 && (int)$hotel->pendingCleaning($cleanRoom)['reservation_id'] === $nextCleanId, 'formulario viejo no completa limpieza nueva');
$pdo->exec('UPDATE rooms SET active=0 WHERE id='.$cleanRoom);
$hotel->completeCleaning($cleanRoom,$nextCleanId,$adminId);
check((int)$hotel->room($cleanRoom)['active'] === 0 && $hotel->pendingCleaning($cleanRoom) === null, 'limpiar habitación inactiva no la reactiva');
$beforeMigration = $pdo->query('SELECT * FROM room_cleanings ORDER BY reservation_id')->fetchAll();
$pdo->exec(file_get_contents($root.'/database/cleaning.sql'));
$pdo->exec(file_get_contents($root.'/database/cleaning.sql'));
check($pdo->query('SELECT * FROM room_cleanings ORDER BY reservation_id')->fetchAll() === $beforeMigration && $hotel->request($futureCleanId)['status'] === 'confirmed', 'migración repetida conserva limpiezas y reservas');

// Dos empleados completando la misma tarea y dos salidas simultáneas.
$cleanRaceRoom = $hotel->saveRoom([...$room,'code'=>'T-CLEAN-RACE'],null);
$cleanRaceId = $hotel->createReservation([...$nextCleanData,'room_id'=>(string)$cleanRaceRoom],bin2hex(random_bytes(32)),$cleanActor);
$hotel->recordStay($cleanRaceId,'checkin',$cleanActor);
foreach (['checkout','clean'] as $raceAction) {
    $pdo->beginTransaction(); $pdo->query('SELECT id FROM rooms WHERE id='.$cleanRaceRoom.' FOR UPDATE');
    foreach ([$cleanActor,$adminId] as $i=>$actor) {
        $ready = $runtime.'/clean-ready-'.$i; $jobPath = $runtime.'/clean-job-'.$i.'.json';
        if (is_file($ready)) unlink($ready);
        file_put_contents($jobPath,json_encode(['action'=>$raceAction,'room'=>$cleanRaceRoom,'reservation'=>$cleanRaceId,'actor'=>$actor],JSON_THROW_ON_ERROR));
        $workers[] = proc_open([PHP_BINARY,__DIR__.'/cleaning-worker.php',$configPath,$jobPath,$ready],
            [0=>['pipe','r'],1=>['file',$runtime.'/worker.log','a'],2=>['file',$runtime.'/worker.log','a']],$pipes); fclose($pipes[0]);
    }
    $deadline = microtime(true)+10;
    while ((!is_file($runtime.'/clean-ready-0') || !is_file($runtime.'/clean-ready-1')) && microtime(true)<$deadline) usleep(30000);
    check(is_file($runtime.'/clean-ready-0') && is_file($runtime.'/clean-ready-1'), 'dos procesos preparados '.$raceAction);
    $pdo->commit(); $codes = [];
    foreach ($workers as $worker) $codes[] = proc_close($worker);
    $workers = []; sort($codes);
    check($codes === [0,2], 'una sola transición concurrente '.$raceAction);
    $row = $pdo->query('SELECT * FROM room_cleanings WHERE reservation_id='.$cleanRaceId)->fetch();
    check($row && ($raceAction === 'clean' ? $row['completed_at'] !== null : $row['completed_at'] === null), 'estado consistente después de '.$raceAction);
    foreach ([0,1] as $i) { unlink($runtime.'/clean-ready-'.$i); unlink($runtime.'/clean-job-'.$i.'.json'); }
}
