<?php
// Base temporal de run.php, nunca hotel_aurora.
$maintActor = (int)$pdo->query("SELECT id FROM users WHERE email='staff@example.test'")->fetchColumn();
$maintRoom = $hotel->saveRoom([...$room,'code'=>'T-MAINT'],null);
$maintData = ['room_id'=>(string)$maintRoom,'start_date'=>$day(10),'end_date'=>$day(13),'reason'=>'Reparación de aire acondicionado'];
$maintBooking = [...$valid,'room_id'=>(string)$maintRoom,'check_in'=>$day(10),'check_out'=>$day(13)];
$maintToken = bin2hex(random_bytes(32));
foreach ([['start_date'=>$day(-1)],['end_date'=>$day(10)],['end_date'=>'2027-02-30'],['room_id'=>['1']],['room_id'=>'999999'],['reason'=>' '],['reason'=>str_repeat('á',501)]] as $bad) {
    rejected(fn() => $hotel->inspectMaintenance([...$maintData,...$bad]),'mantenimiento rechaza datos inválidos');
}
rejected(fn() => $hotel->createMaintenance($maintData,$maintToken,false,$maintActor),'bloqueo exige confirmación');
rejected(fn() => $hotel->createMaintenance($maintData,$maintToken,true,999999),'bloqueo exige empleado válido');
$reservationConflict = $hotel->createReservation($maintBooking,bin2hex(random_bytes(32)),$maintActor);
$beforeConflict = $hotel->request($reservationConflict);
$review = $hotel->inspectMaintenance($maintData);
check(count($review['reservations']) === 1 && (int)$review['reservations'][0]['id'] === $reservationConflict,'revisión identifica reserva afectada');
check($hotel->request($reservationConflict) === $beforeConflict && !$hotel->maintenanceList(),'revisión no cambia reservas ni crea bloqueos');
rejected(fn() => $hotel->createMaintenance($maintData,$maintToken,true,$maintActor),'bloqueo no desplaza reserva confirmada');
$maintReviewPost = [...$maintData,'csrf'=>$staffCsrf,'action'=>'review'];
[$status,$html] = request('mantenimiento.php',$maintReviewPost,'staff','POST');
check($status === 200 && str_contains($html,'Reservas afectadas') && str_contains($html,'solicitud.php?id='.$reservationConflict) && !str_contains($html,'Guardar bloqueo'),'pantalla lista conflictos y no ofrece guardar');
$hotel->cancelReservation($reservationConflict,'Viaje cancelado',true,$maintActor);
$pendingMaintenance = $hotel->submit($maintBooking,bin2hex(random_bytes(32)));
$sourceMaintenance = $hotel->saveRoom([...$room,'code'=>'T-MAINT-SOURCE'],null);
$movingMaintenance = $hotel->createReservation([...$maintBooking,'room_id'=>(string)$sourceMaintenance],bin2hex(random_bytes(32)),$maintActor);
$maintenanceQuote = $hotel->previewReschedule($movingMaintenance,[...$maintBooking,'reason'=>'Cambio de habitación'],$maintActor);
$summaryBeforeMaintenance = $hotel->dashboard($day(10))['summary'];
$maintId = $hotel->createMaintenance($maintData,$maintToken,true,$maintActor);
check($hotel->createMaintenance($maintData,$maintToken,true,$maintActor) === $maintId && count($hotel->maintenanceList()) === 1,'reenvío crea un solo bloqueo');
rejected(fn() => $hotel->createMaintenance([...$maintData,'reason'=>'Otro motivo'],$maintToken,true,$maintActor),'token no acepta otros datos');
rejected(fn() => $hotel->createMaintenance($maintData,bin2hex(random_bytes(32)),true,$maintActor),'bloqueos no se superponen');
check(!in_array($maintRoom,array_column($hotel->availableRooms($day(9),$day(11)),'id')),'consulta excluye intervalo parcialmente bloqueado');
foreach ([[$day(9),$day(10)],[$day(13),$day(14)]] as [$from,$until]) check(in_array($maintRoom,array_column($hotel->availableRooms($from,$until),'id')),'límites adyacentes quedan libres');
rejected(fn() => $hotel->createReservation($maintBooking,bin2hex(random_bytes(32)),$maintActor),'recepción no reserva durante mantenimiento');
rejected(fn() => $hotel->submit($maintBooking,bin2hex(random_bytes(32))),'solicitud pública rechaza mantenimiento');
rejected(fn() => $hotel->resolve($pendingMaintenance,'confirmed',$maintActor),'solicitud anterior no confirma durante mantenimiento');
rejected(fn() => $hotel->previewReschedule($movingMaintenance,[...$maintBooking,'reason'=>'Cambio'],$maintActor),'reprogramación no ofrece destino bloqueado');
rejected(fn() => $hotel->rescheduleReservation($maintenanceQuote,true,$maintActor),'bloqueo posterior invalida propuesta anterior');
check((int)$hotel->request($movingMaintenance)['room_id'] === $sourceMaintenance,'conflicto conserva reserva original');
$calMaintenance = $hotel->calendar($day(9),$day(13),$maintRoom);
check($calMaintenance['cells'][$maintRoom][$day(10)]['state'] === 'maintenance' && $calMaintenance['cells'][$maintRoom][$day(13)]['state'] === 'free','calendario marca inicio inclusivo y fin exclusivo');
$maintJsonText = request('calendario-datos.php?month='.substr($day(10),0,7).'&room_id='.$maintRoom,[],'staff')[1];
$maintJson = json_decode($maintJsonText,true,512,JSON_THROW_ON_ERROR);
check($maintJson['cells'][$maintRoom][$day(10)]['state'] === 'maintenance' && !str_contains($maintJsonText,$maintData['reason']) && !str_contains($maintJsonText,$maintToken) && !str_contains($maintJsonText,'staff@example.test'),'JSON incluye estado sin motivo token ni empleado');
$maintCalendarHtml = request('calendario.php?from='.$day(10).'&to='.$day(10).'&room_id='.$maintRoom,[],'staff')[1];
check(str_contains($maintCalendarHtml,'mantenimiento.php?block_id='.$maintId.'#block-'.$maintId) && !str_contains($maintCalendarHtml,'+ Reservar'),'tabla sin JavaScript enlaza bloqueo y no permite reservar');
check(request('mantenimiento.php?block_id[]=1',[],'staff')[0] === 404 && request('mantenimiento.php?block_id=999999',[],'staff')[0] === 404,'enlace a bloqueo inválido rechazado');
check(str_contains(request('mantenimiento.php?block_id='.$maintId,[],'staff')[1],'id="block-'.$maintId.'"'),'enlace del calendario abre el bloqueo seleccionado');
$summaryWithMaintenance = $hotel->dashboard($day(10))['summary'];
check($summaryWithMaintenance['available'] === $summaryBeforeMaintenance['available']-1 && $summaryWithMaintenance['maintenance'] === 1 && $summaryWithMaintenance['reserved'] === $summaryBeforeMaintenance['reserved'],'panel descuenta mantenimiento sin sumarlo a reservas');
check(str_contains(request('dashboard.php?date='.$day(10),[],'staff')[1],'En mantenimiento'),'panel muestra estado por habitación');
check(request('mantenimiento.php')[0] === 303,'pantalla mantenimiento privada');
$guestMaintCsrf = token(request('login.php')[1]);
check(request('mantenimiento.php',[...$maintReviewPost,'csrf'=>$guestMaintCsrf],'guest','POST')[0] === 303,'visitante no revisa bloqueos');
foreach (['staff','admin'] as $client) check(request('mantenimiento.php',[],$client)[0] === 200,'mantenimiento disponible para '.$client);
check(request('mantenimiento.php',[...$maintReviewPost,'csrf'=>'bad'],'staff','POST')[0] === 403,'revisión requiere CSRF');
check(request('mantenimiento.php',[...$maintReviewPost,'room_id'=>['1']],'staff','POST')[0] === 422,'HTTP rechaza habitación array');
check(request('mantenimiento.php',['action'=>'create','proposal_token'=>'inventado','csrf'=>$staffCsrf],'staff','POST')[0] === 403,'creación exige propuesta de sesión');
$freeMaintData = [...$maintData,'start_date'=>$day(15),'end_date'=>$day(16),'reason'=>"<script>alert('m')</script>"];
[$status,$reviewHtml] = request('mantenimiento.php',[...$freeMaintData,'csrf'=>$staffCsrf,'action'=>'review'],'staff','POST');
check($status === 200 && str_contains($reviewHtml,'Guardar bloqueo') && str_contains($reviewHtml,'&lt;script&gt;') && !str_contains($reviewHtml,"<script>alert('m')"),'revisión libre escapa motivo');
$proposalToken = token($reviewHtml,'proposal_token');
$maintSave = ['action'=>'create','proposal_token'=>$proposalToken,'acknowledged'=>'1','csrf'=>$staffCsrf];
check(request('mantenimiento.php',[...$maintSave,'csrf'=>$adminCsrf],'admin','POST')[0] === 403,'propuesta no sirve en otra sesión');
check(request('mantenimiento.php',[...$maintSave,'acknowledged'=>''],'staff','POST')[0] === 409,'HTTP exige aceptación del bloqueo');
check(request('mantenimiento.php',[...$maintSave,'room_id'=>(string)$sourceMaintenance,'created_by'=>(string)$adminId],'staff','POST')[0] === 303,'HTTP crea con datos revisados');
$createdHttpBlock = $pdo->query('SELECT * FROM room_maintenance ORDER BY id DESC LIMIT 1')->fetch();
check((int)$createdHttpBlock['room_id'] === $maintRoom && (int)$createdHttpBlock['created_by'] === $maintActor,'ignora habitación y empleado enviados al guardar');
check(request('mantenimiento.php',$maintSave,'staff','POST')[0] === 303 && (int)$pdo->query('SELECT COUNT(*) FROM room_maintenance')->fetchColumn() === 2,'reenvío HTTP no duplica bloqueo');
$maintRelease = ['action'=>'release','block_id'=>(string)$maintId,'release_reason'=>'Reparación terminada','acknowledged'=>'1','csrf'=>$staffCsrf];
check(request('mantenimiento.php',[...$maintRelease,'csrf'=>'bad'],'staff','POST')[0] === 403,'levantar requiere CSRF');
check(request('mantenimiento.php',[...$maintRelease,'release_reason'=>' '],'staff','POST')[0] === 409,'levantar requiere motivo');
check(request('mantenimiento.php',[...$maintRelease,'acknowledged'=>''],'staff','POST')[0] === 409,'levantar requiere confirmación');
check(request('mantenimiento.php?'.http_build_query($maintRelease),[],'staff')[0] === 200 && count($hotel->maintenanceDuring($maintRoom,$day(10),$day(13))) === 1,'GET nunca levanta bloqueo');
rejected(fn() => $hotel->releaseMaintenance($maintId,'Motivo',true,999999),'levantar exige actor válido');
check(request('mantenimiento.php',[...$maintRelease,'released_by'=>(string)$adminId],'staff','POST')[0] === 303,'recepción levanta bloqueo');
$releasedMaintenance = $pdo->query('SELECT * FROM room_maintenance WHERE id='.$maintId)->fetch();
check((int)$releasedMaintenance['released_by'] === $maintActor && $releasedMaintenance['release_reason'] === 'Reparación terminada' && $releasedMaintenance['start_date'] === $maintData['start_date'],'levantamiento conserva fechas y registra motivo empleado hora');
check(request('mantenimiento.php',$maintRelease,'staff','POST')[0] === 409 && $pdo->query('SELECT * FROM room_maintenance WHERE id='.$maintId)->fetch() === $releasedMaintenance,'doble levantamiento no sobrescribe auditoría');
check($hotel->calendar($day(10),$day(10),$maintRoom)['cells'][$maintRoom][$day(10)]['state'] === 'free','levantamiento anticipado libera calendario futuro');
$hotel->resolve($pendingMaintenance,'confirmed',$maintActor);
check($hotel->request($pendingMaintenance)['status'] === 'confirmed','fechas liberadas permiten confirmar reserva');
$hotel->deactivate($maintRoom,true);
$hotel->releaseMaintenance((int)$createdHttpBlock['id'],'Reparación finalizada',true,$adminId);
check((int)$hotel->room($maintRoom)['active'] === 0,'levantar no reactiva habitación');

// Estadía atrasada impide empezar mantenimiento hoy, aunque su reserva ya finalizó.
$occupiedMaintRoom = $hotel->saveRoom([...$room,'code'=>'T-MAINT-STAY'],null);
$historical->execute([$occupiedMaintRoom,'Huésped atrasado','late@example.test',$day(-2),$day(-1),'10',bin2hex(random_bytes(32))]);
$occupiedMaintId = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO stays (reservation_id,checked_in_at,checked_in_by) VALUES (?,?,?)')->execute([$occupiedMaintId,$day(-2).' 12:00:00',$maintActor]);
$todayMaint = [...$maintData,'room_id'=>(string)$occupiedMaintRoom,'start_date'=>$day(0),'end_date'=>$day(1)];
check(count($hotel->inspectMaintenance($todayMaint)['occupants']) === 1,'revisión detecta estadía atrasada');
rejected(fn() => $hotel->createMaintenance($todayMaint,bin2hex(random_bytes(32)),true,$maintActor),'no inicia mantenimiento con huésped alojado');
$hotel->recordStay($occupiedMaintId,'checkout',$maintActor);
$todayMaintId = $hotel->createMaintenance($todayMaint,bin2hex(random_bytes(32)),true,$maintActor);
$cleaningBeforeRelease = $hotel->pendingCleaning($occupiedMaintRoom);
$hotel->releaseMaintenance($todayMaintId,'Terminado',true,$maintActor);
check($hotel->pendingCleaning($occupiedMaintRoom) === $cleaningBeforeRelease,'levantar conserva limpieza pendiente');
// Historial con bloqueo iniciado ayer: liberar hoy no borra la noche anterior.
$pdo->prepare('INSERT INTO room_maintenance (room_id,start_date,end_date,reason,created_at,created_by,submission_token) VALUES (?,?,?,?,?,?,?)')
    ->execute([$occupiedMaintRoom,$day(-1),$day(2),'Bloqueo histórico',$day(-1).' 12:00:00',$maintActor,bin2hex(random_bytes(32))]);
$pastMaintId = (int)$pdo->lastInsertId();
$hotel->releaseMaintenance($pastMaintId,'Fin anticipado',true,$maintActor);
check(count($hotel->maintenanceDuring($occupiedMaintRoom,$day(-1),$day(0))) === 1 && !$hotel->maintenanceDuring($occupiedMaintRoom,$day(0),$day(1)),'levantamiento conserva noches pasadas y libera desde hoy');
$rowsBeforeMaintMigration = $pdo->query('SELECT * FROM room_maintenance ORDER BY id')->fetchAll();
$pdo->exec(file_get_contents($root.'/database/maintenance.sql'));
$pdo->exec(file_get_contents($root.'/database/maintenance.sql'));
check($pdo->query('SELECT * FROM room_maintenance ORDER BY id')->fetchAll() === $rowsBeforeMaintMigration,'migración repetida conserva historial');

foreach (['block','create','confirm','move','release'] as $mode) {
    $raceOrigin = $hotel->saveRoom([...$room,'code'=>'T-MNT-'.$mode.'-A'],null);
    $raceTarget = $hotel->saveRoom([...$room,'code'=>'T-MNT-'.$mode.'-B'],null);
    $raceMaintData = [...$maintData,'room_id'=>(string)$raceTarget,'start_date'=>$day(60),'end_date'=>$day(62)];
    $raceMaintBooking = [...$valid,'room_id'=>(string)$raceTarget,'check_in'=>$day(60),'check_out'=>$day(62)];
    $jobs = [['action'=>'block','data'=>$raceMaintData,'token'=>bin2hex(random_bytes(32)),'actor'=>$maintActor]];
    if ($mode === 'block') $jobs[] = ['action'=>'block','data'=>$raceMaintData,'token'=>bin2hex(random_bytes(32)),'actor'=>$adminId];
    elseif ($mode === 'release') {
        $raceBlockId = $hotel->createMaintenance($raceMaintData,bin2hex(random_bytes(32)),true,$maintActor);
        $jobs = [['action'=>'release','id'=>$raceBlockId,'actor'=>$maintActor],['action'=>'release','id'=>$raceBlockId,'actor'=>$adminId]];
    } elseif ($mode === 'confirm') $jobs[] = ['action'=>'confirm','id'=>$hotel->submit($raceMaintBooking,bin2hex(random_bytes(32))),'actor'=>$adminId];
    elseif ($mode === 'move') {
        $raceMoving = $hotel->createReservation([...$raceMaintBooking,'room_id'=>(string)$raceOrigin],bin2hex(random_bytes(32)),$adminId);
        $jobs[] = ['action'=>'move','quote'=>$hotel->previewReschedule($raceMoving,[...$raceMaintBooking,'reason'=>'Cambio concurrente'],$adminId),'actor'=>$adminId];
    } else $jobs[] = ['action'=>'create','data'=>$raceMaintBooking,'actor'=>$adminId];
    $pdo->beginTransaction();
    foreach ([$raceOrigin,$raceTarget] as $lockId) $pdo->query('SELECT id FROM rooms WHERE id='.$lockId.' FOR UPDATE');
    foreach ($jobs as $i=>$job) {
        $jobPath=$runtime.'/maint-job-'.$i.'.json'; $ready=$runtime.'/maint-ready-'.$i;
        if (is_file($ready)) unlink($ready);
        file_put_contents($jobPath,json_encode($job,JSON_THROW_ON_ERROR));
        $workers[]=proc_open([PHP_BINARY,__DIR__.'/maintenance-worker.php',$configPath,$jobPath,$ready],[0=>['pipe','r'],1=>['file',$runtime.'/worker.log','a'],2=>['file',$runtime.'/worker.log','a']],$pipes); fclose($pipes[0]);
    }
    $deadline=microtime(true)+10;
    while ((!is_file($runtime.'/maint-ready-0') || !is_file($runtime.'/maint-ready-1')) && microtime(true)<$deadline) usleep(30000);
    check(is_file($runtime.'/maint-ready-0') && is_file($runtime.'/maint-ready-1'),'procesos listos mantenimiento frente a '.$mode);
    $pdo->commit(); $codes=[];
    foreach ($workers as $worker) $codes[]=proc_close($worker);
    $workers=[]; sort($codes);
    check($codes === [0,2],'una transición ganadora mantenimiento frente a '.$mode);
    $activeBlocks=count($hotel->maintenanceDuring($raceTarget,$day(60),$day(62)));
    $activeReservations=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status='confirmed' AND room_id=".$raceTarget)->fetchColumn();
    check($mode === 'release' ? $activeBlocks === 0 : $activeBlocks+$activeReservations === 1,'sin doble bloqueo ni reserva en mantenimiento '.$mode);
    foreach ([0,1] as $i) { unlink($runtime.'/maint-job-'.$i.'.json'); unlink($runtime.'/maint-ready-'.$i); }
}
