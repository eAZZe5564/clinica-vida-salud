<?php
declare(strict_types=1);
require __DIR__.'/AsistenciaPrueba.php';
$pdo=cu15Conexion();$prueba=new AsistenciaPrueba($pdo);$t=$prueba->t;$f=$t->fixture;$servidor=null;$navegador=null;
try{
    $actor=$f->usuario('administrador');$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$actor]);$correo=$q->fetchColumn();
    $p=$f->paciente();$casos=[];$originales=[];
    for($i=0;$i<2;++$i){
        $doctor=$f->usuario('medico');$m=$f->medico($doctor);$e=$f->especialidades[array_key_last($f->especialidades)];
        $id=$prueba->citaReal($m,$p,$e,$actor);$originales[$id]=$prueba->fila($id);$q->execute(['id'=>$doctor]);
        $casos[]=['cita'=>$id,'correoMedico'=>$q->fetchColumn(),'doctor'=>$doctor];
    }
    foreach(array_keys($originales) as $id){$prueba->esperarInicio($id);}
    $puerto=random_int(30000,44000);$base='http://127.0.0.1:'.$puerto;
    $servidor=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$puerto,'-t',dirname(__DIR__).'/public'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($servidor)){throw new RuntimeException('No se pudo iniciar PHP.');}fclose($pipes[0]);
    $navegador=proc_open(['node',__DIR__.'/citas_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipesNode,dirname(__DIR__));
    if(!is_resource($navegador)){throw new RuntimeException('No se pudo iniciar Node.');}
    fwrite($pipesNode[0],json_encode(['base'=>$base,'correo'=>$correo,'password'=>'Ficticia91!'.$f->sufijo,'asistencia'=>true,'casos'=>$casos],JSON_THROW_ON_ERROR));fclose($pipesNode[0]);
    $salida=stream_get_contents($pipesNode[1]);$resultado=json_decode($salida,true);$error=stream_get_contents($pipesNode[2]);fclose($pipesNode[1]);fclose($pipesNode[2]);$codigo=proc_close($navegador);$navegador=null;
    if($codigo!==0||!isset($resultado['assertions'])||isset($resultado['error'])){
        $diagnostico=preg_replace('/Ficticia91![a-f0-9]+|horario-[^\s"\x27]+@example\.invalid/','[fixture oculto]',$error);
        fwrite(STDERR,'FALLO de navegador: '.($resultado['error']??'interrumpido').' / salida '.$codigo.' / '.mb_substr($diagnostico,0,1500).PHP_EOL);
        throw new RuntimeException('Navegador CU18 no completó el recorrido.');
    }
    $total=$resultado['assertions'];
    foreach($casos as $caso){
        $id=$caso['cita'];$actual=$prueba->fila($id);$original=$originales[$id];
        if($actual['estado']!=='atendida'){throw new RuntimeException('No se finalizó la atención del navegador.');}++$total;
        unset($actual['estado'],$original['estado']);if($actual!==$original){throw new RuntimeException('Cambió un campo distinto de estado.');}++$total;
        $eventos=$prueba->eventos($id);
        if(array_column($eventos,'tipo')!==['llegada','atencion']||(int)$eventos[0]['usuario_id']!==$actor||(int)$eventos[1]['usuario_id']!==$caso['doctor']){throw new RuntimeException('Eventos o responsables inesperados.');}++$total;
        if(new DateTimeImmutable($eventos[1]['cambiado_en'])<new DateTimeImmutable($original['fecha_hora_inicio'])){throw new RuntimeException('Finalización prematura.');}++$total;
    }
    echo "Aprobadas: $total. Edge real escritorio 1440px/móvil 390px, reloj real PostgreSQL; dos llegadas y dos atenciones.\n";
    echo json_encode(['capturas'=>$resultado['captures']],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{
    if(is_resource($navegador)){proc_terminate($navegador);proc_close($navegador);}
    if(is_resource($servidor)){proc_terminate($servidor);proc_close($servidor);}
    $prueba->limpiar();
}
