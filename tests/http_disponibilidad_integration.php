<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/HorariosFixture.php';require __DIR__ . '/HttpCatalogo.php';
use App\Config\Database;use App\Models\HorarioAtencion;use App\Repositories\HorarioRepository;use App\Services\HorarioService;
$pdo=Database::connection();$f=new HorariosFixture($pdo);$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"Falló: $n\n");throw new RuntimeException($n);} $total++;echo "OK: $n\n";};
$slots=static function(string $html):array{preg_match_all('/data-turno-inicio="([^"]+)"/',$html,$m);return $m[1];};
try{
    $http=new HttpCatalogo();$sesiones=[];
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){
        $id=$f->usuario($rol);$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);$correo=$q->fetchColumn();
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$correo,'contrasena'=>'Ficticia91' . $f->sufijo]);
        $ok($r['status']===303,'login ' . $rol);$sesiones[$rol]=['cookie'=>$r['cookie'],'id'=>$id];
    }
    $m=$f->medico($sesiones['medico']['id']);$otro=$f->medico();$e=$f->especialidades[0];$otraEspecialidad=$f->especialidades[1];
    $q=$pdo->prepare('UPDATE medicos SET nombres=:nombre WHERE id=:id');$q->execute(['nombre'=>'MEDICO_PROPIO_DISP','id'=>$m]);$q->execute(['nombre'=>'MEDICO_AJENO_DISP','id'=>$otro]);
    $hs=new HorarioService($pdo,new HorarioRepository($pdo));foreach([$m,$otro] as $medico){$hs->guardar(['medico_id'=>$medico,'dia_semana'=>1,'hora_inicio'=>'08:00','hora_fin'=>'10:00']);}
    $lunes=(new DateTimeImmutable('next monday',HorarioAtencion::zona()))->modify('+14 days')->setTime(0,0);$fecha=$lunes->format('Y-m-d');
    $p=$f->paciente();$c=$f->cita($m,$p,$lunes->setTime(8,30)->format('c'));
    $q=$pdo->prepare("UPDATE pacientes SET nombres='PACIENTE_SECRETO_CU12' WHERE id=:id");$q->execute(['id'=>$p]);
    $q=$pdo->prepare("UPDATE citas SET motivo='MOTIVO_SECRETO_CU12' WHERE id=:id");$q->execute(['id'=>$c]);
    $ruta='/disponibilidad?' . http_build_query(['medico_id'=>$m,'fecha'=>$fecha]);
    $ok($http->pedir('/disponibilidad')['status']===303,'sin sesión no accede al formulario');
    $ok($http->pedir($ruta)['status']===303,'sin sesión no consulta turnos');
    $esperadas=[$fecha . 'T08:00:00-04:00',$fecha . 'T09:00:00-04:00',$fecha . 'T09:30:00-04:00'];
    foreach(['administrador','recepcionista','medico'] as $rol){
        $cookie=$sesiones[$rol]['cookie'];$r=$http->pedir($ruta,'GET',$cookie);
        $ok($r['status']===200 && $slots($r['body'])===$esperadas,'turnos reales exactos para ' . $rol);
        $ok(str_contains($r['body'],'informativa') && str_contains($r['body'],'America/La_Paz'),'explica zona e información no garantizada ' . $rol);
        $ok(!str_contains($r['body'],'PACIENTE_SECRETO') && !str_contains($r['body'],'MOTIVO_SECRETO'),'sin datos privados de pacientes ' . $rol);
    }
    $admin=$sesiones['administrador']['cookie'];$doctor=$sesiones['medico']['cookie'];
    $r=$http->pedir('/disponibilidad','GET',$admin);$ok($r['status']===200 && str_contains($r['body'],'Filtrar médicos') && str_contains($r['body'],'type="date"'),'formulario GET sin reservas');
    $r=$http->pedir('/disponibilidad?accion=filtrar&especialidad_id=' . $e,'GET',$admin);
    $ok($r['status']===200 && str_contains($r['body'],'MEDICO_PROPIO_DISP') && !str_contains($r['body'],'MEDICO_AJENO_DISP'),'filtro de especialidad restringe médicos');
    $r=$http->pedir('/disponibilidad?especialidad_id=' . $e . '&medico_id=' . $m . '&fecha=' . $fecha,'GET',$admin);$ok($slots($r['body'])===$esperadas,'consulta por especialidad vinculada');
    $r=$http->pedir('/disponibilidad?especialidad_id=' . $otraEspecialidad . '&medico_id=' . $m . '&fecha=' . $fecha,'GET',$admin);$ok($r['status']===422 && $slots($r['body'])===[],'rechaza médico fuera de especialidad');
    $r=$http->pedir('/disponibilidad?medico_id=' . $m . '&fecha=' . $lunes->modify('+1 day')->format('Y-m-d'),'GET',$admin);$ok($r['status']===200 && str_contains($r['body'],'no tiene atención activa') && $slots($r['body'])===[],'mensaje de día sin atención');
    foreach([
        ['medico_id'=>[],'fecha'=>$fecha],['medico_id'=>[$m],'fecha'=>$fecha],['medico_id'=>'1 OR 1=1','fecha'=>$fecha],
        ['medico_id'=>$m,'fecha'=>['x']],['medico_id'=>$m,'fecha'=>'2026-02-30'],
        ['medico_id'=>$m,'fecha'=>(new DateTimeImmutable('yesterday',HorarioAtencion::zona()))->format('Y-m-d')],
        ['medico_id'=>$m,'fecha'=>$fecha,'especialidad_id'=>['x']],['medico_id'=>PHP_INT_MAX,'fecha'=>$fecha],
        ['medico_id'=>$m,'fecha'=>$fecha,'accion'=>['x']],
    ] as $parametros){
        // Un array vacío se codifica sin clave; usar accion obliga la validación de consulta.
        $parametros['accion']=$parametros['accion']??'consultar';
        $r=$http->pedir('/disponibilidad?' . http_build_query($parametros),'GET',$admin);$ok($r['status']===422 && $slots($r['body'])===[],'parámetros inválidos no producen resultados');
    }
    $r=$http->pedir('/disponibilidad','GET',$doctor);$ok(!str_contains($r['body'],'MEDICO_AJENO_DISP'),'selector médico no divulga otro profesional');
    $ok($http->pedir('/disponibilidad?medico_id=' . $otro . '&fecha=' . $fecha,'GET',$doctor)['status']===403,'médico no fuerza consulta ajena por URL');
    $r=$http->pedir('/disponibilidad?fecha=' . $fecha,'GET',$doctor);$ok($r['status']===200 && $slots($r['body'])===$esperadas,'médico puede consultar fecha de su agenda por defecto');
    $ok($http->pedir($ruta,'GET',$sesiones['paciente']['cookie'])['status']===403,'paciente no accede al módulo');
    $ok($http->pedir('/disponibilidad','GET',$sesiones['paciente']['cookie'])['status']===403,'paciente no accede al formulario');
    foreach(['POST','DELETE','PUT'] as $metodo){$ok($http->pedir('/disponibilidad',$metodo,$admin,['medico_id'=>$m,'fecha'=>$fecha])['status']===405,'consulta rechaza método ' . $metodo);}
    $q=$pdo->prepare("UPDATE citas SET estado='cancelada' WHERE id=:id");$q->execute(['id'=>$c]);
    $r=$http->pedir($ruta,'GET',$admin);$ok(count($slots($r['body']))===4 && in_array($fecha . 'T08:30:00-04:00',$slots($r['body']),true),'segunda consulta actualiza disponibilidad tras cancelación ficticia');
    $q=$pdo->prepare('SELECT count(*) FROM citas WHERE medico_id=:m');$q->execute(['m'=>$m]);$antes=$q->fetchColumn();$http->pedir($ruta,'GET',$admin);$q->execute(['m'=>$m]);$ok($antes===$q->fetchColumn(),'GET no crea ni elimina citas');
    $q=$pdo->prepare('UPDATE medicos SET nombres=:n WHERE id=:id');$q->execute(['n'=>'<script>MedicoXSS</script>','id'=>$m]);
    $r=$http->pedir($ruta,'GET',$admin);$ok(str_contains($r['body'],'&lt;script&gt;MedicoXSS&lt;/script&gt;') && !str_contains($r['body'],'<script>MedicoXSS</script>'),'escape HTML de nombres');
    $q=$pdo->prepare('UPDATE especialidades SET activo=false WHERE id=:id');$q->execute(['id'=>$e]);
    $r=$http->pedir('/disponibilidad?especialidad_id=' . $e . '&medico_id=' . $m . '&fecha=' . $fecha,'GET',$admin);$ok($r['status']===422,'rechaza especialidad inactiva');
    $q=$pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id');$q->execute(['id'=>$otro]);$ok($http->pedir('/disponibilidad?medico_id=' . $otro . '&fecha=' . $fecha,'GET',$admin)['status']===422,'rechaza médico inactivo');
    $q=$pdo->prepare('UPDATE medicos SET usuario_id=NULL WHERE id=:id');$q->execute(['id'=>$m]);
    $r=$http->pedir('/disponibilidad','GET',$doctor);$ok($r['status']===200 && str_contains($r['body'],'no tiene un registro médico vinculado') && $slots($r['body'])===[] && !str_contains($r['body'],'MEDICO_AJENO_DISP'),'médico sin vínculo no recibe datos ajenos');
    $ok($http->pedir($ruta,'GET',$doctor)['status']===403,'médico sin vínculo no consulta por identificador');
}finally{if($http!==null){$http->cerrar();}$f->limpiar();}
echo "Aprobadas: $total. Datos HTTP ficticios eliminados en finally; ninguna cita permanente.\n";
