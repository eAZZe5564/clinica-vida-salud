<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\CitaRepository;
use App\Repositories\DisponibilidadRepository;
use App\Repositories\HorarioRepository;
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
use App\Services\DisponibilidadService;
use App\Services\HorarioService;
use App\Services\ReprogramacionService;

final class ReprogramacionesController extends CatalogoController
{
    public function __construct(){parent::__construct(false,['administrador','recepcionista']);}
    private function servicio(): ReprogramacionService
    {
        $disponibilidad=new DisponibilidadService($this->pdo,new DisponibilidadRepository($this->pdo),new HorarioService($this->pdo,new HorarioRepository($this->pdo)));
        return new ReprogramacionService($this->pdo,new CitaRepository($this->pdo),$disponibilidad);
    }
    public function formulario(int $id,?array $entrada=null,array $errores=[]): void
    {
        try{$cita=$this->servicio()->obtener($id);}
        catch(CitaException $e){$this->error($e);return;}
        $entrada??=$_GET;
        $fecha=is_string($entrada['fecha']??null)?mb_substr($entrada['fecha'],0,20):(new \DateTimeImmutable($cita['fecha_hora_inicio']))->setTimezone(\App\Models\HorarioAtencion::zona())->format('Y-m-d');
        $hora=is_string($entrada['hora_inicio']??null)?mb_substr($entrada['hora_inicio'],0,5):'';
        $disponibles=null;
        try{$disponibles=$this->servicio()->turnos($cita,$fecha);}
        catch(CatalogoValidacionException $e){$errores=array_merge($errores,$e->errores);}
        $hoy=(new \DateTimeImmutable('now',\App\Models\HorarioAtencion::zona()))->format('Y-m-d');
        $this->pagina('citas/reprogramar','Reprogramar cita',compact('cita','fecha','hora','errores','disponibles','hoy'));
    }
    public function procesar(int $id): void
    {
        $this->middleware->exigirCsrf();
        $accion=$_POST['accion']??'';
        if($accion==='actualizar'){$this->formulario($id,$_POST);return;}
        if(!in_array($accion,['revisar','confirmar'],true)){
            http_response_code(422);$this->formulario($id,$_POST,['solicitud'=>'La solicitud no es válida.']);return;
        }
        try{
            if($accion==='revisar'){
                $resumen=$this->servicio()->previsualizar($id,$_POST);
                $this->pagina('citas/reprogramar-revisar','Revisar reprogramación',$resumen);return;
            }
            $this->servicio()->reprogramar($id,$_POST,(int)$this->actor['id']);
            $_SESSION['cu15_confirmacion']=['id'=>$id];
            \redirigir('/citas/' . $id);
        }catch(CitaException $e){
            http_response_code($e->estadoHttp);
            if(in_array($e->estadoHttp,[403,404],true)){$this->error($e);return;}
            $this->formulario($id,$_POST,$e->errores);
        }
    }
    private function error(CitaException $e): void
    {
        http_response_code($e->estadoHttp);
        if(in_array($e->estadoHttp,[403,404],true)){require dirname(__DIR__) . '/Views/errors/' . $e->estadoHttp . '.php';return;}
        $this->pagina('citas/reprogramar-error','Reprogramación no disponible',['errores'=>$e->errores]);
    }
}
