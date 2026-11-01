<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\AgendaRepository;
use App\Repositories\HorarioRepository;
use App\Services\AgendaService;
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;

final class AgendaController extends CatalogoController
{
    public function __construct(){parent::__construct(false,['administrador','recepcionista','medico']);}
    public function mostrar(string $vista): void
    {
        $servicio=new AgendaService(new AgendaRepository($this->pdo),new HorarioRepository($this->pdo),$this->actor);
        try{$datos=$servicio->consultar($vista,$_GET);}
        catch(CatalogoValidacionException $error){
            http_response_code(422);
            $this->pagina('agenda/error','Consulta de agenda no válida',['errores'=>$error->errores]);return;
        }catch(CitaException $error){
            http_response_code($error->estadoHttp);require dirname(__DIR__).'/Views/errors/'.$error->estadoHttp.'.php';return;
        }
        // pagina() reserva $vista para la ruta del archivo; evitar colisión al extraer datos.
        $datos['modoAgenda']=$datos['vista'];unset($datos['vista']);
        $this->pagina('agenda/index','Agenda médica',$datos);
    }
}
