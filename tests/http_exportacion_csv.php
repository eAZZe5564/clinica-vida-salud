<?php
declare(strict_types=1);
require __DIR__.'/ReportesPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$prueba=null;$http=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$leer=static function(string $csv):array{
    if(!str_starts_with($csv,"\xEF\xBB\xBF")){throw new RuntimeException('Descarga CSV inválida.');}
    $s=fopen('php://temp','w+b');fwrite($s,substr($csv,3));rewind($s);$filas=[];while(($r=fgetcsv($s,0,';','"',''))!==false){$filas[]=$r;}fclose($s);return $filas;
};
try{
    $prueba=new ReportesPrueba($pdo);$b=$prueba->base;$http=new HttpCatalogo();$cookies=[];
    foreach(['administrador','recepcionista','medico','paciente'] as $rol){$login=$http->pedir('/login');$r=$http->pedir('/login','POST',$login['cookie'],['_csrf'=>HttpCatalogo::csrf($login['body']),'correo'=>$b->actores[$rol]['correo'],'contrasena'=>'Ficticia91!'.$b->t->fixture->sufijo]);$ok($r['status']===303,'login ficticio '.$rol);$cookies[$rol]=$r['cookie'];}
    $antes=$b->huella();$url='/reportes/citas/exportar/csv';$f=$prueba->filtros();$query=http_build_query($f);
    foreach(['GET','POST','PUT','DELETE'] as $metodo){
        $ok($http->pedir($url.'?'.$query,$metodo)['status']===303,'sin sesión no descarga '.$metodo);
        foreach(['medico','paciente'] as $rol){$r=$http->pedir($url.'?'.$query,$metodo,$cookies[$rol]);$ok($r['status']===403&&!str_starts_with($r['body'],"\xEF\xBB\xBF"),'rol sin acceso '.$rol.' '.$metodo);}
    }
    foreach(['administrador','recepcionista'] as $rol){
        $r=$http->pedir($url.'?'.$query,'GET',$cookies[$rol]);$ok($r['status']===200,'descarga autorizada '.$rol);
        $headers=implode("\n",$r['headers']);
        $ok(str_contains($headers,'Content-Type: text/csv; charset=UTF-8'),'tipo CSV UTF-8 '.$rol);
        $ok(preg_match('/Content-Disposition: attachment; filename="clinica-vida-salud-reporte-citas-[0-9-]+-a-[0-9-]+\.csv"/',$headers)===1,'attachment y nombre seguro '.$rol);
        $ok(str_contains($headers,'private, no-store')&&str_contains($headers,'X-Content-Type-Options: nosniff'),'sin caché y sin sniff '.$rol);
        $filas=$leer($r['body']);$general=array_values(array_filter($filas,fn($r)=>$r[0]==='General'))[0];
        $pantalla=$http->pedir('/reportes/citas?'.$query,'GET',$cookies[$rol]);preg_match('/data-reporte-indicador="total">([0-9]+)/',$pantalla['body'],$m);
        $ok($general[2]===$m[1]&&$general[2]==='8','CSV coincide con CU21 '.$rol);
        $estados=array_filter($filas,fn($r)=>$r[0]==='Estado');$ok(array_sum(array_column($estados,2))===8,'suma de seis estados igual al total '.$rol);
        foreach(['Médico','Especialidad','Día'] as $seccion){$ok(array_sum(array_column(array_filter($filas,fn($r)=>$r[0]===$seccion),2))===8,'subtotal de sección '.$rol.' '.$seccion);}
        foreach(['MOTIVO_CLINICO_PRIVADO_CU19','DIRECCION_PRIVADA_CU19','CI-PRIVADO-','hash_contrasena','Paciente <b>'] as $privado){$ok(!str_contains($r['body'],$privado),'sin dato personal '.$rol);}
        foreach(['POST','PUT','DELETE'] as $metodo){$ok($http->pedir($url.'?'.$query,$metodo,$cookies[$rol])['status']===405,'solo lectura '.$rol.' '.$metodo);}
    }
    $casos=[['estado'=>'cancelada'],['estado'=>'inasistencia'],['estado'=>'atendida','especialidad_id'=>$b->especialidad],
        ['desde'=>'2010-01-01','hasta'=>'2010-01-01'],['desde'=>'2020-12-31','hasta'=>'2021-01-01'],['medico_id'=>$prueba->sinCitas]];
    foreach($casos as $caso){$entrada=array_replace($f,$caso);$r=$http->pedir($url.'?'.http_build_query($entrada),'GET',$cookies['administrador']);$filas=$leer($r['body']);
        $esperado=$prueba->servicio()->consultar($entrada)['reporte']['general']['total'];$actual=array_values(array_filter($filas,fn($r)=>$r[0]==='General'))[0][2];
        $ok($r['status']===200&&(int)$actual===$esperado,'filtros reutilizados y datos equivalentes');}
    foreach([['desde'=>'2026-02-30'],['desde'=>['x']],['desde'=>'2027-02-01','hasta'=>'2027-01-31'],['estado'=>'desconocido'],['medico_id'=>'1 OR 1=1'],['especialidad_id'=>PHP_INT_MAX]] as $caso){
        $r=$http->pedir($url.'?'.http_build_query(array_replace($f,$caso)),'GET',$cookies['administrador']);$ok($r['status']===422&&!str_starts_with($r['body'],"\xEF\xBB\xBF"),'filtro inválido produce HTML controlado sin attachment');
    }
    $ok($antes===$b->huella(),'descargas y métodos rechazados no escriben en las nueve tablas');
}finally{if($http){$http->cerrar();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. CSV HTTP real, permisos, filtros, cabeceras, CU21 y datos intactos.\n";
