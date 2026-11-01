<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\CitaRepository;
use App\Services\AsistenciaService;
use App\Services\CitaException;

final class AsistenciasController extends CatalogoController
{
    public function __construct(){parent::__construct(false,['administrador','recepcionista','medico']);}
    private function servicio(): AsistenciaService{return new AsistenciaService($this->pdo,new CitaRepository($this->pdo));}
    public function formulario(int $id,string $operacion,array $errores=[]): void
    {
        try{$cita=$this->servicio()->obtener($id,$operacion,(int)$this->actor['id']);}
        catch(CitaException $error){$this->error($error);return;}
        $titulo=AsistenciaService::ACCIONES[$operacion];
        $this->pagina('citas/asistencia',$titulo,compact('cita','operacion','errores'));
    }
    public function procesar(int $id,string $operacion): void
    {
        $this->middleware->exigirCsrf();
        try{
            $this->servicio()->registrar($id,$operacion,$_POST,(int)$this->actor['id']);
            $_SESSION['cu18_confirmacion']=['id'=>$id,'operacion'=>$operacion];
            \redirigir('/citas/'.$id);
        }catch(CitaException $error){
            http_response_code($error->estadoHttp);
            if(in_array($error->estadoHttp,[403,404],true)){$this->error($error);return;}
            $this->formulario($id,$operacion,$error->errores);
        }
    }
    private function error(CitaException $error): void
    {
        http_response_code($error->estadoHttp);
        if(in_array($error->estadoHttp,[403,404],true)){require dirname(__DIR__).'/Views/errors/'.$error->estadoHttp.'.php';return;}
        $this->pagina('citas/asistencia-error','Operación no disponible',['errores'=>$error->errores]);
    }
}
