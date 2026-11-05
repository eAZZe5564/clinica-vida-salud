<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Config/bootstrap.php';
use App\Exports\ReporteCitaCsv;
use App\Exports\ReporteCitaDocumento;
$total=0;$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$estados=array_fill_keys(array_keys(App\Models\Cita::ESTADOS),0);
$datos=['f'=>['desde'=>'2026-10-01','hasta'=>'2026-10-31','medico_id'=>'1','especialidad_id'=>'2','estado'=>''],
    'opciones'=>['medicos'=>[['id'=>1,'nombres'=>"Áéíóú ñ Ñ; \"Prueba\"\nlínea",'apellidos'=>'Ficticio']],
        'especialidades'=>[['id'=>2,'nombre'=>'=1+2']]],
    'reporte'=>['general'=>['total'=>3]+array_replace($estados,['atendida'=>2,'cancelada'=>1]),
        'estados'=>array_replace($estados,['atendida'=>2,'cancelada'=>1]),'medicos'=>[],'especialidades'=>[],'dias'=>[]]];
$doc=new ReporteCitaDocumento($datos,new DateTimeImmutable('2026-11-04T04:30:00+00:00'));
$flujo=(new ReporteCitaCsv())->crear($doc);$contenido=stream_get_contents($flujo);
$ok(str_starts_with($contenido,"\xEF\xBB\xBF"),'BOM UTF-8 sin HTML anterior');
$ok(mb_check_encoding($contenido,'UTF-8')&&str_contains($contenido,'Áéíóú ñ Ñ'),'español UTF-8 conservado');
$ok(str_contains($contenido,"\r\n"),'registros CRLF');
rewind($flujo);fread($flujo,3);$filas=[];while(($fila=fgetcsv($flujo,0,';','"',''))!==false){$filas[]=$fila;}fclose($flujo);
$ok(count(array_filter($filas,fn($r)=>count($r)!==9))===0,'nueve columnas incluso con comillas y saltos');
$por=static fn(string $s,string $c)=>array_values(array_filter($filas,fn($r)=>$r[0]===$s&&$r[1]===$c))[0];
$ok($por('Filtro','Médico')[2]==="Ficticio, Áéíóú ñ Ñ; \"Prueba\"\nlínea",'round-trip de delimitadores, comillas y salto interno');
$ok($por('Filtro','Especialidad')[2]==="\t'=1+2",'texto de catálogo neutralizado');
$ok($por('Información','Generado en America/La_Paz')[2]==='2026-11-04 00:30:00 -04:00','hora de generación de la clínica');
$ok($por('Filtro','Fecha inicial')[2]==='2026-10-01'&&$por('Filtro','Fecha final inclusive')[2]==='2026-10-31','no cambia período al mes de generación');
$ok($por('General','Total de citas')[2]==='3'&&$por('General','Total de citas')[6]==='2','conteos de CU21 sin alterar números');
$ok($por('Estado','Cancelada')[2]==='1'&&$por('Situación actual','Inasistencias')[2]==='0','estados y asistencia del mismo resultado');
$ok($doc->nombre('csv')==='clinica-vida-salud-reporte-citas-2026-10-01-a-2026-10-31.csv','nombre ASCII solo con fechas validadas');
try{$doc->nombre("csv\r\nX-Injected: 1");$ok(false,'formato rechazado');}catch(InvalidArgumentException){$ok(true,'formato de nombre rechazado');}
foreach(['=1+2','+1','-1','@SUM(1)',"\t=1", "\r=1", "\n=1", " \t=1","  =1","\x00=1","\x7F=1","\u{00A0}=1","\u{FEFF}=1","\u{200B}=1",'＝1','＋1','－1','＠SUM(1)'] as $ataque){
    $valor=ReporteCitaCsv::celda($ataque);$ok($valor==="\t'".$ataque,'texto de fórmula/control neutralizado');
    $csv=fopen('php://temp','w+b');fputcsv($csv,[$valor],';','"','',"\r\n");rewind($csv);$raw=stream_get_contents($csv);rewind($csv);
    $ok(str_starts_with($raw,'"')&&fgetcsv($csv,0,';','"','')===[$valor],'fórmula permanece dentro de una sola celda citada');fclose($csv);
}
foreach(['Núñez','Cardiología','Texto;=1+2','Texto "doble"',"Línea\n=1",'2026-10-01',''] as $normal){$ok(ReporteCitaCsv::celda($normal)===$normal,'texto normal conservado');}
foreach([0,42,-12,3.5] as $numero){$ok(ReporteCitaCsv::celda($numero)===$numero,'número legítimo sin prefijo');}
$vacio=$datos;$vacio['reporte']['general']=['total'=>0]+$estados;$vacio['reporte']['estados']=$estados;
$stream=(new ReporteCitaCsv())->crear(new ReporteCitaDocumento($vacio));$texto=stream_get_contents($stream);fclose($stream);
$ok(str_contains($texto,'No hay citas para el período'),'CSV vacío conserva metadatos y mensaje');
echo "Aprobadas: $total. CSV nativo real, UTF-8/Excel estructural, fórmulas y round-trip; sin PostgreSQL.\n";
