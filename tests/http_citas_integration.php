<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/CitasPrueba.php';require __DIR__ . '/HttpCatalogo.php';
use App\Config\Database;
$pdo=Database::connection();$t=new CitasPrueba($pdo);$f=$t->fixture;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"Falló: $n\n");throw new RuntimeException($n);} $total++;echo "OK: $n\n";};
try{
    $http=new HttpCatalogo();$sesiones=[];
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){
        $id=$f->usuario($rol);$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);$correo=$q->fetchColumn();
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$correo,'contrasena'=>'Ficticia91!' . $f->sufijo]);
        $ok($r['status']===303,'login ' . $rol);$panel=$http->pedir('/dashboard','GET',$r['cookie']);$sesiones[$rol]=['id'=>$id,'cookie'=>$r['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];
    }
    $m=$f->medico();$p=$f->paciente();$e=$f->especialidades[0];$t->horario($m);$base=$t->datos($m,$p,$e);
    $q=$pdo->prepare("UPDATE pacientes SET nombres='PACIENTE_PRIVADO_CU13' WHERE id=:id");$q->execute(['id'=>$p]);
    $ok($http->pedir('/citas','POST','',$base)['status']===303,'sin autenticación no crea cita');
    $ok($http->pedir('/citas')['status']===303,'GET sin sesión respeta autenticación');
    foreach(['medico','paciente'] as $rol){$sesion=$sesiones[$rol];$ok($http->pedir('/citas','POST',$sesion['cookie'],$base+['_csrf'=>$sesion['csrf']])['status']===403,'POST denegado a ' . $rol);}
    foreach(['administrador'=>'08:00','recepcionista'=>'08:30'] as $rol=>$hora){
        $sesion=$sesiones[$rol];$cookie=$sesion['cookie'];$token=$sesion['csrf'];$datos=array_replace($base,['hora_inicio'=>$hora]);
        $ok($http->pedir('/citas','POST',$cookie,$datos)['status']===419,'CSRF ausente ' . $rol);
        $ok($http->pedir('/citas','POST',$cookie,$datos+['_csrf'=>'invalido'])['status']===419,'CSRF inválido ' . $rol);
        $ok($http->pedir('/citas','POST',$cookie,$datos+['_csrf'=>['array']])['status']===419,'CSRF tipo inválido ' . $rol);
        $antes=(int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn();
        $ok($http->pedir('/citas','GET',$cookie)['status']===200,'GET consulta CU14 sin creación ' . $rol);
        $ok((int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn()===$antes,'GET no escribe ' . $rol);
        $r=$http->pedir('/citas','POST',$cookie,$datos+['_csrf'=>$token,'motivo'=>'MOTIVO_PRIVADO_CU13']);$json=json_decode($r['body'],true);
        $ok($r['status']===201&&!empty($json['cita']['codigo'])&&$json['cita']['estado']==='programada','reserva real HTTP ' . $rol);
        $ok(!str_contains($r['body'],'PACIENTE_PRIVADO')&&!str_contains($r['body'],'MOTIVO_PRIVADO')&&!str_contains($r['body'],'hash_contrasena'),'confirmación sin datos privados ' . $rol);
        $q=$pdo->prepare('SELECT paciente_id,medico_id,especialidad_id,creada_por_usuario_id FROM citas WHERE id=:id');$q->execute(['id'=>$json['cita']['id']]);$fila=$q->fetch();
        $ok((int)$fila['paciente_id']===$p&&(int)$fila['medico_id']===$m&&(int)$fila['especialidad_id']===$e&&(int)$fila['creada_por_usuario_id']===$sesion['id'],'persistencia completa y actor de sesión ' . $rol);
        $r=$http->pedir('/citas','POST',$cookie,$datos+['_csrf'=>$token]);$json=json_decode($r['body'],true);
        $ok($r['status']===409&&isset($json['errores']['disponibilidad']),'repetición devuelve conflicto ' . $rol);
        $ok(!str_contains($r['body'],'23P01')&&!str_contains($r['body'],'SQLSTATE')&&!str_contains($r['body'],'SELECT '),'error no divulga SQL ' . $rol);
    }
    $admin=$sesiones['administrador'];$datos=array_replace($base,['hora_inicio'=>'09:00','paciente_id'=>PHP_INT_MAX]);$r=$http->pedir('/citas','POST',$admin['cookie'],$datos+['_csrf'=>$admin['csrf']]);
    $ok($r['status']===422&&isset(json_decode($r['body'],true)['errores']['paciente_id']),'errores de negocio por campo');
    $datos=array_replace($base,['hora_inicio'=>'09:00','paciente_id'=>['tipo_invalido']]);$ok($http->pedir('/citas','POST',$admin['cookie'],$datos+['_csrf'=>$admin['csrf']])['status']===422,'entrada de navegador no confiable');
    $ok($http->pedir('/citas','DELETE',$admin['cookie'])['status']===405,'sin endpoint de eliminación');
    $q=$pdo->prepare('SELECT count(*) FROM citas WHERE medico_id=:m');$q->execute(['m'=>$m]);$ok((int)$q->fetchColumn()===2,'solo persisten las dos reservas autorizadas de prueba');
    $r=$http->pedir('/disponibilidad?' . http_build_query(['medico_id'=>$m,'fecha'=>$base['fecha']]),'GET',$admin['cookie']);preg_match_all('/data-turno-inicio="([^"]+)"/',$r['body'],$turnos);
    $ok($r['status']===200&&count($turnos[1])===6&&!in_array($base['fecha'] . 'T08:00:00-04:00',$turnos[1],true),'CU12 actualizado tras reservar por HTTP');
    $ok($http->pedir('/logout','POST',$admin['cookie'],['_csrf'=>$admin['csrf']])['status']===303,'logout');
    $ok($http->pedir('/citas','POST',$admin['cookie'],$base+['_csrf'=>$admin['csrf']])['status']===303,'no crea después de logout');
}finally{if($http!==null){$http->cerrar();}$f->limpiar();}
echo "Aprobadas: $total. Reservas ficticias HTTP eliminadas; ninguna cita permanente.\n";
