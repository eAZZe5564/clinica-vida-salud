<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Config/bootstrap.php';require __DIR__.'/HorariosFixture.php';
$pdo=App\Config\Database::connection();if($pdo->query('SELECT current_database()')->fetchColumn()!=='clinica_vida_salud_dev'){throw new RuntimeException('Base no autorizada');}
$f=new HorariosFixture($pdo);$servidor=null;$navegador=null;$nuevoCorreo='visual-6a-'.$f->sufijo.'@example.invalid';
try{
    $admin=$f->usuario('administrador');$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$admin]);$correo=$q->fetchColumn();
    $paciente=$f->paciente();$otro=$f->paciente();$q=$pdo->prepare('UPDATE pacientes SET nombres=:n,apellidos=:a,documento_identidad=:ci WHERE id=:id');
    $q->execute(['n'=>'María Elena '.$f->sufijo,'a'=>'Pérez Solís','ci'=>'6A-'.$f->sufijo.'-1234567','id'=>$paciente]);$q->execute(['n'=>'Rosa '.$f->sufijo,'a'=>'López Bravo','ci'=>'6A-'.$f->sufijo.'-7654321','id'=>$otro]);
    $rol=(int)(new App\Repositories\RolRepository($pdo))->buscarPorNombre('paciente')['id'];$puerto=random_int(30000,44000);
    $servidor=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$puerto,'-t',dirname(__DIR__).'/public'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
    if(!is_resource($servidor)){throw new RuntimeException('No se pudo iniciar PHP');}fclose($pipes[0]);
    $navegador=proc_open(['node',__DIR__.'/mejoras_6a_browser.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipesNode,dirname(__DIR__));
    if(!is_resource($navegador)){throw new RuntimeException('No se pudo iniciar Node');}
    fwrite($pipesNode[0],json_encode(['base'=>'http://127.0.0.1:'.$puerto,'correo'=>$correo,'password'=>'Ficticia91!'.$f->sufijo,'sufijo'=>$f->sufijo,'nuevoCorreo'=>$nuevoCorreo,'rol'=>$rol,'nuevaClave'=>'Ab1!abcd']));fclose($pipesNode[0]);
    $resultado=json_decode(stream_get_contents($pipesNode[1]),true);fclose($pipesNode[1]);$codigo=proc_close($navegador);$navegador=null;
    if($codigo!==0||!isset($resultado['assertions'])||isset($resultado['error'])){fwrite(STDERR,'Navegador 6A: '.($resultado['error']??'interrumpido').PHP_EOL);throw new RuntimeException('Prueba visual incompleta');}
    $q=$pdo->prepare('SELECT id,hash_contrasena FROM usuarios WHERE correo=:correo');$q->execute(['correo'=>$nuevoCorreo]);$fila=$q->fetch();
    if(!$fila||!password_verify('Ab1!abcd',$fila['hash_contrasena'])){throw new RuntimeException('El navegador no creó una cuenta válida con hash');}
    echo 'Aprobadas: ',($resultado['assertions']+1),'. Edge real; búsqueda y mensajes en escritorio/móvil.',PHP_EOL;
    echo json_encode(['capturas'=>$resultado['captures']],JSON_UNESCAPED_SLASHES),PHP_EOL;
}finally{
    if(is_resource($navegador)){proc_terminate($navegador);proc_close($navegador);}
    if(is_resource($servidor)){proc_terminate($servidor);proc_close($servidor);}
    $q=$pdo->prepare('SELECT id FROM usuarios WHERE correo=:correo');$q->execute(['correo'=>$nuevoCorreo]);$id=$q->fetchColumn();if($id!==false){$f->usuarios[]=(int)$id;}
    $f->limpiar();
}
