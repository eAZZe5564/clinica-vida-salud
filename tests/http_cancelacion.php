<?php
declare(strict_types=1);
require __DIR__.'/CancelacionPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=new CancelacionPrueba($pdo);$t=$prueba->r->t;$f=$t->fixture;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
try{
    $http=new HttpCatalogo();$sesiones=[];
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){
        $id=$f->usuario($rol);$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$q->fetchColumn(),'contrasena'=>'Ficticia91!'.$f->sufijo]);
        $panel=$http->pedir('/dashboard','GET',$r['cookie']);$sesiones[$rol]=['id'=>$id,'cookie'=>$r['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];
    }
    $a=$sesiones['administrador'];$m=$f->medico($sesiones['medico']['id']);$p=$f->paciente();$e=$f->especialidades[0];$h=$t->horario($m);
    $pdo->prepare('UPDATE pacientes SET nombres=:n WHERE id=:id')->execute(['n'=>'Paciente <b>ficticio CU16</b>','id'=>$p]);
    $c=$t->servicio->crear($t->datos($m,$p,$e)+['motivo'=>'MOTIVO_CLINICO_FICTICIO_CU16'],$a['id'])['id'];$url='/citas/'.$c.'/cancelar';$original=$prueba->r->fila($c);
    foreach(['GET','POST'] as $metodo){$ok($http->pedir($url,$metodo)['status']===303,'requiere autenticación '.$metodo);}
    foreach(['medico','paciente'] as $rol){foreach(['GET','POST'] as $metodo){$ok($http->pedir($url,$metodo,$sesiones[$rol]['cookie'],['_csrf'=>$sesiones[$rol]['csrf']])['status']===403,'deniega '.$metodo.' a '.$rol);}}
    foreach(['administrador','recepcionista'] as $rol){
        $r=$http->pedir($url,'GET',$sesiones[$rol]['cookie']);
        $ok($r['status']===200&&str_contains($r['body'],'data-cancelar-confirmar')&&str_contains($r['body'],'Confirmar cancelación'),'confirmación disponible para '.$rol);
    }
    $ok($prueba->r->fila($c)===$original&&$prueba->eventos($c)===[],'GET no cancela ni registra historial');
    $ok(str_contains($r['body'],'&lt;b&gt;ficticio CU16&lt;/b&gt;')&&!str_contains($r['body'],'MOTIVO_CLINICO_FICTICIO_CU16'),'escape XSS y motivo clínico ausente');
    $ok(str_contains($r['body'],App\Models\Cita::codigo($c))&&str_contains($r['body'],'08:00–08:30')&&str_contains($r['body'],'America/La_Paz'),'confirmación muestra identidad y horario local');
    $base=$prueba->datos($c)+['_csrf'=>$a['csrf']];
    foreach(['', 'invalido', ['array']] as $csrf){$ok($http->pedir($url,'POST',$a['cookie'],array_replace($base,['_csrf'=>$csrf]))['status']===419,'CSRF ausente inválido o malformado');}
    foreach([['accion'=>''],['accion'=>['array']],['motivo_cancelacion'=>['array']],['motivo_cancelacion'=>str_repeat('a',251)],['motivo_cancelacion'=>"salto\nlinea"]] as $cambio){
        $respuesta=$http->pedir($url,'POST',$a['cookie'],array_replace($base,$cambio));
        $ok($respuesta['status']===422,'entrada inválida rechazada: '.array_key_first($cambio).' / HTTP '.$respuesta['status']);
    }
    $r=$http->pedir($url,'POST',$a['cookie'],array_replace($base,['accion'=>'','motivo_cancelacion'=>'<script>ficticio</script>']));
    $ok(str_contains($r['body'],'&lt;script&gt;ficticio&lt;/script&gt;')&&!str_contains($r['body'],'<script>ficticio</script>'),'motivo remostrado con escape XSS');
    $ok($http->pedir($url,'PUT',$a['cookie'])['status']===405,'método no permitido');
    $ok($http->pedir('/citas/'.PHP_INT_MAX.'/cancelar','GET',$a['cookie'])['status']===404,'GET inexistente 404');
    $ok($http->pedir('/citas/'.PHP_INT_MAX.'/cancelar','POST',$a['cookie'],$base)['status']===404,'POST inexistente 404');
    $consulta='/citas?codigo='.App\Models\Cita::codigo($c);
    $ok(str_contains($http->pedir($consulta,'GET',$a['cookie'])['body'],$url),'CU14 listado ofrece cancelación administrativa');
    $ok(str_contains($http->pedir('/citas/'.$c,'GET',$a['cookie'])['body'],$url),'CU14 detalle ofrece cancelación administrativa');
    $ok(!str_contains($http->pedir($consulta,'GET',$sesiones['medico']['cookie'])['body'],$url),'médico no recibe acción administrativa');
    $prueba->r->servicio->reprogramar($c,$prueba->r->datos($c,'09:00'),$a['id']);
    $r=$http->pedir($url,'POST',$a['cookie'],$base);
    $ok($r['status']===409&&str_contains($r['body'],'cambió desde'),'formulario viejo no cancela reprogramación posterior');
    $base=$prueba->datos($c,'<b>Motivo administrativo ficticio</b>')+['_csrf'=>$a['csrf']];
    $r=$http->pedir($url,'POST',$a['cookie'],$base+['paciente_id'=>PHP_INT_MAX,'estado'=>'atendida','fecha_hora_inicio'=>'2000-01-01']);
    $ok($r['status']===303&&$r['location']==='/citas/'.$c,'cancelación válida utiliza PRG');
    $detalle=$http->pedir($r['location'],'GET',$a['cookie']);
    $ok($detalle['status']===200&&str_contains($detalle['body'],'Cita cancelada correctamente')&&str_contains($detalle['body'],'Cancelada'),'CU14 confirma cancelación');
    $ok(!str_contains($detalle['body'],$url)&&!str_contains($detalle['body'],'/citas/'.$c.'/reprogramar'),'cancelada sin acciones de cancelación o reprogramación');
    $antes=$original;$despues=$prueba->r->fila($c);unset($antes['estado'],$antes['fecha_hora_inicio'],$despues['estado'],$despues['fecha_hora_inicio']);
    $ok($antes===$despues,'POST manipulado no altera identidad ni otros campos');
    $ok((new DateTimeImmutable($prueba->r->fila($c)['fecha_hora_inicio']))->setTimezone(App\Models\HorarioAtencion::zona())->format('H:i')==='09:00','cancelación conserva horario reprogramado');
    $eventos=$prueba->eventos($c);$ok(count($eventos)===2&&$eventos[1]['motivo_cancelacion']==='<b>Motivo administrativo ficticio</b>','historial separa reprogramación y cancelación con motivo');
    $http->pedir('/citas/'.$c,'GET',$a['cookie']);$ok(count($prueba->eventos($c))===2,'refresh GET no duplica historial');
    $ok($http->pedir($url,'POST',$a['cookie'],$base)['status']===409&&count($prueba->eventos($c))===2,'reenvío POST rechazado sin duplicación');
    $ok($http->pedir('/citas/'.$c.'/reprogramar','GET',$a['cookie'])['status']===409,'CU15 rechaza cita cancelada por HTTP');
    $d=$http->pedir('/disponibilidad?'.http_build_query(['medico_id'=>$m,'fecha'=>$t->lunes->format('Y-m-d')]),'GET',$a['cookie']);
    $ok(str_contains($d['body'],'data-turno-inicio="'.$t->lunes->format('Y-m-d').'T09:00:00-04:00"'),'CU12 HTTP ofrece turno liberado');
    $n=$t->servicio->crear($t->datos($m,$p,$e,'09:00'),$a['id'])['id'];$url2='/citas/'.$n.'/cancelar';
    $recepcion=$sesiones['recepcionista'];$base=$prueba->datos($n)+['_csrf'=>$recepcion['csrf']];
    $ok($http->pedir($url2,'POST',$recepcion['cookie'],$base)['status']===303,'recepcionista cancela sin motivo');
    $ok($prueba->eventos($n)[0]['motivo_cancelacion']===null,'motivo omitido queda NULL');
    $atendida=$t->servicio->crear($t->datos($m,$p,$e,'10:00'),$a['id'])['id'];$base=$prueba->datos($atendida)+['_csrf'=>$a['csrf']];
    $pdo->prepare("UPDATE citas SET estado='atendida' WHERE id=:id")->execute(['id'=>$atendida]);
    foreach(['GET','POST'] as $metodo){$ok($http->pedir('/citas/'.$atendida.'/cancelar',$metodo,$a['cookie'],$base)['status']===409,'atendida rechazada '.$metodo);}
}finally{if($http!==null){$http->cerrar();}$prueba->limpiar();}
echo "Aprobadas: $total. HTTP real, roles, CSRF, XSS y PRG; fixtures propios eliminados.\n";
