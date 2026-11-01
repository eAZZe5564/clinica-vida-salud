<?php
declare(strict_types=1);
require __DIR__.'/AgendaPrueba.php';
$pdo=cu15Conexion();$prueba=null;$servidor=null;$navegador=null;
try{
    $prueba=new AgendaPrueba($pdo);$antes=$prueba->huella();unset($antes['usuarios']);
    $puerto=random_int(30000,44000);$base='http://127.0.0.1:'.$puerto;
    $servidor=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$puerto,'-t',dirname(__DIR__).'/public'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($servidor)){throw new RuntimeException('No se pudo iniciar PHP.');}fclose($pipes[0]);
    $navegador=proc_open(['node',__DIR__.'/citas_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipesNode,dirname(__DIR__));
    if(!is_resource($navegador)){throw new RuntimeException('No se pudo iniciar Node.');}
    fwrite($pipesNode[0],json_encode(['agenda'=>true,'base'=>$base,'correo'=>$prueba->actores['administrador']['correo'],
        'password'=>'Ficticia91!'.$prueba->t->fixture->sufijo,'medicoCorreo'=>$prueba->actores['medico']['correo'],
        'medico'=>$prueba->medico,'otro'=>$prueba->otro,'fecha'=>$prueba->fecha,'hoy'=>(new DateTimeImmutable('today',App\Models\HorarioAtencion::zona()))->format('Y-m-d'),
        'cita'=>$prueba->ids['programada'],'cancelada'=>$prueba->ids['cancelada']],JSON_THROW_ON_ERROR));fclose($pipesNode[0]);
    $salida=stream_get_contents($pipesNode[1]);fclose($pipesNode[1]);$codigo=proc_close($navegador);$navegador=null;$resultado=json_decode($salida,true);
    if($codigo!==0||!isset($resultado['assertions'])||isset($resultado['error'])){fwrite(STDERR,'Navegador CU19: '.($resultado['error']??'interrumpido').PHP_EOL);throw new RuntimeException('Prueba visual CU19 incompleta.');}
    $despues=$prueba->huella();unset($despues['usuarios']);
    if($antes!==$despues){throw new RuntimeException('La agenda modificó datos de dominio.');}
    echo 'Aprobadas: ',($resultado['assertions']+1),'. Edge real escritorio/tablet/móvil; ocho tablas de dominio sin cambios (login registra acceso).',PHP_EOL;
    echo json_encode(['capturas'=>$resultado['captures']],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{
    if(is_resource($navegador)){proc_terminate($navegador);proc_close($navegador);}
    if(is_resource($servidor)){proc_terminate($servidor);proc_close($servidor);}
    if($prueba){$prueba->limpiar();}
}
