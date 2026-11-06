<?php
declare(strict_types=1);
require __DIR__.'/PortalPacientePrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$f=null;$http=null;$browser=null;$total=0;$antes=cu24Huella($pdo);
try{
    $f=new PortalPacientePrueba($pdo);$destinos=[];for($i=0;$i<3;$i++){$destinos[]=['paciente'=>$f->fixture->paciente(),'usuario'=>$f->fixture->usuario('paciente')];}
    $http=new HttpCatalogo();$browser=proc_open(['node',__DIR__.'/portal_paciente_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($browser)){throw new RuntimeException('No se pudo iniciar Edge CU25.');}
    fwrite($pipes[0],json_encode(['base'=>$http->base(),'correo'=>$f->actores['paciente']['correo'],'admin'=>$f->actores['administrador']['correo'],'password'=>'Ficticia91!'.$f->fixture->sufijo,'medico'=>$f->medico,'especialidad'=>$f->especialidad,'fecha'=>$f->base->lunes->format('Y-m-d'),'destinos'=>$destinos],JSON_THROW_ON_ERROR));fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($browser);$browser=null;$r=json_decode($out,true,512,JSON_THROW_ON_ERROR);
    if($code!==0||isset($r['error'])){throw new RuntimeException('Edge CU25: '.($r['error']??'interrumpido'));}
    $total=(int)$r['assertions'];portalAssert($r['noCaptures']===true,'sin capturas privadas');
    $datos=$f->portal()->listar([],true);portalAssert($datos['resultado']['total']===3,'tres reservas reales de navegador');
    foreach($datos['resultado']['filas'] as $c){portalAssert($c['estado']==='cancelada'&&count($f->portal()->detalle((int)$c['id'])['eventos']['filas'])===2,'cada cita conservó reprogramación y cancelación');}
}finally{if(is_resource($browser)){proc_terminate($browser);proc_close($browser);}if($http){$http->cerrar();}if($f){$f->limpiar();}}
portalAssert($antes===cu24Huella($pdo),'Edge retiró todos los fixtures');
echo "Aprobadas: $total. Edge real escritorio, tablet y celular: reservas, cambios, cancelaciones y vínculos.\n";
