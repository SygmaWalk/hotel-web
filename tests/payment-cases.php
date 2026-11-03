<?php
$payActor=(int)$pdo->query("SELECT id FROM users WHERE role='staff' LIMIT 1")->fetchColumn();
$payRoom=$hotel->saveRoom([...$room,'code'=>'T-PAY','nightly_rate'=>'100.10'],null);
$payBooking=[...$valid,'room_id'=>(string)$payRoom,'check_in'=>$day(80),'check_out'=>$day(83)];
$payId=$hotel->createReservation($payBooking,bin2hex(random_bytes(32)),$payActor);
$payData=['kind'=>'deposit','amount'=>'100,10','method'=>'transfer','paid_on'=>$day(0),'note'=>'Seña de prueba'];
$payToken=bin2hex(random_bytes(32));
check(Hotel::moneyCents('0.01')===1 && Hotel::moneyCents('9999999999.99')===999999999999,'centavos exactos y límite de importe');
foreach (['-1','1.234','1e2','1.000,10','NaN','10000000000',''] as $bad) rejected(fn()=>Hotel::moneyCents($bad),'importe rechaza formato ambiguo');
check($hotel->paymentBalance($payId)['due']===30030,'saldo inicial por tres noches sin coma flotante');
foreach ([['amount'=>'0'],['amount'=>'301'],['amount'=>['1']],['kind'=>'bad'],['method'=>'bad'],['paid_on'=>$day(1)],['paid_on'=>'2027-02-30'],['paid_on'=>'1999-01-01'],['note'=>' '],['note'=>str_repeat('á',501)]] as $bad) rejected(fn()=>$hotel->recordPayment($payId,[...$payData,...$bad],$payToken,true,$payActor),'pago rechaza datos inválidos');
rejected(fn()=>$hotel->recordPayment($payId,$payData,$payToken,false,$payActor),'pago exige aceptación');
rejected(fn()=>$hotel->recordPayment($payId,$payData,$payToken,true,999999),'pago exige empleado');
rejected(fn()=>$hotel->recordPayment(999999,$payData,$payToken,true,$payActor),'pago exige reserva');
$payment1=$hotel->recordPayment($payId,$payData,$payToken,true,$payActor);
check($hotel->paymentBalance($payId)['net']===10010 && $hotel->paymentBalance($payId)['due']===20020,'seña reduce saldo exactamente');
check($hotel->recordPayment($payId,$payData,$payToken,true,$payActor)===$payment1 && count($hotel->paymentHistory($payId))===1,'reenvío de seña no duplica');
rejected(fn()=>$hotel->recordPayment($payId,[...$payData,'amount'=>'10'],$payToken,true,$payActor),'token no permite alterar importe');
rejected(fn()=>$hotel->recordPayment($payId,$payData,$payToken,true,$adminId),'token no pertenece a otro empleado');
$payment2=$hotel->recordPayment($payId,[...$payData,'kind'=>'payment','amount'=>'200.20','method'=>'cash'],bin2hex(random_bytes(32)),true,$payActor);
check($hotel->paymentBalance($payId)['due']===0 && $hotel->paymentBalance($payId)['credit']===0,'pago completo sin redondeo');
rejected(fn()=>$hotel->recordPayment($payId,[...$payData,'amount'=>'0.01'],bin2hex(random_bytes(32)),true,$payActor),'no permite sobrecobro');
$quote=$hotel->previewReschedule($payId,[...$payBooking,'check_out'=>$day(81),'reason'=>'Estadía más corta'],$payActor);
$beforePayChanges=$hotel->paymentHistory($payId); $hotel->rescheduleReservation($quote,true,$payActor);
check($hotel->paymentBalance($payId)['credit']===20020 && $hotel->paymentHistory($payId)===$beforePayChanges,'reprogramación menor conserva pagos y muestra crédito');
$refund=$hotel->recordPayment($payId,[...$payData,'kind'=>'refund','amount'=>'200.20','note'=>'Devolución por cambio'],bin2hex(random_bytes(32)),true,$payActor);
check($hotel->paymentBalance($payId)['net']===10010 && $hotel->paymentBalance($payId)['credit']===0,'devolución compensa crédito');
rejected(fn()=>$hotel->recordPayment($payId,[...$payData,'kind'=>'refund','amount'=>'100.11'],bin2hex(random_bytes(32)),true,$payActor),'no devuelve más que cobros netos');
rejected(fn()=>$hotel->voidPayment($payId,$payment2,'Error',true,$payActor),'anulación no deja devoluciones sin respaldo');
foreach ([['',true],['Motivo',false],[str_repeat('a',501),true]] as [$reason,$ack]) rejected(fn()=>$hotel->voidPayment($payId,$refund,$reason,$ack,$payActor),'anulación valida motivo y confirmación');
$hotel->voidPayment($payId,$refund,'Devolución cargada por error',true,$adminId);
check($hotel->paymentBalance($payId)['credit']===20020,'anular devolución restaura crédito');
$voided=$hotel->paymentHistory($payId)[0];
check((int)$voided['voided_by']===$adminId && $voided['void_reason']==='Devolución cargada por error' && $voided['amount']==='200.20','auditoría conserva original y responsable');
rejected(fn()=>$hotel->voidPayment($payId,$refund,'Otro motivo',true,$payActor),'segunda anulación rechazada');
check($hotel->paymentHistory($payId)[0]===$voided,'segunda anulación no sobrescribe auditoría');
$hotel->voidPayment($payId,$payment2,'Importe incorrecto',true,$payActor);
check($hotel->paymentBalance($payId)['net']===10010,'anulación de cobro ajusta saldo');
$hotel->cancelReservation($payId,'Cancelación con seña',true,$payActor);
check($hotel->paymentBalance($payId)['due']===0 && $hotel->paymentBalance($payId)['credit']===10010,'cancelación pasa seña a crédito sin devolución automática');
rejected(fn()=>$hotel->recordPayment($payId,$payData,bin2hex(random_bytes(32)),true,$payActor),'cancelada no recibe cobros');
$cancelRefund=$hotel->recordPayment($payId,[...$payData,'kind'=>'refund'],bin2hex(random_bytes(32)),true,$payActor);
check($hotel->paymentBalance($payId)['net']===0,'cancelada permite registrar devolución');
$report=$hotel->dailyPayments($day(0));
check($report['charged']===10010 && $report['refunded']===10010 && $report['net']===0,'resumen excluye anulados y conserva movimientos vigentes');
check($report['methods']['cash']['net']===0 && $report['methods']['transfer']['charged']===10010 && count($report['entries'])===4,'desglose por medio y lista incluyen auditoría');
check($hotel->dailyPayments($day(-5))['entries']===[],'día sin movimientos muestra cero');
foreach ([$day(1),'2027-02-30','1999-01-01'] as $bad) rejected(fn()=>$hotel->dailyPayments($bad),'reporte valida fecha');
$pendingPay=$hotel->submit([...$payBooking,'check_in'=>$day(85),'check_out'=>$day(86)],bin2hex(random_bytes(32)));
rejected(fn()=>$hotel->recordPayment($pendingPay,$payData,bin2hex(random_bytes(32)),true,$payActor),'pendiente no recibe seña');
$hotel->resolve($pendingPay,'rejected',$payActor);
rejected(fn()=>$hotel->recordPayment($pendingPay,$payData,bin2hex(random_bytes(32)),true,$payActor),'rechazada no recibe seña');
rejected(fn()=>$hotel->voidPayment($pendingPay,$payment1,'Error',true,$payActor),'no anula pago de otra reserva');
rejected(fn()=>$hotel->voidPayment($payId,$payment1,'Error',true,999999),'anulación exige empleado válido');

// HTTP real: autorización, CSRF, token de sesión, escape y actor tomado del servidor.
$httpPay=$hotel->createReservation([...$payBooking,'check_in'=>$day(90),'check_out'=>$day(93)],bin2hex(random_bytes(32)),$payActor);
$payUrl='reserva-pagos.php?id='.$httpPay;
check(request($payUrl)[0]===303 && request('cobros.php')[0]===303,'pagos y cobros privados');
check(request('reserva-pagos.php?id[]=1',[],'staff')[0]===404 && request('reserva-pagos.php?id=999999',[],'staff')[0]===404,'reserva inválida rechazada');
foreach (['staff','admin'] as $client) check(request($payUrl,[],$client)[0]===200 && request('cobros.php',[],$client)[0]===200,'pagos para '.$client);
$payForm=request($payUrl,[],'staff')[1];
$httpPayData=[...$payData,'csrf'=>$staffCsrf,'action'=>'record','payment_token'=>token($payForm,'payment_token'),'acknowledged'=>'1','note'=>'<script>alert(1)</script>'];
check(request($payUrl,[...$httpPayData,'csrf'=>'bad'],'staff','POST')[0]===403,'pago requiere CSRF');
check(request($payUrl,[...$httpPayData,'payment_token'=>'bad'],'staff','POST')[0]===403,'pago requiere formulario de sesión');
check(request($payUrl,[...$httpPayData,'csrf'=>$adminCsrf],'admin','POST')[0]===403,'formulario no sirve en otra sesión');
check(request($payUrl,[...$httpPayData,'acknowledged'=>''],'staff','POST')[0]===409,'pago HTTP exige confirmación');
check(request($payUrl,[...$httpPayData,'amount'=>['1']],'staff','POST')[0]===409,'importe array rechazado');
check(request('reserva-pagos.php?id='.$payId,$httpPayData,'staff','POST')[0]===403,'token vinculado a reserva');
check(request($payUrl,[...$httpPayData,'recorded_by'=>(string)$adminId],'staff','POST')[0]===303,'pago HTTP guardado');
check(request($payUrl,$httpPayData,'staff','POST')[0]===303 && count($hotel->paymentHistory($httpPay))===1,'reenvío HTTP no duplica');
$httpEntry=$hotel->paymentHistory($httpPay)[0];
check((int)$httpEntry['recorded_by']===$payActor,'empleado no se acepta del navegador');
$payHtml=request($payUrl,[],'staff')[1]; $cashHtml=request('cobros.php',[],'staff')[1];
check(str_contains($payHtml,'&lt;script&gt;') && !str_contains($payHtml,'<script>alert(1)') && !str_contains($cashHtml,'<script>alert(1)'),'conceptos escapados en reserva y resumen');
$httpVoid=['csrf'=>$staffCsrf,'action'=>'void','payment_id'=>(string)$httpEntry['id'],'void_reason'=>'Error de carga','acknowledged'=>'1'];
check(request($payUrl,[...$httpVoid,'csrf'=>'bad'],'staff','POST')[0]===403,'anulación HTTP requiere CSRF');
check(request($payUrl,[...$httpVoid,'acknowledged'=>''],'staff','POST')[0]===409,'anulación HTTP requiere aceptación');
check(request($payUrl.'&'.http_build_query($httpVoid),[],'staff')[0]===200 && $hotel->paymentBalance($httpPay)['net']===10010,'GET no anula pagos');
check(request($payUrl,$httpVoid,'staff','POST')[0]===303 && $hotel->paymentBalance($httpPay)['net']===0,'anulación HTTP actualiza saldo');
check(request($payUrl,$httpVoid,'staff','POST')[0]===409,'doble anulación HTTP rechazada');
check(str_contains(request('solicitud.php?id='.$httpPay,[],'staff')[1],'Gestionar señas y pagos'),'detalle enlaza pagos');
check(request('cobros.php?date[]=1',[],'staff')[0]===422,'fecha array rechazada');
$payBeforeMigration=$pdo->query('SELECT * FROM reservation_payments ORDER BY id')->fetchAll();
$pdo->exec(file_get_contents($root.'/database/payments.sql')); $pdo->exec(file_get_contents($root.'/database/payments.sql'));
check($pdo->query('SELECT * FROM reservation_payments ORDER BY id')->fetchAll()===$payBeforeMigration,'migración repetida conserva movimientos');

// Dos procesos esperan sobre la reserva y revalidan el saldo al obtener el bloqueo.
foreach (['pay','refund','void','duplicate','cancel','move'] as $mode) {
    $racePayRoom=$hotel->saveRoom([...$room,'code'=>'T-PAY-'.$mode,'nightly_rate'=>'100.00'],null);
    $racePayBooking=[...$valid,'room_id'=>(string)$racePayRoom,'check_in'=>$day(100),'check_out'=>$day(101)];
    $racePayId=$hotel->createReservation($racePayBooking,bin2hex(random_bytes(32)),$payActor);
    $raceData=[...$payData,'amount'=>'70.00'];
    $jobs=[['action'=>'pay','id'=>$racePayId,'data'=>$raceData,'token'=>bin2hex(random_bytes(32)),'actor'=>$payActor]];
    if (in_array($mode,['refund','void'],true)) {
        $raceEntry=$hotel->recordPayment($racePayId,[...$raceData,'amount'=>'100.00'],bin2hex(random_bytes(32)),true,$payActor);
        $jobs=[['action'=>$mode,'id'=>$racePayId,'payment_id'=>$raceEntry,'data'=>[...$raceData,'kind'=>'refund'],'token'=>bin2hex(random_bytes(32)),'actor'=>$payActor]];
    }
    $jobs[]=[...$jobs[0],'token'=>$mode==='duplicate'?$jobs[0]['token']:bin2hex(random_bytes(32))];
    if ($mode==='cancel') $jobs[1]=['action'=>'cancel','id'=>$racePayId,'actor'=>$adminId];
    if ($mode==='move') {
        $cheaper=$hotel->saveRoom([...$room,'code'=>'T-PAY-CHEAP','nightly_rate'=>'50.00'],null);
        $jobs[1]=['action'=>'move','quote'=>$hotel->previewReschedule($racePayId,[...$racePayBooking,'room_id'=>(string)$cheaper,'reason'=>'Tarifa menor'],$adminId),'actor'=>$adminId];
    }
    $pdo->beginTransaction(); $pdo->query('SELECT id FROM reservations WHERE id='.$racePayId.' FOR UPDATE');
    foreach ($jobs as $i=>$job) {
        $jobPath=$runtime.'/pay-job-'.$i.'.json'; $ready=$runtime.'/pay-ready-'.$i;
        if (is_file($ready)) unlink($ready); file_put_contents($jobPath,json_encode($job,JSON_THROW_ON_ERROR));
        $workers[]=proc_open([PHP_BINARY,__DIR__.'/payment-worker.php',$configPath,$jobPath,$ready],[0=>['pipe','r'],1=>['file',$runtime.'/worker.log','a'],2=>['file',$runtime.'/worker.log','a']],$pipes); fclose($pipes[0]);
    }
    $deadline=microtime(true)+10;
    while ((!is_file($runtime.'/pay-ready-0') || !is_file($runtime.'/pay-ready-1')) && microtime(true)<$deadline) usleep(30000);
    check(is_file($runtime.'/pay-ready-0') && is_file($runtime.'/pay-ready-1'),'procesos de pagos listos '.$mode);
    $pdo->commit(); $codes=[]; foreach ($workers as $worker) $codes[]=proc_close($worker); $workers=[]; sort($codes);
    if (in_array($mode,['cancel','move'],true)) check(in_array($codes,[[0,0],[0,2]],true),'transacciones compatibles '.$mode);
    else check($codes===($mode==='duplicate'?[0,0]:[0,2]),'pagos simultáneos sin doble efecto '.$mode);
    $raceBalance=$hotel->paymentBalance($racePayId);
    $expected=match($mode) {'refund'=>3000,'void'=>0,'cancel','move'=>$raceBalance['net'],default=>7000};
    check($raceBalance['net']===$expected && count($hotel->paymentHistory($racePayId))<=($mode==='refund'?2:1),'saldo consistente '.$mode);
    if ($mode==='cancel') check($hotel->request($racePayId)['status']==='cancelled' && $raceBalance['due']===0 && $raceBalance['credit']===$raceBalance['net'],'cancelación concurrente conserva crédito');
    if ($mode==='move') check($raceBalance['total']===5000 && $raceBalance['credit']===max(0,$raceBalance['net']-5000),'reprogramación concurrente recalcula saldo');
    foreach ([0,1] as $i) { unlink($runtime.'/pay-job-'.$i.'.json'); unlink($runtime.'/pay-ready-'.$i); }
}
