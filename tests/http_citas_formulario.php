<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/CitasPrueba.php';require __DIR__ . '/HttpCatalogo.php';
use App\Config\Database;
$pdo=Database::connection();$t=new CitasPrueba($pdo);$f=$t->fixture;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"Falló: $n\n");throw new RuntimeException($n);} $total++;echo "OK: $n\n";};
$campos=static function(string $html):array{
    if(!preg_match('#<form[^>]*data-booking-confirm[^>]*>(.*?)</form>#s',$html,$form)){throw new RuntimeException('No se encontró el formulario final');}
    preg_match_all('/<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"/',$form[1],$m,PREG_SET_ORDER);$resultado=[];
    foreach($m as $campo){$resultado[$campo[1]]=html_entity_decode($campo[2],ENT_QUOTES|ENT_HTML5,'UTF-8');}return $resultado;
};
try{
    $http=new HttpCatalogo();$sesiones=[];
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){$id=$f->usuario($rol);$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);$correo=$q->fetchColumn();$login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$correo,'contrasena'=>'Ficticia91!' . $f->sufijo]);$panel=$http->pedir('/dashboard','GET',$r['cookie']);$sesiones[$rol]=['id'=>$id,'cookie'=>$r['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];}
    $m=$f->medico();$otro=$f->medico();$p=$f->paciente();$inactivo=$f->paciente();$e=$f->especialidades[0];$otra=$f->especialidades[1];$t->horario($m);
    $pdo->prepare('UPDATE pacientes SET activo=false WHERE id=:id')->execute(['id'=>$inactivo]);
    $pdo->prepare('UPDATE medicos SET nombres=:n WHERE id=:id')->execute(['n'=>'AJENO_FORMULARIO','id'=>$otro]);
    $pdo->prepare('UPDATE pacientes SET nombres=:n WHERE id=:id')->execute(['n'=>'PACIENTE_FORM_' . $f->sufijo,'id'=>$p]);
    $ok($http->pedir('/citas/nueva')['status']===303,'formulario exige sesión');
    foreach(['medico','paciente'] as $rol){$ok($http->pedir('/citas/nueva','GET',$sesiones[$rol]['cookie'])['status']===403,'formulario denegado ' . $rol);}
    $admin=$sesiones['administrador'];$a=$admin['cookie'];$token=$admin['csrf'];$base=$t->datos($m,$p,$e)+['_csrf'=>$token,'_formato'=>'html','motivo'=>'MOTIVO_PRIVADO_FORMULARIO'];
    foreach(['administrador','recepcionista'] as $rol){$r=$http->pedir('/citas/nueva','GET',$sesiones[$rol]['cookie']);$ok($r['status']===200&&str_contains($r['body'],'Seleccionar paciente')&&str_contains($r['body'],'Programar cita'),'formulario para ' . $rol);}
    $r=$http->pedir('/citas/nueva?q=' . $f->sufijo,'GET',$a);$ok(str_contains($r['body'],'value="' . $p . '"')&&!str_contains($r['body'],'value="' . $inactivo . '"'),'búsqueda incluye activos y excluye inactivos');
    $r=$http->pedir('/citas','POST',$a,array_replace($base,['accion'=>'actualizar','q'=>$f->sufijo]));
    $ok($r['status']===200&&str_contains($r['body'],'PAC-' . str_pad((string)$p,6,'0',STR_PAD_LEFT)),'paciente seleccionado muestra código');
    $ok(!str_contains($r['body'],'AJENO_FORMULARIO'),'médicos filtrados por especialidad');
    preg_match_all('/name="hora_inicio" value="([^"]+)"/',$r['body'],$turnos);$ok($turnos[1]===['08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30'],'turnos reales de CU12 en el formulario');
    $cantidad=(int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn();
    $r=$http->pedir('/citas','POST',$a,$base+['accion'=>'previsualizar']);$ok($r['status']===200&&str_contains($r['body'],'Todavía no se ha registrado'),'revisión previa visible');
    $ok((int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn()===$cantidad,'revisión no guarda cita');
    $ok(str_contains($r['body'],'08:00–08:30')&&str_contains($r['body'],'PACIENTE_FORM_'),'resumen tiene paciente y turno');
    $post=$campos($r['body']);$ok($post['accion']==='confirmar'&&$post['motivo']==='MOTIVO_PRIVADO_FORMULARIO','confirmación transporta parámetros del formulario');
    $r=$http->pedir('/citas','POST',$a,$post);$ok($r['status']===303&&preg_match('#^/citas/confirmacion/[1-9][0-9]*$#',$r['location'])===1,'reserva HTML usa POST Redirect GET');$recibo=$r['location'];
    preg_match('#/([0-9]+)$#',$recibo,$id);$id=(int)$id[1];$q=$pdo->prepare('SELECT paciente_id,medico_id,especialidad_id,estado FROM citas WHERE id=:id');$q->execute(['id'=>$id]);$fila=$q->fetch();
    $ok((int)$fila['paciente_id']===$p&&(int)$fila['medico_id']===$m&&(int)$fila['especialidad_id']===$e&&$fila['estado']==='programada','reserva persistida en PostgreSQL');
    $r=$http->pedir($recibo,'GET',$a);$ok($r['status']===200&&str_contains($r['body'],App\Models\Cita::codigo($id))&&str_contains($r['body'],'08:00–08:30'),'recibo muestra código y horario');
    $ok(!str_contains($r['body'],'MOTIVO_PRIVADO_FORMULARIO')&&str_contains($r['body'],'Programar otra cita'),'recibo sin motivo clínico y con nueva programación');
    $antes=(int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn();$http->pedir($recibo,'GET',$a);$ok((int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn()===$antes,'recargar confirmación no duplica');
    $ok($http->pedir($recibo)['status']===303,'recibo exige sesión');$ok($http->pedir($recibo,'GET',$sesiones['recepcionista']['cookie'])['status']===404,'otra sesión no consulta el recibo ajeno');
    foreach(['medico','paciente'] as $rol){$ok($http->pedir($recibo,'GET',$sesiones[$rol]['cookie'])['status']===403,'recibo denegado ' . $rol);}
    $r=$http->pedir('/citas','POST',$a,$post);$ok($r['status']===409&&str_contains($r['body'],'ya no está disponible'),'segundo envío muestra conflicto');
    $ok(str_contains($r['body'],'MOTIVO_PRIVADO_FORMULARIO')&&str_contains($r['body'],'PACIENTE_FORM_'),'conflicto conserva datos válidos para elegir otro turno');
    $ok(!str_contains($r['body'],'name="hora_inicio" value="08:00"'),'formulario refrescado no ofrece el turno ocupado');
    $ok(!str_contains($r['body'],'SQLSTATE')&&!str_contains($r['body'],'23P01'),'conflicto HTML no revela SQL');
    $r=$http->pedir('/disponibilidad?' . http_build_query(['medico_id'=>$m,'fecha'=>$base['fecha']]),'GET',$a);$ok(!str_contains($r['body'],'data-turno-inicio="' . $base['fecha'] . 'T08:00:00-04:00"'),'reserva desaparece de CU12');
    $r=$http->pedir('/citas','POST',$a,array_replace($post,['hora_inicio'=>'08:30','_csrf'=>'invalido']));$ok($r['status']===419,'formulario rechaza CSRF inválido');
    $r=$http->pedir('/citas','POST',$a,array_replace($post,['paciente_id'=>$inactivo,'hora_inicio'=>'08:30']));$ok($r['status']===422&&str_contains($r['body'],'inactivo'),'paciente inactivo manipulado rechazado');
    $r=$http->pedir('/citas','POST',$a,array_replace($post,['especialidad_id'=>$otra,'hora_inicio'=>'08:30']));$ok($r['status']===422,'especialidad manipulada rechazada');
    $r=$http->pedir('/citas','POST',$a,array_replace($post,['paciente_id'=>['array']]));$ok($r['status']===422,'tipo manipulado rechazado');
    $r=$http->pedir('/citas','POST',$a,array_replace($base,['fecha'=>'2000-01-01','accion'=>'actualizar']));$ok($r['status']===422&&str_contains($r['body'],'pasadas'),'fecha pasada rechazada en PHP');
    $r=$http->pedir('/citas','POST',$a,array_replace($base,['accion'=>'actualizar','fecha'=>$t->lunes->modify('+1 day')->format('Y-m-d')]));$ok($r['status']===200&&str_contains($r['body'],'no atiende ese día'),'mensaje para día sin atención');
    // Recepción completa el mismo flujo con un turno distinto.
    $recepcion=$sesiones['recepcionista'];$b=array_replace($base,['_csrf'=>$recepcion['csrf'],'hora_inicio'=>'08:30','accion'=>'previsualizar']);$r=$http->pedir('/citas','POST',$recepcion['cookie'],$b);$ok($r['status']===200,'recepción revisa reserva');
    $r=$http->pedir('/citas','POST',$recepcion['cookie'],$campos($r['body']));$ok($r['status']===303&&$http->pedir($r['location'],'GET',$recepcion['cookie'])['status']===200,'recepción guarda y consulta su confirmación');
    $q=$pdo->prepare('SELECT count(*) FROM citas WHERE medico_id=:m');$q->execute(['m'=>$m]);$ok((int)$q->fetchColumn()===2,'solo dos reservas autorizadas del flujo');
    $ok($http->pedir('/citas/nueva','POST',$a) ['status']===405,'ruta de formulario solo GET');
}finally{if($http!==null){$http->cerrar();}$f->limpiar();}
echo "Aprobadas: $total. Fixtures y citas temporales eliminados.\n";
