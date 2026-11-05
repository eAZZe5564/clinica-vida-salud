<?php
declare(strict_types=1);
require __DIR__.'/ReportesPrueba.php';
use App\Exports\ReporteCitaDocumento;
use App\Exports\ReporteCitaCsv;
use App\Exports\ReporteCitaPdf;
$pdo=cu15Conexion();$prueba=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
try{
    $prueba=new ReportesPrueba($pdo);$b=$prueba->base;
    $pdo->prepare("UPDATE medicos SET apellidos='=1+2',nombres=:n WHERE id=:id")->execute(['n'=>"=1+2; \"Niño\"",'id'=>$b->medico]);
    $pdo->prepare('UPDATE especialidades SET nombre=:n WHERE id=:id')->execute(['n'=>"\t@SUM(1); Ñandú",'id'=>$b->especialidad]);
    $antes=$b->huella();$pdo->beginTransaction();$pdo->exec('SET TRANSACTION READ ONLY');$spy=new AgendaLecturaPDO($pdo);
    $datos=$prueba->servicio('administrador',$spy)->consultar($prueba->filtros());$ok(count($spy->consultas)===3,'solo los tres SELECT existentes de CU21');
    $doc=new ReporteCitaDocumento($datos);$stream=(new ReporteCitaCsv())->crear($doc);fread($stream,3);$filas=[];
    while(($fila=fgetcsv($stream,0,';','"',''))!==false){$filas[]=$fila;}fclose($stream);
    foreach(['medicos'=>'Médico','especialidades'=>'Especialidad','dias'=>'Día'] as $grupo=>$seccion){
        $actual=array_values(array_filter($filas,fn($f)=>$f[0]===$seccion));$esperado=$datos['reporte'][$grupo];
        $ok(count($actual)===count($esperado),'mismas filas agregadas '.$grupo);
        foreach($esperado as $i=>$fila){
            $conteos=[$fila['total'],$fila['programada'],$fila['confirmada'],$fila['presente'],$fila['atendida'],$fila['inasistencia'],$fila['cancelada']];
            $ok(array_map('intval',array_slice($actual[$i],2))===$conteos,'siete cifras idénticas '.$grupo);
            $ok($actual[$i][1]===ReporteCitaCsv::celda($fila['etiqueta']),'nombre real conservado y protegido '.$grupo);
        }
    }
    $pdfExport=new ReporteCitaPdf();$html=$pdfExport->html($doc);$pdf=$pdfExport->crear($doc);
    $ok(str_starts_with($pdf,'%PDF-'),'PDF real con los mismos datos PostgreSQL');
    $ok(count($spy->consultas)===3,'exportadores no consultan por fila ni vuelven a calcular');
    $ok(str_contains($html,'&quot;Niño&quot;')&&!str_contains($html,'<script>'),'nombres escapados también en PDF');
    $ok($datos['reporte']['general']['total']===8&&array_sum($datos['reporte']['estados'])===8,'total y estados sin contar historial CU15');
    $ok($pdo->query('SHOW transaction_read_only')->fetchColumn()==='on','ambos exportadores funcionan en READ ONLY');
    $pdo->rollBack();$ok($antes===$b->huella(),'ninguna de las nueve tablas cambió');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. CSV/PDF del mismo resultado CU21, PDO vigilado y READ ONLY.\n";
