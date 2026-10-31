<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\CitaRepository;
use App\Services\ConfirmacionService;
use App\Services\CitaException;

final class ConfirmacionesController extends CatalogoController
{
    public function __construct(){parent::__construct(false,['administrador','recepcionista']);}
    private function servicio(): ConfirmacionService{return new ConfirmacionService($this->pdo,new CitaRepository($this->pdo));}
    public function formulario(int $id,array $errores=[]): void
    {
        try{$cita=$this->servicio()->obtener($id);}
        catch(CitaException $e){$this->error($e);return;}
        $this->pagina('citas/confirmar','Confirmar cita',compact('cita','errores'));
    }
    public function procesar(int $id): void
    {
        $this->middleware->exigirCsrf();
        try{
            $this->servicio()->confirmar($id,$_POST,(int)$this->actor['id']);
            $_SESSION['cu17_confirmacion']=['id'=>$id];
            \redirigir('/citas/'.$id);
        }catch(CitaException $e){
            http_response_code($e->estadoHttp);
            if(in_array($e->estadoHttp,[403,404],true)){$this->error($e);return;}
            $this->formulario($id,$e->errores);
        }
    }
    private function error(CitaException $e): void
    {
        http_response_code($e->estadoHttp);
        if(in_array($e->estadoHttp,[403,404],true)){require dirname(__DIR__).'/Views/errors/'.$e->estadoHttp.'.php';return;}
        $this->pagina('citas/confirmar-error','Confirmación no disponible',['errores'=>$e->errores]);
    }
}
