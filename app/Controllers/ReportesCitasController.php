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
    public function mostrar(): void
    {
        $servicio=new ReporteCitaService(new ReporteCitaRepository($this->pdo),
            new ConsultaCitaService(new CitaRepository($this->pdo),new HorarioRepository($this->pdo),$this->actor),$this->actor);
        try{$datos=$servicio->consultar($_GET);}
        catch(CatalogoValidacionException $error){
            http_response_code(422);$this->pagina('reportes/error','Revisa los filtros',['errores'=>$error->errores]);return;
        }
        $this->pagina('reportes/citas','Reportes de citas médicas',$datos);
    }
}
