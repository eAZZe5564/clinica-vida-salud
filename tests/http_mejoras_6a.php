<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Config/bootstrap.php';require __DIR__.'/HorariosFixture.php';require __DIR__.'/HttpCatalogo.php';
$pdo=App\Config\Database::connection();if($pdo->query('SELECT current_database()')->fetchColumn()!=='clinica_vida_salud_dev'){throw new RuntimeException('Base no autorizada');}
$f=new HorariosFixture($pdo);$http=null;$total=0;$r=null;
$ok=static function(bool $v,string $n)use(&$total,&$r):void{if(!$v){fwrite(STDERR,$n.'; HTTP '.($r['status']??'sin respuesta').PHP_EOL);throw new RuntimeException($n);}$total++;echo "OK: $n\n";};
try{
    $http=new HttpCatalogo();$sesiones=[];$s=$f->sufijo;
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){
        $id=$f->usuario($rol);$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);$correo=$q->fetchColumn();$login=$http->pedir('/login');
        $r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$correo,'contrasena'=>'Ficticia91!'.$s]);
        $ok($r['status']===303,'sesión '.$rol);$panel=$http->pedir('/dashboard','GET',$r['cookie']);$sesiones[$rol]=['id'=>$id,'cookie'=>$r['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];
    }
    $p=$f->paciente();$otro=$f->paciente();$q=$pdo->prepare('UPDATE pacientes SET nombres=:n,apellidos=:a,documento_identidad=:ci WHERE id=:id');
    $q->execute(['n'=>'María Elena '.$s,'a'=>'Pérez Solís','ci'=>'6A-'.$s.'-1234567','id'=>$p]);$q->execute(['n'=>'Rosa '.$s,'a'=>'López Bravo','ci'=>'6A-'.$s.'-7654321','id'=>$otro]);
    $ids=static function(string $html):array{preg_match_all('#href="/pacientes/([0-9]+)"#',$html,$m);return array_values(array_unique(array_map('intval',$m[1])));};
    $a=$sesiones['administrador'];$url=static fn(mixed $q)=>'/pacientes?'.http_build_query(['q'=>$q]);
    foreach(['María','Pérez','1234567','María Pérez','Pérez María','María 1234567','1234567 Pérez','Pérez 1234567 María','1234567 María Pérez','mArÍa PÉREZ','María   Pérez','maria perez'] as $i=>$texto){
        $r=$http->pedir($url($texto.' '.$s),'GET',$a['cookie']);$ok($r['status']===200&&$ids($r['body'])===[$p],'búsqueda HTTP por término/orden '.$i);
    }
    $r=$http->pedir($url('María López '.$s),'GET',$a['cookie']);$ok($r['status']===200&&$ids($r['body'])===[],'no hay coincidencia cruzada entre pacientes');
    $r=$http->pedir($url('  María   Pérez '.$s.'  '),'GET',$a['cookie']);$ok(str_contains($r['body'],'value="María Pérez '.$s.'"'),'búsqueda normalizada se conserva en campo');
    $r=$http->pedir($url('   '),'GET',$a['cookie']);$ok($r['status']===200&&str_contains($r['body'],'value=""'),'vacío mantiene listado habitual');
    foreach([['tipo_invalido'],str_repeat('z',2000),"' OR 1=1 -- ".$s,'<script>alert(6)</script> '.$s] as $i=>$entrada){
        $r=$http->pedir($url($entrada),'GET',$a['cookie']);$ok($r['status']===200&&!str_contains($r['body'],'SQLSTATE')&&!str_contains($r['body'],'<script>alert(6)</script>'),'entrada adversa HTTP '.$i);
        if(is_string($entrada)&&!str_starts_with($entrada,'zzz')){$ok($ids($r['body'])===[],'entrada adversa no amplía coincidencias '.$i);}
    }
    $r=$http->pedir($url('María Pérez '.$s),'GET',$sesiones['recepcionista']['cookie']);$ok($r['status']===200&&$ids($r['body'])===[$p],'recepción conserva búsqueda autorizada');
    foreach(['medico','paciente'] as $rol){$r=$http->pedir($url('María Pérez '.$s),'GET',$sesiones[$rol]['cookie']);$ok($r['status']===403,'búsqueda denegada '.$rol);}
    $ok($http->pedir($url('María Pérez '.$s))['status']===303,'búsqueda exige sesión');
    for($i=0;$i<21;$i++){$id=$f->paciente();$q->execute(['n'=>'María '.$s,'a'=>'Pérez paginada','ci'=>'6A-'.$s.'-P'.$i,'id'=>$id]);}
    $r=$http->pedir($url('María Pérez '.$s),'GET',$a['cookie']);preg_match('/href="([^"]+)">Siguiente/',$r['body'],$enlace);$siguiente=html_entity_decode($enlace[1]??'',ENT_QUOTES,'UTF-8');
    $ok(count($ids($r['body']))===20&&str_contains($r['body'],'22 pacientes'),'primera página multi-término y conteo');
    parse_str((string)parse_url($siguiente,PHP_URL_QUERY),$query);$ok(($query['q']??null)==='María Pérez '.$s&&($query['pagina']??null)==='2','enlace mantiene texto y página');
    $r=$http->pedir('/pacientes'.$siguiente,'GET',$a['cookie']);$ok($r['status']===200&&count($ids($r['body']))===2&&str_contains($r['body'],'Página 2 de 2'),'segunda página real multi-término');
    $roles=new App\Repositories\RolRepository($pdo);$rol=(int)$roles->buscarPorNombre('paciente')['id'];$ur=new App\Repositories\UsuarioRepository($pdo);
    $correo='nueva-6a-'.$s.'@example.invalid';$r=$http->pedir('/admin/users/new','GET',$a['cookie']);$ok($r['status']===200&&str_contains($r['body'],'minlength="8"')&&str_contains($r['body'],'72 bytes'),'formulario anuncia política exacta');
    $cantidad=(int)$pdo->query('SELECT count(*) FROM usuarios')->fetchColumn();
    foreach([['Ab1!xyz','8 caracteres'],['abcdef1!','mayúscula'],['ABCDEF1!','minúscula'],['Abcdefg!','número'],['Abcdef12 ','símbolo']] as $i=>[$clave,$mensaje]){
        $r=$http->pedir('/admin/users','POST',$a['cookie'],['_csrf'=>$a['csrf'],'correo'=>$correo,'rol_id'=>(string)$rol,'contrasena'=>$clave]);
        $ok($r['status']===422&&str_contains($r['body'],$mensaje),'backend sin JS indica requisito faltante '.$i);
        $ok((int)$pdo->query('SELECT count(*) FROM usuarios')->fetchColumn()===$cantidad,'rechazo no crea cuenta '.$i);
        $ok(str_contains($r['body'],'value="'.$correo.'"')&&!str_contains($r['body'],'value="'.$clave.'"'),'error conserva correo y no contraseña '.$i);
    }
    $r=$http->pedir('/admin/users','POST',$a['cookie'],['_csrf'=>$a['csrf'],'correo'=>$correo,'rol_id'=>(string)$rol,'contrasena'=>'<script>alert(1)</script>']);
    $ok($r['status']===422&&!str_contains($r['body'],'<script>alert(1)</script>')&&!str_contains($r['body'],'&lt;script&gt;alert(1)'),'contraseña inválida no se refleja ni escapada en respuesta');
    $r=$http->pedir('/admin/users','POST',$a['cookie'],['_csrf'=>'invalido','correo'=>$correo,'rol_id'=>(string)$rol,'contrasena'=>'Ab1!abcd']);$ok($r['status']===419,'CSRF protege alta');
    $r=$http->pedir('/admin/users','POST',$a['cookie'],['_csrf'=>$a['csrf'],'correo'=>$correo,'rol_id'=>(string)$rol,'contrasena'=>'Ab1!abcd']);
    $q=$pdo->prepare('SELECT id,hash_contrasena FROM usuarios WHERE correo=:correo');$q->execute(['correo'=>$correo]);$fila=$q->fetch();$id=(int)($fila['id']??0);if($id){$f->usuarios[]=$id;}
    $ok($r['status']===303&&$id>0&&password_verify('Ab1!abcd',$fila['hash_contrasena']),'alta HTTP válida de ocho caracteres con hash');
    $hashAntes=$fila['hash_contrasena'];$ruta='/admin/users/'.$id.'/edit';
    $r=$http->pedir($ruta,'POST',$a['cookie'],['_csrf'=>$a['csrf'],'correo'=>$correo,'rol_id'=>(string)$rol,'contrasena'=>'']);$q->execute(['correo'=>$correo]);$fila=$q->fetch();
    $ok($r['status']===303&&$fila['hash_contrasena']===$hashAntes,'edición vacía conserva hash exacto');
    $r=$http->pedir($ruta,'POST',$a['cookie'],['_csrf'=>$a['csrf'],'correo'=>$correo,'rol_id'=>(string)$rol,'contrasena'=>'invalida']);$q->execute(['correo'=>$correo]);$fila=$q->fetch();
    $ok($r['status']===422&&$fila['hash_contrasena']===$hashAntes,'edición inválida conserva hash');
    $nuevaClave='Árbol١!xx';$r=$http->pedir($ruta,'POST',$a['cookie'],['_csrf'=>$a['csrf'],'correo'=>$correo,'rol_id'=>(string)$rol,'contrasena'=>$nuevaClave]);$q->execute(['correo'=>$correo]);$fila=$q->fetch();
    $ok($r['status']===303&&password_verify($nuevaClave,$fila['hash_contrasena']),'edición válida Unicode cambia hash');
    $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$correo,'contrasena'=>$nuevaClave]);$ok($r['status']===303&&$r['location']==='/dashboard','login después del cambio válido');
    $correoLegacy='legacy-6a-'.$s.'@example.invalid';$hashLegacy=password_hash('Anterior12345',PASSWORD_DEFAULT);$legacy=$ur->crear($correoLegacy,$hashLegacy,$rol);$f->usuarios[]=$legacy;
    $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$correoLegacy,'contrasena'=>'Anterior12345']);$ok($r['status']===303&&$r['location']==='/dashboard','cuenta histórica inicia sesión sin nueva política');
    foreach(['recepcionista','medico','paciente'] as $perfil){$r=$http->pedir('/admin/users','POST',$sesiones[$perfil]['cookie'],['_csrf'=>$sesiones[$perfil]['csrf'],'correo'=>'sin-permiso-'.$s.'@example.invalid','rol_id'=>(string)$rol,'contrasena'=>'Ab1!abcd']);$ok($r['status']===403,'nueva política no amplía permisos '.$perfil);}
    $r=$http->pedir($ruta,'POST',$a['cookie'],['_csrf'=>'invalido','correo'=>$correo,'rol_id'=>(string)$rol,'contrasena'=>'Ab1!abcd']);$ok($r['status']===419,'CSRF protege cambio');
    $cli=static function(string $clave)use($s):array{
        $proc=proc_open([PHP_BINARY,dirname(__DIR__).'/scripts/crear-administrador.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
        if(!is_resource($proc)){throw new RuntimeException('No se pudo probar CLI');}
        fwrite($pipes[0],'inicial-6a-'.$s.'@example.invalid'."\n".$clave."\n");fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);return [proc_close($proc),$out.$err];
    };
    [$codigo,$salida]=$cli('Anterior12345');$ok($codigo===1&&str_contains($salida,'símbolo')&&!str_contains($salida,'Anterior12345'),'script inicial exige política y no imprime contraseña');
    [$codigo,$salida]=$cli('Ab1!abcd');$ok($codigo===1&&str_contains($salida,'todavía no hay cuentas')&&!str_contains($salida,'Ab1!abcd'),'script inicial conserva guarda de base con cuentas');
}finally{
    if($http!==null){$http->cerrar();}
    $q=$pdo->prepare('SELECT id FROM usuarios WHERE correo=:correo');
    foreach(['nueva-6a-','legacy-6a-','sin-permiso-','inicial-6a-'] as $prefijo){$q->execute(['correo'=>$prefijo.$f->sufijo.'@example.invalid']);$id=$q->fetchColumn();if($id!==false&&!in_array((int)$id,$f->usuarios,true)){$f->usuarios[]=(int)$id;}}
    $f->limpiar();
}
echo "Aprobadas: $total. Pruebas HTTP/CLI sin JS; fixtures propios retirados.\n";
