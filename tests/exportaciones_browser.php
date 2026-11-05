<?php
declare(strict_types=1);
require __DIR__.'/ReportesPrueba.php';
$pdo=cu15Conexion();$prueba=null;$servidor=null;$navegador=null;
try{
    $prueba=new ReportesPrueba($pdo);$b=$prueba->base;$antes=$b->huella();
    $puerto=random_int(30000,44000);$base='http://127.0.0.1:'.$puerto;
    $servidor=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$puerto,'-t',dirname(__DIR__).'/public'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($servidor)){throw new RuntimeException('No se pudo iniciar PHP.');}fclose($pipes[0]);
    $navegador=proc_open(['node',__DIR__.'/citas_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipesNode,dirname(__DIR__));
    if(!is_resource($navegador)){throw new RuntimeException('No se pudo iniciar Edge.');}
    fwrite($pipesNode[0],json_encode(['exportaciones'=>true,'base'=>$base,'correo'=>$b->actores['administrador']['correo'],
        'password'=>'Ficticia91!'.$b->t->fixture->sufijo,'query'=>http_build_query($prueba->filtros())],JSON_THROW_ON_ERROR));fclose($pipesNode[0]);
    $salida=stream_get_contents($pipesNode[1]);fclose($pipesNode[1]);$codigo=proc_close($navegador);$navegador=null;$resultado=json_decode($salida,true);
    if($codigo!==0||isset($resultado['error'])||!isset($resultado['assertions'])){throw new RuntimeException('Edge CU22: '.($resultado['error']??'interrumpido'));}
    $despues=$b->huella();unset($antes['usuarios'],$despues['usuarios']);if($antes!==$despues){throw new RuntimeException('Descargas alteraron tablas de dominio.');}
    echo 'Aprobadas: ',($resultado['assertions']+1),'. Edge real escritorio/móvil, cuatro descargas y lector PDF; dominio intacto.',PHP_EOL;
    echo json_encode(['capturas'=>$resultado['captures'],'descargas'=>$resultado['downloads']],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{
    if(is_resource($navegador)){proc_terminate($navegador);proc_close($navegador);}
    if(is_resource($servidor)){proc_terminate($servidor);proc_close($servidor);}
    if($prueba){$prueba->limpiar();}
}
