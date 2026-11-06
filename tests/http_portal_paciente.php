<?php
declare(strict_types=1);
require __DIR__.'/PortalPacientePrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$f=null;$http=null;$total=0;$cookies=[];$antes=cu24Huella($pdo);
try{
    $f=new PortalPacientePrueba($pdo);$id=$f->cita();$ajena=$f->cita('08:30','otro');$http=new HttpCatalogo();
    foreach($f->actores as $rol=>$a){$l=$http->pedir('/login');$r=$http->pedir('/login','POST',$l['cookie'],['_csrf'=>HttpCatalogo::csrf($l['body']),'correo'=>$a['correo'],'contrasena'=>'Ficticia91!'.$f->fixture->sufijo]);portalAssert($r['status']===303,'login ficticio');$cookies[$rol]=$r['cookie'];}
    $rutas=['/mis-citas','/mis-citas/historial','/mis-citas/nueva','/mis-citas/'.$id,'/mis-citas/'.$id.'/historial','/mis-citas/'.$id.'/reprogramar','/mis-citas/'.$id.'/cancelar'];
    foreach($rutas as $ruta){
        foreach(['GET','POST','PUT','DELETE'] as $metodo){
            portalAssert($http->pedir($ruta,$metodo)['status']===303,'anónimo sin acceso');
            foreach(['administrador','recepcionista','medico'] as $rol){portalAssert($http->pedir($ruta,$metodo,$cookies[$rol])['status']===403,'rol ajeno rechazado por ruta y método');}
        }
        $r=$http->pedir($ruta,'GET',$cookies['paciente']);portalAssert($r['status']===200,'vista propia disponible');
        $h=implode("\n",$r['headers']);portalAssert(str_contains($h,'Cache-Control: private, no-store')&&str_contains($h,'Content-Security-Policy:')&&str_contains($h,'Pragma: no-cache'),'privacidad y CSP');
    }
    $c=$cookies['paciente'];$csrf=HttpCatalogo::csrf($http->pedir('/mis-citas','GET',$c)['body']);
    foreach(['/mis-citas/'.$ajena,'/mis-citas/'.$ajena.'/historial','/mis-citas/'.$ajena.'/reprogramar','/mis-citas/'.$ajena.'/cancelar','/mis-citas/9223372036854775808'] as $ruta){portalAssert($http->pedir($ruta,'GET',$c)['status']===404,'IDOR rechazado');}
    foreach(['reprogramar','cancelar'] as $a){portalAssert($http->pedir('/mis-citas/'.$ajena.'/'.$a,'POST',$c,['_csrf'=>$csrf,'confirmar'=>'1'])['status']===404,'escritura IDOR rechazada');}
    $sin=$http->pedir('/mis-citas','GET',$cookies['sin_vinculo']);portalAssert($sin['status']===403&&str_contains($sin['body'],'no tiene una vinculación activa'),'mensaje sin vínculo');
    foreach(['paciente_id','usuario_id','ci','correo','rol'] as $key){portalAssert($http->pedir('/mis-citas?'.http_build_query([$key=>'CU25_AJENO']),'GET',$c)['status']===422,'parámetro identidad GET rechazado');portalAssert($http->pedir('/mis-citas','POST',$c,['_csrf'=>$csrf,$key=>'CU25_AJENO']+$f->entrada('09:00'))['status']===422,'parámetro identidad POST rechazado');}
    foreach(['/mis-citas','/mis-citas/'.$id.'/reprogramar','/mis-citas/'.$id.'/cancelar'] as $ruta){foreach([null,'incorrecto',[]] as $token){$e=$f->entrada('09:00');if($token!==null){$e['_csrf']=$token;}portalAssert($http->pedir($ruta,'POST',$c,$e)['status']===419,'CSRF obligatorio');}}
    $r=$http->pedir('/mis-citas','POST',$c,['_csrf'=>$csrf]+$f->entrada('09:00'));portalAssert($r['status']===303&&preg_match('#^/mis-citas/[1-9][0-9]*$#D',$r['location'])===1,'reserva PRG propia');
    $nuevo=(int)basename($r['location']);$d=$http->pedir($r['location'],'GET',$c);portalAssert(str_contains($d['body'],'La operación se confirmó correctamente.'),'confirmación mostrada');
    $r=$http->pedir('/mis-citas/'.$nuevo.'/reprogramar','POST',$c,['_csrf'=>$csrf]+$f->mutacion($nuevo,'09:30'));portalAssert($r['status']===303,'reprogramación PRG');
    $r=$http->pedir('/mis-citas/'.$nuevo.'/cancelar','POST',$c,['_csrf'=>$csrf]+$f->mutacion($nuevo,accion:'cancelar')+['motivo_cancelacion'=>'CU25_MOTIVO_NO_PUBLICO']);portalAssert($r['status']===303,'cancelación PRG');
    $r=$http->pedir('/mis-citas/'.$nuevo.'/historial','GET',$c);portalAssert(str_contains($r['body'],'Reprogramacion')&&str_contains($r['body'],'Cancelacion')&&!str_contains($r['body'],'CU25_MOTIVO_NO_PUBLICO')&&!str_contains($r['body'],$f->actores['administrador']['correo']),'historial propio sin datos internos');
    portalAssert(!str_contains($r['body'],'/reprogramar')&&!str_contains($r['body'],'/cancelar'),'acciones de estado incompatible ocultas');
    $payload='<img src=x onerror=window.CU25_XSS=1>';
    $pdo->prepare('UPDATE medicos SET apellidos=:n WHERE id=:id')->execute(['n'=>$payload,'id'=>$f->medico]);
    foreach(['/mis-citas?periodo=todas','/mis-citas/'.$id,'/mis-citas/'.$id.'/reprogramar'] as $ruta){$r=$http->pedir($ruta,'GET',$c);portalAssert($r['status']===200&&str_contains($r['body'],htmlspecialchars($payload,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))&&!str_contains($r['body'],$payload),'escape XSS');}
    // Vinculación inicial real HTTP con comprobación explícita, solo fixtures.
    $ruta='/pacientes/'.$f->sinCuenta.'/vincular-cuenta';$admin=$cookies['administrador'];$r=$http->pedir($ruta,'GET',$admin);portalAssert($r['status']===200,'formulario de vinculación administrador');$token=HttpCatalogo::csrf($r['body']);
    foreach(['paciente','recepcionista','medico'] as $rol){foreach(['GET','POST','DELETE'] as $m){portalAssert($http->pedir($ruta,$m,$cookies[$rol])['status']===403,'sin autovinculación ni otros roles');}}
    portalAssert($http->pedir($ruta,'POST',$admin,['cuenta_id'=>$f->actores['sin_vinculo']['id'],'identidad_verificada'=>'1'])['status']===419,'CSRF vinculación');
    portalAssert($http->pedir($ruta,'POST',$admin,['_csrf'=>$token,'cuenta_id'=>$f->actores['sin_vinculo']['id']])['status']===422,'verificación identidad requerida');
    $e=['_csrf'=>$token,'cuenta_id'=>$f->actores['sin_vinculo']['id'],'identidad_verificada'=>'1'];$r=$http->pedir($ruta,'POST',$admin,$e);portalAssert($r['status']===303&&$r['location']===$ruta,'vínculo PRG');
    $r=$http->pedir($ruta,'GET',$admin);portalAssert(str_contains($r['body'],'Vinculación realizada por el administrador')&&!str_contains($r['body'],'data-portal-vincular'),'recibo transitorio y no sustitución');
    portalAssert($http->pedir($ruta,'POST',$admin,$e)['status']===409,'doble envío rechazado');
    portalAssert($http->pedir('/mis-citas','GET',$cookies['sin_vinculo'])['status']===200,'vínculo efectivo en siguiente solicitud');
    foreach(['/agenda','/citas','/historial-citas','/pacientes','/disponibilidad','/reportes/citas','/reportes/citas/exportar/pdf'] as $ruta){portalAssert($http->pedir($ruta,'GET',$c)['status']===403,'portal no abre rutas administrativas');}
    portalAssert($http->pedir('/mis-citas/'.$id,'DELETE',$c)['status']===405,'método no permitido');
}finally{if($http){$http->cerrar();}if($f){$f->limpiar();}}
portalAssert($antes===cu24Huella($pdo),'HTTP preserva todos los registros anteriores');
echo "Aprobadas: $total. HTTP real, roles, CSRF, XSS, IDOR, privacidad, PRG y vínculo inicial.\n";
