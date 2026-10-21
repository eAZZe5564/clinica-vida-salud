<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';
require __DIR__ . '/HttpCatalogo.php';
use App\Config\Database;
use App\Repositories\UsuarioRepository;
use App\Repositories\RolRepository;
use App\Services\UsuarioService;

$pdo=Database::connection();
if (!$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name='medicos' AND column_name='correo_contacto' AND table_schema='public'")->fetchColumn()) { fwrite(STDERR,"PENDIENTE: requiere migración 003.\n"); exit(2); }
$s=bin2hex(random_bytes(8)); $prefijo='CATTEST' . strtoupper($s);
$usuarios=new UsuarioService($pdo,new UsuarioRepository($pdo),new RolRepository($pdo));
$total=0; $idsUsuarios=[]; $http=null;
$ok=static function(bool $valor,string $nombre)use(&$total):void{ if(!$valor){throw new RuntimeException($nombre);} $total++; echo "OK: $nombre\n"; };
try {
    $http=new HttpCatalogo();
    foreach(['/medicos','/especialidades','/medicos/nuevo','/especialidades/nuevo'] as $ruta) { $ok($http->pedir($ruta)['status']===303,"sin sesión $ruta"); }
    $sesiones=[];
    foreach((new RolRepository($pdo))->listar() as $rol) {
        $correo='catalogo-' . $rol['nombre'] . '-' . $s . '@example.invalid'; $password='Prueba91' . bin2hex(random_bytes(8));
        $idsUsuarios[]=$usuarios->crear($correo,$password,(int)$rol['id']);
        $login=$http->pedir('/login'); $sesion=$http->pedir('/login','POST',$login['cookie'],['correo'=>$correo,'contrasena'=>$password,'_csrf'=>HttpCatalogo::csrf($login['body'])]);
        $ok($sesion['status']===303,'login ' . $rol['nombre']);
        $panel=$http->pedir('/dashboard','GET',$sesion['cookie']);
        $sesiones[$rol['nombre']]=['cookie'=>$sesion['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];
    }
    $admin=$sesiones['administrador']; $cookie=$admin['cookie']; $token=$admin['csrf'];
    $especialidad=['nombre'=>$prefijo . ' Cardiología','descripcion'=>'Ficticia','_csrf'=>$token];
    $r=$http->pedir('/especialidades','POST',$cookie,$especialidad);
    $ok($r['status']===303,'administrador registra especialidad');
    $q=$pdo->prepare('SELECT id FROM especialidades WHERE nombre=:n'); $q->execute(['n'=>$especialidad['nombre']]); $e=(int)$q->fetchColumn(); $ok($e>0,'especialidad persistida');
    $r=$http->pedir('/especialidades','POST',$cookie,array_replace($especialidad,['nombre'=>strtolower($prefijo) . '   cardiología']));
    $ok($r['status']===422 && str_contains($r['body'],'Ya existe'),'duplicado normalizado HTTP');
    $r=$http->pedir('/especialidades','POST',$cookie,array_replace($especialidad,['nombre'=>$prefijo . ' Interna']));
    $q->execute(['n'=>$prefijo . ' Interna']); $e2=(int)$q->fetchColumn(); $ok($e2>0 && $r['status']===303,'segunda especialidad');
    $datos=['nombres'=>'Ana' . $s,'apellidos'=>'Apellido ficticio','numero_colegiatura'=>$prefijo . '-01','telefono'=>'+591 70000000','correo_contacto'=>'prueba@example.invalid','especialidades'=>[$e,$e2],'_csrf'=>$token];
    $r=$http->pedir('/medicos','POST',$cookie,$datos);
    $ok($r['status']===303,'administrador registra médico con dos especialidades');
    $q=$pdo->prepare('SELECT id FROM medicos WHERE numero_colegiatura=:r'); $q->execute(['r'=>$datos['numero_colegiatura']]); $m=(int)$q->fetchColumn(); $ok($m>0,'médico persistido');
    $ruta='/medicos/' . $m;
    $detalle=$http->pedir($ruta,'GET',$cookie);
    $ok($detalle['status']===200 && str_contains($detalle['body'],$especialidad['nombre']) && str_contains($detalle['body'],'Interna'),'detalle muestra múltiples especialidades');
    $r=$http->pedir('/medicos','POST',$cookie,$datos); $ok($r['status']===422 && str_contains($r['body'],'Ya existe'),'registro duplicado HTTP');
    $r=$http->pedir('/medicos','POST',$cookie,array_replace($datos,['nombres'=>' ','telefono'=>'x','correo_contacto'=>'invalido']));
    $ok($r['status']===422 && str_contains($r['body'],'Este campo es obligatorio') && str_contains($r['body'],'Ingresa un correo'),'errores por campo HTTP');
    $r=$http->pedir($ruta . '/editar','POST',$cookie,array_replace($datos,['nombres'=>'Editado' . $s,'id'=>'1','usuario_id'=>''])); $ok($r['status']===303,'edición de médico ignora PK enviada');
    $r=$http->pedir('/medicos?q=' . $s . '&especialidad=' . $e . '&estado=activo','GET',$cookie);
    $ok($r['status']===200 && str_contains($r['body'],'Editado' . $s),'búsqueda y filtros HTTP');
    $r=$http->pedir($ruta . '/especialidades','POST',$cookie,['_csrf'=>$token,'especialidades'=>[$e]]); $ok($r['status']===303,'retira una asociación');
    $r=$http->pedir('/especialidades/' . $e . '/estado','POST',$cookie,['_csrf'=>$token,'activo'=>'0']); $ok($r['status']===422,'bloquea desactivar última especialidad activa');
    $r=$http->pedir($ruta . '/especialidades','POST',$cookie,['_csrf'=>$token,'especialidades'=>[$e,$e]]); $ok($r['status']===422,'rechaza asociaciones duplicadas');
    $r=$http->pedir($ruta . '/especialidades','POST',$cookie,['_csrf'=>$token,'especialidades'=>[]]); $ok($r['status']===422,'rechaza quitar todas las especialidades de activo');
    foreach(['/medicos','/especialidades',$ruta . '/editar',$ruta . '/estado',$ruta . '/especialidades','/especialidades/' . $e . '/editar','/especialidades/' . $e . '/estado'] as $escritura) {
        $ok($http->pedir($escritura,'POST',$cookie,['_csrf'=>'invalido'])['status']===419,'CSRF inválido ' . $escritura);
        $ok($http->pedir($escritura,'POST',$cookie) ['status']===419,'CSRF ausente ' . $escritura);
    }
    $q=$pdo->prepare('SELECT activo FROM medicos WHERE id=:id');
    $ok($http->pedir($ruta . '/estado','GET',$cookie)['status']===200,'confirmación GET'); $q->execute(['id'=>$m]); $ok((bool)$q->fetchColumn(),'GET conserva estado');
    $ok($http->pedir($ruta . '/estado','POST',$cookie,['_csrf'=>$token,'activo'=>'0'])['status']===303,'desactiva médico');
    $q->execute(['id'=>$m]); $ok(!$q->fetchColumn(),'médico persiste inactivo');
    $ok($http->pedir($ruta . '/estado','POST',$cookie,['_csrf'=>$token,'activo'=>'1'])['status']===303,'reactiva médico');
    $ok($http->pedir('/especialidades/' . $e2 . '/estado','POST',$cookie,['_csrf'=>$token,'activo'=>'0'])['status']===303,'desactiva especialidad no necesaria');
    $ok($http->pedir('/especialidades/' . $e2 . '/estado','POST',$cookie,['_csrf'=>$token,'activo'=>'1'])['status']===303,'reactiva especialidad');
    $r=$http->pedir('/especialidades/' . $e2 . '/editar','POST',$cookie,['_csrf'=>$token,'nombre'=>$prefijo . ' Interna','descripcion'=>'<script>Ficticio</script>']);
    $r=$http->pedir('/especialidades/' . $e2,'GET',$cookie); $ok(str_contains($r['body'],'&lt;script&gt;Ficticio&lt;/script&gt;') && !str_contains($r['body'],'<script>Ficticio</script>'),'HTML escapa XSS');
    foreach($sesiones as $rol=>$sesion) {
        if($rol==='administrador'){continue;}
        $c=$sesion['cookie']; $permitido=$rol==='recepcionista';
        foreach(['/medicos',$ruta,'/medicos?especialidad=' . $e] as $consulta) { $ok($http->pedir($consulta,'GET',$c)['status']===($permitido?200:403),"consulta $rol $consulta"); }
        foreach(['/medicos/nuevo',$ruta . '/editar',$ruta . '/estado',$ruta . '/especialidades','/especialidades','/especialidades/nuevo','/especialidades/' . $e,'/especialidades/' . $e . '/editar','/especialidades/' . $e . '/estado'] as $consulta) { $ok($http->pedir($consulta,'GET',$c)['status']===403,"GET administrativo denegado $rol"); }
        foreach(['/medicos',$ruta . '/editar',$ruta . '/estado',$ruta . '/especialidades','/especialidades','/especialidades/' . $e . '/editar','/especialidades/' . $e . '/estado'] as $escritura) { $ok($http->pedir($escritura,'POST',$c,['_csrf'=>$sesion['csrf']])['status']===403,"POST administrativo denegado $rol"); }
    }
    foreach(['/medicos/' . PHP_INT_MAX,'/especialidades/' . PHP_INT_MAX] as $r) { $ok($http->pedir($r,'GET',$cookie)['status']===404,'inexistente devuelve 404'); }
    $ok($http->pedir('/medicos','DELETE',$cookie)['status']===405,'sin eliminación física HTTP');
    $ok($http->pedir($ruta,'POST',$cookie,['_csrf'=>$token])['status']===405,'detalle solo GET');
    $ok($http->pedir('/logout','POST',$cookie,['_csrf'=>$token])['status']===303,'logout');
    $ok($http->pedir('/medicos','GET',$cookie)['status']===303,'sin acceso tras logout');
} finally {
    if($http!==null){$http->cerrar();}
    // Limpieza limitada al prefijo aleatorio de esta suite, incluso si una aserción falla.
    $q=$pdo->prepare('DELETE FROM medicos_especialidades WHERE medico_id IN (SELECT id FROM medicos WHERE numero_colegiatura LIKE :p)'); $q->execute(['p'=>$prefijo . '%']);
    $q=$pdo->prepare('DELETE FROM medicos WHERE numero_colegiatura LIKE :p'); $q->execute(['p'=>$prefijo . '%']);
    $q=$pdo->prepare('DELETE FROM especialidades WHERE nombre LIKE :p'); $q->execute(['p'=>$prefijo . '%']);
    $q=$pdo->prepare('DELETE FROM usuarios WHERE id=:id'); foreach($idsUsuarios as $id){$q->execute(['id'=>$id]);}
}
echo "Aprobadas: $total. Eliminados únicamente registros ficticios de la suite.\n";
