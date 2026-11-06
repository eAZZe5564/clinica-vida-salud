<?php
declare(strict_types=1);
require __DIR__.'/PortalPacientePrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$f=null;$http=null;$cookies=[];$total=0;$antes=cu24Huella($pdo);
$dir=__DIR__.'/artifacts/cu26';if(!is_dir($dir)){mkdir($dir,0700,true);}$log=$dir.'/http.log';file_put_contents($log,'');
$catalogo=static function(string $html):array{
    if(!preg_match('/data-catalogo="([^"]+)"/',$html,$m)){throw new RuntimeException('Falta catálogo de voz.');}
    return json_decode(html_entity_decode($m[1],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),true,512,JSON_THROW_ON_ERROR);
};
try{
    $f=new PortalPacientePrueba($pdo);$propia=$f->cita();$ajena=$f->cita('08:30','otro');$http=new HttpCatalogo(registroPrueba:$log);
    $r=$http->pedir('/login');portalAssert(!str_contains($r['body'],'data-voz'),'login anónimo sin micrófono ni catálogo');
    foreach($f->actores as $rol=>$a){$l=$http->pedir('/login');$r=$http->pedir('/login','POST',$l['cookie'],['_csrf'=>HttpCatalogo::csrf($l['body']),'correo'=>$a['correo'],'contrasena'=>'Ficticia91!'.$f->fixture->sufijo]);portalAssert($r['status']===303,'login ficticio');$cookies[$rol]=$r['cookie'];}
    $huella=cu24Huella($pdo);
    foreach($cookies as $rol=>$cookie){
        $r=$http->pedir('/dashboard','GET',$cookie);portalAssert($r['status']===200,'panel autorizado');$c=$catalogo($r['body']);
        $esperado=App\Services\CatalogoVoz::paraRol(in_array($rol,['otro','sin_vinculo'],true)?'paciente':$rol,$rol==='paciente'||$rol==='otro');portalAssert($c===$esperado,'catálogo filtrado por rol y vínculo real');
        portalAssert(str_contains($r['body'],'envían audio a un servicio externo')&&str_contains($r['body'],'acepto voluntariamente')&&str_contains($r['body'],'data-voz-iniciar disabled'),'aviso previo y aceptación sin inicio automático');
        foreach($c as $comando){
            if($comando['ruta']!==null){$destino=$http->pedir($comando['ruta'],'GET',$cookie);portalAssert($destino['status']===200,'destino real autorizado y existente');}
        }
        // Los campos de texto, consentimiento y selector no tienen name: ni un envío nativo los transmite.
        preg_match('#<form data-voz-manual.*?</form>#s',$r['body'],$form);portalAssert(isset($form[0])&&!preg_match('/\bname\s*=/',$form[0]),'formulario manual no serializa transcripciones');
    }
    $edit=$http->pedir('/admin/users/'.$f->actores['paciente']['id'].'/edit','GET',$cookies['administrador']);
    portalAssert($edit['status']===200&&$catalogo($edit['body'])===App\Services\CatalogoVoz::paraRol('administrador'),'catálogo del actor real al editar una cuenta Paciente');
    portalAssert($huella===cu24Huella($pdo),'abrir ayuda y destinos solo GET no modifica ninguna de las dieciséis tablas');
    foreach(['/agenda','/citas','/pacientes','/reportes/citas','/historial-citas','/pacientes/'.$f->paciente.'/perfil-clinico','/pacientes/'.$f->paciente.'/alergias','/pacientes/'.$f->paciente.'/antecedentes','/pacientes/'.$f->paciente.'/laboratorios'] as $ruta){portalAssert($http->pedir($ruta,'GET',$cookies['paciente'])['status']===403,'servidor rechaza rutas fuera del alcance Paciente');}
    portalAssert($http->pedir('/mis-citas/'.$ajena,'GET',$cookies['paciente'])['status']===404,'ID ajeno sigue denegado');
    portalAssert($http->pedir('/mis-citas/'.$propia,'GET',$cookies['paciente'])['status']===200,'cita propia autorizada');
    portalAssert($http->pedir('/mis-citas','GET',$cookies['sin_vinculo'])['status']===403,'vínculo ausente no obtiene citas');
    foreach(['medico','recepcionista','administrador'] as $rol){portalAssert($http->pedir('/mis-citas','GET',$cookies[$rol])['status']===403,'sin suplantación de portal');}
    foreach(['recepcionista','administrador'] as $rol){foreach(['perfil-clinico','alergias','antecedentes','laboratorios'] as $tipo){portalAssert($http->pedir('/pacientes/'.$f->paciente.'/'.$tipo,'GET',$cookies[$rol])['status']===403,'catálogo de consultas no habilita clínica');}}
    $r=$http->pedir('/dashboard','GET',$cookies['paciente']);$csrf=HttpCatalogo::csrf($r['body']);
    portalAssert($http->pedir('/logout','POST',$cookies['paciente'],['_csrf'=>'incorrecto'])['status']===419,'logout mantiene CSRF');
    portalAssert($http->pedir('/logout','POST',$cookies['paciente'],['_csrf'=>$csrf])['status']===303,'logout habitual conservado');
    portalAssert($http->pedir('/mis-citas','GET',$cookies['paciente'])['status']===303,'sesión cerrada no vuelve al portal por voz ni URL');
    portalAssert(!str_contains(file_get_contents($log),'PDOException'),'sin errores SQL del módulo');
}finally{if($http){$http->cerrar();}if($f){$f->limpiar();}}
portalAssert($antes===cu24Huella($pdo),'fixtures retirados y filas anteriores intactas');
echo "Aprobadas: $total. Catálogo HTTP, rutas reales, roles, vínculo, CSRF y ausencia de escrituras de voz.\n";
