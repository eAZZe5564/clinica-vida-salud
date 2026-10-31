<?php
declare(strict_types=1);
require __DIR__.'/ConfirmacionPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=new ConfirmacionPrueba($pdo);$t=$prueba->c->r->t;$f=$t->fixture;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){fwrite(STDERR,"FALLO: $n\n");throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
try{
    $http=new HttpCatalogo();$sesiones=[];
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){
        $id=$f->usuario($rol);$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$q->fetchColumn(),'contrasena'=>'Ficticia91!'.$f->sufijo]);
        $panel=$http->pedir('/dashboard','GET',$r['cookie']);$sesiones[$rol]=['id'=>$id,'cookie'=>$r['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];
    }
    $a=$sesiones['administrador'];$m=$f->medico($sesiones['medico']['id']);$p=$f->paciente();$e=$f->especialidades[0];$t->horario($m);
    $pdo->prepare('UPDATE pacientes SET nombres=:n WHERE id=:id')->execute(['n'=>'Paciente <b>ficticio CU17</b>','id'=>$p]);
    $c=$t->servicio->crear($t->datos($m,$p,$e)+['motivo'=>'MOTIVO_CLINICO_FICTICIO_CU17'],$a['id'])['id'];$url='/citas/'.$c.'/confirmar';$original=$prueba->c->r->fila($c);
    foreach(['GET','POST'] as $metodo){$ok($http->pedir($url,$metodo)['status']===303,'requiere autenticación '.$metodo);}
    foreach(['medico','paciente'] as $rol){foreach(['GET','POST'] as $metodo){$ok($http->pedir($url,$metodo,$sesiones[$rol]['cookie'],['_csrf'=>$sesiones[$rol]['csrf']])['status']===403,'deniega '.$metodo.' a '.$rol);}}
    foreach(['administrador','recepcionista'] as $rol){
        $r=$http->pedir($url,'GET',$sesiones[$rol]['cookie']);
        $ok($r['status']===200&&str_contains($r['body'],'data-confirmar-cita')&&str_contains($r['body'],'Confirmar cita médica'),'pantalla para '.$rol);
    }
    $ok($prueba->c->r->fila($c)===$original&&$prueba->c->eventos($c)===[],'GET no cambia cita ni historial');
    $ok(str_contains($r['body'],'&lt;b&gt;ficticio CU17&lt;/b&gt;')&&!str_contains($r['body'],'MOTIVO_CLINICO_FICTICIO_CU17'),'XSS escapado y motivo clínico ausente');
    $ok(str_contains($r['body'],App\Models\Cita::codigo($c))&&str_contains($r['body'],'08:00–08:30')&&str_contains($r['body'],'America/La_Paz'),'muestra código y horario local');
    $base=$prueba->datos($c)+['_csrf'=>$a['csrf']];
    foreach(['','invalido',['array']] as $csrf){$ok($http->pedir($url,'POST',$a['cookie'],array_replace($base,['_csrf'=>$csrf]))['status']===419,'CSRF ausente inválido o malformado');}
    foreach(['',['array']] as $accion){$ok($http->pedir($url,'POST',$a['cookie'],array_replace($base,['accion'=>$accion]))['status']===422,'acción explícita requerida');}
    foreach(['',['array']] as $referencia){$ok($http->pedir($url,'POST',$a['cookie'],array_replace($base,['original'=>$referencia]))['status']===409,'referencia inválida rechazada');}
    $ok($http->pedir($url,'PUT',$a['cookie'])['status']===405,'método no permitido');
    foreach(['GET','POST'] as $metodo){$ok($http->pedir('/citas/'.PHP_INT_MAX.'/confirmar',$metodo,$a['cookie'],$base)['status']===404,'inexistente 404 '.$metodo);}
    $consulta='/citas?codigo='.App\Models\Cita::codigo($c);
    $ok(str_contains($http->pedir($consulta,'GET',$a['cookie'])['body'],$url),'CU14 listado ofrece confirmar');
    $ok(str_contains($http->pedir('/citas/'.$c,'GET',$a['cookie'])['body'],$url),'CU14 detalle ofrece confirmar');
    $ok(!str_contains($http->pedir($consulta,'GET',$sesiones['medico']['cookie'])['body'],$url),'médico no recibe acción administrativa');
    $prueba->c->r->servicio->reprogramar($c,$prueba->c->r->datos($c,'09:00'),$a['id']);
    $r=$http->pedir($url,'POST',$a['cookie'],$base);$ok($r['status']===409&&str_contains($r['body'],'cambió desde'),'formulario anterior a CU15 no confirma horario cambiado');
    $antes=$prueba->c->r->fila($c);$base=$prueba->datos($c)+['_csrf'=>$a['csrf']];
    $r=$http->pedir($url,'POST',$a['cookie'],$base+['estado'=>'cancelada','paciente_id'=>PHP_INT_MAX,'fecha_hora_inicio'=>'2000-01-01','motivo_cancelacion'=>'IGNORADO_FICTICIO']);
    $ok($r['status']===303&&$r['location']==='/citas/'.$c,'confirmación válida utiliza PRG');
    $detalle=$http->pedir($r['location'],'GET',$a['cookie']);
    $ok($detalle['status']===200&&str_contains($detalle['body'],'Cita confirmada correctamente')&&str_contains($detalle['body'],'El horario continúa reservado'),'detalle confirma éxito y ocupación');
    $ok(!str_contains($detalle['body'],$url)&&!str_contains($detalle['body'],'/citas/'.$c.'/reprogramar')&&!str_contains($detalle['body'],'/citas/'.$c.'/cancelar'),'confirmada sin acciones incompatibles');
    $despues=$prueba->c->r->fila($c);unset($antes['estado'],$despues['estado']);$ok($antes===$despues,'POST manipulado conserva todos los demás datos');
    $eventos=$prueba->c->eventos($c);$ok(count($eventos)===2&&$eventos[1]['tipo']==='confirmacion'&&$eventos[1]['motivo_cancelacion']===null,'historial CU15 y CU17 con motivo NULL');
    $http->pedir('/citas/'.$c,'GET',$a['cookie']);$ok(count($prueba->c->eventos($c))===2,'refresh GET no duplica historial');
    $ok($http->pedir($url,'POST',$a['cookie'],$base)['status']===409&&count($prueba->c->eventos($c))===2,'segunda confirmación rechazada sin duplicar');
    foreach(['reprogramar','cancelar'] as $operacion){foreach(['GET','POST'] as $metodo){$ok($http->pedir('/citas/'.$c.'/'.$operacion,$metodo,$a['cookie'],$base)['status']===409,'confirmada rechazada por '.$operacion.' '.$metodo);}}
    $lista=$http->pedir($consulta.'&estado=confirmada','GET',$a['cookie']);$ok($lista['status']===200&&str_contains($lista['body'],'data-cita-id="'.$c.'"')&&str_contains($lista['body'],'Confirmada'),'CU14 filtra confirmadas por HTTP');
    $d=$http->pedir('/disponibilidad?'.http_build_query(['medico_id'=>$m,'fecha'=>$t->lunes->format('Y-m-d')]),'GET',$a['cookie']);
    $ok(!str_contains($d['body'],'data-turno-inicio="'.$t->lunes->format('Y-m-d').'T09:00:00-04:00"'),'CU12 HTTP no libera turno confirmado');
    $n=$t->servicio->crear($t->datos($m,$p,$e,'10:00'),$a['id'])['id'];$recepcion=$sesiones['recepcionista'];$base=$prueba->datos($n)+['_csrf'=>$recepcion['csrf']];
    $ok($http->pedir('/citas/'.$n.'/confirmar','POST',$recepcion['cookie'],$base)['status']===303,'recepcionista confirma por HTTP');
    foreach(['cancelada','atendida'] as $i=>$estado){
        $id=$t->servicio->crear($t->datos($m,$p,$e,$i===0?'11:00':'11:30'),$a['id'])['id'];$base=$prueba->datos($id)+['_csrf'=>$a['csrf']];
        $pdo->prepare('UPDATE citas SET estado=:estado WHERE id=:id')->execute(['estado'=>$estado,'id'=>$id]);
        foreach(['GET','POST'] as $metodo){$ok($http->pedir('/citas/'.$id.'/confirmar',$metodo,$a['cookie'],$base)['status']===409,'rechaza '.$estado.' '.$metodo);}
    }
}finally{if($http!==null){$http->cerrar();}$prueba->limpiar();}
echo "Aprobadas: $total. HTTP real, roles, CSRF, XSS y PRG; fixtures propios eliminados.\n";
