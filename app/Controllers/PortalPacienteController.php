<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{PortalPacienteService,CitaException,CatalogoValidacionException};

final class PortalPacienteController extends CatalogoController
{
    private PortalPacienteService $servicio;
    public function __construct()
    {
        parent::__construct(false,['paciente']);
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        $this->servicio=new PortalPacienteService($this->pdo,(int)$this->actor['id']);
    }
    public function mostrar(?int $id=null,string $accion=''): void
    {
        try{
            if($accion==='nueva'||in_array($accion,['reprogramar','cancelar'],true)){
                $datos=$this->servicio->formulario($_GET,$id,$accion);
                $this->pagina('portal/formulario',$accion==='nueva'?'Reservar cita':ucfirst($accion).' cita',$datos);
            }elseif($id!==null){
                $this->pagina('portal/detalle','Mi cita',$this->servicio->detalle($id,$_GET));
            }else{
                $this->pagina('portal/index',$accion==='historial'?'Mi historial de citas':'Mis citas',
                    $this->servicio->listar($_GET,$accion==='historial')+['historico'=>$accion==='historial']);
            }
        }catch(CitaException|CatalogoValidacionException $e){$this->error($e);}
    }
    public function guardar(?int $id,string $accion): void
    {
        $this->middleware->exigirCsrf();
        try{
            PortalPacienteService::parametros($_GET);
            $resultado=$id===null?$this->servicio->reservar($_POST):$this->servicio->cambiar($id,$accion,$_POST);
            $_SESSION['portal_confirmacion']=(int)$resultado['id'];
            \redirigir('/mis-citas/'.(int)$resultado['id']);
        }catch(CitaException|CatalogoValidacionException $e){$this->error($e);}
    }
    private function error(CitaException|CatalogoValidacionException $e): void
    {
        http_response_code($e instanceof CitaException?$e->estadoHttp:422);
        $this->pagina('portal/error','Operación no disponible',['errores'=>$e->errores]);
    }
}
