<?php
declare(strict_types=1);
require __DIR__.'/PerfilClinicoPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=null;$http=null;$navegador=null;$total=0;
try{
    $prueba=new PerfilClinicoPrueba($pdo);$antes=$prueba->huella();$http=new HttpCatalogo();
    $base=$http->base();
    $navegador=proc_open(['node',__DIR__.'/perfil_clinico_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($navegador)){throw new RuntimeException('No se pudo iniciar Edge CU23.');}
    fwrite($pipes[0],json_encode(['base'=>$base,'paciente'=>$prueba->paciente,'correo'=>$prueba->actores['medico']['correo'],
        'password'=>'Ficticia91!'.$prueba->fixture->sufijo],JSON_THROW_ON_ERROR));fclose($pipes[0]);
    $salida=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($navegador);$navegador=null;
    $r=json_decode($salida,true,512,JSON_THROW_ON_ERROR);
    if($code!==0||isset($r['error'])){throw new RuntimeException('Edge CU23: '.($r['error']??'interrumpido'));}
    $total=(int)$r['assertions'];clinicoAssert($r['noClinicalCaptures']===true,'no se generan capturas clínicas');
    $d=$prueba->servicio()->consultar($prueba->paciente);
    clinicoAssert(count($d['alergias'])===3&&count(array_filter($d['alergias'],static fn($a)=>$a['estado']==='inactiva'))===3,'tres tamaños guardaron e inactivaron registros reales propios');
    $despues=$prueba->huella();foreach(['usuarios','perfiles_clinicos','alergias_paciente','alergias_historial'] as $k){unset($antes[$k],$despues[$k]);}
    clinicoAssert($antes===$despues,'flujos clínicos no modifican citas ni los otros catálogos');
}finally{if(is_resource($navegador)){proc_terminate($navegador);proc_close($navegador);}if($http){$http->cerrar();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. Edge real escritorio/tablet/celular, guardados y revisiones sin capturas ni terceros.\n";
