<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Repositories\PerfilClinicoRepository;
use App\Services\PerfilClinicoService;
use App\Services\PerfilClinicoException;

final class PerfilesClinicosController extends CatalogoController
{
    private PerfilClinicoService $servicio;
    public function __construct()
    {
        header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');
        parent::__construct(false,['medico']);
        $this->servicio=new PerfilClinicoService($this->pdo,new PerfilClinicoRepository($this->pdo),(int)$this->actor['id']);
    }
    private function error(PerfilClinicoException $e): void
    {
        http_response_code($e->estadoHttp);
        if(in_array($e->estadoHttp,[403,404],true)){require dirname(__DIR__).'/Views/errors/'.$e->estadoHttp.'.php';return;}
        $this->pagina('clinico/error','Operación clínica no completada',['errores'=>$e->errores]);
    }
    public function mostrar(int $paciente,string $vista,?int $alergia=null): void
    {
        try{$datos=$this->servicio->consultar($paciente,$alergia);}
        catch(PerfilClinicoException $e){$this->error($e);return;}
        $datos['errores']=[];
        $this->pagina('clinico/'.$vista,'Perfil clínico y alergias',$datos);
    }
    public function guardar(int $paciente,string $vista,?int $alergia=null): void
    {
        $this->middleware->exigirCsrf();
        try{
            if($vista==='perfil'){$this->servicio->guardarPerfil($paciente,$_POST);}
            else{$this->servicio->guardarAlergia($paciente,$_POST,$alergia);}
            \redirigir('/pacientes/'.$paciente.'/'.($vista==='perfil'?'perfil-clinico':'alergias').'?guardado=1');
        }catch(PerfilClinicoException $e){
            if(!in_array($e->estadoHttp,[422,409],true)){$this->error($e);return;}
            // Volver a autorizar antes de renderizar; no guardar valores clínicos en sesión.
            try{$datos=$this->servicio->consultar($paciente,$alergia);}
            catch(PerfilClinicoException $denegado){$this->error($denegado);return;}
            $datos['errores']=$e->errores;$datos['entrada']=[];
            foreach(['grupo_sanguineo','fuente_grupo_sanguineo','observaciones','estado_alergias','sustancia','reaccion','estado','motivo_cambio','version'] as $campo){
                $datos['entrada'][$campo]=is_string($_POST[$campo]??null)?mb_substr($_POST[$campo],0,500):'';
            }
            http_response_code($e->estadoHttp);$this->pagina('clinico/'.$vista,'Revisa la información clínica',$datos);
        }
    }
}
