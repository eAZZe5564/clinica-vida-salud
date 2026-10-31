<?php
declare(strict_types=1);
require __DIR__ . '/ReprogramacionPrueba.php';require __DIR__ . '/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=new ReprogramacionPrueba($pdo);$t=$prueba->t;$f=$t->fixture;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$campos=static function(string $html):array{
    if(preg_match('#<form[^>]*data-reprogramar-confirmar[^>]*>(.*?)</form>#s',$html,$form)!==1){throw new RuntimeException('Falta revisión final.');}
    preg_match_all('/<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"/',$form[1],$matches,PREG_SET_ORDER);$datos=[];
    foreach($matches as $m){$datos[$m[1]]=html_entity_decode($m[2],ENT_QUOTES|ENT_HTML5,'UTF-8');}return $datos;
};
try{
    $http=new HttpCatalogo();$sesiones=[];
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){
        $id=$f->usuario($rol);$q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);
        $login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$q->fetchColumn(),'contrasena'=>'Ficticia91!'.$f->sufijo]);
        $panel=$http->pedir('/dashboard','GET',$r['cookie']);$sesiones[$rol]=['id'=>$id,'cookie'=>$r['cookie'],'csrf'=>HttpCatalogo::csrf($panel['body'])];
    }
    $a=$sesiones['administrador'];$m=$f->medico($sesiones['medico']['id']);$p=$f->paciente();$e=$f->especialidades[0];$t->horario($m);
    $pdo->prepare('UPDATE pacientes SET nombres=:n WHERE id=:id')->execute(['n'=>'Paciente <b>ficticio CU15</b>','id'=>$p]);
    $c=$t->servicio->crear($t->datos($m,$p,$e)+['motivo'=>'MOTIVO_PRIVADO_CU15'],$a['id'])['id'];$url='/citas/'.$c.'/reprogramar';$original=$prueba->fila($c);
    $ok($http->pedir($url)['status']===303,'GET requiere autenticación');
    $ok($http->pedir($url,'POST','',['accion'=>'confirmar'])['status']===303,'POST requiere autenticación');
    foreach(['medico','paciente'] as $rol){
        foreach(['GET','POST'] as $metodo){$r=$http->pedir($url,$metodo,$sesiones[$rol]['cookie'],['_csrf'=>$sesiones[$rol]['csrf']]);$ok($r['status']===403,'denegación '.$metodo.' a '.$rol);}
    }
    foreach(['administrador','recepcionista'] as $rol){$r=$http->pedir($url,'GET',$sesiones[$rol]['cookie']);$ok($r['status']===200&&str_contains($r['body'],'Horario actual')&&str_contains($r['body'],'Nuevo horario solicitado'),'formulario para '.$rol);}
    $r=$http->pedir($url,'GET',$a['cookie']);
    $ok(str_contains($r['body'],'&lt;b&gt;ficticio CU15&lt;/b&gt;')&&!str_contains($r['body'],'MOTIVO_PRIVADO_CU15'),'XSS escapado y motivo clínico ausente');
    $ok(str_contains($r['body'],'(horario actual)')&&substr_count($r['body'],'name="hora_inicio"')===8,'CU12 incluye turno propio correctamente etiquetado');
    $base=$prueba->datos($c,'10:00')+['_csrf'=>$a['csrf']];
    $ok($http->pedir($url,'POST',$a['cookie'],$base+['accion'=>'confirmar','_csrf_ignorado'=>'x'])['status']===303,'confirmación válida usa PRG');
    $r=$http->pedir('/citas/'.$c,'GET',$a['cookie']);
    $ok($r['status']===200&&str_contains($r['body'],'Cita reprogramada correctamente')&&str_contains($r['body'],'10:00–10:30'),'confirmación muestra nuevo horario en CU14');
    $q=$pdo->prepare('SELECT count(*) FROM citas_historial WHERE cita_id=:id');$q->execute(['id'=>$c]);$eventos=(int)$q->fetchColumn();
    $http->pedir('/citas/'.$c,'GET',$a['cookie']);$q->execute(['id'=>$c]);$ok((int)$q->fetchColumn()===$eventos,'recargar GET no repite cambios');
    $r=$http->pedir($url,'POST',$a['cookie'],$base+['accion'=>'confirmar']);$ok($r['status']===409&&str_contains($r['body'],'cambió desde'),'reenvío desactualizado no sobrescribe');
    $base=$prueba->datos($c,'10:30')+['_csrf'=>$a['csrf']];
    $r=$http->pedir($url,'POST',$a['cookie'],$base+['accion'=>'revisar']);
    $ok($r['status']===200&&str_contains($r['body'],'Todavía no se ha modificado')&&str_contains($r['body'],'10:00–10:30')&&str_contains($r['body'],'10:30–11:00'),'revisión con ambos horarios');
    $post=$campos($r['body']);$q->execute(['id'=>$c]);$ok((int)$q->fetchColumn()===$eventos,'revisión no registra historial');
    $t->servicio->crear($t->datos($m,$p,$e,'10:30'),$a['id']);$antesConflicto=$prueba->fila($c);
    $r=$http->pedir($url,'POST',$a['cookie'],$post);
    $ok($r['status']===409&&str_contains($r['body'],'horario original se conserva')&&!str_contains($r['body'],'SQLSTATE'),'turno ocupado tras revisión se rechaza sin SQL');
    $ok($prueba->fila($c)===$antesConflicto,'conflicto HTTP conserva cita original');
    $nuevo=$prueba->datos($c,'11:00')+['_csrf'=>$a['csrf'],'accion'=>'confirmar'];
    foreach(['', 'invalido', ['array']] as $csrf){$r=$http->pedir($url,'POST',$a['cookie'],array_replace($nuevo,['_csrf'=>$csrf]));$ok($r['status']===419,'CSRF ausente inválido o malformado');}
    $r=$http->pedir($url,'POST',$a['cookie'],array_replace($nuevo,['fecha'=>['array']]));$ok($r['status']===422,'fecha malformada rechazada');
    $r=$http->pedir($url,'POST',$a['cookie'],array_replace($nuevo,['fecha'=>'2000-01-01']));$ok($r['status']===422,'fecha pasada rechazada');
    $r=$http->pedir($url,'POST',$a['cookie'],array_replace($nuevo,['hora_inicio'=>'10:00']));$ok($r['status']===409&&str_contains($r['body'],'ningún cambio'),'horario actual informa sin cambios');
    $r=$http->pedir('/citas/'.PHP_INT_MAX.'/reprogramar','GET',$a['cookie']);$ok($r['status']===404,'inexistente devuelve 404');
    $ok($http->pedir($url,'PUT',$a['cookie'])['status']===405,'método no permitido');
    foreach(['cancelada','atendida'] as $estado){
        $pdo->prepare('UPDATE citas SET estado=:s WHERE id=:id')->execute(['s'=>$estado,'id'=>$c]);
        $ok($http->pedir($url,'GET',$a['cookie'])['status']===409,'formulario deniega '.$estado);
        $ok(!str_contains($http->pedir('/citas/'.$c,'GET',$a['cookie'])['body'],$url),'detalle oculta acción '.$estado);
    }
    $pdo->prepare("UPDATE citas SET estado='programada' WHERE id=:id")->execute(['id'=>$c]);
    $consulta='/citas?codigo='.App\Models\Cita::codigo($c);
    $ok(str_contains($http->pedir($consulta,'GET',$a['cookie'])['body'],$url),'listado ofrece acción al administrador');
    $ok(!str_contains($http->pedir($consulta,'GET',$sesiones['medico']['cookie'])['body'],$url),'listado médico sin acción');
    $r=$http->pedir($url,'POST',$sesiones['recepcionista']['cookie'],array_replace($nuevo,['_csrf'=>$sesiones['recepcionista']['csrf']]));
    $ok($r['status']===303,'recepcionista confirma cambio');
    $despues=$prueba->fila($c);unset($original['fecha_hora_inicio'],$despues['fecha_hora_inicio']);$ok($despues===$original,'todos los campos salvo horario conservados por HTTP');
    $d=$http->pedir('/disponibilidad?'.http_build_query(['medico_id'=>$m,'fecha'=>$nuevo['fecha']]),'GET',$a['cookie']);
    $ok(str_contains($d['body'],'data-turno-inicio="'.$nuevo['fecha'].'T08:00:00-04:00"')&&!str_contains($d['body'],'data-turno-inicio="'.$nuevo['fecha'].'T11:00:00-04:00"'),'CU12 HTTP libera original y ocupa nuevo');
}finally{if($http!==null){$http->cerrar();}$prueba->limpiar();}
echo "Aprobadas: $total. HTTP real, CSRF, roles y PRG; fixtures propios eliminados.\n";
