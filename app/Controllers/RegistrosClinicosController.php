<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\RegistroClinico;
use App\Repositories\RegistroClinicoRepository;
use App\Services\RegistroClinicoService;
use App\Services\PerfilClinicoException;

final class RegistrosClinicosController extends CatalogoController
{
    private RegistroClinicoService $servicio;
    public function __construct()
    {
        header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');
        parent::__construct(false,['medico']);
        $this->servicio=new RegistroClinicoService($this->pdo,new RegistroClinicoRepository($this->pdo),(int)$this->actor['id']);
    }
    private function error(PerfilClinicoException $e): void
    {
        http_response_code($e->estadoHttp);
        if(in_array($e->estadoHttp,[403,404],true)){require dirname(__DIR__).'/Views/errors/'.$e->estadoHttp.'.php';return;}
        $this->pagina('clinico/error','Operación clínica no completada',['errores'=>$e->errores]);
    }
    private function presentar(array $datos,string $tipo,string $accion): void
    {
        $datos['tipo']=$tipo;$datos['definicion']=RegistroClinico::tipo($tipo);$datos['accion']=$accion;
        $datos['clinicoTitulo']=$datos['definicion']['titulo'];$datos['errores']=$datos['errores']??[];
        $this->pagina('clinico/registros',$datos['clinicoTitulo'],$datos);
    }
    public function mostrar(int $paciente,string $tipo,?int $id,string $accion): void
    {
        try{$datos=$this->servicio->consultar($paciente,$tipo,$id);}
        catch(PerfilClinicoException $e){$this->error($e);return;}
        $this->presentar($datos,$tipo,$accion);
    }
    public function guardar(int $paciente,string $tipo,?int $id): void
    {
        $this->middleware->exigirCsrf();
        try{
            $this->servicio->guardar($paciente,$tipo,$_POST,$id);
            \redirigir('/pacientes/'.$paciente.'/'.$tipo.'?guardado=1');
        }catch(PerfilClinicoException $e){
            if(!in_array($e->estadoHttp,[422,409],true)){$this->error($e);return;}
            try{$datos=$this->servicio->consultar($paciente,$tipo,$id);}
            catch(PerfilClinicoException $denegado){$this->error($denegado);return;}
            $datos['errores']=$e->errores;$datos['entrada']=[];
            foreach([...RegistroClinico::tipo($tipo)['campos'],'motivo_cambio','version'] as $campo){
                $datos['entrada'][$campo]=is_string($_POST[$campo]??null)?mb_substr($_POST[$campo],0,2000):'';
            }
            http_response_code($e->estadoHttp);$this->presentar($datos,$tipo,$id===null?'':'editar');
        }
    }
}
