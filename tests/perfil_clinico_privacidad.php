<?php
declare(strict_types=1);
require __DIR__.'/PerfilClinicoPrueba.php';
use App\Repositories\ReporteCitaRepository;
use App\Repositories\CitaRepository;
use App\Repositories\HorarioRepository;
use App\Services\ConsultaCitaService;
use App\Services\ReporteCitaService;
use App\Exports\ReporteCitaDocumento;
use App\Exports\ReporteCitaCsv;
use App\Exports\ReporteCitaPdf;
$pdo=cu15Conexion();$prueba=null;$total=0;
try{
    $prueba=new PerfilClinicoPrueba($pdo);$actor=['id'=>$prueba->actores['administrador']['id'],'rol'=>'administrador'];
    $reporte=new ReporteCitaService(new ReporteCitaRepository($pdo),new ConsultaCitaService(new CitaRepository($pdo),new HorarioRepository($pdo),$actor),$actor);
    $hoy=(new DateTimeImmutable('today',App\Models\HorarioAtencion::zona()))->format('Y-m-d');$f=['desde'=>$hoy,'hasta'=>$hoy,'medico_id'=>(string)$prueba->medico];
    $antes=$reporte->consultar($f);$marcador='CU23_PRIVADO_FICTICIO_'.bin2hex(random_bytes(6));$s=$prueba->servicio();$p=$prueba->paciente;
    $s->guardarPerfil($p,$prueba->perfil(['observaciones'=>$marcador]));$id=$s->guardarAlergia($p,$prueba->alergia(['sustancia'=>$marcador,'reaccion'=>$marcador,'observaciones'=>$marcador]));
    $s->guardarAlergia($p,$prueba->cambio($id,['reaccion'=>$marcador.'-2']),$id);$despues=$reporte->consultar($f);
    clinicoAssert($antes['reporte']===$despues['reporte'],'CU21 conserva exactamente los cálculos después de cambios clínicos');
    clinicoAssert(!str_contains(json_encode($despues,JSON_THROW_ON_ERROR),$marcador),'resultado y catálogos CU21 sin datos clínicos');
    $doc=new ReporteCitaDocumento($despues);$stream=(new ReporteCitaCsv())->crear($doc);$csv=stream_get_contents($stream);fclose($stream);
    clinicoAssert(!str_contains($csv,$marcador)&&str_starts_with($csv,"\xEF\xBB\xBF"),'CSV administrativo real no exporta valores clínicos');
    $exportador=new ReporteCitaPdf();$html=$exportador->html($doc);$pdf=$exportador->crear($doc);
    clinicoAssert(!str_contains($html,$marcador)&&str_starts_with($pdf,'%PDF-'),'plantilla y PDF administrativo sin inclusión clínica');
    $q=$pdo->prepare('SELECT count(*) FROM citas_historial WHERE cita_id=:id');$q->execute(['id'=>$prueba->citas['propia']]);clinicoAssert((int)$q->fetchColumn()===0,'cambios clínicos no inventan eventos de citas');
    try{$s->guardarAlergia($p,$prueba->alergia(['sustancia'=>$marcador]));throw new RuntimeException('Duplicado no rechazado.');}
    catch(App\Services\PerfilClinicoException $e){
        clinicoAssert($e->estadoHttp===409&&!str_contains($e->getMessage(),$marcador)&&!str_contains(json_encode($e->errores),$marcador),'excepciones y mensajes de conflicto no incluyen valores clínicos');
        clinicoAssert($e->getPrevious()===null,'excepción pública no arrastra una excepción PDO con parámetros');
    }
    $q=$pdo->prepare('SELECT count(*) FROM alergias_historial WHERE alergia_id=:a');$q->execute(['a'=>$id]);clinicoAssert((int)$q->fetchColumn()===2,'solo revisiones autorizadas sin evento del intento fallido');
}finally{if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. Privacidad CU21/CU22, errores y separación de auditoría con datos inventados.\n";
