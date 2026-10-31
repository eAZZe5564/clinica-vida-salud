<?php
declare(strict_types=1);
require __DIR__.'/CancelacionPrueba.php';
$pdo=cu15Conexion();$prueba=new CancelacionPrueba($pdo);$t=$prueba->r->t;$f=$t->fixture;$servidor=null;$navegador=null;
try{
    $actor=$f->usuario('recepcionista');$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$actor]);$correo=$q->fetchColumn();
    $m=$f->medico();$p=$f->paciente();$e=$f->especialidades[0];$t->horario($m);
    $c=$t->servicio->crear($t->datos($m,$p,$e),$actor)['id'];$movil=$t->servicio->crear($t->datos($m,$p,$e,'09:00'),$actor)['id'];
    $originales=[$c=>$prueba->r->fila($c),$movil=>$prueba->r->fila($movil)];
    $puerto=random_int(30000,44000);$base='http://127.0.0.1:'.$puerto;
    $servidor=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$puerto,'-t',dirname(__DIR__).'/public'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($servidor)){throw new RuntimeException('No se pudo iniciar PHP.');}fclose($pipes[0]);
    $navegador=proc_open(['node',__DIR__.'/citas_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipesNode,dirname(__DIR__));
    if(!is_resource($navegador)){throw new RuntimeException('No se pudo iniciar Node.');}
    fwrite($pipesNode[0],json_encode(['base'=>$base,'correo'=>$correo,'password'=>'Ficticia91!'.$f->sufijo,'cancelar'=>true,'cita'=>$c,'citaMovil'=>$movil],JSON_THROW_ON_ERROR));fclose($pipesNode[0]);
    $resultado=json_decode(stream_get_contents($pipesNode[1]),true);$error=stream_get_contents($pipesNode[2]);fclose($pipesNode[1]);fclose($pipesNode[2]);$codigo=proc_close($navegador);$navegador=null;
    if($codigo!==0||!isset($resultado['assertions'])||isset($resultado['error'])){throw new RuntimeException('Navegador CU16: '.($resultado['error']??'interrumpido'));}
    $total=$resultado['assertions'];
    foreach($originales as $id=>$original){
        $actual=$prueba->r->fila($id);
        if($actual['estado']!=='cancelada'){throw new RuntimeException('No se canceló la cita del navegador.');}++$total;
        unset($actual['estado'],$original['estado']);if($actual!==$original){throw new RuntimeException('Cambió un campo diferente del estado.');}++$total;
        $eventos=$prueba->eventos($id);
        if(count($eventos)!==1||$eventos[0]['tipo']!=='cancelacion'||$eventos[0]['motivo_cancelacion']!=='Cambio administrativo ficticio'){throw new RuntimeException('Historial inesperado.');}++$total;
    }
    echo "Aprobadas: $total. Edge real en escritorio 1440px y móvil 390px; dos cancelaciones y dos eventos.\n";
    echo json_encode(['capturas'=>$resultado['captures']],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{
    if(is_resource($navegador)){proc_terminate($navegador);proc_close($navegador);}
    if(is_resource($servidor)){proc_terminate($servidor);proc_close($servidor);}
    $prueba->limpiar();
}
