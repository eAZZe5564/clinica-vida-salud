<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Config/bootstrap.php';
use App\Exports\ReporteCitaDocumento;
use App\Exports\ReporteCitaPdf;
$total=0;$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$estados=array_fill_keys(array_keys(App\Models\Cita::ESTADOS),0);
$datos=['f'=>['desde'=>'2026-01-01','hasta'=>'2026-12-31','medico_id'=>'','especialidad_id'=>'','estado'=>''],
    'opciones'=>['medicos'=>[],'especialidades'=>[]],
    'reporte'=>['general'=>['total'=>130]+array_replace($estados,['atendida'=>130]),
        'estados'=>array_replace($estados,['atendida'=>130]),'medicos'=>[],'especialidades'=>[],'dias'=>[]]];
$fila=['total'=>1]+array_replace($estados,['atendida'=>1]);
for($i=0;$i<130;++$i){$dia=(new DateTimeImmutable('2026-01-01'))->modify('+'.$i.' days')->format('Y-m-d');$datos['reporte']['dias'][]=['etiqueta'=>$dia]+$fila;}
$datos['reporte']['medicos'][]=['etiqueta'=>'ÁÉÍÓÚ áéíóú ñ Ñ — Médico ficticio con nombre largo '.str_repeat('N',100)]+$datos['reporte']['general'];
$datos['reporte']['especialidades'][]=['etiqueta'=>'Especialidad "ficticia"; Niño & Niña <script>PDF_XSS</script>']+$datos['reporte']['general'];
$doc=new ReporteCitaDocumento($datos,new DateTimeImmutable('2026-11-04T04:30:00+00:00'));
$exportador=new ReporteCitaPdf();$html=$exportador->html($doc);
$ok(str_contains($html,'&lt;script&gt;PDF_XSS&lt;/script&gt;')&&!str_contains($html,'<script>PDF_XSS</script>'),'datos escapados en HTML PDF');
$ok(str_contains($html,'table-header-group')&&str_contains($html,'page-break-inside: avoid'),'tablas con encabezados repetidos y filas sin cortes');
$antes=glob(sys_get_temp_dir().'/clinica-cu22-*');$nivel=ob_get_level();$pdf=$exportador->crear($doc);
$ok(str_starts_with($pdf,'%PDF-')&&str_contains(substr($pdf,-1024),'%%EOF'),'PDF binario real completo');
$ok(ob_get_level()===$nivel,'buffer de salida restaurado');
$ok($antes===glob(sys_get_temp_dir().'/clinica-cu22-*'),'temporales PDF retirados');
$dir=__DIR__.'/artifacts/cu22';if(!is_dir($dir)){mkdir($dir,0700,true);}file_put_contents($dir.'/multipagina.pdf',$pdf);
$vacio=$datos;$vacio['reporte']=['general'=>['total'=>0]+$estados,'estados'=>$estados,'medicos'=>[],'especialidades'=>[],'dias'=>[]];
file_put_contents($dir.'/vacio.pdf',$exportador->crear(new ReporteCitaDocumento($vacio)));
$ok(is_file($dir.'/vacio.pdf'),'PDF sin registros generado');
$limite=$datos;$limite['reporte']['dias']=array_fill(0,ReporteCitaPdf::MAX_FILAS+1,['etiqueta'=>'2026-01-01']+$fila);
try{$exportador->crear(new ReporteCitaDocumento($limite));$ok(false,'debía rechazar exceso');}catch(App\Exports\ReportePdfLimiteException){$ok(true,'límite PDF rechaza sin exportar datos parciales');}
$codigo=file_get_contents(dirname(__DIR__).'/app/Exports/ReporteCitaPdf.php');
$ok(str_contains($codigo,'setIsRemoteEnabled(false)')&&str_contains($codigo,'setAllowedProtocols([])'),'recursos remotos y protocolos desactivados');
$ok(str_contains($codigo,'setIsPhpEnabled(false)')&&str_contains($codigo,'setIsJavascriptEnabled(false)'),'PHP y JavaScript embebidos desactivados');
$ok(!str_contains($pdf,'MOTIVO_CLINICO_PRIVADO'),'sin datos clínicos');
echo "Aprobadas: $total. PDF Dompdf real; documentos ficticios privados para lectura y render de prueba.\n";
