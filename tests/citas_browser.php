<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/CitasPrueba.php';
$pdo=App\Config\Database::connection();$t=new CitasPrueba($pdo);$f=$t->fixture;$servidor=null;$navegador=null;
try{
    $actor=$f->usuario('administrador');$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$actor]);$correo=$q->fetchColumn();
    $m=$f->medico();$p=$f->paciente();$e=$f->especialidades[0];$t->horario($m);
    $puerto=random_int(30000,44000);$base='http://127.0.0.1:' . $puerto;
    $servidor=proc_open([PHP_BINARY,'-S','127.0.0.1:' . $puerto,'-t',dirname(__DIR__) . '/public'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($servidor)){throw new RuntimeException('No se pudo iniciar PHP');}fclose($pipes[0]);
    $navegador=proc_open(['node',__DIR__ . '/citas_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipesNode,dirname(__DIR__));
    if(!is_resource($navegador)){throw new RuntimeException('No se pudo iniciar Node');}
    fwrite($pipesNode[0],json_encode(['base'=>$base,'correo'=>$correo,'password'=>'Ficticia91!' . $f->sufijo,'paciente'=>$p,'medico'=>$m,'especialidad'=>$e,'fecha'=>$t->lunes->format('Y-m-d'),'sufijo'=>$f->sufijo]));fclose($pipesNode[0]);
    $resultado=json_decode(stream_get_contents($pipesNode[1]),true);fclose($pipesNode[1]);$codigo=proc_close($navegador);$navegador=null;
    if($codigo!==0||isset($resultado['error'])){fwrite(STDERR,'Prueba de navegador: ' . ($resultado['error']??'interrumpida') . PHP_EOL);throw new RuntimeException('No se completó la prueba de navegador');}
    $q=$pdo->prepare('SELECT count(*) FROM citas WHERE medico_id=:m');$q->execute(['m'=>$m]);if((int)$q->fetchColumn()!==1){throw new RuntimeException('El flujo de navegador duplicó la reserva');}
    echo 'Aprobadas: ',($resultado['assertions']+1),'. Navegador Edge real; una sola reserva tras doble envío y recarga.',PHP_EOL;
    echo json_encode(['capturas'=>[$resultado['desktop'],$resultado['mobile'],$resultado['receipt']]],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{if(is_resource($navegador)){proc_terminate($navegador);proc_close($navegador);}if(is_resource($servidor)){proc_terminate($servidor);proc_close($servidor);}$f->limpiar();}
