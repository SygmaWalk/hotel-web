<?php
// Aislar los casos en habitaciones propias; la suite usa una base temporal.
$receptionRoom = $hotel->saveRoom([...$room, 'code'=>'T-DESK'], null);
$receptionActor = (int) $pdo->query("SELECT id FROM users WHERE email = 'staff@example.test'")->fetchColumn();
$deskData = [...$valid, 'room_id'=>(string) $receptionRoom, 'check_in'=>$day(40), 'check_out'=>$day(43)];
$deskToken = bin2hex(random_bytes(32));
$deskId = $hotel->createReservation([...$deskData, 'nightly_rate'=>'1', 'status'=>'pending', 'resolved_by'=>'0'], $deskToken, $receptionActor);
$deskReservation = $hotel->request($deskId);
check($deskReservation['status'] === 'confirmed' && (int) $deskReservation['resolved_by'] === $receptionActor && $deskReservation['resolved_at'] !== null, 'recepción crea confirmada con actor y fecha');
check($deskReservation['nightly_rate'] === $room['nightly_rate'], 'tarifa y estado controlados por servidor');
check($hotel->stay($deskId) === null, 'crear no registra llegada');
$beforeDesk = count($hotel->requests());
check($hotel->createReservation($deskData, $deskToken, $receptionActor) === $deskId && count($hotel->requests()) === $beforeDesk, 'reintento idéntico sin duplicado');
rejected(fn() => $hotel->createReservation([...$deskData, 'guest_name'=>'Otro huésped'], $deskToken, $receptionActor), 'token usado no cambia datos');
rejected(fn() => $hotel->createReservation($deskData, $deskToken, $adminId), 'token usado pertenece al actor');
rejected(fn() => $hotel->createReservation($deskData, bin2hex(random_bytes(32)), 999999), 'actor inexistente rechazado');
foreach ([['check_in'=>$day(-1)], ['check_out'=>$day(40)], ['check_out'=>(new DateTimeImmutable(bookingLimit()))->modify('+1 day')->format('Y-m-d')], ['room_id'=>'999999'], ['room_id'=>(string)$roomId], ['email'=>'incorrecto'], ['room_id'=>['1']]] as $bad) {
    rejected(fn() => $hotel->createReservation([...$deskData, ...$bad], bin2hex(random_bytes(32)), $receptionActor), 'alta recepción rechaza datos inválidos');
}
foreach ([[$day(39),$day(41)], [$day(42),$day(44)], [$day(41),$day(42)], [$day(39),$day(44)], [$day(40),$day(43)]] as [$start,$end]) {
    rejected(fn() => $hotel->createReservation([...$deskData, 'check_in'=>$start, 'check_out'=>$end], bin2hex(random_bytes(32)), $receptionActor), 'solapamiento recepción rechazado');
}
check(count($hotel->requests()) === $beforeDesk, 'errores no dejan solicitudes pendientes ni reservas parciales');
$availableIds = fn($start,$end) => array_column($hotel->availableRooms($start,$end), 'id');
check(!in_array($receptionRoom, $availableIds($day(41),$day(42))), 'consulta excluye habitación ocupada');
check(in_array($receptionRoom, $availableIds($day(43),$day(44))), 'consulta permite entrada el día de salida');
check(!in_array($roomId, $availableIds($day(43),$day(44))), 'consulta excluye habitación inactiva');
rejected(fn() => $hotel->availableRooms($day(44),$day(43)), 'consulta rechaza intervalo invertido');
$adjacentDesk = $hotel->createReservation([...$deskData, 'check_in'=>$day(43), 'check_out'=>$day(44)], bin2hex(random_bytes(32)), $adminId);
check($hotel->request($adjacentDesk)['status'] === 'confirmed', 'administración crea reserva contigua');
$pendingDesk = $hotel->submit([...$deskData, 'check_in'=>$day(45), 'check_out'=>$day(46)], bin2hex(random_bytes(32)));
check(in_array($receptionRoom, $availableIds($day(45),$day(46))), 'solicitud pendiente no bloquea consulta');
$hotel->createReservation([...$deskData, 'check_in'=>$day(45), 'check_out'=>$day(46)], bin2hex(random_bytes(32)), $receptionActor);
rejected(fn() => $hotel->resolve($pendingDesk, 'confirmed', $adminId), 'confirmación pública respeta reserva manual');
$limitEntry = (new DateTimeImmutable(bookingLimit()))->modify('-1 day')->format('Y-m-d');
$limitDesk = $hotel->createReservation([...$deskData, 'check_in'=>$limitEntry, 'check_out'=>bookingLimit()], bin2hex(random_bytes(32)), $receptionActor);
check($hotel->request($limitDesk)['check_out'] === bookingLimit(), 'alta acepta salida en límite anual');

// Un huésped atrasado bloquea ingresos de hoy, pero no toda la agenda futura.
$occupiedDeskRoom = $hotel->saveRoom([...$room, 'code'=>'T-DESK-OCC'], null);
$historical->execute([$occupiedDeskRoom, 'Salida pendiente', 'old@example.test', $day(-2), $day(0), '10', bin2hex(random_bytes(32))]);
$occupiedDeskId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO stays (reservation_id, checked_in_at, checked_in_by) VALUES (?, ?, ?)')->execute([$occupiedDeskId, $day(-2).' 12:00:00', $receptionActor]);
$todayDesk = [...$deskData, 'room_id'=>(string)$occupiedDeskRoom, 'check_in'=>$day(0), 'check_out'=>$day(1)];
check(!in_array($occupiedDeskRoom, $availableIds($day(0),$day(1))), 'estadía abierta bloquea disponibilidad hoy');
rejected(fn() => $hotel->createReservation($todayDesk, bin2hex(random_bytes(32)), $receptionActor), 'alta hoy exige salida registrada');
check(in_array($occupiedDeskRoom, $availableIds($day(1),$day(2))), 'estadía abierta no bloquea agenda futura sin solapamiento');
$hotel->recordStay($occupiedDeskId, 'checkout', $receptionActor);
check(in_array($occupiedDeskRoom, $availableIds($day(0),$day(1))), 'salida real libera consulta para hoy');
$hotel->createReservation($todayDesk, bin2hex(random_bytes(32)), $receptionActor);

check(request('reserva-nueva.php')[0] === 303, 'nueva reserva exige sesión');
check(request('reserva-nueva.php', ['csrf'=>token(request('login.php')[1])], 'guest', 'POST')[0] === 303, 'visitante no crea reserva por POST');
foreach (['staff','admin'] as $role) check(request('reserva-nueva.php', [], $role)[0] === 200, 'formulario disponible para '.$role);
check(request('reserva-nueva.php?check_in[]=2026-10-20', [], 'staff')[0] === 422, 'fecha array rechazada en consulta');
$deskFormHtml = request('reserva-nueva.php?check_in='.$day(50).'&room_id='.$receptionRoom, [], 'staff')[1];
check(str_contains($deskFormHtml, 'value="'.$day(51).'"') && str_contains($deskFormHtml, 'aria-current="page">Calendario'), 'acceso desde calendario precarga noche y navegación');
$deskForm = [...$deskData, 'check_in'=>$day(50), 'check_out'=>$day(52), 'csrf'=>token($deskFormHtml), 'submission_token'=>token($deskFormHtml,'submission_token')];
check(request('reserva-nueva.php', [...$deskForm, 'csrf'=>'incorrecto'], 'staff', 'POST')[0] === 403, 'CSRF requerido en alta recepción');
check(request('reserva-nueva.php', [...$deskForm, 'submission_token'=>bin2hex(random_bytes(32))], 'staff', 'POST')[0] === 403, 'token de formulario debe pertenecer a sesión');
$publicDeskHtml = request('reservar.php', [], 'staff')[1];
check(request('reserva-nueva.php', [...$deskForm, 'submission_token'=>token($publicDeskHtml,'submission_token')], 'staff', 'POST')[0] === 403, 'token público no autoriza alta directa');
[$code,$html] = request('reserva-nueva.php', [...$deskForm, 'guest_name'=>'<script>desk()</script>', 'email'=>'mal'], 'staff', 'POST');
check($code === 422 && str_contains($html,'&lt;script&gt;desk()&lt;/script&gt;') && !str_contains($html,'<script>desk()'), 'errores conservan datos y escapan HTML');
$beforeDeskHttp = count($hotel->requests());
[$code,$html,$headers] = request('reserva-nueva.php', [...$deskForm, 'resolved_by'=>(string)$adminId, 'status'=>'pending', 'nightly_rate'=>'1'], 'staff', 'POST');
check($code === 303 && str_contains($headers,'calendario.php?from='.$day(50)) && count($hotel->requests()) === $beforeDeskHttp+1, 'alta HTTP vuelve al calendario en fecha elegida');
$httpDeskId = (int) $pdo->query('SELECT MAX(id) FROM reservations')->fetchColumn();
check((int)$hotel->request($httpDeskId)['resolved_by'] === $receptionActor && $hotel->request($httpDeskId)['nightly_rate'] === $room['nightly_rate'], 'HTTP ignora actor y precio enviados por cliente');
check(request('reserva-nueva.php', $deskForm, 'staff', 'POST')[0] === 303 && count($hotel->requests()) === $beforeDeskHttp+1, 'doble envío HTTP idempotente');
$monthDesk = json_decode(request('calendario-datos.php?month='.substr($day(50),0,7), [], 'staff')[1],true,512,JSON_THROW_ON_ERROR);
check($monthDesk['cells'][$receptionRoom][$day(50)]['stays'][0]['id'] === $httpDeskId, 'reserva manual aparece en calendario JSON');
$deskDetail = request('solicitud.php?id='.$httpDeskId, [], 'staff')[1];
check(str_contains($deskDetail,'staff@example.test') && str_contains($deskDetail,'Ver en el calendario'), 'detalle muestra responsable y regreso al calendario');
$conflictHtml = request('reserva-nueva.php', [], 'staff')[1];
$conflictPost = [...$deskForm, 'submission_token'=>token($conflictHtml,'submission_token')];
[$code,$html] = request('reserva-nueva.php', $conflictPost, 'staff', 'POST');
check($code === 409 && str_contains($html, 'ya está ocupada') && str_contains($html, 'value="'.$deskData['guest_name'].'"'), 'conflicto de disponibilidad conserva datos y explica error');
check(count($hotel->requests()) === $beforeDeskHttp+1, 'conflicto HTTP no deja filas nuevas');
$freeHtml = request('calendario.php?from='.$day(55).'&to='.$day(55).'&room_id='.$receptionRoom, [], 'staff')[1];
check(str_contains($freeHtml, '+ Reservar') && str_contains($freeHtml,'reserva-nueva.php?check_in='), 'tabla sin JavaScript ofrece creación desde noche libre');
$busyHtml = request('calendario.php?from='.$day(50).'&to='.$day(50).'&room_id='.$receptionRoom, [], 'staff')[1];
check(!str_contains($busyHtml,'+ Reservar'), 'tabla ocupada no ofrece crear en esa noche');
$pastHtml = request('calendario.php?from='.$day(-1).'&to='.$day(-1).'&room_id='.$receptionRoom, [], 'staff')[1];
check(!str_contains($pastHtml,'+ Reservar'), 'tabla pasada no ofrece crear reservas');

// Competencia real: dos altas; alta frente a confirmación; reenvíos del mismo formulario.
foreach (['two-create','create-confirm','same-token'] as $raceMode) {
    $raceDeskRoom = $hotel->saveRoom([...$room, 'code'=>'T-D-'.strtoupper($raceMode)], null);
    $raceDeskData = [...$deskData, 'room_id'=>(string)$raceDeskRoom, 'check_in'=>$day(60), 'check_out'=>$day(62)];
    $raceDeskToken = bin2hex(random_bytes(32));
    $jobs = [['data'=>$raceDeskData,'token'=>$raceDeskToken,'actor'=>$receptionActor], ['data'=>$raceDeskData,'token'=>$raceMode === 'same-token' ? $raceDeskToken : bin2hex(random_bytes(32)),'actor'=>$receptionActor]];
    if ($raceMode === 'create-confirm') $jobs[1] = ['confirm'=>$hotel->submit($raceDeskData, bin2hex(random_bytes(32))),'actor'=>$adminId];
    $pdo->beginTransaction();
    $pdo->query('SELECT id FROM rooms WHERE id = '.$raceDeskRoom.' FOR UPDATE');
    foreach ($jobs as $i=>$job) {
        $jobPath = $runtime.'/desk-job-'.$i.'.json'; $readyPath = $runtime.'/desk-ready-'.$i;
        if (is_file($readyPath)) unlink($readyPath);
        file_put_contents($jobPath, json_encode($job,JSON_THROW_ON_ERROR));
        $workers[] = proc_open([PHP_BINARY,__DIR__.'/reception-worker.php',$configPath,$jobPath,$readyPath], [0=>['pipe','r'],1=>['file',$runtime.'/worker.log','a'],2=>['file',$runtime.'/worker.log','a']],$pipes);
        fclose($pipes[0]);
    }
    $deadline = microtime(true)+10;
    while ((!is_file($runtime.'/desk-ready-0') || !is_file($runtime.'/desk-ready-1')) && microtime(true)<$deadline) usleep(30000);
    check(is_file($runtime.'/desk-ready-0') && is_file($runtime.'/desk-ready-1'), 'procesos listos '.$raceMode);
    $pdo->commit();
    $exitCodes = [];
    foreach ($workers as $worker) $exitCodes[] = proc_close($worker);
    $workers = []; sort($exitCodes);
    check($exitCodes === ($raceMode === 'same-token' ? [0,0] : [0,2]), 'resultado concurrente '.$raceMode);
    $q = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE room_id = ? AND status = 'confirmed'"); $q->execute([$raceDeskRoom]);
    check((int)$q->fetchColumn() === 1, 'una sola confirmada '.$raceMode);
    foreach ([0,1] as $i) { unlink($runtime.'/desk-job-'.$i.'.json'); unlink($runtime.'/desk-ready-'.$i); }
}
