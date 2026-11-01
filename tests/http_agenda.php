<?php
declare(strict_types=1);
require __DIR__.'/AgendaPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=null;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
try{
    $prueba=new AgendaPrueba($pdo);$http=new HttpCatalogo();$sesiones=[];
    foreach($prueba->actores as $rol=>$actor){
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$actor['correo'],'contrasena'=>'Ficticia91!'.$prueba->t->fixture->sufijo]);
        $ok($r['status']===303,'login fixture '.$rol);$sesiones[$rol]=$r['cookie'];
    }
    $pdo->prepare('UPDATE medicos SET nombres=:n WHERE id=:id')->execute(['n'=>'Médico <script>FICTICIO_CU19</script>','id'=>$prueba->medico]);
    $pdo->prepare('UPDATE especialidades SET nombre=:n WHERE id=:id')->execute(['n'=>'Especialidad <img src=x onerror=FICTICIO_CU19>','id'=>$prueba->especialidad]);
    $parametros=['fecha'=>$prueba->fecha,'medico_id'=>$prueba->medico];$query=http_build_query($parametros);
    $antes=$prueba->huella();
    foreach(['/agenda','/agenda/diaria','/agenda/semanal'] as $url){
        foreach(['GET','POST'] as $metodo){$ok($http->pedir($url.'?'.$query,$metodo)['status']===303,'sesión requerida '.$url.' '.$metodo);$ok($http->pedir($url.'?'.$query,$metodo,$sesiones['paciente'])['status']===403,'paciente rechazado '.$url.' '.$metodo);}
        foreach(['administrador','recepcionista','medico'] as $rol){
            $r=$http->pedir($url.'?'.$query,'GET',$sesiones[$rol]);$ok($r['status']===200&&str_contains($r['body'],'data-agenda-cita="'.$prueba->ids['programada'].'"'),'lectura autorizada '.$url.' '.$rol);
            foreach(['POST','PUT','DELETE'] as $metodo){$ok($http->pedir($url.'?'.$query,$metodo,$sesiones[$rol],['estado'=>'cancelada'])['status']===405,'solo GET '.$rol.' '.$metodo.' '.$url);}
        }
    }
    $a=$sesiones['administrador'];$m=$sesiones['medico'];
    $r=$http->pedir('/agenda/diaria?'.$query,'GET',$a);$body=$r['body'];
    $ok(str_contains($body,'action="/agenda/diaria"')&&str_contains($body,'data-agenda-vista-actual="diaria"')&&str_contains($body,'Vista diaria'),'diaria conserva modo y acción de formulario al renderizar');
    $ok(str_contains($body,'&lt;b&gt;ficticio CU19&lt;/b&gt;')&&!str_contains($body,'<b>ficticio CU19</b>'),'paciente escapado');
    $ok(str_contains($body,'&lt;script&gt;FICTICIO_CU19&lt;/script&gt;')&&!str_contains($body,'<script>FICTICIO_CU19</script>'),'médico escapado en encabezado/selector');
    $ok(str_contains($body,'&lt;img src=x onerror=FICTICIO_CU19&gt;')&&!str_contains($body,'<img src=x onerror=FICTICIO_CU19>'),'especialidad escapada');
    foreach(['MOTIVO_CLINICO_PRIVADO_CU19','CI-PRIVADO-','DIRECCION_PRIVADA_CU19','70001122','hash_contrasena'] as $privado){$ok(!str_contains($body,$privado),'sin dato privado '.$privado);}
    preg_match_all('/data-agenda-cita="([0-9]+)"/',$body,$matches);$ok(count($matches[1])===6&&!in_array((string)$prueba->ids['cancelada'],$matches[1],true),'diaria operativa sin canceladas');
    $ok(!in_array((string)$prueba->ids['ajena'],$matches[1],true),'sin citas de otro profesional');
    foreach($matches[1] as $id){$ok(str_contains($body,'href="/citas/'.$id.'"')&&$http->pedir('/citas/'.$id,'GET',$m)['status']===200,'detalle CU14 propio autorizado');}
    $ok(!preg_match('#href="/citas/[0-9]+/(reprogramar|cancelar|confirmar|llegada|atencion|inasistencia)"#',$body),'agenda no ofrece acciones de escritura');
    $ok(!preg_match('#<form[^>]+method="post"[^>]+action="(?!/logout")#',$body),'solo formulario GET, salvo logout global');
    $ok(str_contains($body,'Sin cita registrada')&&str_contains($body,'Fuera de atención')&&str_contains($body,'08:00–12:00, 14:00–15:00'),'horarios y huecos diferenciados de citas');
    $todos=$http->pedir('/agenda/semanal?'.$query.'&estado=todas','GET',$a);$ok($todos['status']===200&&str_contains($todos['body'],'data-agenda-cita="'.$prueba->ids['cancelada'].'"')&&str_contains($todos['body'],'no reserva el horario'),'cancelada histórica explícita');
    $ok(str_contains($todos['body'],'action="/agenda/semanal"')&&str_contains($todos['body'],'data-agenda-vista-actual="semanal"'),'semanal conserva modo y acción de formulario al renderizar');
    preg_match_all('/data-agenda-dia="([^"]+)"/',$todos['body'],$dias);$ok(count($dias[1])===7&&$dias[1][0]===$prueba->fecha,'semana lunes a domingo');
    foreach(App\Models\Cita::ESTADOS as $estado=>$label){
        $r=$http->pedir('/agenda/diaria?'.$query.'&estado='.$estado,'GET',$a);preg_match_all('/data-agenda-cita="([0-9]+)"/',$r['body'],$c);
        $esperado=$estado==='programada'?2:1;$ok($r['status']===200&&count($c[1])===$esperado&&str_contains($r['body'],$label),'estado HTTP '.$estado);
    }
    $r=$http->pedir('/agenda?fecha='.$prueba->fecha,'GET',$m);$ok($r['status']===200&&str_contains($r['body'],'data-agenda-cita="'.$prueba->ids['programada'].'"')&&!str_contains($r['body'],'id="agenda-medico"'),'médico sin selector de agendas ajenas');
    foreach([$prueba->otro,PHP_INT_MAX] as $id){$r=$http->pedir('/agenda/semanal?fecha='.$prueba->fecha.'&medico_id='.$id,'GET',$m);$ok($r['status']===403&&!str_contains($r['body'],'data-agenda-cita='),'IDOR rechazado sin citas');}
    $r=$http->pedir('/agenda/semanal','GET',$sesiones['sin_vinculo']);$ok($r['status']===200&&str_contains($r['body'],'no tiene un registro médico vinculado')&&!str_contains($r['body'],'data-agenda-cita='),'cuenta sin vínculo controlada');
    $r=$http->pedir('/agenda','GET',$a);$ok($r['status']===200&&str_contains($r['body'],'Selecciona un médico')&&!str_contains($r['body'],'data-agenda-cita='),'sin selección no carga agendas generales');
    foreach(['fecha=2026-02-30','fecha%5B%5D=2026-01-01','estado=eliminada','estado%5B%5D=presente','medico_id=0','medico_id=1%20OR%201=1','medico_id%5B%5D=1','fecha=%3Cscript%3EFICTICIO%3C%2Fscript%3E'] as $invalido){$r=$http->pedir('/agenda?'.$invalido,'GET',$a);$ok($r['status']===422&&!str_contains($r['body'],'data-agenda-cita=')&&!str_contains($r['body'],'<script>FICTICIO</script>'),'filtro malformado controlado sin eco peligroso');}
    $ok($http->pedir('/agenda?medico_id='.PHP_INT_MAX,'GET',$a)['status']===404,'médico inexistente 404');
    $ok($http->pedir('/agenda/mensual','GET',$a)['status']===404,'vista inexistente 404');
    $r=$http->pedir('/agenda/diaria?fecha=2031-06-03&medico_id='.$prueba->medico,'GET',$a);$ok($r['status']===200&&str_contains($r['body'],'Sin citas para este día y filtro.'),'diaria vacía válida');
    $r=$http->pedir('/agenda/semanal?fecha=2031-06-03&medico_id='.$prueba->medico,'GET',$a);$ok($r['status']===200&&!str_contains($r['body'],'data-agenda-cita='),'semana vacía válida');
    $ok($antes===$prueba->huella(),'todas las rutas/filtros/métodos conservan las nueve tablas tras login');
}finally{if($http){$http->cerrar();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. HTTP real, permisos/IDOR/privacidad/GET y huellas sin cambios; fixtures propios retirados.\n";
