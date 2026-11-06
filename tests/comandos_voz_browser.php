<?php
declare(strict_types=1);
require __DIR__.'/PortalPacientePrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$f=null;$http=null;$browser=null;$total=0;$antes=cu24Huella($pdo);
try{
    $f=new PortalPacientePrueba($pdo);$f->cita();$f->cita('08:30','otro');$inicial=cu24Huella($pdo);$http=new HttpCatalogo();
    $browser=proc_open(['node',__DIR__.'/comandos_voz_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($browser)){throw new RuntimeException('No se pudo iniciar Edge CU26.');}
    fwrite($pipes[0],json_encode(['base'=>$http->base(),'actores'=>$f->actores,'password'=>'Ficticia91!'.$f->fixture->sufijo],JSON_THROW_ON_ERROR));fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($browser);$browser=null;$r=json_decode($out,true,512,JSON_THROW_ON_ERROR);
    $dir=__DIR__.'/artifacts/cu26';if(!is_dir($dir)){mkdir($dir,0700,true);}
    if($code!==0||isset($r['error'])){file_put_contents($dir.'/browser-diagnostico.json',json_encode(['error'=>$r['error']??'interrumpido'],JSON_THROW_ON_ERROR));throw new RuntimeException('Edge CU26: '.($r['error']??'interrumpido'));}
    file_put_contents($dir.'/compatibilidad.json',json_encode($r['real'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    $total=(int)$r['assertions'];portalAssert($r['microfonoRealProbado']===false&&$r['sinCapturas']===true,'simulación distinguida de micrófono real y sin capturas');
    $actual=cu24Huella($pdo);unset($inicial['usuarios'],$actual['usuarios']);portalAssert($actual===$inicial,'voz y navegación no modifican citas ni los otros quince conjuntos de datos');
    // usuarios cambia únicamente por los inicios de sesión ficticios y su último acceso habitual.
}finally{if(is_resource($browser)){proc_terminate($browser);proc_close($browser);}if($http){$http->cerrar();}if($f){$f->limpiar();}}
portalAssert($antes===cu24Huella($pdo),'Edge retira fixtures y conserva los registros previos');
echo "Aprobadas: $total. Edge real en tres tamaños; eventos de voz simulados, alternativa manual y sonda de API sin micrófono.\n";
