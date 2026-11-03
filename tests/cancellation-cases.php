<?php
// Solo la base temporal de run.php. Simula la actualización desde el esquema anterior.
$beforeCancelMigration = $pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll();
$pdo->exec('DROP TABLE reservation_cancellations');
$pdo->exec("ALTER TABLE reservations MODIFY status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending'");
$pdo->exec(file_get_contents($root.'/database/cancellations.sql'));
$pdo->exec(file_get_contents($root.'/database/cancellations.sql'));
check($pdo->query('SELECT * FROM reservations ORDER BY id')->fetchAll() === $beforeCancelMigration, 'migración desde esquema previo conserva reservas y estados');
$cancelActor = (int)$pdo->query("SELECT id FROM users WHERE email='staff@example.test'")->fetchColumn();
$cancelRoom = $hotel->saveRoom([...$room,'code'=>'T-CANCEL'],null);
$cancelData = [...$valid,'room_id'=>(string)$cancelRoom,'check_in'=>$day(15),'check_out'=>$day(18)];
$cancelId = $hotel->createReservation($cancelData,bin2hex(random_bytes(32)),$adminId);
$originalCancel = $hotel->request($cancelId);
$cancelPage = 'solicitud.php?id='.$cancelId;
$cancelPost = ['action'=>'cancel','reason'=>'El huésped cambió su viaje.','acknowledged'=>'1','csrf'=>$staffCsrf];
foreach (['', '  ', str_repeat('á',501)] as $reason) rejected(fn() => $hotel->cancelReservation($cancelId,$reason,true,$cancelActor), 'motivo inválido no cancela');
rejected(fn() => $hotel->cancelReservation($cancelId,'Motivo',false,$cancelActor), 'requiere confirmación explícita');
rejected(fn() => $hotel->cancelReservation($cancelId,'Motivo',true,999999), 'actor inexistente no cancela');
rejected(fn() => $hotel->cancelReservation(999999,'Motivo',true,$cancelActor), 'reserva inexistente no cancela');
$pendingCancel = $hotel->submit([...$cancelData,'check_in'=>$day(20),'check_out'=>$day(21)],bin2hex(random_bytes(32)));
rejected(fn() => $hotel->cancelReservation($pendingCancel,'Motivo',true,$cancelActor), 'pendiente debe resolverse antes de cancelar');
$hotel->resolve($pendingCancel,'rejected',$cancelActor);
rejected(fn() => $hotel->cancelReservation($pendingCancel,'Motivo',true,$cancelActor), 'rechazada no se puede cancelar');
check(request($cancelPage)[0] === 303, 'detalle de cancelación privado');
$guestCancelCsrf = token(request('login.php')[1]);
check(request($cancelPage,[...$cancelPost,'csrf'=>$guestCancelCsrf],'guest','POST')[0] === 303, 'visitante no puede cancelar');
check(request($cancelPage,[...$cancelPost,'csrf'=>'incorrecto'],'staff','POST')[0] === 403, 'cancelación requiere CSRF');
foreach ([['reason'=>['motivo']],['reason'=>''],['reason'=>str_repeat('x',501)],['acknowledged'=>''],['acknowledged'=>['1']]] as $bad) {
    check(request($cancelPage,[...$cancelPost,...$bad],'staff','POST')[0] === 409, 'POST inválido no cancela');
}
check(request($cancelPage.'&'.http_build_query($cancelPost),[],'staff')[0] === 200 && $hotel->request($cancelId)['status'] === 'confirmed', 'GET no cancela');
check($hotel->request($cancelId) === $originalCancel, 'intentos inválidos conservan la reserva');
foreach (['admin','staff'] as $client) check(str_contains(request($cancelPage,[],$client)[1],'Confirmar cancelación'), 'formulario para '.$client);

// Fallo entre auditoría y cambio de estado: ninguna de las dos escrituras persiste.
$pdo->exec("CREATE TRIGGER test_cancel_failure BEFORE UPDATE ON reservations FOR EACH ROW
    BEGIN IF NEW.status = 'cancelled' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo aislado'; END IF; END");
try {
    $cancelRolledBack = false;
    try { $hotel->cancelReservation($cancelId,'Motivo',true,$cancelActor); } catch (PDOException $error) { $cancelRolledBack = true; }
    check($cancelRolledBack && $hotel->request($cancelId) === $originalCancel && (int)$pdo->query('SELECT COUNT(*) FROM reservation_cancellations')->fetchColumn() === 0, 'fallo revierte estado y auditoría juntos');
} finally { $pdo->exec('DROP TRIGGER test_cancel_failure'); }
$dashboardBeforeCancel = $hotel->dashboard($day(15))['summary'];
$cancelPost['reason'] = "<script>alert('prueba')</script>\nCambio de viaje";
check(request($cancelPage,[...$cancelPost,'cancelled_by'=>(string)$adminId],'staff','POST')[0] === 303, 'recepción cancela por POST');
$cancelled = $hotel->request($cancelId);
check($cancelled['status'] === 'cancelled' && $cancelled['cancellation_reason'] === $cancelPost['reason'] && $cancelled['cancelled_actor'] === 'staff@example.test' && $cancelled['cancelled_at'] !== null, 'cancelación guarda motivo fecha y actor de sesión');
foreach (['guest_name','email','room_id','check_in','check_out','nightly_rate','resolved_at','resolved_by','created_at','submission_token'] as $key) {
    check($cancelled[$key] === $originalCancel[$key], 'conserva dato original '.$key);
}
$cancelHtml = request($cancelPage,[],'staff')[1];
check(str_contains($cancelHtml,'Cancelación registrada') && str_contains($cancelHtml,'&lt;script&gt;') && !str_contains($cancelHtml,"<script>alert('prueba')") && !str_contains($cancelHtml,'Confirmar cancelación') && !str_contains($cancelHtml,'Registrar llegada'), 'detalle escapa motivo y no ofrece acciones incompatibles');
check(str_contains(request('solicitudes.php?status=cancelled',[],'staff')[1],'Ver detalle #'.$cancelId) && count($hotel->requests('cancelled')) === 1, 'filtro de canceladas conserva consulta');
check(request($cancelPage,$cancelPost,'staff','POST')[0] === 409 && $hotel->request($cancelId) === $cancelled, 'doble envío no sobrescribe auditoría');
rejected(fn() => $hotel->recordStay($cancelId,'checkin',$cancelActor), 'cancelada no permite llegada');
rejected(fn() => $hotel->resolve($cancelId,'confirmed',$cancelActor), 'cancelada no puede reconfirmarse');
check(in_array($cancelRoom,array_column($hotel->availableRooms($day(15),$day(18)),'id')), 'cancelación libera disponibilidad');
$afterCalendar = $hotel->calendar($day(15),$day(18),$cancelRoom);
check($afterCalendar['cells'][$cancelRoom][$day(15)]['state'] === 'free' && !$afterCalendar['cells'][$cancelRoom][$day(15)]['arrivals'] && !$afterCalendar['cells'][$cancelRoom][$day(18)]['departures'], 'calendario libera noches llegadas y salidas');
$cancelJson = json_decode(request('calendario-datos.php?month='.substr($day(15),0,7).'&room_id='.$cancelRoom,[],'staff')[1],true,512,JSON_THROW_ON_ERROR);
check($cancelJson['cells'][$cancelRoom][$day(15)]['state'] === 'free', 'calendario mensual JSON actualizado');
$dashboardAfterCancel = $hotel->dashboard($day(15))['summary'];
check($dashboardAfterCancel['reserved'] === $dashboardBeforeCancel['reserved']-1 && $dashboardAfterCancel['arrivals'] === $dashboardBeforeCancel['arrivals']-1 && $dashboardAfterCancel['available'] === $dashboardBeforeCancel['available']+1, 'panel actualiza ocupación disponibilidad y llegadas');
$replacementId = $hotel->createReservation($cancelData,bin2hex(random_bytes(32)),$cancelActor);
check($hotel->request($replacementId)['status'] === 'confirmed', 'fechas liberadas admiten nueva reserva');
check(request('solicitud.php?id='.$replacementId,[...$cancelPost,'csrf'=>$adminCsrf],'admin','POST')[0] === 303 && $hotel->request($replacementId)['cancelled_actor'] === 'admin@example.test', 'administrador también cancela');
// La reserva ya confirmada permanece cancelable si luego se desactiva su habitación.
$inactiveCancelId = $hotel->createReservation([...$cancelData,'check_in'=>$day(80),'check_out'=>$day(81)],bin2hex(random_bytes(32)),$cancelActor);
$hotel->deactivate($cancelRoom,true);
$hotel->cancelReservation($inactiveCancelId,str_repeat('á',500),true,$cancelActor);
check((int)$hotel->room($cancelRoom)['active'] === 0 && mb_strlen($hotel->request($inactiveCancelId)['cancellation_reason']) === 500, 'acepta límite de motivo y no reactiva habitación');

$stayCancelRoom = $hotel->saveRoom([...$room,'code'=>'T-CANCEL-STAY'],null);
$stayCancelId = $hotel->createReservation([...$cancelData,'room_id'=>(string)$stayCancelRoom,'check_in'=>$day(0),'check_out'=>$day(1)],bin2hex(random_bytes(32)),$cancelActor);
$hotel->recordStay($stayCancelId,'checkin',$cancelActor);
foreach (['open','closed'] as $stage) {
    if ($stage === 'closed') $hotel->recordStay($stayCancelId,'checkout',$cancelActor);
    rejected(fn() => $hotel->cancelReservation($stayCancelId,'Motivo',true,$cancelActor), 'estadía '.$stage.' no admite cancelación');
    check(request('solicitud.php?id='.$stayCancelId,$cancelPost,'staff','POST')[0] === 409 && !str_contains(request('solicitud.php?id='.$stayCancelId,[],'staff')[1],'Confirmar cancelación'), 'bloqueo HTTP y formulario oculto con estadía '.$stage);
}
$auditBeforeMigration = $pdo->query('SELECT * FROM reservation_cancellations ORDER BY reservation_id')->fetchAll();
$detailBeforeMigration = $hotel->request($cancelId);
$pdo->exec(file_get_contents($root.'/database/cancellations.sql'));
check($pdo->query('SELECT * FROM reservation_cancellations ORDER BY reservation_id')->fetchAll() === $auditBeforeMigration && $hotel->request($cancelId) === $detailBeforeMigration, 'migración repetida conserva cancelaciones y auditoría');

// Dos cancelaciones o cancelación y llegada simultáneas: solo una transición gana.
foreach (['cancel','checkin'] as $otherAction) {
    $raceCancelRoom = $hotel->saveRoom([...$room,'code'=>'T-CANCEL-'.$otherAction],null);
    $raceCancelId = $hotel->createReservation([...$cancelData,'room_id'=>(string)$raceCancelRoom,'check_in'=>$day(0),'check_out'=>$day(1)],bin2hex(random_bytes(32)),$cancelActor);
    $pdo->beginTransaction(); $pdo->query('SELECT id FROM rooms WHERE id='.$raceCancelRoom.' FOR UPDATE');
    foreach (['cancel',$otherAction] as $i=>$action) {
        $jobPath = $runtime.'/cancel-job-'.$i.'.json'; $ready = $runtime.'/cancel-ready-'.$i;
        if (is_file($ready)) unlink($ready);
        file_put_contents($jobPath,json_encode(['action'=>$action,'reservation'=>$raceCancelId,'actor'=>$i ? $adminId : $cancelActor],JSON_THROW_ON_ERROR));
        $workers[] = proc_open([PHP_BINARY,__DIR__.'/cancellation-worker.php',$configPath,$jobPath,$ready],
            [0=>['pipe','r'],1=>['file',$runtime.'/worker.log','a'],2=>['file',$runtime.'/worker.log','a']],$pipes); fclose($pipes[0]);
    }
    $deadline = microtime(true)+10;
    while ((!is_file($runtime.'/cancel-ready-0') || !is_file($runtime.'/cancel-ready-1')) && microtime(true)<$deadline) usleep(30000);
    check(is_file($runtime.'/cancel-ready-0') && is_file($runtime.'/cancel-ready-1'), 'procesos listos cancelación frente a '.$otherAction);
    $pdo->commit(); $codes = [];
    foreach ($workers as $worker) $codes[] = proc_close($worker);
    $workers = []; sort($codes);
    check($codes === [0,2], 'una transición ganadora cancelación frente a '.$otherAction);
    $raceCancelled = $hotel->request($raceCancelId)['status'] === 'cancelled';
    check($raceCancelled ? ($hotel->stay($raceCancelId) === null && $hotel->request($raceCancelId)['cancelled_at'] !== null) : ($hotel->stay($raceCancelId) !== null && $hotel->request($raceCancelId)['cancelled_at'] === null), 'cancelación y llegada nunca coexisten');
    foreach ([0,1] as $i) { unlink($runtime.'/cancel-job-'.$i.'.json'); unlink($runtime.'/cancel-ready-'.$i); }
}
