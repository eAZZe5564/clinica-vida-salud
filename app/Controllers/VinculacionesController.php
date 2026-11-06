<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\{VinculacionPacienteService,CitaException};

final class VinculacionesController extends CatalogoController
{
    public function __construct()
    {
        parent::__construct(true);
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
    }
    public function mostrar(int $id): void
    {
        try{
            $datos=(new VinculacionPacienteService($this->pdo))->contexto($id,(int)$this->actor['id']);
            $recibo=$_SESSION['vinculacion_recibo']??null;unset($_SESSION['vinculacion_recibo']);
            if(!is_array($recibo)||($recibo['paciente_id']??null)!==$id){$recibo=null;}
            $this->pagina('portal/vincular','Vincular cuenta Paciente',$datos+['recibo'=>$recibo]);
        }catch(CitaException $e){$this->error($e);}
    }
    public function guardar(int $id): void
    {
        $this->escritura();
        try{
            $recibo=(new VinculacionPacienteService($this->pdo))->vincular($id,$_POST['cuenta_id']??null,$_POST['identidad_verificada']??null,(int)$this->actor['id']);
            $_SESSION['vinculacion_recibo']=$recibo+['paciente_id'=>$id];
            \redirigir('/pacientes/'.$id.'/vincular-cuenta');
        }catch(CitaException $e){$this->error($e);}
    }
    private function error(CitaException $e): void
    {
        http_response_code($e->estadoHttp);
        $this->pagina('portal/error','Vinculación no disponible',['errores'=>$e->errores,'administrativa'=>true]);
    }
}
