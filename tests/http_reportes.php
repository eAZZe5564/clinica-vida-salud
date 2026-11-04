<?php
declare(strict_types=1);
require __DIR__.'/ReportesPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=null;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$indicadores=static function(string $body):array{preg_match_all('/data-reporte-indicador="([a-z]+)">([0-9]+)/',$body,$m,PREG_SET_ORDER);$r=[];foreach($m as $fila){$r[$fila[1]]=(int)$fila[2];}return $r;};
try{
    $prueba=new ReportesPrueba($pdo);$b=$prueba->base;$http=new HttpCatalogo();$sesiones=[];
    foreach($b->actores as $rol=>$actor){$login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$actor['correo'],'contrasena'=>'Ficticia91!'.$b->t->fixture->sufijo]);$ok($r['status']===303,'login ficticio '.$rol);$sesiones[$rol]=$r['cookie'];}
    $pdo->prepare('UPDATE medicos SET nombres=:n WHERE id=:id')->execute(['n'=>'Médico <script>FICTICIO_CU21</script>','id'=>$b->medico]);
    $pdo->prepare('UPDATE especialidades SET nombre=:n WHERE id=:id')->execute(['n'=>'Especialidad <img src=x onerror=FICTICIO_CU21>','id'=>$b->especialidad]);
    $antes=$b->huella();$ruta='/reportes/citas';$f=$prueba->filtros();$url=$ruta.'?'.http_build_query($f);
    foreach(['GET','POST','PUT','DELETE'] as $metodo){
        $ok($http->pedir($url,$metodo)['status']===303,'sin sesión redirige '.$metodo);
        foreach(['medico','paciente','sin_vinculo','otro_medico'] as $rol){$ok($http->pedir($url,$metodo,$sesiones[$rol])['status']===403,'rol rechazado '.$rol.' '.$metodo);}
    }
    foreach(['administrador','recepcionista'] as $rol){
        $cookie=$sesiones[$rol];$r=$http->pedir($url,'GET',$cookie);$body=$r['body'];$g=$indicadores($body);
        $ok($r['status']===200&&str_contains($body,'Reportes de citas médicas'),'GET autorizado '.$rol);
        $ok($g['total']===8&&$g['programada']===3&&array_sum(array_diff_key($g,['total'=>0]))===8,'tarjetas excluyentes correctas '.$rol);
        foreach(['estados','medicos','especialidades','dias'] as $grupo){$ok(str_contains($body,'data-reporte-tabla="'.$grupo.'"'),'tabla presente '.$grupo.' '.$rol);}
        $ok(str_contains($body,'&lt;script&gt;FICTICIO_CU21&lt;/script&gt;')&&!str_contains($body,'<script>FICTICIO_CU21</script>'),'médico escapado '.$rol);
        $ok(str_contains($body,'&lt;img src=x onerror=FICTICIO_CU21&gt;')&&!str_contains($body,'<img src=x onerror=FICTICIO_CU21>'),'especialidad escapada '.$rol);
        foreach(['Paciente &lt;b&gt;','MOTIVO_CLINICO_PRIVADO_CU19','DIRECCION_PRIVADA_CU19','CI-PRIVADO-','hash_contrasena','motivo_cancelacion','70001122'] as $privado){$ok(!str_contains($body,$privado),'sin dato de paciente o secreto '.$rol);}
        $ok(!preg_match('/href="[^\"]*\.(pdf|xlsx|csv)/i',$body)&&!str_contains($body,'Exportar'),'sin exportaciones CU22 '.$rol);
        foreach(['POST','PUT','DELETE'] as $metodo){$ok($http->pedir($url,$metodo,$cookie,['estado'=>'cancelada'])['status']===405,'método sin escritura '.$rol.' '.$metodo);}
        $ok(str_contains($http->pedir('/dashboard','GET',$cookie)['body'],'href="/reportes/citas"'),'integración menú/panel '.$rol);
    }
    $a=$sesiones['administrador'];
    foreach(array_keys(App\Models\Cita::ESTADOS) as $estado){$r=$http->pedir($ruta.'?'.http_build_query($f+['estado'=>$estado]),'GET',$a);$g=$indicadores($r['body']);$ok($r['status']===200&&$g[$estado]===$g['total']&&$g['total']>0,'filtro HTTP '.$estado);}
    $combinado=array_replace($f,['especialidad_id'=>$b->especialidad,'estado'=>'atendida']);$g=$indicadores($http->pedir($ruta.'?'.http_build_query($combinado),'GET',$a)['body']);$ok($g['total']===1,'filtros combinados consistentes');
    $cruce=array_replace($f,['desde'=>'2020-12-31','hasta'=>'2021-01-01']);$r=$http->pedir($ruta.'?'.http_build_query($cruce),'GET',$a);$ok($indicadores($r['body'])['total']===2&&str_contains($r['body'],'2020-12-31')&&str_contains($r['body'],'2021-01-01'),'cruce año local visible');
    $legado=array_replace($f,['desde'=>'2010-01-01','hasta'=>'2010-01-01']);$r=$http->pedir($ruta.'?'.http_build_query($legado),'GET',$a);$ok($indicadores($r['body'])['total']===1&&str_contains($r['body'],'Sin registrar'),'especialidad NULL legada visible');
    $vacio=array_replace($f,['medico_id'=>$prueba->sinCitas]);$r=$http->pedir($ruta.'?'.http_build_query($vacio),'GET',$a);$ok($indicadores($r['body'])['total']===0&&str_contains($r['body'],'No hay citas'),'sin registros muestra ceros');
    foreach([['desde'=>'2026-02-30'],['hasta'=>'0000-01-01'],['desde'=>['x']],['desde'=>'<script>ATAQUE_CU21</script>'],['desde'=>'2027-02-01','hasta'=>'2027-01-31'],['estado'=>['atendida']],['estado'=>'borrada'],['medico_id'=>'1 OR 1=1'],['medico_id'=>-1],['especialidad_id'=>['x']],['medico_id'=>PHP_INT_MAX]] as $mal){
        $r=$http->pedir($ruta.'?'.http_build_query(array_replace($f,$mal)),'GET',$a);$ok($r['status']===422&&!str_contains($r['body'],'<script>ATAQUE_CU21</script>')&&!str_contains($r['body'],'data-reporte-indicador='),'manipulación controlada sin datos');
    }
    foreach(['medico','paciente'] as $rol){$ok(!str_contains($http->pedir('/dashboard','GET',$sesiones[$rol])['body'],'href="/reportes/citas"'),'sin enlace de reportes '.$rol);}
    $ok($antes===$b->huella(),'GET y métodos rechazados no cambian las nueve tablas después del login');
}finally{if($http){$http->cerrar();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. HTTP real CU21: roles, filtros, XSS, agregados, métodos y privacidad.\n";
