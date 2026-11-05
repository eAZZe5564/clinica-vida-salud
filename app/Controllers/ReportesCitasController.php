<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\CitaRepository;
use App\Repositories\HorarioRepository;
use App\Repositories\ReporteCitaRepository;
use App\Services\ConsultaCitaService;
use App\Services\ReporteCitaService;
use App\Services\CatalogoValidacionException;

final class ReportesCitasController extends CatalogoController
{
    public function __construct(){parent::__construct(false,['administrador','recepcionista']);}
    private function consultar(): array
    {
        $servicio=new ReporteCitaService(new ReporteCitaRepository($this->pdo),
            new ConsultaCitaService(new CitaRepository($this->pdo),new HorarioRepository($this->pdo),$this->actor),$this->actor);
        return $servicio->consultar($_GET);
    }
    public function mostrar(): void
    {
        try{$datos=$this->consultar();}
        catch(CatalogoValidacionException $error){
            http_response_code(422);$this->pagina('reportes/error','Revisa los filtros',['errores'=>$error->errores]);return;
        }
        $this->pagina('reportes/citas','Reportes de citas médicas',$datos);
    }
    public function exportar(string $formato): void
    {
        $flujo=null;
        try{
            $documento=new \App\Exports\ReporteCitaDocumento($this->consultar());
            $nombre=$documento->nombre($formato);
            if($formato==='csv'){$flujo=(new \App\Exports\ReporteCitaCsv())->crear($documento);}
            else{$pdf=(new \App\Exports\ReporteCitaPdf())->crear($documento);}
        }catch(CatalogoValidacionException $error){
            http_response_code(422);$this->pagina('reportes/error','Revisa los filtros',['errores'=>$error->errores]);return;
        }catch(\App\Exports\ReportePdfLimiteException $error){
            http_response_code(422);$this->pagina('reportes/error','Reporte extenso',['errores'=>['exportacion'=>$error->getMessage()]]);return;
        }catch(\Throwable){
            error_log('No se pudo generar la exportación de citas.');
            http_response_code(500);$this->pagina('reportes/error','Exportación no disponible',['errores'=>['exportacion'=>'No se pudo generar el archivo. Intenta nuevamente.']]);return;
        }
        try{
            header('Content-Type: '.($formato==='csv'?'text/csv; charset=UTF-8':'application/pdf'));header('Content-Disposition: attachment; filename="'.$nombre.'"');
            header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');
            if($formato==='csv'){$estado=fstat($flujo);if($estado!==false){header('Content-Length: '.$estado['size']);}fpassthru($flujo);}
            else{header('Content-Length: '.strlen($pdf));echo $pdf;}
        }finally{if(is_resource($flujo)){fclose($flujo);}}
    }
}
