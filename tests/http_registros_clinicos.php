<?php
declare(strict_types=1);
require __DIR__.'/RegistroClinicoPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$f=null;$http=null;$total=0;$cookies=[];
$dir=__DIR__.'/artifacts/cu24';if(!is_dir($dir)){mkdir($dir,0700,true);}$log=$dir.'/http-servidor.log';file_put_contents($log,'');$marca='CU24_PRIVADO_FICTICIO_'.bin2hex(random_bytes(6));
try{
    $f=new PerfilClinicoPrueba($pdo);$http=new HttpCatalogo(registroPrueba:$log);
    foreach($f->actores as $rol=>$actor){$l=$http->pedir('/login');$r=$http->pedir('/login','POST',$l['cookie'],['_csrf'=>HttpCatalogo::csrf($l['body']),'correo'=>$actor['correo'],'contrasena'=>'Ficticia91!'.$f->fixture->sufijo]);clinicoAssert($r['status']===303,'cuenta ficticia autenticada');$cookies[$rol]=$r['cookie'];}
    $p=$f->paciente;$cookie=$cookies['medico'];$s=cu24Servicio($f);
    foreach(['antecedentes','laboratorios'] as $tipo){
        $base='/pacientes/'.$p.'/'.$tipo;$r=$http->pedir($base,'GET',$cookie);$csrf=HttpCatalogo::csrf($r['body']);
        clinicoAssert($r['status']===200&&str_contains($r['body'],'data-cu24-vacio')&&str_contains($r['body'],'Esto no confirma ausencia'),'listado vacío autorizado sin afirmar ausencia');
        $headers=implode("\n",$r['headers']);clinicoAssert(str_contains($headers,'Cache-Control: private, no-store')&&str_contains($headers,'Pragma: no-cache')&&str_contains($headers,'Content-Security-Policy:'),'cabeceras privadas y CSP conservadas');
        $antes=cu24Huella($pdo);foreach([null,'incorrecto',[]] as $token){$e=cu24Entrada($tipo);if($token!==null){$e['_csrf']=$token;}clinicoAssert($http->pedir($base,'POST',$cookie,$e)['status']===419,'POST sin CSRF válido rechazado');}
        clinicoAssert($antes===cu24Huella($pdo),'CSRF rechazado no modifica ninguna tabla');
        $campo=$tipo==='antecedentes'?'descripcion':'resultado_texto';$payload='<script>window.CU24_XSS=1</script>'.$marca;
        $r=$http->pedir($base,'POST',$cookie,cu24Entrada($tipo,['_csrf'=>$csrf,$campo=>$payload]));clinicoAssert($r['status']===303&&$r['location']===$base.'?guardado=1','alta PRG sin datos en URL');
        $id=(int)$s->consultar($p,$tipo)['registros'][0]['id'];$detalle=$base.'/'.$id;$edit=$detalle.'/editar';$post=$detalle.'/actualizar';$hist=$detalle.'/historial';
        foreach([$base,$detalle,$edit,$hist] as $ruta){$r=$http->pedir($ruta,'GET',$cookie);clinicoAssert($r['status']===200&&str_contains($r['body'],htmlspecialchars($payload,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))&&!str_contains($r['body'],$payload),'vista clínica escapa texto XSS y conserva contenido autorizado');}
        $r=$http->pedir($post,'POST',$cookie,cu24Cambio($f,$tipo,$id,['_csrf'=>$csrf,$campo=>$marca.'-rectificado']));clinicoAssert($r['status']===303,'rectificación con versión, motivo y CSRF');
        $r=$http->pedir($hist,'GET',$cookie);clinicoAssert($r['status']===200&&str_contains($r['body'],'Versión 1')&&str_contains($r['body'],'Versión 2')&&str_contains($r['body'],'Anterior')&&str_contains($r['body'],'Actual'),'historial muestra instantáneas anterior y actual');
        $r=$http->pedir($post,'POST',$cookie,cu24Cambio($f,$tipo,$id,['_csrf'=>$csrf,'version'=>'1','estado'=>'inactivo']));clinicoAssert($r['status']===409&&str_contains($r['body'],'Recarga antes de guardar'),'versión obsoleta produce HTTP 409');
        $r=$http->pedir($post,'POST',$cookie,cu24Cambio($f,$tipo,$id,['_csrf'=>$csrf,'motivo_cambio'=>'','estado'=>'inactivo']));clinicoAssert($r['status']===422,'rectificación sin motivo produce 422');
        foreach([$base,$detalle,$edit,$post,$hist] as $ruta){foreach(['GET','POST','PUT','DELETE'] as $m){
            clinicoAssert($http->pedir($ruta,$m)['status']===303,'anónimo no accede por rutas o métodos alternativos');
            foreach(['administrador','recepcionista','paciente'] as $rol){$r=$http->pedir($ruta,$m,$cookies[$rol]);clinicoAssert($r['status']===403&&!str_contains($r['body'],$marca),'rol no clínico rechazado sin datos');}
        }}
        foreach(['otro_medico','sin_vinculo'] as $rol){foreach([$base,$detalle,$edit,$hist] as $ruta){clinicoAssert($http->pedir($ruta,'GET',$cookies[$rol])['status']===404,'médico sin relación o vínculo rechazado');}}
        foreach(['/pacientes/'.$f->ajeno.'/'.$tipo.'/'.$id.'/historial','/pacientes/'.$f->sinCita.'/'.$tipo,$base.'/9223372036854775808/historial','/pacientes/9223372036854775808/'.$tipo] as $ruta){$r=$http->pedir($ruta,'GET',$cookie);clinicoAssert($r['status']===404&&!str_contains($r['body'],$marca),'IDOR, recurso ajeno o desbordamiento rechazados');}
        clinicoAssert($http->pedir('/pacientes/'.$f->ajeno.'/'.$tipo.'/'.$id.'/actualizar','POST',$cookie,cu24Cambio($f,$tipo,$id,['_csrf'=>$csrf,'estado'=>'inactivo']))['status']===404,'POST con paciente manipulado rechazado');
        foreach([$base,$detalle,$edit,$hist] as $ruta){clinicoAssert($http->pedir($ruta,'DELETE',$cookie)['status']===405,'no existe borrado mediante DELETE');}
        clinicoAssert($http->pedir($post,'GET',$cookie)['status']===405&&$http->pedir($hist,'POST',$cookie,['_csrf'=>$csrf])['status']===405,'GET no escribe y POST no modifica historial');
        $r=$http->pedir($base,'POST',$cookie,cu24Entrada($tipo,['_csrf'=>$csrf,'confirmar'=>'0']));clinicoAssert($r['status']===422,'confirmación expresa obligatoria');
    }
    $logTexto=file_get_contents($log);clinicoAssert(!str_contains($logTexto,$marca)&&!str_contains($logTexto,'PDOException'),'log real sin valores clínicos ni errores PDO');
    $rutaSesion=session_save_path()?:sys_get_temp_dir();
    foreach($cookies as $c){if(preg_match('/^[a-zA-Z0-9,-]+$/D',$c)&&is_file($rutaSesion.'/sess_'.$c)){$v=file_get_contents($rutaSesion.'/sess_'.$c);clinicoAssert(!str_contains($v,$marca),'sesiones ficticias sin datos clínicos de formularios');}}
}finally{if($http){$http->cerrar();}cu24Limpiar($f);}
echo "Aprobadas: $total. HTTP real CU24, roles, CSRF, XSS, IDOR, no caché, logs y sesiones.\n";
