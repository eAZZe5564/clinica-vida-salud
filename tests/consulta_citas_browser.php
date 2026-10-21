<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/ConsultaCitasPrueba.php';
$pdo=App\Config\Database::connection();$f=null;$servidor=null;$navegador=null;
try{
    $f=new ConsultaCitasPrueba($pdo);$antes=(int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn();
    $puerto=random_int(30000,44000);$base='http://127.0.0.1:'.$puerto;
    $servidor=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$puerto,'-t',dirname(__DIR__).'/public'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($servidor)){throw new RuntimeException('No se pudo iniciar PHP');}fclose($pipes[0]);
    $navegador=proc_open(['node',__DIR__.'/citas_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipesNode,dirname(__DIR__));
    if(!is_resource($navegador)){throw new RuntimeException('No se pudo iniciar Node');}
    fwrite($pipesNode[0],json_encode(['consulta'=>true,'base'=>$base,'correo'=>$f->actores['administrador']['correo'],'medicoCorreo'=>$f->actores['medico']['correo'],
        'password'=>'Ficticia91'.$f->t->fixture->sufijo,'medico'=>$f->medico,'codigo'=>App\Models\Cita::codigo($f->reserva),'reserva'=>$f->reserva,'ajena'=>$f->ajena]));fclose($pipesNode[0]);
    $resultado=json_decode(stream_get_contents($pipesNode[1]),true);fclose($pipesNode[1]);$codigo=proc_close($navegador);$navegador=null;
    if($codigo!==0||!isset($resultado['assertions'])||isset($resultado['error'])){fwrite(STDERR,'Navegador CU14: '.($resultado['error']??'interrumpido').PHP_EOL);throw new RuntimeException('Prueba visual incompleta');}
    if((int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn()!==$antes){throw new RuntimeException('CU14 realizó una escritura inesperada');}
    echo 'Aprobadas: ',($resultado['assertions']+1),'. Edge real, escritorio y móvil, sin escrituras.',PHP_EOL;
    echo json_encode(['capturas'=>$resultado['captures']],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{
    if(is_resource($navegador)){proc_terminate($navegador);proc_close($navegador);}
    if(is_resource($servidor)){proc_terminate($servidor);proc_close($servidor);}
    if($f!==null){$f->t->fixture->limpiar();}
}
