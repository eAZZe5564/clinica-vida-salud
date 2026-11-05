<?php
declare(strict_types=1);
require __DIR__.'/PerfilClinicoPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=null;$http=null;$total=0;
$dir=__DIR__.'/artifacts/cu23';if(!is_dir($dir)){mkdir($dir,0700,true);}$log=$dir.'/http-servidor.log';
file_put_contents($log,'');$marcador='CU23_PRIVADO_FICTICIO_'.bin2hex(random_bytes(6));
try{
    $prueba=new PerfilClinicoPrueba($pdo);$http=new HttpCatalogo(registroPrueba:$log);$cookies=[];
    foreach($prueba->actores as $rol=>$actor){
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),
            'correo'=>$actor['correo'],'contrasena'=>'Ficticia91!'.$prueba->fixture->sufijo]);
        clinicoAssert($r['status']===303,'login de cuenta ficticia');$cookies[$rol]=$r['cookie'];
    }
    $p=$prueba->paciente;$base='/pacientes/'.$p;$cookie=$cookies['medico'];
    $r=$http->pedir($base.'/perfil-clinico','GET',$cookie);$csrf=HttpCatalogo::csrf($r['body']);
    clinicoAssert($r['status']===200&&str_contains($r['body'],'Perfil aún no registrado'),'GET perfil vacío autorizado');
    $headers=implode("\n",$r['headers']);
    clinicoAssert(str_contains($headers,'Cache-Control: private, no-store')&&str_contains($headers,'Pragma: no-cache'),'páginas clínicas privadas sin caché');
    clinicoAssert(str_contains($headers,'Content-Security-Policy:')&&str_contains($headers,'X-Content-Type-Options: nosniff'),'cabeceras de protección conservadas');
    $antes=$prueba->huella();
    clinicoAssert($http->pedir($base.'/perfil-clinico','POST',$cookie,$prueba->perfil())['status']===419,'CSRF ausente rechazado');
    foreach(['incorrecto',[]] as $token){clinicoAssert($http->pedir($base.'/alergias','POST',$cookie,$prueba->alergia(['_csrf'=>$token]))['status']===419,'CSRF inválido o array rechazado');}
    clinicoAssert($antes===$prueba->huella(),'CSRF rechazado no cambia las doce tablas');
    $payload='<script>window.CU23_XSS=1</script>'.$marcador;
    $r=$http->pedir($base.'/perfil-clinico','POST',$cookie,$prueba->perfil(['_csrf'=>$csrf,'observaciones'=>$payload]));
    clinicoAssert($r['status']===303&&$r['location']===$base.'/perfil-clinico?guardado=1','perfil POST-Redirect-GET sin valores clínicos en URL');
    $r=$http->pedir($base.'/perfil-clinico','GET',$cookie);
    clinicoAssert(str_contains($r['body'],htmlspecialchars($payload,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))&&!str_contains($r['body'],$payload),'perfil escapa XSS conservando texto');
    clinicoAssert(str_contains($r['body'],'No registrado')&&str_contains($r['body'],'value="no_registrado" selected'),'valores desconocidos no se convierten en confirmaciones');
    $r=$http->pedir($base.'/alergias','POST',$cookie,$prueba->alergia(['_csrf'=>$csrf,'sustancia'=>$marcador,'reaccion'=>'<img src=x onerror=window.CU23_XSS=2>']));
    clinicoAssert($r['status']===303&&$r['location']===$base.'/alergias?guardado=1','alta de alergia POST-Redirect-GET');
    $datos=$prueba->servicio()->consultar($p);$id=(int)$datos['alergias'][0]['id'];$edit=$base.'/alergias/'.$id.'/editar';$post=$base.'/alergias/'.$id.'/actualizar';$hist=$base.'/alergias/'.$id.'/historial';
    $r=$http->pedir($base.'/alergias','GET',$cookie);clinicoAssert($r['status']===200&&str_contains($r['body'],'&lt;img')&&!str_contains($r['body'],'<img src=x'),'alergia escapada y no ejecutable');
    clinicoAssert(str_contains($r['body'],'href="'.$edit.'"')&&str_contains($r['body'],'href="'.$hist.'"'),'navegación a edición e historial');
    $r=$http->pedir($edit,'GET',$cookie);clinicoAssert($r['status']===200&&str_contains($r['body'],'name="motivo_cambio"')&&str_contains($r['body'],'name="version"'),'formulario exige motivo, versión y confirmación');
    $r=$http->pedir($post,'POST',$cookie,$prueba->cambio($id,['_csrf'=>$csrf,'estado'=>'inactiva']));clinicoAssert($r['status']===303,'inactivación por POST');
    $r=$http->pedir($hist,'GET',$cookie);clinicoAssert($r['status']===200&&str_contains($r['body'],'Versión 2')&&str_contains($r['body'],'Versión 1'),'historial conserva versiones y responsable');
    $r=$http->pedir($post,'POST',$cookie,$prueba->cambio($id,['_csrf'=>$csrf,'version'=>'1','estado'=>'activa']));clinicoAssert($r['status']===409&&str_contains($r['body'],'Recarga antes de guardar'),'edición obsoleta HTTP 409');
    $r=$http->pedir($base.'/perfil-clinico','POST',$cookie,$prueba->perfil(['_csrf'=>$csrf,'version'=>'2','estado_alergias'=>'sin_conocidas','confirmar'=>'0']));
    clinicoAssert($r['status']===422,'declaración sin confirmación no aceptada');
    $version=$prueba->servicio()->consultar($p)['perfil']['version'];
    $r=$http->pedir($base.'/perfil-clinico','POST',$cookie,$prueba->perfil(['_csrf'=>$csrf,'version'=>(string)$version,'estado_alergias'=>'sin_conocidas']));clinicoAssert($r['status']===303,'declaración expresa sin entradas activas aceptada');
    $rutas=[$base.'/perfil-clinico',$base.'/alergias',$edit,$post,$hist];
    foreach($rutas as $ruta){foreach(['GET','POST','PUT','DELETE'] as $metodo){
        clinicoAssert($http->pedir($ruta,$metodo)['status']===303,'anónimo no accede por URL ni método alternativo');
        foreach(['administrador','recepcionista','paciente'] as $rol){$r=$http->pedir($ruta,$metodo,$cookies[$rol]);clinicoAssert($r['status']===403&&!str_contains($r['body'],$marcador),'rol no clínico rechazado sin valores privados');}
    }}
    foreach(['sin_vinculo','otro_medico'] as $rol){foreach([$base.'/perfil-clinico',$base.'/alergias',$edit,$hist] as $ruta){$r=$http->pedir($ruta,'GET',$cookies[$rol]);clinicoAssert($r['status']===404&&!str_contains($r['body'],$marcador),'médico fuera de alcance rechazado por URL');}}
    foreach([$base.'/alergias/9223372036854775808/historial','/pacientes/9223372036854775808/perfil-clinico',
        '/pacientes/'.$prueba->ajeno.'/alergias/'.$id.'/historial','/pacientes/'.$prueba->sinCita.'/perfil-clinico'] as $ruta){clinicoAssert($http->pedir($ruta,'GET',$cookie)['status']===404,'IDOR, desbordamiento y paciente sin cita rechazados');}
    $r=$http->pedir('/pacientes/'.$prueba->ajeno.'/alergias/'.$id.'/actualizar','POST',$cookie,$prueba->cambio($id,['_csrf'=>$csrf]));clinicoAssert($r['status']===404,'POST con paciente/alergia manipulados rechazado');
    foreach([$base.'/perfil-clinico',$base.'/alergias'] as $ruta){foreach(['PUT','DELETE'] as $m){clinicoAssert($http->pedir($ruta,$m,$cookie)['status']===405,'métodos ajenos rechazados');}}
    clinicoAssert($http->pedir($post,'GET',$cookie)['status']===405,'GET no modifica alergias');
    clinicoAssert($http->pedir($hist,'POST',$cookie,['_csrf'=>$csrf])['status']===405,'historial no admite escritura HTTP');
    $antes=$prueba->huella();
    foreach([['sustancia'=>['x']],['sustancia'=>''],['reaccion'=>str_repeat('x',251)],['sustancia'=>'1 OR 1=1','confirmar'=>'0']] as $c){
        $r=$http->pedir($base.'/alergias','POST',$cookie,$prueba->alergia($c+['_csrf'=>$csrf]));clinicoAssert($r['status']===422&&!str_contains($r['body'],'PDOException'),'validación HTTP sin error nativo');
    }
    clinicoAssert($antes===$prueba->huella(),'URLs, métodos y validaciones fallidas sin efectos secundarios');
    $hoy=(new DateTimeImmutable('now',App\Models\HorarioAtencion::zona()))->format('Y-m-d');
    $r=$http->pedir('/agenda/diaria?fecha='.$hoy,'GET',$cookie);
    clinicoAssert($r['status']===200&&str_contains($r['body'],'href="'.$base.'/perfil-clinico"')&&!str_contains($r['body'],$marcador),'agenda propia ofrece enlace sin datos clínicos');
    foreach(['administrador','recepcionista'] as $rol){$r=$http->pedir('/agenda/diaria?fecha='.$hoy.'&medico_id='.$prueba->medico,'GET',$cookies[$rol]);clinicoAssert($r['status']===200&&!str_contains($r['body'],$base.'/perfil-clinico')&&!str_contains($r['body'],$marcador),'agenda administrativa no muestra acceso ni detalles clínicos');}
    $logTexto=file_get_contents($log);clinicoAssert(strlen($logTexto)>0&&!str_contains($logTexto,$marcador)&&!str_contains($logTexto,'CU23_XSS'),'registro real del servidor no contiene valores clínicos POST');
    $rutaSesion=ini_get('session.save_path')?:sys_get_temp_dir();if(str_contains($rutaSesion,';')||ini_get('session.save_handler')!=='files'){throw new RuntimeException('Ruta de sesiones no verificable en esta prueba.');}
    foreach($cookies as $session){$archivo=rtrim($rutaSesion,'/\\').DIRECTORY_SEPARATOR.'sess_'.$session;
        clinicoAssert(is_readable($archivo)&&!str_contains(file_get_contents($archivo),$marcador),'sesión propia del fixture sin valores clínicos');}
}finally{if($http){$http->cerrar();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. HTTP real CU23, roles, IDOR, CSRF/XSS, caché, sesión y logs; sin capturas clínicas.\n";
