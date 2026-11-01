<?php
declare(strict_types=1);
require __DIR__.'/AsistenciaPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=new AsistenciaPrueba($pdo);$t=$prueba->t;$f=$t->fixture;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"FALLO: $n\n");throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
try{
    $http=new HttpCatalogo();$sesiones=[];
    foreach(['administrador','recepcionista','medico','paciente','otro_medico'] as $rol){
        $id=$f->usuario($rol==='otro_medico'?'medico':$rol);$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$q->fetchColumn(),'contrasena'=>'Ficticia91!'.$f->sufijo]);
        $panel=$http->pedir('/dashboard','GET',$r['cookie']);$sesiones[$rol]=['id'=>$id,'cookie'=>$r['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];
    }
    $a=$sesiones['administrador'];$recepcion=$sesiones['recepcionista'];$doctor=$sesiones['medico'];
    $m=$f->medico($doctor['id']);$p=$f->paciente();$e=$f->especialidades[0];$t->horario($m);
    $pdo->prepare('UPDATE pacientes SET nombres=:n WHERE id=:id')->execute(['n'=>'Paciente <b>ficticio CU18</b>','id'=>$p]);
    $c=$t->servicio->crear($t->datos($m,$p,$e)+['motivo'=>'MOTIVO_CLINICO_FICTICIO_CU18'],$a['id'])['id'];$original=$prueba->fila($c);
    $base=$prueba->datos($c)+['_csrf'=>$a['csrf']];
    foreach(['llegada','atencion','inasistencia'] as $accion){
        $url='/citas/'.$c.'/'.$accion;
        foreach(['GET','POST'] as $metodo){$ok($http->pedir($url,$metodo)['status']===303,'requiere sesión '.$accion.' '.$metodo);}
        foreach(['GET','POST'] as $metodo){$ok($http->pedir($url,$metodo,$sesiones['paciente']['cookie'],array_replace($base,['_csrf'=>$sesiones['paciente']['csrf']]))['status']===403,'paciente sin acción '.$accion.' '.$metodo);}
    }
    foreach(['administrador','recepcionista'] as $rol){
        $r=$http->pedir('/citas/'.$c.'/llegada','GET',$sesiones[$rol]['cookie']);
        $ok($r['status']===200&&str_contains($r['body'],'data-asistencia-confirmar'),'formulario llegada para '.$rol);
        $ok($http->pedir('/citas/'.$c.'/atencion','GET',$sesiones[$rol]['cookie'])['status']===403,'rol administrativo no finaliza atención');
        $ok($http->pedir('/citas/'.$c.'/inasistencia','GET',$sesiones[$rol]['cookie'])['status']===409,'inasistencia prematura no disponible');
    }
    $ok(str_contains($r['body'],'&lt;b&gt;ficticio CU18&lt;/b&gt;')&&!str_contains($r['body'],'MOTIVO_CLINICO_FICTICIO_CU18'),'formulario escapa nombres y no selecciona motivo clínico');
    $ok($prueba->fila($c)===$original&&$prueba->eventos($c)===[],'GET no modifica cita ni historial');
    foreach(['medico','otro_medico'] as $rol){
        foreach(['llegada','inasistencia'] as $accion){$ok($http->pedir('/citas/'.$c.'/'.$accion,'GET',$sesiones[$rol]['cookie'])['status']===403,'médico no registra '.$accion);}
    }
    foreach(['GET','POST'] as $metodo){$ok($http->pedir('/citas/'.$c.'/atencion',$metodo,$sesiones['otro_medico']['cookie'],array_replace($base,['_csrf'=>$sesiones['otro_medico']['csrf']]))['status']===404,'médico ajeno recibe 404 '.$metodo);}
    foreach(['','invalido',['array']] as $csrf){$ok($http->pedir('/citas/'.$c.'/llegada','POST',$a['cookie'],array_replace($base,['_csrf'=>$csrf]))['status']===419,'CSRF ausente inválido o malformado');}
    foreach(['',['array']] as $accion){$ok($http->pedir('/citas/'.$c.'/llegada','POST',$a['cookie'],array_replace($base,['accion'=>$accion]))['status']===422,'confirmación explícita requerida');}
    $ok($http->pedir('/citas/'.$c.'/llegada','PUT',$a['cookie'])['status']===405,'rechaza método no permitido');
    $ok($http->pedir('/citas/'.PHP_INT_MAX.'/llegada','GET',$a['cookie'])['status']===404,'cita inexistente 404');
    $r=$http->pedir('/citas/'.$c.'/llegada','POST',$recepcion['cookie'],array_replace($base,['_csrf'=>$recepcion['csrf']])+['estado'=>'atendida','usuario_id'=>PHP_INT_MAX,'cambiado_en'=>'2099-01-01']);
    $ok($r['status']===303&&$r['location']==='/citas/'.$c,'llegada válida utiliza PRG');
    $detalle=$http->pedir($r['location'],'GET',$recepcion['cookie']);
    $ok($detalle['status']===200&&str_contains($detalle['body'],'Llegada registrada correctamente')&&str_contains($detalle['body'],'Presente'),'CU14 diferencia presente de atendida');
    $ok(!str_contains($detalle['body'],'Trazabilidad administrativa'),'recepcionista sin traza administrativa completa');
    $evento=$prueba->eventos($c)[0];$ahora=new DateTimeImmutable($pdo->query('SELECT clock_timestamp()')->fetchColumn());
    $ok((int)$evento['usuario_id']===$recepcion['id']&&abs($ahora->getTimestamp()-(new DateTimeImmutable($evento['cambiado_en']))->getTimestamp())<10,'responsable y momento real, sin confiar en POST');
    $despues=$prueba->fila($c);unset($original['estado'],$despues['estado']);$ok($original===$despues,'POST manipulado conserva todos los demás datos');
    $http->pedir('/citas/'.$c,'GET',$recepcion['cookie']);$ok(count($prueba->eventos($c))===1,'recarga GET no duplica llegada');
    $ok($http->pedir('/citas/'.$c.'/llegada','POST',$recepcion['cookie'],array_replace($base,['_csrf'=>$recepcion['csrf']]))['status']===409,'segunda llegada rechazada');
    $medicoBase=$prueba->datos($c)+['_csrf'=>$doctor['csrf']];
    $ok($http->pedir('/citas/'.$c.'/atencion','POST',$doctor['cookie'],$medicoBase)['status']===409,'médico no finaliza antes del inicio');
    $ok($http->pedir('/citas/'.$c.'/inasistencia','POST',$a['cookie'],$prueba->datos($c)+['_csrf'=>$a['csrf']])['status']===409,'presente no admite inasistencia');
    foreach(['reprogramar','cancelar','confirmar'] as $accion){$ok($http->pedir('/citas/'.$c.'/'.$accion,'GET',$a['cookie'])['status']===409,'presente bloquea CU15–CU17: '.$accion);}
    $admin=$http->pedir('/citas/'.$c,'GET',$a['cookie']);
    $ok(str_contains($admin['body'],'Trazabilidad administrativa')&&str_contains($admin['body'],'Llegada'),'administrador consulta traza de llegada');
    $lista=$http->pedir('/citas?estado=presente&codigo='.App\Models\Cita::codigo($c),'GET',$doctor['cookie']);
    $ok($lista['status']===200&&str_contains($lista['body'],'data-cita-id="'.$c.'"'),'médico filtra presentes de su agenda');
    $ajeno=$http->pedir('/citas/'.$c,'GET',$sesiones['otro_medico']['cookie']);$ok($ajeno['status']===404,'médico ajeno tampoco consulta detalle');
    // Un fixture a dos segundos permite probar el reloj real sin cambiar triggers o sistema.
    $real=$prueba->citaReal($m,$p,$e,$a['id']);$antes=$prueba->fila($real);$prueba->esperarInicio($real);
    $r=$http->pedir('/citas/'.$real.'/llegada','POST',$a['cookie'],$prueba->datos($real)+['_csrf'=>$a['csrf']]);
    $ok($r['status']===303,'administrador también registra llegada real');
    $doctorDetalle=$http->pedir('/citas/'.$real,'GET',$doctor['cookie']);
    $ok(str_contains($doctorDetalle['body'],'/citas/'.$real.'/atencion'),'solo agenda propia ofrece finalizar atención');
    $r=$http->pedir('/citas/'.$real.'/atencion','POST',$doctor['cookie'],$prueba->datos($real)+['_csrf'=>$doctor['csrf']]);
    $ok($r['status']===303,'médico vinculado finaliza con reloj real');
    $detalle=$http->pedir('/citas/'.$real,'GET',$doctor['cookie']);
    $ok(str_contains($detalle['body'],'Atención completada registrada correctamente')&&str_contains($detalle['body'],'Atendida'),'CU14 muestra cierre y marca de atención');
    $eventos=$prueba->eventos($real);$ok(count($eventos)===2&&(int)$eventos[0]['usuario_id']===$a['id']&&(int)$eventos[1]['usuario_id']===$doctor['id'],'historial diferencia responsables de recepción y atención');
    $ok(new DateTimeImmutable($eventos[1]['cambiado_en'])>=new DateTimeImmutable($antes['fecha_hora_inicio']),'finalización real posterior al inicio');
    foreach(['llegada','atencion','inasistencia'] as $accion){$actor=$accion==='atencion'?$doctor:$a;$ok($http->pedir('/citas/'.$real.'/'.$accion,'POST',$actor['cookie'],$prueba->datos($real)+['_csrf'=>$actor['csrf']])['status']===409,'atendida terminal por HTTP '.$accion);}
}finally{if($http!==null){$http->cerrar();}$prueba->limpiar();}
echo "Aprobadas: $total. HTTP real con reloj PostgreSQL; llegada/atención y rechazos temporales; fixtures propios eliminados.\n";
