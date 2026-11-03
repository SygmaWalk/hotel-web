<?php
// Todas las escrituras usan la base temporal creada por run.php.
$moveActor = (int)$pdo->query("SELECT id FROM users WHERE email='staff@example.test'")->fetchColumn();
$moveSource = $hotel->saveRoom([...$room,'code'=>'T-MOVE-A','nightly_rate'=>'100.25'],null);
$moveTarget = $hotel->saveRoom([...$room,'code'=>'T-MOVE-B','nightly_rate'=>'200.50'],null);
$moveData = [...$valid,'room_id'=>(string)$moveSource,'check_in'=>$day(30),'check_out'=>$day(32)];
$moveId = $hotel->createReservation($moveData,bin2hex(random_bytes(32)),$adminId);
$originalMove = $hotel->request($moveId);
$proposal = ['room_id'=>(string)$moveSource,'check_in'=>$day(31),'check_out'=>$day(34),'reason'=>'El huésped cambió las fechas.'];
check(in_array($moveSource,array_column($hotel->availableRooms($day(31),$day(34),$moveId),'id')), 'consulta excluye la propia reserva');
check(!in_array($moveSource,array_column($hotel->availableRooms($day(31),$day(34)),'id')), 'consulta normal sigue bloqueando la reserva');
foreach ([['check_in'=>$day(-1)],['check_out'=>$day(31)],['room_id'=>'0'],['room_id'=>['1']],['reason'=>'  '],['reason'=>str_repeat('á',501)]] as $bad) {
    rejected(fn() => $hotel->previewReschedule($moveId,[...$proposal,...$bad],$moveActor), 'revisión rechaza datos inválidos');
}
rejected(fn() => $hotel->previewReschedule($moveId,[...$proposal,...$moveData],$moveActor), 'revisión rechaza cambios vacíos');
rejected(fn() => $hotel->previewReschedule($moveId,$proposal,999999), 'revisión exige empleado');
rejected(fn() => $hotel->previewReschedule(999999,$proposal,$moveActor), 'revisión rechaza reserva inexistente');
$hotel->saveRoom([...$room,'code'=>'T-MOVE-A','nightly_rate'=>'150.00'],$moveSource);
$quote = $hotel->previewReschedule($moveId,$proposal,$moveActor);
check($quote['rate'] === '100.25' && $quote['old_total'] === 20050 && $quote['new_total'] === 30075, 'misma habitación conserva tarifa acordada y calcula centavos exactos');
check($hotel->request($moveId) === $originalMove && $hotel->reservationChanges($moveId) === [], 'revisar no modifica la reserva ni crea historial');
rejected(fn() => $hotel->rescheduleReservation($quote,false,$moveActor), 'guardar exige confirmación explícita');
rejected(fn() => $hotel->rescheduleReservation($quote,true,$adminId), 'propuesta pertenece al empleado');
rejected(fn() => $hotel->rescheduleReservation([...$quote,'expires'=>time()-1],true,$moveActor), 'propuesta vencida no guarda');
$hotel->rescheduleReservation($quote,true,$moveActor);
$moved = $hotel->request($moveId); $history = $hotel->reservationChanges($moveId);
check($moved['check_in'] === $day(31) && $moved['check_out'] === $day(34) && $moved['status'] === 'confirmed' && $moved['nightly_rate'] === '100.25', 'reprogramación conserva estado y cambia fechas');
check(count($history) === 1 && $history[0]['old_check_in'] === $day(30) && $history[0]['new_check_out'] === $day(34) && $history[0]['changed_actor'] === 'staff@example.test' && $history[0]['reason'] === $proposal['reason'], 'historial incluye antes después motivo y empleado');
foreach (['id','guest_name','email','created_at','submission_token','resolved_by','resolved_at'] as $key) check($moved[$key] === $originalMove[$key], 'reprogramación conserva '.$key);
rejected(fn() => $hotel->rescheduleReservation($quote,true,$moveActor), 'doble envío no duplica historial');
check(count($hotel->reservationChanges($moveId)) === 1, 'doble envío conserva un solo cambio');
$returnQuote = $hotel->previewReschedule($moveId,[...$proposal,'check_in'=>$day(30),'check_out'=>$day(32)],$moveActor);
$hotel->rescheduleReservation($returnQuote,true,$moveActor);
rejected(fn() => $hotel->rescheduleReservation($quote,true,$moveActor), 'formulario antiguo no se acepta después de ida y vuelta');
$toOther = [...$proposal,'room_id'=>(string)$moveTarget];
$otherQuote = $hotel->previewReschedule($moveId,$toOther,$moveActor);
check($otherQuote['rate'] === '200.50' && $otherQuote['new_total'] === 60150, 'otra habitación usa tarifa vigente');
$hotel->saveRoom([...$room,'code'=>'T-MOVE-B','nightly_rate'=>'250.75'],$moveTarget);
rejected(fn() => $hotel->rescheduleReservation($otherQuote,true,$moveActor), 'cambio de tarifa requiere nueva revisión');
$otherQuote = $hotel->previewReschedule($moveId,$toOther,$moveActor);
$conflictMoveId = $hotel->createReservation([...$moveData,'room_id'=>(string)$moveTarget,'check_in'=>$day(32),'check_out'=>$day(33)],bin2hex(random_bytes(32)),$adminId);
rejected(fn() => $hotel->rescheduleReservation($otherQuote,true,$moveActor), 'conflicto posterior a revisión no sobrescribe reserva');
rejected(fn() => $hotel->previewReschedule($moveId,$toOther,$moveActor), 'consulta rechaza solapamiento');
$hotel->cancelReservation($conflictMoveId,'Libera prueba',true,$adminId);
$hotel->deactivate($moveTarget,true);
rejected(fn() => $hotel->rescheduleReservation($otherQuote,true,$moveActor), 'destino desactivado no acepta cambio');
$pdo->exec('UPDATE rooms SET active=1 WHERE id='.$moveTarget);
$beforeMoveFailure = $hotel->request($moveId); $historyBeforeFailure = $hotel->reservationChanges($moveId);
$pdo->exec("CREATE TRIGGER test_move_failure BEFORE UPDATE ON reservations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo aislado'");
try {
    $failedMove = false;
    try { $hotel->rescheduleReservation($otherQuote,true,$moveActor); } catch (PDOException $error) { $failedMove = true; }
    check($failedMove && $hotel->request($moveId) === $beforeMoveFailure && $hotel->reservationChanges($moveId) === $historyBeforeFailure, 'fallo revierte historial y reprogramación juntos');
} finally { $pdo->exec('DROP TRIGGER test_move_failure'); }
$hotel->rescheduleReservation($otherQuote,true,$moveActor);
check((int)$hotel->request($moveId)['room_id'] === $moveTarget && $hotel->request($moveId)['nightly_rate'] === '250.75', 'cambio de habitación e importe guardados');
check($hotel->calendar($day(30),$day(34),$moveSource)['cells'][$moveSource][$day(30)]['state'] === 'free', 'habitación anterior libera las fechas');
check((int)$hotel->calendar($day(31),$day(34),$moveTarget)['cells'][$moveTarget][$day(31)]['stays'][0]['id'] === $moveId, 'calendario muestra misma reserva en nuevo destino');
check(in_array($moveId,array_column($hotel->dashboard($day(31))['arrivals'],'id')), 'panel refleja nueva llegada');
$pdo->exec('UPDATE rooms SET active=0 WHERE id='.$moveTarget);
$escapeInactive = $hotel->previewReschedule($moveId,[...$proposal,'room_id'=>(string)$moveSource],$moveActor);
$hotel->rescheduleReservation($escapeInactive,true,$moveActor);
check((int)$hotel->room($moveTarget)['active'] === 0 && (int)$hotel->request($moveId)['room_id'] === $moveSource, 'permite salir de habitación inactiva sin reactivarla');
$pdo->exec('UPDATE rooms SET active=1 WHERE id='.$moveTarget);

$movePage = 'reserva-reprogramar.php?id='.$moveId;
check(request($movePage)[0] === 303, 'pantalla de reprogramación requiere sesión');
$guestMoveCsrf = token(request('login.php')[1]);
$movePost = [...$toOther,'reason'=>"<script>alert('cambio')</script>",'csrf'=>$staffCsrf,'action'=>'preview'];
check(request($movePage,[...$movePost,'csrf'=>$guestMoveCsrf],'guest','POST')[0] === 303, 'visitante no revisa cambios');
check(request($movePage,[...$movePost,'csrf'=>'bad'],'staff','POST')[0] === 403, 'revisión requiere CSRF');
check(request('reserva-reprogramar.php?id[]=1',[],'staff')[0] === 404, 'identificador inválido rechazado');
check(request($movePage.'&check_in[]=1',[],'staff')[0] === 422, 'fecha array rechazada');
check(request($movePage,[...$movePost,'action'=>'unknown'],'staff','POST')[0] === 422, 'acción inválida rechazada');
foreach (['staff','admin'] as $client) check(request($movePage,[],$client)[0] === 200, 'reprogramación disponible para '.$client);
[$code,$html] = request($movePage,$movePost,'staff','POST');
check($code === 200 && str_contains($html,'Revisá el cambio') && str_contains($html,'Diferencia total') && str_contains($html,'&lt;script&gt;') && !str_contains($html,"<script>alert('cambio')"), 'revisión muestra comparación y escapa motivo');
$moveToken = token($html,'quote_token');
$moveSave = ['action'=>'save','quote_token'=>$moveToken,'acknowledged'=>'1','csrf'=>$staffCsrf];
check(request($movePage,[...$moveSave,'csrf'=>'bad'],'staff','POST')[0] === 403, 'guardar requiere CSRF');
check(request($movePage,[...$moveSave,'quote_token'=>'inventado'],'staff','POST')[0] === 403, 'no acepta propuesta inventada');
check(request($movePage,[...$moveSave,'csrf'=>$adminCsrf],'admin','POST')[0] === 403, 'no acepta propuesta de otra sesión');
check(request('reserva-reprogramar.php?id='.$conflictMoveId,$moveSave,'staff','POST')[0] === 403, 'propuesta no sirve para otra reserva');
check(request($movePage,[...$moveSave,'acknowledged'=>''],'staff','POST')[0] === 409, 'guardar requiere casilla de confirmación');
check(request($movePage,[...$moveSave,'room_id'=>(string)$moveSource,'nightly_rate'=>'0','changed_by'=>(string)$adminId,'check_in'=>$day(80)],'staff','POST')[0] === 303, 'guardar usa propuesta del servidor');
$httpMoved = $hotel->request($moveId); $httpHistory = $hotel->reservationChanges($moveId);
check((int)$httpMoved['room_id'] === $moveTarget && $httpMoved['nightly_rate'] === '250.75' && $httpMoved['check_in'] === $toOther['check_in'] && (int)$httpHistory[0]['changed_by'] === $moveActor, 'ignora fechas precio habitación y empleado falsificados');
check(request($movePage,$moveSave,'staff','POST')[0] === 403 && count($hotel->reservationChanges($moveId)) === count($httpHistory), 'reenvío de propuesta consumida no duplica cambio');
$historyHtml = request('solicitud.php?id='.$moveId,[],'staff')[1];
check(str_contains($historyHtml,'Historial de reprogramaciones') && str_contains($historyHtml,'&lt;script&gt;') && !str_contains($historyHtml,"<script>alert('cambio')"), 'detalle muestra historial escapado');
$moveJson = json_decode(request('calendario-datos.php?month='.substr($day(31),0,7).'&room_id='.$moveTarget,[],'staff')[1],true,512,JSON_THROW_ON_ERROR);
check((int)$moveJson['cells'][$moveTarget][$day(31)]['stays'][0]['id'] === $moveId, 'calendario JSON refleja reprogramación');
$staleQuote = $hotel->previewReschedule($moveId,[...$proposal,'check_in'=>$day(40),'check_out'=>$day(42)],$moveActor);
$hotel->cancelReservation($moveId,'Cancelación posterior',true,$moveActor);
rejected(fn() => $hotel->rescheduleReservation($staleQuote,true,$moveActor), 'cancelación posterior invalida propuesta');
check(request($movePage,[],'staff')[0] === 409 && !str_contains(request('solicitud.php?id='.$moveId,[],'staff')[1],'>Reprogramar reserva</a>'), 'cancelada no ofrece reprogramar');
check(count($hotel->reservationChanges($moveId)) === count($httpHistory), 'cancelación conserva historial de cambios');
$pendingMove = $hotel->submit([...$moveData,'check_in'=>$day(50),'check_out'=>$day(51)],bin2hex(random_bytes(32)));
rejected(fn() => $hotel->previewReschedule($pendingMove,$proposal,$moveActor), 'pendiente no se reprograma');
$hotel->resolve($pendingMove,'rejected',$moveActor);
rejected(fn() => $hotel->previewReschedule($pendingMove,$proposal,$moveActor), 'rechazada no se reprograma');
$stayMoveId = $hotel->createReservation([...$moveData,'check_in'=>$day(0),'check_out'=>$day(1)],bin2hex(random_bytes(32)),$moveActor);
$stayQuote = $hotel->previewReschedule($stayMoveId,[...$proposal,'room_id'=>(string)$moveTarget],$moveActor);
$hotel->recordStay($stayMoveId,'checkin',$moveActor);
foreach (['open','closed'] as $state) {
    if ($state === 'closed') $hotel->recordStay($stayMoveId,'checkout',$moveActor);
    rejected(fn() => $hotel->previewReschedule($stayMoveId,$proposal,$moveActor), 'estadía '.$state.' bloquea revisión');
    rejected(fn() => $hotel->rescheduleReservation($stayQuote,true,$moveActor), 'estadía '.$state.' invalida propuesta anterior');
    check(request('reserva-reprogramar.php?id='.$stayMoveId,[],'staff')[0] === 409, 'HTTP bloquea reprogramación con estadía '.$state);
}
$allHistory = $pdo->query('SELECT * FROM reservation_changes ORDER BY id')->fetchAll();
$allReservations = $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll();
$pdo->exec(file_get_contents($root.'/database/rescheduling.sql'));
$pdo->exec(file_get_contents($root.'/database/rescheduling.sql'));
check($pdo->query('SELECT * FROM reservation_changes ORDER BY id')->fetchAll() === $allHistory && $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll() === $allReservations, 'migración repetida conserva reservas e historial');

// Competencia con otra reprogramación, ingreso y alta para la habitación de destino.
foreach (['move','checkin','create'] as $mode) {
    $origin = $hotel->saveRoom([...$room,'code'=>'T-M-'.$mode.'-A'],null);
    $destination = $hotel->saveRoom([...$room,'code'=>'T-M-'.$mode.'-B'],null);
    $raceData = [...$moveData,'room_id'=>(string)$origin,'check_in'=>$day(0),'check_out'=>$day(1)];
    $raceId = $hotel->createReservation($raceData,bin2hex(random_bytes(32)),$moveActor);
    $raceProposal = ['room_id'=>(string)$destination,'check_in'=>$day(0),'check_out'=>$day(1),'reason'=>'Cambio concurrente'];
    $raceQuote = $hotel->previewReschedule($raceId,$raceProposal,$moveActor);
    $jobs = [['action'=>'move','quote'=>$raceQuote,'actor'=>$moveActor]];
    $jobs[] = $mode === 'move' ? ['action'=>'move','quote'=>$hotel->previewReschedule($raceId,$raceProposal,$adminId),'actor'=>$adminId]
        : ['action'=>$mode,'id'=>$raceId,'data'=>[...$raceData,'room_id'=>(string)$destination],'actor'=>$adminId];
    $pdo->beginTransaction();
    foreach ([$origin,$destination] as $lockId) $pdo->query('SELECT id FROM rooms WHERE id='.$lockId.' FOR UPDATE');
    foreach ($jobs as $i=>$job) {
        $jobPath = $runtime.'/move-job-'.$i.'.json'; $ready = $runtime.'/move-ready-'.$i;
        if (is_file($ready)) unlink($ready);
        file_put_contents($jobPath,json_encode($job,JSON_THROW_ON_ERROR));
        $workers[] = proc_open([PHP_BINARY,__DIR__.'/reschedule-worker.php',$configPath,$jobPath,$ready],
            [0=>['pipe','r'],1=>['file',$runtime.'/worker.log','a'],2=>['file',$runtime.'/worker.log','a']],$pipes); fclose($pipes[0]);
    }
    $deadline=microtime(true)+10;
    while ((!is_file($runtime.'/move-ready-0') || !is_file($runtime.'/move-ready-1')) && microtime(true)<$deadline) usleep(30000);
    check(is_file($runtime.'/move-ready-0') && is_file($runtime.'/move-ready-1'), 'procesos listos reprogramación frente a '.$mode);
    $pdo->commit(); $codes=[];
    foreach ($workers as $worker) $codes[]=proc_close($worker);
    $workers=[]; sort($codes);
    check($codes === [0,2], 'una operación ganadora reprogramación frente a '.$mode);
    $raceMoved = (int)$hotel->request($raceId)['room_id'] === $destination;
    check(count($hotel->reservationChanges($raceId)) === ($raceMoved ? 1 : 0), 'historial coincide con resultado concurrente');
    if ($mode === 'checkin') check($raceMoved ? $hotel->stay($raceId) === null : $hotel->stay($raceId) !== null, 'ingreso no usa habitación anterior a la reprogramación');
    if ($mode === 'create') check((int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status='confirmed' AND room_id=".$destination)->fetchColumn() === 1, 'destino no queda sobrevendido');
    foreach ([0,1] as $i) { unlink($runtime.'/move-job-'.$i.'.json'); unlink($runtime.'/move-ready-'.$i); }
}
