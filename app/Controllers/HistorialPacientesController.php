<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\CitaRepository;
use App\Repositories\HorarioRepository;
use App\Repositories\HistorialPacienteRepository;
use App\Services\ConsultaCitaService;
use App\Services\HistorialPacienteService;
use App\Services\CatalogoValidacionException;
use App\Services\CitaException;

final class HistorialPacientesController extends CatalogoController
{
    public function __construct(){parent::__construct(false,['administrador','recepcionista','medico']);}
    public function mostrar(?int $pacienteId=null,?int $citaId=null): void
    {
        $consulta=new ConsultaCitaService(new CitaRepository($this->pdo),new HorarioRepository($this->pdo),$this->actor);
        $servicio=new HistorialPacienteService(new HistorialPacienteRepository($this->pdo),$consulta);
        try{
            if($pacienteId===null){$datos=$servicio->buscar($_GET);$archivo='buscar';}
            elseif($citaId===null){$datos=$servicio->consultar($pacienteId,$_GET);$archivo='index';}
            else{$datos=$servicio->eventos($pacienteId,$citaId,$_GET);$archivo='eventos';}
        }catch(CatalogoValidacionException $error){
            http_response_code(422);$this->pagina('historial/error','Revisa los filtros',['errores'=>$error->errores]);return;
        }catch(CitaException $error){http_response_code($error->estadoHttp);require dirname(__DIR__).'/Views/errors/'.$error->estadoHttp.'.php';return;}
        $this->pagina('historial/'.$archivo,'Historial de citas',$datos);
    }
}
