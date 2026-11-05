<?php
declare(strict_types=1);
require __DIR__.'/ReportesPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=null;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
try{
    $prueba=new ReportesPrueba($pdo);$b=$prueba->base;$http=new HttpCatalogo();$cookies=[];
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){$login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$b->actores[$rol]['correo'],'contrasena'=>'Ficticia91!'.$b->t->fixture->sufijo]);$ok($r['status']===303,'login ficticio '.$rol);$cookies[$rol]=$r['cookie'];}
    $pdo->prepare('UPDATE medicos SET nombres=:n WHERE id=:id')->execute(['n'=>'Médico áéíóú ñ Ñ','id'=>$b->medico]);
    $pdo->prepare('UPDATE especialidades SET nombre=:n WHERE id=:id')->execute(['n'=>'Especialidad Niño Ñandú','id'=>$b->especialidad]);
    $q=$pdo->prepare("INSERT INTO citas(medico_id,paciente_id,especialidad_id,fecha_hora_inicio,estado) SELECT :m,:p,:e,CAST('1800-01-01T08:00:00-04:00' AS timestamptz)+make_interval(days=>n),'cancelada' FROM generate_series(0,2000) n");
    $q->execute(['m'=>$b->medico,'p'=>$b->paciente,'e'=>$b->especialidad]);
    $antes=$b->huella();$publicos=iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/public',FilesystemIterator::SKIP_DOTS)));
    $dir=__DIR__.'/artifacts/cu22';if(!is_dir($dir)){mkdir($dir,0700,true);}$ruta='/reportes/citas/exportar/pdf';$f=$prueba->filtros();$query=http_build_query($f);
    foreach(['GET','POST','PUT','DELETE'] as $metodo){
        $ok($http->pedir($ruta.'?'.$query,$metodo)['status']===303,'sin sesión no descarga PDF '.$metodo);
        foreach(['medico','paciente'] as $rol){$r=$http->pedir($ruta.'?'.$query,$metodo,$cookies[$rol]);$ok($r['status']===403&&!str_starts_with($r['body'],'%PDF-'),'rol sin PDF '.$rol.' '.$metodo);}
    }
    foreach(['administrador','recepcionista'] as $rol){
        $r=$http->pedir($ruta.'?'.$query,'GET',$cookies[$rol]);$h=implode("\n",$r['headers']);
        $ok($r['status']===200&&str_starts_with($r['body'],'%PDF-')&&str_contains(substr($r['body'],-1024),'%%EOF'),'PDF HTTP real '.$rol);
        $ok(str_contains($h,'Content-Type: application/pdf')&&str_contains($h,'Content-Disposition: attachment; filename="clinica-vida-salud-reporte-citas-'),'cabeceras PDF '.$rol);
        $ok(str_contains($h,'private, no-store')&&str_contains($h,'X-Content-Type-Options: nosniff'),'PDF privado sin caché '.$rol);
        $ok(str_contains($h,'Content-Length: '.strlen($r['body'])),'longitud binaria correcta '.$rol);
        file_put_contents($dir.'/http-'.$rol.'.pdf',$r['body']);
        foreach(['POST','PUT','DELETE'] as $metodo){$ok($http->pedir($ruta.'?'.$query,$metodo,$cookies[$rol])['status']===405,'PDF solo GET '.$rol.' '.$metodo);}
    }
    $casos=[[],['estado'=>'cancelada'],['estado'=>'inasistencia'],['estado'=>'atendida','especialidad_id'=>$b->especialidad],['desde'=>'2010-01-01','hasta'=>'2010-01-01'],['desde'=>'2020-12-31','hasta'=>'2021-01-01'],['medico_id'=>$prueba->sinCitas]];
    $esperados=[];
    foreach($casos as $i=>$caso){$entrada=array_replace($f,$caso);$r=$http->pedir($ruta.'?'.http_build_query($entrada),'GET',$cookies['administrador']);
        $ok($r['status']===200&&str_starts_with($r['body'],'%PDF-'),'PDF conserva combinación de filtros');
        $datos=$prueba->servicio()->consultar($entrada);$g=$datos['reporte']['general'];$file='http-caso-'.$i.'.pdf';file_put_contents($dir.'/'.$file,$r['body']);
        $esperados[$file]=['desde'=>$datos['f']['desde'],'hasta'=>$datos['f']['hasta'],'conteos'=>array_map(static fn($estado)=>$g[$estado],['total',...array_keys(App\Models\Cita::ESTADOS)])];
    }
    file_put_contents($dir.'/esperados.json',json_encode($esperados,JSON_THROW_ON_ERROR));
    foreach([['desde'=>'2026-02-30'],['desde'=>['x']],['desde'=>'2027-02-01','hasta'=>'2027-01-31'],['estado'=>'borrada'],['medico_id'=>'1 OR 1=1'],['especialidad_id'=>PHP_INT_MAX]] as $caso){$r=$http->pedir($ruta.'?'.http_build_query(array_replace($f,$caso)),'GET',$cookies['administrador']);$ok($r['status']===422&&!str_contains(implode("\n",$r['headers']),'attachment'),'error de filtros sin archivo parcial');}
    $extenso=array_replace($f,['desde'=>'1800-01-01','hasta'=>'1806-01-01']);$q=http_build_query($extenso);
    $r=$http->pedir($ruta.'?'.$q,'GET',$cookies['administrador']);$ok($r['status']===422&&str_contains($r['body'],'Reduce los filtros o utiliza CSV')&&!str_contains(implode("\n",$r['headers']),'attachment'),'límite de recursos PDF controlado sin datos parciales');
    $r=$http->pedir('/reportes/citas/exportar/csv?'.$q,'GET',$cookies['administrador']);$ok($r['status']===200&&str_contains($r['body'],'2001'),'mismo período extenso exportable en CSV');
    $ok($http->pedir('/reportes/citas/exportar/xlsx','GET',$cookies['administrador'])['status']===404,'formato no implementado rechazado');
    $actuales=iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/public',FilesystemIterator::SKIP_DOTS)));
    $ok(array_keys($publicos)===array_keys($actuales),'ningún archivo descargable o temporal público');
    $ok($antes===$b->huella(),'PDF/CSV no alteran las nueve tablas ni crean historial');
}finally{if($http){$http->cerrar();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. PDF HTTP real, permisos, filtros, recursos limitados y PostgreSQL intacto.\n";
