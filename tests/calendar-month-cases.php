<?php
check(request('calendario-datos.php?month='.date('Y-m'))[0] === 401, 'calendario JSON requiere sesión');
foreach (['admin','staff'] as $role) {
    [$code,$body] = request('calendario-datos.php?month='.date('Y-m'), [], $role);
    $json = json_decode($body,true,512,JSON_THROW_ON_ERROR);
    check($code === 200 && $json['from'] === date('Y-m-01') && count($json['days']) <= 31, 'mes JSON accesible para '.$role);
    check(!str_contains($body,'submission_token') && !str_contains($body,'password_hash') && !str_contains($body,'"email"'), 'JSON sin datos privados '.$role);
}
foreach (['month[]=2026-10','month=2026-13','month=1999-12','month=2099-01','month='.date('Y-m').'&room_id[]=1','month='.date('Y-m').'&room_id=999999'] as $query) {
    [$code,$body]=request('calendario-datos.php?'.$query,[],'admin');
    check($code===422 && isset(json_decode($body,true)['error']), 'consulta JSON inválida: '.$query);
}
[$code,$body]=request('calendario-datos.php?month='.substr(bookingLimit(),0,7),[],'admin');
check($code===200 && json_decode($body,true)['to']===bookingLimit(),'último mes se recorta al límite anual');
[$code,$body]=request('calendario-datos.php?month=2024-02',[],'admin');
check($code===200 && count(json_decode($body,true)['days'])===29,'mes bisiesto JSON');
$firstRoom=(int)$pdo->query('SELECT MIN(id) FROM rooms')->fetchColumn();
[$code,$body]=request('calendario-datos.php?month='.date('Y-m').'&room_id='.$firstRoom,[],'staff');
check($code===200 && count(json_decode($body,true)['rooms'])===1,'filtro habitación JSON');
