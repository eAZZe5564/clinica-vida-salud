<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/HttpCatalogo.php';require __DIR__ . '/HorariosFixture.php';
use App\Config\Database;use App\Repositories\HorarioRepository;use App\Services\HorarioService;
$pdo=Database::connection();$fixture=new HorariosFixture($pdo);$repo=new HorarioRepository($pdo);$servicio=new HorarioService($pdo,$repo);$http=null;$total=0;
$ok=static function(bool $valor,string $nombre)use(&$total):void{if(!$valor){throw new RuntimeException($nombre);} $total++;echo "OK: $nombre\n";};
try{
    $http=new HttpCatalogo();$sesiones=[];
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){
        $id=$fixture->usuario($rol);$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);$correo=$q->fetchColumn();
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$correo,'contrasena'=>'Ficticia91!' . $fixture->sufijo]);
        $ok($r['status']===303,'login ' . $rol);$panel=$http->pedir('/dashboard','GET',$r['cookie']);
        $sesiones[$rol]=['id'=>$id,'cookie'=>$r['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];
    }
    $m=$fixture->medico($sesiones['medico']['id']);$otro=$fixture->medico();
    $base=['medico_id'=>$m,'dia_semana'=>1,'hora_inicio'=>'08:00','hora_fin'=>'12:00'];
    $otroHorario=$servicio->guardar(array_replace($base,['medico_id'=>$otro]));
    $admin=$sesiones['administrador'];$c=$admin['cookie'];$token=$admin['csrf'];
    foreach(['/horarios','/horarios/nuevo','/horarios/' . $otroHorario] as $ruta){$ok($http->pedir($ruta)['status']===303,'sin sesión ' . $ruta);}
    $ok($http->pedir('/horarios','POST','',$base)['status']===303,'escritura requiere sesión');
    $ok($http->pedir('/horarios/nuevo','GET',$c)['status']===200,'formulario real de alta');
    $r=$http->pedir('/horarios','POST',$c,$base+['_csrf'=>$token]);$ok($r['status']===303,'alta HTTP');
    preg_match('#^/horarios/([0-9]+)#',$r['location'],$match);$id=(int)($match[1]??0);$ok($id>0,'alta redirige al detalle');$ruta='/horarios/' . $id;
    $r=$http->pedir($ruta,'GET',$c);$ok($r['status']===200 && str_contains($r['body'],'Lunes') && str_contains($r['body'],'08:00–12:00') && str_contains($r['body'],'30 minutos'),'detalle contiene datos reales');
    $r=$http->pedir('/horarios?medico=' . $m . '&dia=1','GET',$c);$ok($r['status']===200 && str_contains($r['body'],$ruta) && !str_contains($r['body'],'/horarios/' . $otroHorario . '"'),'filtro por médico y día');
    $ok($http->pedir($ruta . '/editar','GET',$c)['status']===200,'formulario real de edición');
    $r=$http->pedir('/horarios','POST',$c,array_replace($base,['_csrf'=>$token,'hora_inicio'=>'11:30','hora_fin'=>'14:00']));$ok($r['status']===422 && str_contains($r['body'],'superpone'),'superposición HTTP');
    $r=$http->pedir('/horarios','POST',$c,array_replace($base,['_csrf'=>$token,'dia_semana'=>'8','hora_fin'=>'07:00']));$ok($r['status']===422 && str_contains($r['body'],'posterior') && str_contains($r['body'],'Selecciona una opción válida'),'errores por campo');
    $ok($http->pedir($ruta . '/editar','POST',$c,array_replace($base,['_csrf'=>$token,'hora_inicio'=>'08:30','id'=>'1']))['status']===303,'edición ignora clave primaria enviada');
    foreach(['/horarios',$ruta . '/editar',$ruta . '/estado'] as $escritura){
        $ok($http->pedir($escritura,'POST',$c,['_csrf'=>'invalido'])['status']===419,'CSRF inválido ' . $escritura);
        $ok($http->pedir($escritura,'POST',$c) ['status']===419,'CSRF ausente ' . $escritura);
    }
    $ok($http->pedir($ruta . '/estado','GET',$c)['status']===200 && $servicio->obtener($id)['activo'],'confirmación GET no escribe');
    $ok($http->pedir($ruta . '/estado','POST',$c,['_csrf'=>$token,'activo'=>'0'])['status']===303 && !$servicio->obtener($id)['activo'],'desactivar HTTP conserva fila');
    $ok($http->pedir($ruta . '/estado','POST',$c,['_csrf'=>$token,'activo'=>'1'])['status']===303,'reactivar HTTP');
    $p=$fixture->paciente();$fecha=(new DateTimeImmutable('next monday',App\Models\HorarioAtencion::zona()))->modify('+14 days')->setTime(9,0);$fixture->cita($m,$p,$fecha->format('c'));
    $r=$http->pedir($ruta . '/estado','POST',$c,['_csrf'=>$token,'activo'=>'0']);$ok($r['status']===422 && str_contains($r['body'],'cita futura'),'desactivación HTTP protege cita futura');
    $r=$http->pedir($ruta . '/editar','POST',$c,array_replace($base,['_csrf'=>$token,'hora_inicio'=>'09:30']));$ok($r['status']===422 && str_contains($r['body'],'cita futura'),'edición HTTP protege cita futura');
    $ok($servicio->obtener($id)['activo'],'fallo de protección conserva horario');
    $r=$http->pedir('/medicos/' . $m,'GET',$c);$ok($r['status']===200 && str_contains($r['body'],'Horarios semanales registrados') && str_contains($r['body'],$ruta),'detalle médico integra horarios');
    $q=$pdo->prepare('UPDATE medicos SET nombres=:nombre WHERE id=:id');$q->execute(['nombre'=>'<script>Ficticio</script>','id'=>$otro]);
    $r=$http->pedir('/horarios/' . $otroHorario,'GET',$c);$ok(str_contains($r['body'],'&lt;script&gt;Ficticio&lt;/script&gt;') && !str_contains($r['body'],'<script>Ficticio</script>'),'HTML escapa XSS');
    foreach($sesiones as $rol=>$sesion){
        $cookie=$sesion['cookie'];if($rol==='administrador'){continue;}
        $esperado=$rol==='paciente'?403:200;
        $ok($http->pedir('/horarios','GET',$cookie)['status']===$esperado,'listado según rol ' . $rol);
        $ok($http->pedir($ruta,'GET',$cookie)['status']===$esperado,'detalle propio/autorizado ' . $rol);
        foreach(['/horarios/nuevo',$ruta . '/editar',$ruta . '/estado'] as $consulta){$ok($http->pedir($consulta,'GET',$cookie)['status']===403,'formulario denegado ' . $rol);}
        foreach(['/horarios',$ruta . '/editar',$ruta . '/estado'] as $escritura){$ok($http->pedir($escritura,'POST',$cookie,['_csrf'=>$sesion['csrf']])['status']===403,'escritura denegada ' . $rol);}
    }
    $doctor=$sesiones['medico']['cookie'];
    $ok($http->pedir('/horarios/' . $otroHorario,'GET',$doctor)['status']===403,'médico no consulta detalle ajeno');
    $ok($http->pedir('/horarios?medico=' . $otro,'GET',$doctor)['status']===403,'médico no fuerza filtro ajeno');
    $r=$http->pedir('/horarios','GET',$doctor);$ok(!str_contains($r['body'],'/horarios/' . $otroHorario . '"'),'listado médico no incluye horario ajeno');
    $q=$pdo->prepare('UPDATE medicos SET usuario_id=NULL WHERE id=:id');$q->execute(['id'=>$m]);
    $r=$http->pedir('/horarios','GET',$doctor);$ok($r['status']===200 && str_contains($r['body'],'no tiene un registro médico vinculado') && !str_contains($r['body'],$ruta . '"'),'médico no vinculado no ve horarios');
    $ok($http->pedir($ruta,'GET',$doctor)['status']===403,'médico sin vínculo no usa detalle directo');
    $ok($http->pedir('/horarios/' . PHP_INT_MAX,'GET',$c)['status']===404,'horario inexistente 404');
    $ok($http->pedir('/horarios','DELETE',$c)['status']===405,'sin DELETE');
    $ok($http->pedir($ruta,'POST',$c,['_csrf'=>$token])['status']===405,'detalle solo GET');
    $ok($http->pedir('/logout','POST',$c,['_csrf'=>$token])['status']===303,'logout');
    $ok($http->pedir('/horarios','GET',$c)['status']===303,'sin acceso tras logout');
}finally{if($http!==null){$http->cerrar();}$fixture->limpiar();}
echo "Aprobadas: $total. Eliminados únicamente los datos ficticios de la suite.\n";
