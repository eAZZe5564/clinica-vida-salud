<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Config/bootstrap.php';require __DIR__.'/HorariosFixture.php';
use App\Repositories\PacienteRepository;
use App\Repositories\UsuarioRepository;
use App\Repositories\RolRepository;
use App\Services\UsuarioService;
use App\Services\AuthService;
use App\Services\ContrasenaValidacionException;
$pdo=App\Config\Database::connection();if($pdo->query('SELECT current_database()')->fetchColumn()!=='clinica_vida_salud_dev'){throw new RuntimeException('Base no autorizada');}
$total=0;$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}$total++;echo "OK: $n\n";};
$pdo->beginTransaction();
try{
    $f=new HorariosFixture($pdo);$s=$f->sufijo;$repo=new PacienteRepository($pdo);$antes=(int)$pdo->query('SELECT count(*) FROM pacientes')->fetchColumn();
    $p=$f->paciente();$otro=$f->paciente();$q=$pdo->prepare('UPDATE pacientes SET nombres=:n,apellidos=:a,documento_identidad=:ci WHERE id=:id');
    $q->execute(['n'=>'María Elena '.$s,'a'=>'Pérez Solís','ci'=>'6A-'.$s.'-1234567','id'=>$p]);$q->execute(['n'=>'Rosa '.$s,'a'=>'López Bravo','ci'=>'6A-'.$s.'-7654321','id'=>$otro]);
    foreach(['María','Pérez','1234567','María Pérez','Pérez María','María 1234567','1234567 Pérez','Pérez 1234567 María','María Pérez 1234567','1234567 María Pérez','MARÍA péREZ','  María   Pérez  ','maria perez','Mar Per','Elena Solis','6a-'.$s.'-1234567'] as $i=>$texto){
        $r=$repo->listar($texto.' '.$s,1);$ok($r['total']===1&&(int)$r['filas'][0]['id']===$p,'búsqueda individual/múltiple/orden '.$i);
    }
    $ok($repo->listar("Mari\u{0301}a Pe\u{0301}rez ".$s,1)['total']===1,'tildes combinadas desde cliente');
    $ok($repo->listar('María López '.$s,1)['total']===0,'no mezcla términos de pacientes distintos');
    $ok($repo->listar('María 7654321 '.$s,1)['total']===0,'no mezcla CI de otro paciente');
    $ok($repo->listar('',1)['total']===$antes+2,'búsqueda vacía conserva listado');
    foreach(["' OR 1=1 --",'%','_','!',"\\"] as $i=>$texto){$r=$repo->listar($texto.' '.$s,1);$ok($r['total']===0,'inyección/comodín literal sin ampliar búsqueda '.$i);}
    $especial=$f->paciente();$q->execute(['n'=>'Literal %_!\\ '.$s,'a'=>'Paciente ficticio','ci'=>'6A-'.$s.'-LIT','id'=>$especial]);
    $ok($repo->listar('%_!\\ '.$s,1)['total']===1,'comodines y escape literal encuentran solo el texto');
    for($i=0;$i<22;$i++){$id=$f->paciente();$q->execute(['n'=>'María '.$s,'a'=>'Pérez paginada','ci'=>'6A-'.$s.'-P'.$i,'id'=>$id]);}
    $r1=$repo->listar('perez maria '.$s,1);$r2=$repo->listar('maria perez '.$s,2);
    $ok($r1['total']===23&&count($r1['filas'])===20&&$r1['paginas']===2,'conteo y primera página con varios términos');
    $ok($r2['total']===23&&count($r2['filas'])===3&&$r2['pagina']===2,'segunda página usa los mismos filtros');
    $ok(count(array_unique(array_column(array_merge($r1['filas'],$r2['filas']),'id')))===23,'páginas sin duplicar ni perder registros');
    $q=$pdo->prepare('UPDATE pacientes SET activo=false WHERE id=:id');$q->execute(['id'=>$p]);
    $ok($repo->listar('maria perez '.$s,1)['total']===23&&$repo->listar('maria perez '.$s,1,true)['total']===22,'mantiene filtro de pacientes activos e inactivos');
    $ok($repo->listar(str_repeat('z',2000),1)['total']===0,'consulta larga acotada');
    $ur=new UsuarioRepository($pdo);$roles=new RolRepository($pdo);$us=new UsuarioService($pdo,$ur,$roles);$auth=new AuthService($ur);$rol=(int)$roles->buscarPorNombre('paciente')['id'];
    $correo='6a-'.$s.'@example.invalid';$clave='Ab1!abcd';$id=$us->crear($correo,$clave,$rol);$f->usuarios[]=$id;
    $hash=static function(int $id)use($pdo):string{$q=$pdo->prepare('SELECT hash_contrasena FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);return $q->fetchColumn();};
    $h=$hash($id);$ok($h!==$clave&&password_verify($clave,$h),'creación guarda hash y admite ocho caracteres');
    $ok($auth->autenticar($correo,$clave)?->id===$id,'login con cuenta nueva');
    $conteo=(int)$pdo->query('SELECT count(*) FROM usuarios')->fetchColumn();
    foreach(['Ab1!xyz','abcdef1!','ABCDEF1!','Abcdefg!','Abcdef12'] as $i=>$invalida){
        try{$us->crear('invalida-'.$i.'-'.$s.'@example.invalid',$invalida,$rol);$ok(false,'se esperaba rechazo de alta');}catch(ContrasenaValidacionException){$ok((int)$pdo->query('SELECT count(*) FROM usuarios')->fetchColumn()===$conteo,'alta inválida no crea cuenta '.$i);}
    }
    $us->actualizar($id,$correo,$rol,'');$ok($hash($id)===$h,'edición vacía conserva hash exacto');
    $us->actualizar($id,$correo,$rol,'Árbol١!xx');$nuevoHash=$hash($id);$ok($nuevoHash!==$h&&password_verify('Árbol١!xx',$nuevoHash),'cambio válido Unicode persiste hash');
    try{$us->actualizar($id,'cambio-'.$s.'@example.invalid',$rol,'sinseguridad');$ok(false,'se esperaba rechazo de cambio');}catch(ContrasenaValidacionException){$ok($hash($id)===$nuevoHash&&$us->obtener($id)['correo']===$correo,'cambio inválido no altera contraseña ni otros datos');}
    $legacy='Anterior12345';$legacyCorreo='legacy-'.$s.'@example.invalid';$legacyHash=password_hash($legacy,PASSWORD_DEFAULT);$legacyId=$ur->crear($legacyCorreo,$legacyHash,$rol);$f->usuarios[]=$legacyId;
    $ok($auth->autenticar($legacyCorreo,$legacy)?->id===$legacyId&&$hash($legacyId)===$legacyHash,'login histórico no impone política nueva ni cambia hash');
    $us->actualizar($legacyId,$legacyCorreo,$rol,'');$ok($hash($legacyId)===$legacyHash,'edición vacía tampoco invalida cuenta histórica');
    try{$us->crearAdministradorInicial('inicial-'.$s.'@example.invalid','sinseguridad');$ok(false,'se esperaba política inicial');}catch(ContrasenaValidacionException $e){$ok(isset($e->errores['mayuscula'],$e->errores['numero'],$e->errores['simbolo']),'primer administrador usa la misma política');}
    try{$us->crearAdministradorInicial('inicial-'.$s.'@example.invalid','Ab1!abcd');$ok(false,'se esperaba guarda de inicialización');}catch(ContrasenaValidacionException){$ok(false,'contraseña válida debe pasar política');}catch(DomainException){$ok(true,'primera cuenta conserva guarda de usuarios vacíos');}
    $ok($pdo->inTransaction(),'servicios conservan transacción externa');
}finally{$pdo->rollBack();}
echo "Aprobadas: $total. Fixtures revertidos sin alterar registros previos.\n";
