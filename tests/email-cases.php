<?php
$fakeMail = new class implements HotelMailTransport {
    public array $messages=[];
    public string $mode='success';
    public bool $active=true;
    public function enabled(): bool { return $this->active; }
    public function send(array $message): void {
        $this->messages[]=$message;
        if ($this->mode==='fail') throw new MailDeliveryException();
        if ($this->mode==='uncertain') throw new MailDeliveryException(true);
    }
};
$emailService=new ReservationEmails($pdo,$fakeMail);
$mailActor=(int)$pdo->query("SELECT id FROM users WHERE role='staff' LIMIT 1")->fetchColumn();
$mailRoom=$hotel->saveRoom([...$room,'code'=>'T-EMAIL'],null);
$mailData=[...$valid,'room_id'=>(string)$mailRoom,'check_in'=>$day(100),'check_out'=>$day(102),'guest_name'=>'José de prueba'];
$mailToken=bin2hex(random_bytes(32));
$mailId=$hotel->submit($mailData,$mailToken);
$received=$emailService->history($mailId)[0];
check($received['kind']==='received' && $received['status']==='pending','solicitud guarda aviso pendiente');
$hotel->submit($mailData,$mailToken);
check(count($emailService->history($mailId))===1,'reenvío del formulario no duplica correo');
$fakeMail->active=false;
check($emailService->send((int)$received['id'])==='disabled' && (int)$emailService->history($mailId)[0]['attempts']===0,'correo desactivado no envía ni consume intentos');
$fakeMail->active=true;
check($emailService->send((int)$received['id'])==='sent','aviso de solicitud enviado con transporte simulado');
$message=end($fakeMail->messages);
check(str_contains($message['body'],'NO confirma') && str_contains($message['body'],'José de prueba') && str_contains($message['body'],$day(100))===false,'texto distingue pendiente y usa fechas legibles');
check(!str_contains($message['body'],$mailToken) && !str_contains($message['body'],'password'),'correo no incluye token ni credenciales');
$count=count($fakeMail->messages); $emailService->send((int)$received['id']);
check(count($fakeMail->messages)===$count,'aviso aceptado no vuelve a enviarse');
$hotel->resolve($mailId,'confirmed',$mailActor);
$confirmation=$emailService->history($mailId)[1];
check($confirmation['kind']==='confirmed','confirmar agrega evento distinto de recepción');
$fakeMail->mode='fail';
check($emailService->send((int)$confirmation['id'])==='failed' && $hotel->request($mailId)['status']==='confirmed','fallo SMTP conserva reserva confirmada');
check($emailService->history($mailId)[1]['error_code']==='smtp_failed','fallo persistido sin respuesta SMTP ni secretos');
check($emailService->send((int)$confirmation['id'])==='wait','reintento limitado por tiempo');
$pdo->exec('UPDATE reservation_emails SET attempted_at=DATE_SUB(NOW(), INTERVAL 2 MINUTE) WHERE id='.(int)$confirmation['id']);
$fakeMail->mode='success';
check($emailService->send((int)$confirmation['id'])==='sent','reintento recupera fallo');
$sent=end($fakeMail->messages);
check(str_contains($sent['body'],'Tu reserva está confirmada') && str_contains($sent['body'],'130.000,00 ARS'),'confirmación informa total y estado');
check($sent['message_key']===$fakeMail->messages[count($fakeMail->messages)-2]['message_key'],'reintento mantiene Message-ID lógico');

$directToken=bin2hex(random_bytes(32));
$directData=[...$mailData,'check_in'=>$day(110),'check_out'=>$day(112)];
$directId=$hotel->createReservation($directData,$directToken,$mailActor);
$hotel->createReservation($directData,$directToken,$mailActor);
check(count($emailService->history($directId))===1 && $emailService->history($directId)[0]['kind']==='confirmed','reserva directa encola una confirmación idempotente');
$directEmail=$emailService->history($directId)[0];
$fakeMail->mode='uncertain';
check($emailService->send((int)$directEmail['id'])==='uncertain','respuesta perdida queda sin confirmar');
$pdo->exec('UPDATE reservation_emails SET attempted_at=DATE_SUB(NOW(), INTERVAL 2 MINUTE) WHERE id='.(int)$directEmail['id']);
$count=count($fakeMail->messages); $emailService->sendPending($directId);
check(count($fakeMail->messages)===$count && $emailService->send((int)$directEmail['id'])==='uncertain','resultado incierto no se reenvía automáticamente');
$fakeMail->mode='success';
check($emailService->send((int)$directEmail['id'],true)==='sent','reenvío incierto exige aceptación explícita');

$staleId=$hotel->submit([...$mailData,'check_in'=>$day(120),'check_out'=>$day(122)],bin2hex(random_bytes(32)));
$hotel->resolve($staleId,'confirmed',$mailActor);
$staleHistory=$emailService->history($staleId);
check($emailService->send((int)$staleHistory[0]['id'])==='obsolete','no envía solicitud pendiente después de confirmarla');
$hotel->cancelReservation($staleId,'Prueba cancelación',true,$mailActor);
check($emailService->send((int)$staleHistory[1]['id'])==='obsolete','no envía confirmación de reserva cancelada');
$changedId=$hotel->createReservation([...$mailData,'check_in'=>$day(130),'check_out'=>$day(132)],bin2hex(random_bytes(32)),$mailActor);
$changedEmail=$emailService->history($changedId)[0];
$quote=$hotel->previewReschedule($changedId,[...$mailData,'check_in'=>$day(131),'check_out'=>$day(133),'reason'=>'Cambio de prueba'],$mailActor);
$hotel->rescheduleReservation($quote,true,$mailActor);
check($emailService->send((int)$changedEmail['id'])==='obsolete','no envía fechas anteriores a reprogramación');
$before=(int)$pdo->query('SELECT COUNT(*) FROM reservation_emails')->fetchColumn();
rejected(fn()=>$hotel->createReservation($directData,bin2hex(random_bytes(32)),$mailActor),'reserva conflictiva rechazada');
check((int)$pdo->query('SELECT COUNT(*) FROM reservation_emails')->fetchColumn()===$before,'reserva fallida no deja aviso');
$pdo->beginTransaction();
ReservationEmails::queue($pdo,$changedId,'received');
$pdo->rollBack();
check(count($emailService->history($changedId))===1,'rollback incluye correo');

$raceEmailId=$hotel->createReservation([...$mailData,'check_in'=>$day(140),'check_out'=>$day(142)],bin2hex(random_bytes(32)),$mailActor);
$raceMail=(int)$emailService->history($raceEmailId)[0]['id'];
$mailLog=$runtime.'/email-race-'.$schema.'.log';
for($i=0;$i<2;$i++) {
    $ready=$runtime.'/email-ready-'.$schema.'-'.$i;
    $workers[]=proc_open([PHP_BINARY,__DIR__.'/email-worker.php',$configPath,(string)$raceMail,$mailLog,$ready],[0=>['pipe','r'],1=>['file',$runtime.'/email-worker.log','a'],2=>['file',$runtime.'/email-worker.log','a']],$pipes);
}
foreach($workers as $worker) check(proc_close($worker)===0,'proceso concurrente de correo termina');
$workers=[];
check(count(file($mailLog,FILE_IGNORE_NEW_LINES))===1,'dos procesos envían una sola vez');
unlink($mailLog);
foreach(glob($runtime.'/email-ready-'.$schema.'-*')?:[] as $file)unlink($file);

$limitId=$hotel->createReservation([...$mailData,'check_in'=>$day(150),'check_out'=>$day(152)],bin2hex(random_bytes(32)),$mailActor);
$limitMail=(int)$emailService->history($limitId)[0]['id'];
$pdo->exec("UPDATE reservation_emails SET attempts=5,status='failed' WHERE id=$limitMail");
check($emailService->send($limitMail)==='limit','límite de cinco intentos');
$beforeRows=$pdo->query('SELECT * FROM reservation_emails ORDER BY id')->fetchAll();
$pdo->exec(file_get_contents($root.'/database/emails.sql'));
check($pdo->query('SELECT * FROM reservation_emails ORDER BY id')->fetchAll()===$beforeRows,'migración repetida conserva avisos');

check(request('solicitud.php?id='.$mailId,[],'guest')[0]===303,'historial de correo requiere acceso');
$mailPage=request('solicitud.php?id='.$limitId,[],'staff')[1];
check(str_contains($mailPage,'Correos al huésped') && str_contains($mailPage,'Gmail no está activado'),'recepción ve estado real y configuración desactivada');
check(request('solicitud.php?id='.$limitId,['action'=>'send_email','email_id'=>$limitMail,'csrf'=>'incorrecto'],'staff','POST')[0]===403,'reintento exige CSRF');
check(request('solicitud.php?id='.$limitId,['action'=>'send_email','email_id'=>$received['id'],'csrf'=>$staffCsrf],'staff','POST')[0]===404,'reintento no acepta correo de otra reserva');
check(request('solicitud.php?id='.$limitId,['action'=>'send_email','email_id'=>$limitMail,'csrf'=>$staffCsrf],'staff','POST')[0]===303,'reintento desactivado vuelve al detalle sin fallo');
check(!str_contains(request('solicitud-enviada.php')[1],$valid['email']),'comprobante sigue sin exponer destinatario');
check(!GmailTransport::fromLocalConfig()->enabled(),'sin configuración privada no hay SMTP real');
try {(new GmailTransport(['enabled'=>true,'gmail_address'=>"x@gmail.com\r\nBcc:y@x.com",'app_password'=>'abcdefghabcdefgh']))->send(['recipient'=>'test@example.test','subject'=>'Prueba']);check(false,'inyección cabecera');}
catch(MailDeliveryException $e){check(!$e->uncertain,'configuración inválida rechazada antes de conexión');}
