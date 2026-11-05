<?php
declare(strict_types=1);
require __DIR__.'/RegistroClinicoPrueba.php';
$pdo=cu15Conexion();$f=null;$total=0;
try{
    $f=new PerfilClinicoPrueba($pdo);$actor=['id'=>$f->actores['administrador']['id'],'rol'=>'administrador'];
    $consulta=new App\Services\ConsultaCitaService(new App\Repositories\CitaRepository($pdo),new App\Repositories\HorarioRepository($pdo),$actor);
    $reportes=new App\Services\ReporteCitaService(new App\Repositories\ReporteCitaRepository($pdo),$consulta,$actor);
    $agenda=new App\Services\AgendaService(new App\Repositories\AgendaRepository($pdo),new App\Repositories\HorarioRepository($pdo),$actor);
    $historial=new App\Services\HistorialPacienteService(new App\Repositories\HistorialPacienteRepository($pdo),$consulta);
    $hoy=(new DateTimeImmutable('today',App\Models\HorarioAtencion::zona()))->format('Y-m-d');$filtros=['desde'=>$hoy,'hasta'=>$hoy,'medico_id'=>(string)$f->medico];
    $antes=$reportes->consultar($filtros);$aAntes=$agenda->consultar('diaria',['fecha'=>$hoy,'medico_id'=>$f->medico]);$hAntes=$historial->consultar($f->paciente,[]);
    $huella=cu24Huella($pdo);$marca='CU24_PRIVADO_FICTICIO_'.bin2hex(random_bytes(6));$s=cu24Servicio($f);
    foreach(['antecedentes','laboratorios'] as $tipo){$campo=$tipo==='antecedentes'?'descripcion':'resultado_texto';$id=$s->guardar($f->paciente,$tipo,cu24Entrada($tipo,[$campo=>$marca,'observaciones'=>$marca]));$s->guardar($f->paciente,$tipo,cu24Cambio($f,$tipo,$id,[$campo=>$marca.'-2','motivo_cambio'=>$marca]),$id);}
    $despues=$reportes->consultar($filtros);$aDespues=$agenda->consultar('diaria',['fecha'=>$hoy,'medico_id'=>$f->medico]);$hDespues=$historial->consultar($f->paciente,[]);
    clinicoAssert($antes['reporte']===$despues['reporte'],'CU21 conserva exactamente cifras y filtros');
    clinicoAssert($aAntes==$aDespues,'agenda CU19 no cambia por información CU24');clinicoAssert($hAntes['resultado']===$hDespues['resultado'],'historial administrativo CU20 conserva filas y cifras');
    foreach([$despues,$aDespues,$hDespues] as $datos){clinicoAssert(!str_contains(json_encode($datos,JSON_THROW_ON_ERROR),$marca),'proyecciones administrativas sin información CU24');}
    $doc=new App\Exports\ReporteCitaDocumento($despues);$csv=(new App\Exports\ReporteCitaCsv())->crear($doc);$contenido=stream_get_contents($csv);fclose($csv);
    clinicoAssert(str_starts_with($contenido,"\xEF\xBB\xBF")&&!str_contains($contenido,$marca),'CSV administrativo real sin antecedentes o resultados');
    $exportador=new App\Exports\ReporteCitaPdf();$pdf=$exportador->crear($doc);$html=$exportador->html($doc);
    clinicoAssert(str_starts_with($pdf,'%PDF-')&&!str_contains($html,$marca),'PDF real y plantilla no reciben información CU24');
    $fallo=cu24Servicio($f,pdo:new RegistroClinicoFalloPDO($pdo));
    foreach(['antecedentes','laboratorios'] as $tipo){
        try{$fallo->guardar($f->paciente,$tipo,cu24Entrada($tipo,['observaciones'=>$marca]));throw new RuntimeException('No falló auditoría ficticia.');}
        catch(App\Services\PerfilClinicoException $e){clinicoAssert($e->estadoHttp===503&&!str_contains($e->getMessage(),$marca)&&$e->getPrevious()===null,'error público no imprime valores ni encadena PDO');}
    }
    $despuesHuella=cu24Huella($pdo);foreach(['antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial'] as $t){unset($huella[$t],$despuesHuella[$t]);}
    clinicoAssert($huella===$despuesHuella,'CU24 no modifica las doce tablas anteriores ni alergias');
    clinicoAssert($f->servicio()->consultar($f->paciente)['perfil']===null,'antecedentes y estudios no crean perfiles o alergias implícitos');
}finally{cu24Limpiar($f);}
echo "Aprobadas: $total. Privacidad CU19–CU22, PDF/CSV reales, errores y separación de CU23.\n";
