<?php
declare(strict_types=1);
require __DIR__.'/HistorialPacientePrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=null;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
try{
    $prueba=new HistorialPacientePrueba($pdo);$b=$prueba->base;$p=$b->paciente;$ids=$b->ids;$prueba->reprogramaciones();$http=new HttpCatalogo();$sesiones=[];
    foreach($b->actores as $rol=>$actor){$login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$actor['correo'],'contrasena'=>'Ficticia91!'.$b->t->fixture->sufijo]);$ok($r['status']===303,'login fixture '.$rol);$sesiones[$rol]=$r['cookie'];}
    $pdo->prepare('UPDATE medicos SET nombres=:n WHERE id=:id')->execute(['n'=>'Médico <script>FICTICIO_CU20</script>','id'=>$b->medico]);
    $pdo->prepare('UPDATE especialidades SET nombre=:n WHERE id=:id')->execute(['n'=>'Especialidad <img src=x onerror=FICTICIO_CU20>','id'=>$b->especialidad]);
    $base='/pacientes/'.$p.'/historial-citas';$ev=$base.'/'.$ids['programada'].'/eventos';$a=$sesiones['administrador'];$m=$sesiones['medico'];$antes=$b->huella();
    foreach(['/historial-citas',$base,$ev] as $url){
        foreach(['GET','POST'] as $metodo){$ok($http->pedir($url,$metodo)['status']===303,'sesión requerida '.$url.' '.$metodo);$ok($http->pedir($url,$metodo,$sesiones['paciente'])['status']===403,'paciente vinculado sin portal '.$metodo);}
        foreach(['administrador','recepcionista','medico'] as $rol){$ok($http->pedir($url,'GET',$sesiones[$rol])['status']===200,'lectura autorizada '.$rol);foreach(['POST','PUT','DELETE'] as $metodo){$ok($http->pedir($url,$metodo,$sesiones[$rol],['estado'=>'cancelada'])['status']===405,'solo GET '.$rol.' '.$metodo);}}
    }
    $ok($http->pedir('/pacientes/'.$prueba->ajeno.'/historial-citas','GET',$sesiones['paciente'])['status']===403,'paciente no consulta otro historial');
    $ok($http->pedir('/citas/'.$ids['programada'],'GET',$sesiones['paciente'])['status']===403,'CU14 tampoco dispone de lectura propia para paciente');
    $r=$http->pedir($base,'GET',$a);$body=$r['body'];
    $ok(str_contains($body,'&lt;b&gt;ficticio CU19&lt;/b&gt;')&&!str_contains($body,'<b>ficticio CU19</b>'),'nombre del paciente escapado');
    $ok(str_contains($body,'&lt;script&gt;FICTICIO_CU20&lt;/script&gt;')&&!str_contains($body,'<script>FICTICIO_CU20</script>'),'médico escapado');
    $ok(str_contains($body,'&lt;img src=x onerror=FICTICIO_CU20&gt;')&&!str_contains($body,'<img src=x onerror=FICTICIO_CU20>'),'especialidad escapada');
    foreach(['MOTIVO_CLINICO_PRIVADO_CU19','CI-PRIVADO-','DIRECCION_PRIVADA_CU19','70001122','hash_contrasena','motivo_cancelacion'] as $privado){$ok(!str_contains($body,$privado),'historial sin dato sensible '.$privado);}
    preg_match_all('/data-historial-cita="([0-9]+)"/',$body,$rows);$ok(count($rows[1])===20&&str_contains($body,'41 citas visibles'),'primera página de veinte citas');
    $ok(count(array_filter($rows[1],fn($id)=>(int)$id===$ids['programada']))===1&&str_contains($body,'Ver eventos (23)'),'reprogramada única con conteo de eventos');
    $ok(!preg_match('#href="/citas/[0-9]+/(reprogramar|cancelar|confirmar|llegada|atencion|inasistencia)"#',$body),'no acciones de escritura desde historial');
    $ok(str_contains($body,'method="get" action="'.$base.'"'),'formulario de filtros GET acotado al paciente');
    foreach(App\Models\Cita::ESTADOS as $estado=>$label){$r=$http->pedir($base.'?estado='.$estado,'GET',$a);$ok($r['status']===200&&str_contains($r['body'],$label)&&str_contains($r['body'],'data-historial-cita='),'estado HTTP '.$estado);}
    $r=$http->pedir($base.'?desde='.$b->fecha.'&hasta='.$b->fecha,'GET',$a);$ok($r['status']===200&&str_contains($r['body'],'8 citas visibles'),'rango del mismo día incluye sus citas');
    $r=$http->pedir($base.'?medico_id='.$b->medico.'&especialidad_id='.$b->especialidad,'GET',$a);$ok($r['status']===200&&str_contains($r['body'],'39 citas visibles'),'filtros médico/especialidad en SQL');
    $r=$http->pedir($base.'?pagina=3','GET',$a);$ok($r['status']===200&&str_contains($r['body'],'Sin registrar')&&str_contains($r['body'],'Sin eventos registrados'),'legado con especialidad opcional NULL');
    $r=$http->pedir($base.'?estado=cancelada&pagina=2','GET',$a);$ok($r['status']===200&&str_contains($r['body'],'estado=cancelada')&&str_contains($r['body'],'Página 2 de 2'),'paginación mantiene filtros');
    $r=$http->pedir($ev,'GET',$a);$ok($r['status']===200&&substr_count($r['body'],'data-historial-evento=')===20&&str_contains($r['body'],'Inicio anterior')&&str_contains($r['body'],'Inicio nuevo')&&str_contains($r['body'],'<dt>Responsable</dt>'),'eventos reales con horario y responsable administrativo');
    $r=$http->pedir($ev.'?pagina=2','GET',$a);$ok($r['status']===200&&substr_count($r['body'],'data-historial-evento=')===3,'segunda página con los tres eventos restantes');
    foreach(['recepcionista','medico'] as $rol){$r=$http->pedir($ev,'GET',$sesiones[$rol]);$ok($r['status']===200&&!str_contains($r['body'],'<dt>Responsable</dt>')&&!str_contains($r['body'],$b->actores['administrador']['correo']),'sin identidad de responsables para '.$rol);}
    foreach(['confirmada','presente','atendida','inasistencia','cancelada'] as $estado){$r=$http->pedir($base.'/'.$ids[$estado].'/eventos','GET',$a);$ok($r['status']===200&&str_contains($r['body'],'data-historial-evento='),'auditoría HTTP de '.$estado);}
    $r=$http->pedir($base.'/'.$prueba->legado.'/eventos','GET',$a);$ok($r['status']===200&&str_contains($r['body'],'Sin eventos registrados')&&!str_contains($r['body'],'<dt>Responsable</dt>'),'cita antigua sin inferir cambios');
    $r=$http->pedir($base,'GET',$m);preg_match_all('/data-historial-cita="([0-9]+)"/',$r['body'],$rows);
    $ok(str_contains($r['body'],'40 citas visibles')&&!in_array((string)$ids['ajena'],$rows[1],true)&&!str_contains($r['body'],'id="historial-medico"'),'médico solo consulta su atención');
    $ok($http->pedir($base.'?medico_id='.$b->otro,'GET',$m)['status']===422,'manipulación del filtro médico rechazada');
    foreach([$base.'/'.$ids['ajena'].'/eventos','/pacientes/'.$prueba->ajeno.'/historial-citas','/pacientes/'.$prueba->unico.'/historial-citas/'.$ids['programada'].'/eventos','/pacientes/'.PHP_INT_MAX.'/historial-citas'] as $url){$r=$http->pedir($url,'GET',$m);$ok($r['status']===404&&!str_contains($r['body'],'data-historial-cita=')&&!str_contains($r['body'],'data-historial-evento='),'IDOR protegido sin datos');}
    $ok($http->pedir('/pacientes/'.PHP_INT_MAX.'/historial-citas','GET',$a)['status']===404,'paciente inexistente para administrador');
    $r=$http->pedir('/historial-citas?q=paciente','GET',$sesiones['sin_vinculo']);$ok($r['status']===200&&str_contains($r['body'],'no tiene un registro médico vinculado')&&!str_contains($r['body'],'Ver historial de citas'),'sin vínculo no enumera pacientes');
    $r=$http->pedir('/historial-citas?q=ficticio%20paciente','GET',$m);$ok($r['status']===200&&str_contains($r['body'],$base)&&!str_contains($r['body'],'/pacientes/'.$prueba->ajeno.'/historial-citas'),'búsqueda médica acotada');
    foreach(['desde=2026-02-30','desde=2026-11-02&hasta=2026-11-01','estado%5B%5D=atendida','pagina=0','medico_id=1%20OR%201=1','especialidad_id%5B%5D=2'] as $q){$ok($http->pedir($base.'?'.$q,'GET',$a)['status']===422,'filtro HTTP inválido controlado');}
    foreach(['q%5B%5D=x','q=%00','pagina=0'] as $q){$ok($http->pedir('/historial-citas?'.$q,'GET',$a)['status']===422,'búsqueda HTTP malformada');}
    $ok($http->pedir($ev.'?pagina=0','GET',$a)['status']===422,'página inválida de eventos');
    $r=$http->pedir('/pacientes/'.$prueba->vacio.'/historial-citas','GET',$a);$ok($r['status']===200&&str_contains($r['body'],'No hay citas visibles'),'paciente vacío controlado');
    $r=$http->pedir($base.'?desde=2030-01-01','GET',$a);$ok($r['status']===200&&str_contains($r['body'],'0 citas visibles'),'filtro sin resultados normal');
    foreach(['/pacientes/'.$p,'/pacientes?q=ficticio'] as $url){$r=$http->pedir($url,'GET',$a);$ok($r['status']===200&&str_contains($r['body'],'href="'.$base.'"'),'acceso integrado en CU04–CU06');}
    $ok($http->pedir('/pacientes/'.$p,'GET',$m)['status']===403,'CU20 no amplía el CRUD de pacientes al médico');
    $ok($antes===$b->huella(),'todas las lecturas y métodos rechazados conservan las nueve tablas tras login');
}finally{if($http){$http->cerrar();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. HTTP real, alcance/IDOR/privacidad/GET/eventos y huellas intactas; fixtures propios retirados.\n";
