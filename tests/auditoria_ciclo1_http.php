<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__.'/HttpCatalogo.php';require __DIR__.'/HorariosFixture.php';
use App\Models\Cita;
use App\Models\HorarioAtencion;
$pdo=App\Config\Database::connection();
if($pdo->query('SELECT current_database()')->fetchColumn()!=='clinica_vida_salud_dev'){throw new RuntimeException('Base no autorizada');}
$f=new HorariosFixture($pdo);$http=null;$total=0;$r=null;
$ok=static function(bool $v,string $n)use(&$total,&$r):void{
    if(!$v){fwrite(STDERR,$n.'; HTTP '.($r['status']??'sin respuesta').PHP_EOL);throw new RuntimeException($n);}
    $total++;echo "OK: $n\n";
};
$buscar=static function(string $sql,array $parametros)use($pdo):int{$q=$pdo->prepare($sql);$q->execute($parametros);return (int)$q->fetchColumn();};
$documento='AUD-'.$f->sufijo;$registro='AUD-MED-'.$f->sufijo;$nombreEspecialidad='Auditoría ficticia '.$f->sufijo;
try{
    // Solo la cuenta inicial de prueba se prepara por servicio; los registros clínicos nacen por HTTP.
    $admin=$f->usuario('administrador');$cuentaMedica=$f->usuario('medico');
    $q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$admin]);$correo=$q->fetchColumn();
    $http=new HttpCatalogo();$login=$http->pedir('/login');
    $headers=strtolower(implode("\n",$login['headers']));
    $ok($login['status']===200&&str_contains($headers,'httponly')&&str_contains($headers,'samesite=lax'),'cookie de sesión HttpOnly y SameSite');
    $ok(str_contains($headers,'cache-control: no-store')&&str_contains($headers,"frame-ancestors 'none'")&&str_contains($headers,"script-src 'self'"),'cabeceras de caché y CSP');
    $r=$http->pedir('/login?mensaje%5B%5D=prueba');$ok($r['status']===200&&!str_contains($r['body'],'TypeError'),'mensaje array no produce HTTP 500');
    foreach(['/.env','/.git/config','/app/Config/Database.php','/database/migrations/001_esquema_ciclo1.sql','/scripts/crear-administrador.php','/tests/HttpCatalogo.php','/../.env'] as $ruta){
        $r=$http->pedir($ruta);$ok($r['status']===404&&!str_contains($r['body'],'DB_PASSWORD'),'raíz pública bloquea '.$ruta);
    }
    $r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$correo,'contrasena'=>'Ficticia91'.$f->sufijo]);$cookie=$r['cookie'];
    $ok($r['status']===303&&$r['location']==='/dashboard','administrador inicia sesión');
    $ok($cookie!==$login['cookie'],'login renueva ID de sesión');
    $r=$http->pedir('/dashboard','GET',$cookie);$csrf=HttpCatalogo::csrf($r['body']);$ok($r['status']===200,'panel autenticado');
    foreach(['/admin/users','/admin/users/new','/admin/users/'.$cuentaMedica.'/edit'] as $ruta){
        $r=$http->pedir($ruta,'GET',$cookie);
        $ok($r['status']===200&&str_contains($r['body'],'href="/citas/nueva"')&&str_contains($r['body'],'action="/logout"'),'CU03 conserva menú y salida del actor '.$ruta);
        $ok(str_contains($r['body'],'Administrador · '.htmlspecialchars($correo,ENT_QUOTES,'UTF-8')),'CU03 muestra identidad de sesión '.$ruta);
    }
    $r=$http->pedir('/dashboard?mensaje%5B%5D=prueba','GET',$cookie);$ok($r['status']===200,'mensaje inválido tampoco rompe vista privada');
    $r=$http->pedir('/pacientes/nuevo','GET',$cookie);$ok($r['status']===200,'consulta formulario de paciente');
    $r=$http->pedir('/pacientes','POST',$cookie,['_csrf'=>$csrf,'nombres'=>'Paciente auditoría','apellidos'=>'Ficticio','documento_identidad'=>$documento,'fecha_nacimiento'=>'2000-01-01','sexo'=>'no_especificado']);
    $paciente=$buscar('SELECT id FROM pacientes WHERE documento_identidad=:v',['v'=>strtoupper($documento)]);if($paciente){$f->pacientes[]=$paciente;}
    $ok($r['status']===303&&$paciente>0,'registra paciente por CU04');
    $r=$http->pedir('/pacientes/'.$paciente,'GET',$cookie);$ok($r['status']===200&&str_contains($r['body'],'Paciente auditoría'),'consulta paciente por CU05');
    $r=$http->pedir('/especialidades','POST',$cookie,['_csrf'=>$csrf,'nombre'=>$nombreEspecialidad,'descripcion'=>'Catálogo ficticio temporal de auditoría']);
    $especialidad=$buscar('SELECT id FROM especialidades WHERE nombre=:v',['v'=>$nombreEspecialidad]);if($especialidad){$f->especialidades[]=$especialidad;}
    $ok($r['status']===303&&$especialidad>0,'registra especialidad por CU09');
    $r=$http->pedir('/medicos','POST',$cookie,['_csrf'=>$csrf,'nombres'=>'Profesional auditoría','apellidos'=>'Ficticio','numero_colegiatura'=>$registro,'usuario_id'=>(string)$cuentaMedica,'especialidades'=>[(string)$especialidad]]);
    $medico=$buscar('SELECT id FROM medicos WHERE numero_colegiatura=:v',['v'=>strtoupper($registro)]);if($medico){$f->medicos[]=$medico;}
    $ok($r['status']===303&&$medico>0,'registra médico vinculado y especialidad por CU07/CU10');
    $r=$http->pedir('/medicos/'.$medico,'GET',$cookie);$ok($r['status']===200&&str_contains($r['body'],$nombreEspecialidad),'consulta médico con especialidad por CU08');
    $r=$http->pedir('/medicos/'.$medico.'/especialidades','POST',$cookie,['_csrf'=>$csrf,'especialidades'=>[(string)$especialidad]]);
    $ok($r['status']===303&&$buscar('SELECT count(*) FROM medicos_especialidades WHERE medico_id=:m AND especialidad_id=:e',['m'=>$medico,'e'=>$especialidad])===1,'asignación explícita CU10 conserva asociación');
    $r=$http->pedir('/horarios','POST',$cookie,['_csrf'=>$csrf,'medico_id'=>(string)$medico,'dia_semana'=>'1','hora_inicio'=>'08:00','hora_fin'=>'12:00']);
    $ok($r['status']===303,'configura horario por CU11');
    $lunes=(new DateTimeImmutable('next monday',HorarioAtencion::zona()))->modify('+14 days')->format('Y-m-d');
    $disponibilidad='/disponibilidad?'.http_build_query(['medico_id'=>$medico,'especialidad_id'=>$especialidad,'fecha'=>$lunes]);
    $r=$http->pedir($disponibilidad,'GET',$cookie);preg_match_all('/data-turno-inicio="([^"]+)"/',$r['body'],$libres);
    $ok($r['status']===200&&count($libres[1])===8&&in_array($lunes.'T08:00:00-04:00',$libres[1],true),'CU12 ofrece ocho turnos antes de reservar');
    $r=$http->pedir('/citas/nueva?'.http_build_query(['paciente_id'=>$paciente,'medico_id'=>$medico,'especialidad_id'=>$especialidad,'fecha'=>$lunes]),'GET',$cookie);
    $ok($r['status']===200&&str_contains($r['body'],'name="hora_inicio" value="08:00"'),'formulario CU13 integra paciente y disponibilidad');
    $reserva=['_csrf'=>$csrf,'_formato'=>'html','accion'=>'previsualizar','paciente_id'=>$paciente,'medico_id'=>$medico,'especialidad_id'=>$especialidad,'fecha'=>$lunes,'hora_inicio'=>'08:00','motivo'=>'Motivo ficticio de auditoría'];
    $r=$http->pedir('/citas','POST',$cookie,$reserva);$ok($r['status']===200&&str_contains($r['body'],'08:00–08:30'),'revisa reserva CU13');
    $reserva['accion']='confirmar';$r=$http->pedir('/citas','POST',$cookie,$reserva);
    $ok($r['status']===303&&preg_match('#^/citas/confirmacion/([0-9]+)$#',$r['location'],$id)===1,'registra cita y redirige al recibo');$cita=(int)$id[1];
    $r=$http->pedir($r['location'],'GET',$cookie);$ok($r['status']===200&&str_contains($r['body'],Cita::codigo($cita)),'recibo de reserva con código');
    $r=$http->pedir($disponibilidad,'GET',$cookie);preg_match_all('/data-turno-inicio="([^"]+)"/',$r['body'],$libres);
    $ok($r['status']===200&&count($libres[1])===7&&!in_array($lunes.'T08:00:00-04:00',$libres[1],true),'CU12 retira exactamente el turno reservado');
    $r=$http->pedir('/citas?codigo='.Cita::codigo($cita),'GET',$cookie);
    $ok($r['status']===200&&str_contains($r['body'],'data-cita-id="'.$cita.'"')&&str_contains($r['body'],'Programada'),'CU14 encuentra nueva cita');
    $r=$http->pedir('/citas/'.$cita,'GET',$cookie);$ok($r['status']===200&&str_contains($r['body'],'08:00–08:30')&&str_contains($r['body'],$nombreEspecialidad),'CU14 muestra detalle integrado');
    $r=$http->pedir('/logout','POST',$cookie,['_csrf'=>$csrf]);$ok($r['status']===303,'cierra sesión');
    foreach(['/dashboard','/pacientes/'.$paciente,'/medicos/'.$medico,'/horarios','/disponibilidad','/citas','/citas/'.$cita,'/admin/users'] as $ruta){
        $r=$http->pedir($ruta,'GET',$cookie);$ok($r['status']===303&&str_starts_with($r['location'],'/login'),'logout bloquea acceso privado '.$ruta);
    }
    // Simulación solo local: otro proceso, sin editar .env ni conectar a otra base.
    $produccion=new HttpCatalogo(array_merge(getenv(),['APP_ENV'=>'production']));
    try{
        $r=$produccion->pedir('/login');$headers=strtolower(implode("\n",$r['headers']));
        $ok($r['status']===200&&preg_match('/set-cookie: phpsessid=[^\n]+; secure(?:;|\s|$)/',$headers)===1,'APP_ENV del proceso prevalece y la cookie exige Secure en producción');
    }finally{$produccion->cerrar();}
}finally{
    if($http!==null){$http->cerrar();}
    // Si falló una respuesta antes de rastrear su ID, recuperar solo la identidad única del fixture.
    foreach([['pacientes','documento_identidad',strtoupper($documento)],['medicos','numero_colegiatura',strtoupper($registro)],['especialidades','nombre',$nombreEspecialidad]] as [$tabla,$columna,$valor]){
        $id=$buscar('SELECT id FROM '.$tabla.' WHERE '.$columna.'=:v',['v'=>$valor]);if($id&&!in_array($id,$f->{$tabla},true)){$f->{$tabla}[]=$id;}
    }
    $f->limpiar();
}
echo "Aprobadas: $total. Flujo completo HTTP y seguridad; fixtures propios retirados.\n";
