<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\CitaRepository;
use App\Services\CancelacionService;
use App\Services\CitaException;

final class CancelacionesController extends CatalogoController
{
    public function __construct(){parent::__construct(false,['administrador','recepcionista']);}
    private function servicio(): CancelacionService{return new CancelacionService($this->pdo,new CitaRepository($this->pdo));}

    public function formulario(int $id,array $errores=[],string $motivo=''): void
    {
        try{$cita=$this->servicio()->obtener($id);}
        catch(CitaException $e){$this->error($e);return;}
        $this->pagina('citas/cancelar','Cancelar cita',compact('cita','errores','motivo'));
    }
    public function procesar(int $id): void
    {
        $this->middleware->exigirCsrf();
        try{
            $this->servicio()->cancelar($id,$_POST,(int)$this->actor['id']);
            $_SESSION['cu16_confirmacion']=['id'=>$id];
            \redirigir('/citas/'.$id);
        }catch(CitaException $e){
            http_response_code($e->estadoHttp);
            if(in_array($e->estadoHttp,[403,404],true)){$this->error($e);return;}
            $valor=$_POST['motivo_cancelacion']??'';
            $motivo=is_string($valor)&&mb_check_encoding($valor,'UTF-8')?mb_substr($valor,0,250):'';
            $this->formulario($id,$e->errores,$motivo);
        }
    }
    private function error(CitaException $e): void
    {
        http_response_code($e->estadoHttp);
        if(in_array($e->estadoHttp,[403,404],true)){require dirname(__DIR__).'/Views/errors/'.$e->estadoHttp.'.php';return;}
        $this->pagina('citas/cancelar-error','Cancelación no disponible',['errores'=>$e->errores]);
    }
}
