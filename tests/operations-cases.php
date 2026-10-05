<?php
// Se ejecuta dentro de la base y el servidor efímeros de run.php.
check(bookingLimit(new DateTimeImmutable('2028-02-29')) === '2029-02-28', 'aniversario bisiesto');
$limit = bookingLimit();
$afterLimit = (new DateTimeImmutable($limit))->modify('+1 day')->format('Y-m-d');
$beforeLimit = (new DateTimeImmutable($limit))->modify('-1 day')->format('Y-m-d');
check(validateRequest([...$valid,'check_in'=>$beforeLimit,'check_out'=>$limit])[1] === [], 'salida exactamente al año permitida');
check(isset(validateRequest([...$valid,'check_in'=>$limit,'check_out'=>$afterLimit])[1]['check_out']), 'reserva fuera del año rechazada');
check(request('calendario.php?from='.$afterLimit,[],'admin')[0] === 422, 'calendario rechaza futuro fuera del año');
check(request('reservar.php',[...$form,'check_out'=>$afterLimit],'guest','POST')[0] === 422, 'POST fuera del año rechazado');
check(str_contains(request('reservar.php')[1], 'max="'.$limit.'"'), 'límite HTML formulario');
$opsRoom = $hotel->saveRoom([...$room,'code'=>'OPS-STAY'],null);
$newReservation = function (string $from, string $to, string $state='confirmed') use ($pdo,$opsRoom): int {
    $q=$pdo->prepare('INSERT INTO reservations (room_id,guest_name,email,check_in,check_out,nightly_rate,status,submission_token) VALUES (?,?,?,?,?,?,?,?)');
    $q->execute([$opsRoom,'Prueba estadía','stay@example.test',$from,$to,'10',$state,bin2hex(random_bytes(32))]);
    return (int)$pdo->lastInsertId();
};
$outsideId=$newReservation($beforeLimit,$afterLimit,'pending');
rejected(fn()=>$hotel->resolve($outsideId,'confirmed',$adminId),'confirmación fuera del año rechazada');
$stayId=$newReservation($day(0),$day(2));
$otherId=$newReservation($day(0),$day(2));
$pendingId=$newReservation($day(0),$day(2),'pending');
$earlyId=$newReservation($day(1),$day(2));
$expiredId=$newReservation($day(-2),$day(0));
rejected(fn()=>$hotel->recordStay($stayId,'checkout',$adminId),'salida sin entrada rechazada');
rejected(fn()=>$hotel->recordStay($pendingId,'checkin',$adminId),'entrada solo confirmada');
rejected(fn()=>$hotel->recordStay($earlyId,'checkin',$adminId),'entrada anticipada rechazada');
rejected(fn()=>$hotel->recordStay($expiredId,'checkin',$adminId),'entrada tras salida prevista rechazada');
rejected(fn()=>$hotel->recordStay($stayId,'checkin',999999),'actor inexistente rechazado');
check(request('solicitud.php?id='.$stayId,['csrf'=>'incorrecto','action'=>'checkin'],'staff','POST')[0]===403,'CSRF entrada');
check(request('solicitud.php?id='.$stayId,['csrf'=>$staffCsrf,'action'=>'checkin'],'staff','POST')[0]===303,'checkin por recepción');
$stay=$hotel->stay($stayId);
check($stay!==null && $stay['checked_out_at']===null && $stay['arrival_actor']==='staff@example.test','registro de fecha y actor llegada');
check(request('solicitud.php?id='.$stayId,['csrf'=>$staffCsrf,'action'=>'checkin'],'staff','POST')[0]===409,'checkin repetido rechazado');
rejected(fn()=>$hotel->recordStay($otherId,'checkin',$adminId),'otra llegada en habitación ocupada rechazada');
check($hotel->calendar($day(0),$day(0),$opsRoom)['cells'][$opsRoom][$day(0)]['state']==='occupied','calendario refleja estadía real');
check(str_contains(request('dashboard.php',[],'staff')[1],'Prueba estadía'),'panel huéspedes alojados');
check(request('solicitud.php?id='.$stayId,['csrf'=>$staffCsrf,'action'=>'checkout'],'staff','POST')[0]===303,'checkout anticipado permitido');
$stay=$hotel->stay($stayId);
check($stay['checked_out_at']!==null && $stay['departure_actor']==='staff@example.test','fecha y actor salida');
check(request('solicitud.php?id='.$stayId,['csrf'=>$staffCsrf,'action'=>'checkout'],'staff','POST')[0]===409,'checkout repetido rechazado');
$hotel->recordStay($otherId,'checkin',$adminId);
check($hotel->stay($otherId)!==null,'nueva llegada después de salida');
$pdo->prepare('UPDATE reservations SET check_in=?, check_out=? WHERE id=?')->execute([$day(-1),$day(0),$otherId]);
// Simular estadía que comenzó ayer para registrar una salida tardía.
$pdo->prepare('UPDATE reservations SET check_in=? WHERE id=?')->execute([$day(-1),$otherId]);
$hotel->recordStay($otherId,'checkout',$adminId);
check($hotel->stay($otherId)['checked_out_at']!==null,'salida tardía registrada');
$pdo->exec('UPDATE rooms SET active=0 WHERE id='.$opsRoom);
rejected(fn()=>$hotel->recordStay($pendingId,'checkin',$adminId),'inactiva rechaza entrada');
$pdo->exec(file_get_contents($root.'/database/operations.sql'));
check($hotel->stay($stayId)!==null,'migración repetida conserva movimientos');


// Dos recepcionistas no pueden registrar ingresos simultáneos en la misma habitación.
$pdo->exec('UPDATE rooms SET active=1 WHERE id='.$opsRoom);
$concurrentIds=[$newReservation($day(0),$day(2)),$newReservation($day(0),$day(2))];
$pdo->beginTransaction();
$pdo->query('SELECT id FROM rooms WHERE id='.$opsRoom.' FOR UPDATE');
foreach($concurrentIds as $i=>$candidate) {
    $ready=$runtime.'/stay-ready-'.$i;
    if(is_file($ready)) unlink($ready);
    $workers[]=proc_open([PHP_BINARY,__DIR__.'/stay-worker.php',$configPath,(string)$candidate,(string)$adminId,$ready],
        [0=>['pipe','r'],1=>['file',$runtime.'/worker.log','a'],2=>['file',$runtime.'/worker.log','a']],$pipes);
    fclose($pipes[0]);
}
$deadline=microtime(true)+10;
while((!is_file($runtime.'/stay-ready-0') || !is_file($runtime.'/stay-ready-1')) && microtime(true)<$deadline) usleep(30000);
check(is_file($runtime.'/stay-ready-0') && is_file($runtime.'/stay-ready-1'),'dos checkin concurrentes preparados');
$pdo->commit();
$codes=[];
foreach($workers as $worker) $codes[]=proc_close($worker);
$workers=[]; sort($codes);
check($codes===[0,2],'solo un checkin concurrente');
foreach($concurrentIds as $candidate) if($hotel->stay($candidate)) $hotel->recordStay($candidate,'checkout',$adminId);

$account=['csrf'=>$adminCsrf,'email'=>'newstaff@example.test','password'=>'Clave de prueba 123!','confirmation'=>'Clave de prueba 123!','role'=>'admin'];
check(request('usuarios.php',[],'guest')[0]===303,'usuarios exige sesión');
check(request('usuarios.php',[],'staff')[0]===403,'recepción sin acceso usuarios');
check(request('usuarios.php',[...$account,'csrf'=>$staffCsrf],'staff','POST')[0]===403,'recepción no crea cuentas');
check(request('usuarios.php',[...$account,'csrf'=>'bad'],'admin','POST')[0]===403,'CSRF alta usuario');
check(request('usuarios.php',[...$account,'password'=>'corta','confirmation'=>'corta'],'admin','POST')[0]===422,'contraseña corta');
check(request('usuarios.php',[...$account,'confirmation'=>'No coincide 123!'],'admin','POST')[0]===422,'confirmación contraseña');
check(request('usuarios.php',[...$account,'email'=>'mal'],'admin','POST')[0]===422,'correo inválido usuario');
check(request('usuarios.php',$account,'admin','POST')[0]===303,'administrador crea staff');
$newUser=$pdo->query("SELECT * FROM users WHERE email='newstaff@example.test'")->fetch();
check($newUser['role']==='staff' && password_verify($account['password'],$newUser['password_hash']),'rol fijo staff y contraseña con hash');
check(request('usuarios.php',$account,'admin','POST')[0]===422,'correo duplicado');
check(!str_contains(request('usuarios.php',[],'admin')[1],$newUser['password_hash']),'listado no expone hash');
$loginNew=request('login.php',[],'newstaff')[1];
check(request('login.php',['csrf'=>token($loginNew),'email'=>$account['email'],'password'=>$account['password']],'newstaff','POST')[0]===303,'usuario nuevo puede ingresar');
check(request('dashboard.php',[],'newstaff')[0]===200 && request('usuarios.php',[],'newstaff')[0]===403,'usuario nuevo mismos permisos staff');

$png=$runtime.'/photo.png';
file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6DpcAAAAASUVORK5CYII='));
$fake=$runtime.'/fake.png'; file_put_contents($fake,'<?php echo "NO EJECUTAR";');
$large=$runtime.'/large.png'; file_put_contents($large,str_repeat('x',1048577));
$photoForm=['csrf'=>$adminCsrf,'action'=>'save',...$room,'code'=>'OPS-PHOTO','images[0]'=>new CURLFile($png,'image/png','foto.png')];
check(request('habitaciones-admin.php',[...$photoForm,'csrf'=>$staffCsrf],'staff','POST',true)[0]===403,'staff no carga imágenes');
check(request('habitaciones-admin.php',[...$photoForm,'csrf'=>'bad'],'admin','POST',true)[0]===403,'CSRF carga de imágenes');
check(request('habitaciones-admin.php',[...$photoForm,'images[0]'=>new CURLFile($fake,'image/png','foto.png')],'admin','POST',true)[0]===422,'PHP disfrazado de PNG rechazado');
check(request('habitaciones-admin.php',[...$photoForm,'images[0]'=>new CURLFile($large,'image/png','grande.png')],'admin','POST',true)[0]===422,'imagen mayor de 1 MB rechazada');
check(request('habitaciones-admin.php',[...$photoForm,'images[1]'=>new CURLFile($fake,'image/png','falsa.png')],'admin','POST',true)[0]===422,'lote mixto inválido rechazado');
check((int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE code='OPS-PHOTO'")->fetchColumn()===0,'lote inválido no crea habitación');
check(request('habitaciones-admin.php',[...$photoForm,'images[1]'=>new CURLFile($png,'image/png','segunda.png')],'admin','POST',true)[0]===303,'crear habitación con dos fotos');
$photoRoom=(int)$pdo->query("SELECT id FROM rooms WHERE code='OPS-PHOTO'")->fetchColumn();
$photos=$hotel->images($photoRoom);
check(count($photos)===2,'dos fotos vinculadas');
[$imageCode,$imageBody,$imageHeaders]=request('imagen.php?id='.$photos[0]['id']);
check($imageCode===200 && $imageBody===file_get_contents($png) && str_contains($imageHeaders,'image/png') && str_contains(strtolower($imageHeaders),'nosniff'),'imagen servida como archivo inerte');
check(str_contains(request('habitaciones.php')[1],'imagen.php?id='.$photos[0]['id']),'fotos visibles en catálogo');
check(request('imagen.php?id=../../config/local.php')[0]===404,'ruta de imagen no manipulable');
check(request('imagen.php?id=999999')[0]===404,'imagen inexistente');
$editPhotos=[...$photoForm,'code'=>'OPS-PHOTO','images[1]'=>new CURLFile($png,'image/png','2.png'),'images[2]'=>new CURLFile($png,'image/png','3.png')];
check(request('habitaciones-admin.php?id='.$photoRoom,$editPhotos,'admin','POST',true)[0]===303,'agregar fotos hasta cinco');
check(request('habitaciones-admin.php?id='.$photoRoom,$photoForm,'admin','POST',true)[0]===422 && count($hotel->images($photoRoom))===5,'límite cinco fotos conserva anteriores');
$jpegRoomForm=[...$photoForm,'code'=>'OPS-JPEG','images[0]'=>new CURLFile($root.'/public/img/hotel-interior.jpg','image/jpeg','prueba.php')];
check(request('habitaciones-admin.php',$jpegRoomForm,'admin','POST',true)[0]===303,'JPEG real aceptado sin confiar en extensión original');
$jpegImage=$pdo->query("SELECT i.* FROM room_images i JOIN rooms r ON r.id=i.room_id WHERE r.code='OPS-JPEG'")->fetch();
check(str_ends_with($jpegImage['filename'],'.jpg') && !str_contains($jpegImage['filename'],'prueba'),'nombre aleatorio con extensión segura');

$hotel->deactivate($photoRoom,false);
check(request('imagen.php?id='.$photos[0]['id'])[0]===303 && request('imagen.php?id='.$photos[0]['id'],[],'staff')[0]===403,'foto inactiva privada');
check(request('imagen.php?id='.$photos[0]['id'],[],'admin')[0]===200,'administrador ve fotos de inactiva');
foreach ([$png,$fake,$large] as $fixture) unlink($fixture);
