<?php
declare(strict_types=1);
require '/var/www/clinica/app/Config/bootstrap.php';
use App\Exports\ReporteCitaDocumento;
use App\Exports\ReporteCitaPdf;
use App\Exports\ReporteCitaCsv;
use App\Models\Cita;
$states=array_fill_keys(array_keys(Cita::ESTADOS),0);$states['atendida']=1;$row=['total'=>1]+$states;
$data=['f'=>['desde'=>'2026-01-01','hasta'=>'2026-01-01','medico_id'=>'','especialidad_id'=>'','estado'=>''],
    'opciones'=>['medicos'=>[],'especialidades'=>[]],
    'reporte'=>['general'=>$row,'estados'=>$states,'medicos'=>[['etiqueta'=>'Médico ficticio ÁÉÍÓÚ Ñ']+$row],
        'especialidades'=>[['etiqueta'=>'Especialidad ficticia']+$row],'dias'=>[['etiqueta'=>'2026-01-01']+$row]]];
if(!is_writable('/var/lib/clinica/sessions')||!is_writable(sys_get_temp_dir())){throw new RuntimeException('Permisos incorrectos');}
$doc=new ReporteCitaDocumento($data);$pdf=(new ReporteCitaPdf())->crear($doc);
if(!str_starts_with($pdf,'%PDF-')||!str_contains(substr($pdf,-1024),'%%EOF')){throw new RuntimeException('PDF incompleto');}
$stream=(new ReporteCitaCsv())->crear($doc);$csv=stream_get_contents($stream);fclose($stream);
if(!is_string($csv)||!str_starts_with($csv,"\xEF\xBB\xBF")||!mb_check_encoding($csv,'UTF-8')||!str_contains($csv,'Médico ficticio ÁÉÍÓÚ Ñ')){throw new RuntimeException('CSV incorrecto');}
echo json_encode(['pdf'=>'valid','pdf_bytes'=>strlen($pdf),'csv'=>'valid','csv_bytes'=>strlen($csv),'data'=>'fictional','postgresql'=>false],JSON_THROW_ON_ERROR)."\n";
