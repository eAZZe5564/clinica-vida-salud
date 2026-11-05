<?php
declare(strict_types=1);
require __DIR__.'/RegistroClinicoPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$f=null;$http=null;$browser=null;$total=0;
try{
    $f=new PerfilClinicoPrueba($pdo);$antes=cu24Huella($pdo);$http=new HttpCatalogo();
    $browser=proc_open(['node',__DIR__.'/registros_clinicos_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($browser)){throw new RuntimeException('No se pudo iniciar Edge CU24.');}
    fwrite($pipes[0],json_encode(['base'=>$http->base(),'paciente'=>$f->paciente,'correo'=>$f->actores['medico']['correo'],'password'=>'Ficticia91!'.$f->fixture->sufijo],JSON_THROW_ON_ERROR));fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($browser);$browser=null;$r=json_decode($out,true,512,JSON_THROW_ON_ERROR);
    if($code!==0||isset($r['error'])){
        // Solo el diagnóstico fijo de la prueba, sin URL completa ni contenido clínico.
        file_put_contents(__DIR__.'/artifacts/cu24/browser-diagnostico.json',json_encode(['error'=>$r['error']??'interrumpido'],JSON_THROW_ON_ERROR));
        throw new RuntimeException('Edge CU24: '.($r['error']??'interrumpido'));
    }
    $total=(int)$r['assertions'];clinicoAssert($r['noClinicalCaptures']===true,'sin capturas de páginas clínicas');
    foreach(['antecedentes','laboratorios'] as $tipo){$d=cu24Servicio($f)->consultar($f->paciente,$tipo);clinicoAssert(count($d['registros'])===3&&array_unique(array_column($d['registros'],'version'))===[2],'tres tamaños guardaron y rectificaron registros reales propios');}
    $despues=cu24Huella($pdo);foreach(['usuarios','antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial'] as $t){unset($antes[$t],$despues[$t]);}
    clinicoAssert($antes===$despues,'navegador no altera citas, perfiles ni alergias');
}finally{if(is_resource($browser)){proc_terminate($browser);proc_close($browser);}if($http){$http->cerrar();}cu24Limpiar($f);}
echo "Aprobadas: $total. Edge CU24 real: escritorio, tablet y celular; formularios, revisiones y privacidad.\n";
