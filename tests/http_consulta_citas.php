<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/ConsultaCitasPrueba.php';require __DIR__ . '/HttpCatalogo.php';
$pdo=App\Config\Database::connection();$f=null;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total,&$r):void{
    if(!$v){fwrite(STDERR,$n.'; último estado HTTP: '.($r['status']??'sin respuesta').PHP_EOL);throw new RuntimeException($n);}
    $total++;echo "OK: $n\n";
};
try{
    $f=new ConsultaCitasPrueba($pdo);$http=new HttpCatalogo();$sesiones=[];
    foreach($f->actores as $rol=>$actor){
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$actor['correo'],'contrasena'=>'Ficticia91'.$f->t->fixture->sufijo]);
        $ok($r['status']===303,'sesión '.$rol);$panel=$http->pedir('/dashboard','GET',$r['cookie']);$sesiones[$rol]=['cookie'=>$r['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];
    }
    $a=$sesiones['administrador']['cookie'];$d=$sesiones['medico']['cookie'];$codigo=App\Models\Cita::codigo($f->reserva);$ajena=App\Models\Cita::codigo($f->ajena);
    $base=['medico_id'=>$f->medico];$url=static fn(array $q)=>'/citas?'.http_build_query($q);
    $ids=static function(string $body):array{preg_match_all('/data-cita-id="([0-9]+)"/',$body,$m);return array_map('intval',$m[1]);};
    $antes=(int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn();
    $ok($http->pedir('/citas')['status']===303&&$http->pedir('/citas/'.$f->reserva)['status']===303,'listado y detalle exigen autenticación');
    foreach(['administrador','recepcionista'] as $rol){
        $cookie=$sesiones[$rol]['cookie'];$r=$http->pedir($url($base),'GET',$cookie);
        $ok($r['status']===200&&count($ids($r['body']))===20&&str_contains($r['body'],'24 citas'),'listado autorizado paginado '.$rol);
        $r=$http->pedir('/citas/'.$f->reserva,'GET',$cookie);$ok($r['status']===200&&str_contains($r['body'],$codigo)&&str_contains($r['body'],'08:00–08:30'),'detalle administrativo '.$rol);
        $ok(str_contains($r['body'],'MOTIVO_PRIVADO_CU14 &lt;script&gt;')&&!str_contains($r['body'],'<script>alert(1)</script>'),'motivo autorizado escapado '.$rol);
    }
    $r=$http->pedir('/citas/'.$f->reserva,'GET',$a);$ok(str_contains($r['body'],'Registrada por')&&str_contains($r['body'],$f->actores['administrador']['correo']),'auditoría solo administrador');
    $r=$http->pedir('/citas/'.$f->reserva,'GET',$sesiones['recepcionista']['cookie']);$ok(!str_contains($r['body'],'Registrada por')&&!str_contains($r['body'],$f->actores['administrador']['correo']),'recepción sin datos de cuenta creadora');
    $r=$http->pedir($url($base+['fecha'=>'2010-01-01']),'GET',$a);$ok($ids($r['body'])===[$f->canceladas[0]]&&str_contains($r['body'],'01/01/2010')&&str_contains($r['body'],'23:30'),'fecha exacta y presentación local');
    $r=$http->pedir($url($base+['desde'=>'2010-01-01','hasta'=>'2010-01-03']),'GET',$a);$ok(count($ids($r['body']))===3,'filtro rango inclusivo');
    $r=$http->pedir($url(['medico_id'=>$f->otro]),'GET',$a);$ok($ids($r['body'])===[$f->ajena],'filtro médico');
    $r=$http->pedir($url(['especialidad_id'=>$f->especialidad,'pagina'=>2]),'GET',$a);$ok(count($ids($r['body']))===3&&str_contains($r['body'],'23 citas'),'filtro especialidad');
    foreach(['programada'=>1,'cancelada'=>21,'atendida'=>2] as $estado=>$cantidad){$r=$http->pedir($url($base+['estado'=>$estado]),'GET',$a);$ok(str_contains($r['body'],$cantidad.' citas'),'filtro estado '.$estado);}
    $combinados=$base+['especialidad_id'=>$f->especialidad,'estado'=>'cancelada','desde'=>'2010-01-01','hasta'=>'2010-01-03'];
    $r=$http->pedir($url($combinados),'GET',$a);$ok(count($ids($r['body']))===3,'filtros combinados');
    $r=$http->pedir($url(['codigo'=>$codigo]),'GET',$a);$ok($ids($r['body'])===[$f->reserva]&&str_contains($r['body'],'Programada')&&str_contains($r['body'],'08:30'),'CU13 aparece por código en CU14');
    $ok(str_contains($r['body'],'Paciente &lt;b&gt;ficticio CU14&lt;/b&gt;')&&!str_contains($r['body'],'Paciente <b>'),'nombre escapado en listado');
    $r=$http->pedir($url($base+['estado'=>'cancelada']),'GET',$a);
    preg_match('/href="([^"]+)">Siguiente/',$r['body'],$enlace);$siguiente=html_entity_decode($enlace[1]??'',ENT_QUOTES,'UTF-8');
    $ok(str_contains($siguiente,'medico_id='.$f->medico)&&str_contains($siguiente,'estado=cancelada')&&str_contains($siguiente,'pagina=2'),'paginación conserva filtros');
    $r=$http->pedir($siguiente,'GET',$a);$ok(count($ids($r['body']))===1&&str_contains($r['body'],'Página 2 de 2'),'navegación real segunda página');
    preg_match('/href="([^"]+)">Ver detalle/',$r['body'],$enlace);$r=$http->pedir(html_entity_decode($enlace[1]??'',ENT_QUOTES,'UTF-8'),'GET',$a);
    preg_match('/href="([^"]+)">Volver al listado/',$r['body'],$enlace);$volver=html_entity_decode($enlace[1]??'',ENT_QUOTES,'UTF-8');
    $ok($r['status']===200&&str_contains($volver,'pagina=2')&&str_contains($volver,'estado=cancelada'),'detalle conserva retorno local y página');
    $ok(count($ids($http->pedir($volver,'GET',$a)['body']))===1,'retorno al listado filtrado');
    $r=$http->pedir($url(['medico_id'=>PHP_INT_MAX]),'GET',$a);$ok($r['status']===200&&str_contains($r['body'],'No se encontraron citas'),'mensaje vacío');
    foreach([['fecha'=>'2026-02-30'],['desde'=>'2026-03-02','hasta'=>'2026-03-01'],['fecha'=>['array']],['medico_id'=>"1 OR 1=1"],['codigo'=>"' OR 1=1 --"],['estado'=>'pendiente'],['pagina'=>-1],['periodo'=>'hack']] as $i=>$q){
        $r=$http->pedir($url($q),'GET',$a);$ok($r['status']===422&&$ids($r['body'])===[]&&!str_contains($r['body'],'SQLSTATE'),'rechazo HTTP de filtro '.$i);
    }
    $ok($http->pedir('/citas/'.PHP_INT_MAX,'GET',$a)['status']===404,'ID inexistente devuelve 404');
    $ok($http->pedir('/citas/99999999999999999999999','GET',$a)['status']===404,'ID desbordado devuelve 404');
    $r=$http->pedir('/citas','GET',$d);$ok($r['status']===200&&!in_array($f->ajena,$ids($r['body']),true)&&!str_contains($r['body'],'PACIENTE_AGENDA_AJENA_CU14'),'listado médico no divulga otra agenda');
    $ok(str_contains($r['body'],'Mis citas')&&!str_contains($r['body'],'href="/citas/nueva"'),'menú médico de solo lectura');
    $r=$http->pedir($url(['medico_id'=>$f->otro]),'GET',$d);$ok($r['status']===422&&$ids($r['body'])===[],'no amplía agenda con filtro médico manipulado');
    $r=$http->pedir($url(['codigo'=>$ajena]),'GET',$d);$ok($r['status']===200&&$ids($r['body'])===[],'código ajeno no revela datos');
    $r=$http->pedir('/citas/'.$f->ajena,'GET',$d);$inexistente=$http->pedir('/citas/'.PHP_INT_MAX,'GET',$d);$ok($r['status']===404&&$r['body']===$inexistente['body'],'IDOR indistinguible de inexistente');
    $r=$http->pedir('/citas/'.$f->reserva,'GET',$d);$ok($r['status']===200&&str_contains($r['body'],$codigo),'médico consulta detalle propio');
    $ok(!str_contains($r['body'],'MOTIVO_PRIVADO')&&!str_contains($r['body'],'Registrada por')&&!str_contains($r['body'],'CI-PRIVADO')&&!str_contains($r['body'],'70001234')&&!str_contains($r['body'],'DIRECCION_PRIVADA')&&!str_contains($r['body'],'hash_contrasena'),'detalle médico solo datos básicos');
    $r=$http->pedir('/citas','GET',$sesiones['sin_vinculo']['cookie']);$ok($r['status']===200&&str_contains($r['body'],'Cuenta sin médico vinculado')&&$ids($r['body'])===[],'médico sin vínculo recibe mensaje y ningún dato');
    $ok($http->pedir('/citas/'.$f->reserva,'GET',$sesiones['sin_vinculo']['cookie'])['status']===404,'sin vínculo no consulta por ID');
    foreach(['/citas','/citas/'.$f->reserva] as $ruta){$ok($http->pedir($ruta,'GET',$sesiones['paciente']['cookie'])['status']===403,'paciente denegado '.$ruta);}
    foreach(['/citas/nueva','/citas/confirmacion/'.$f->reserva] as $ruta){$ok($http->pedir($ruta,'GET',$d)['status']===403,'médico no accede a reserva '.$ruta);}
    $ok($http->pedir('/citas','POST',$d,['_csrf'=>$sesiones['medico']['csrf']])['status']===403,'médico no reserva');
    $r=$http->pedir('/citas/nueva','GET',$a);$ok($r['status']===200&&str_contains($r['body'],'Programar cita'),'ruta literal nueva funciona antes del ID');
    $ok($http->pedir('/citas/confirmacion/'.$f->reserva,'GET',$a)['status']===404,'ruta recibo conserva condición de sesión CU13');
    $ok($http->pedir('/citas/'.$f->reserva,'POST',$a,['_csrf'=>$sesiones['administrador']['csrf']])['status']===405,'detalle no acepta escrituras');
    $r=$http->pedir('/citas/'.$f->legado,'GET',$a);$ok($r['status']===200&&str_contains($r['body'],'Sin registrar'),'especialidad nula histórica visible');
    $r=$http->pedir('/disponibilidad?'.http_build_query(['medico_id'=>$f->medico,'fecha'=>$f->t->lunes->format('Y-m-d')]),'GET',$a);
    $ok($r['status']===200&&!str_contains($r['body'],'data-turno-inicio="'.$f->t->lunes->format('Y-m-d').'T08:00:00-04:00"'),'CU12 conserva turno ocupado');
    $ok((int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn()===$antes,'todas las consultas mantienen citas intactas');
    // Integración completa desde el formulario CU13, después de verificar lectura pura.
    $post=$f->t->datos($f->medico,$f->paciente,$f->especialidad,'08:30')+['_csrf'=>$sesiones['administrador']['csrf'],'_formato'=>'html','accion'=>'previsualizar'];
    $r=$http->pedir('/citas','POST',$a,$post);$ok($r['status']===200&&str_contains($r['body'],'08:30–09:00'),'CU13 revisión de nueva reserva HTML');
    $post['accion']='confirmar';$r=$http->pedir('/citas','POST',$a,$post);
    $ok($r['status']===303&&preg_match('#^/citas/confirmacion/([0-9]+)$#',$r['location'],$coincidencia)===1,'CU13 conserva PRG al registrar formulario');
    $nueva=(int)$coincidencia[1];$ok($http->pedir($r['location'],'GET',$a)['status']===200,'recibo CU13 de la sesión continúa operativo');
    $r=$http->pedir($url(['codigo'=>App\Models\Cita::codigo($nueva)]),'GET',$a);
    $ok($ids($r['body'])===[$nueva]&&str_contains($r['body'],'08:30')&&str_contains($r['body'],'09:00')&&str_contains($r['body'],'Programada'),'reserva del formulario aparece inmediatamente en CU14');
    $r=$http->pedir('/citas/'.$nueva,'GET',$a);
    $q=$pdo->prepare('SELECT nombre FROM especialidades WHERE id=:id');$q->execute(['id'=>$f->especialidad]);$nombreEspecialidad=$q->fetchColumn();
    $ok($r['status']===200&&str_contains($r['body'],'Paciente &lt;b&gt;ficticio CU14&lt;/b&gt;')&&str_contains($r['body'],htmlspecialchars($nombreEspecialidad,ENT_QUOTES,'UTF-8'))&&str_contains($r['body'],$f->t->lunes->format('d/m/Y'))&&str_contains($r['body'],'08:30–09:00'),'detalle CU14 coincide con reserva HTML CU13');
    $r=$http->pedir('/disponibilidad?'.http_build_query(['medico_id'=>$f->medico,'fecha'=>$f->t->lunes->format('Y-m-d')]),'GET',$a);
    $ok($r['status']===200&&!str_contains($r['body'],'data-turno-inicio="'.$f->t->lunes->format('Y-m-d').'T08:30:00-04:00"')&&(int)$pdo->query('SELECT count(*) FROM citas')->fetchColumn()===$antes+1,'CU12 bloquea nueva reserva HTML y solo existe una inserción');
}finally{if($http!==null){$http->cerrar();}if($f!==null){$f->t->fixture->limpiar();}}
echo "Aprobadas: $total. Fixtures HTTP ficticios retirados.\n";
