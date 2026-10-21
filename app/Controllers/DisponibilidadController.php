<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Repositories\DisponibilidadRepository;
use App\Repositories\HorarioRepository;
use App\Services\DisponibilidadService;
use App\Services\HorarioService;
use App\Services\CatalogoValidacionException;

final class DisponibilidadController extends CatalogoController
{
    public function __construct(){parent::__construct(false,['administrador','recepcionista','medico']);}
    private function id(mixed $valor,string $campo): ?int
    {
        if($valor===''){return null;}
        $id=is_string($valor)?filter_var($valor,FILTER_VALIDATE_INT):false;
        if($id===false || $id<=0){throw new CatalogoValidacionException([$campo=>'Selecciona una opción válida.']);}
        return $id;
    }
    public function index(): void
    {
        $repo=new DisponibilidadRepository($this->pdo);$hr=new HorarioRepository($this->pdo);
        $servicio=new DisponibilidadService($this->pdo,$repo,new HorarioService($this->pdo,$hr));
        $propio=$this->actor['rol']==='medico'?$hr->vinculado($this->actor['id']):null;
        $sinVinculo=$this->actor['rol']==='medico' && $propio===null;
        $seleccion=[];foreach(['medico_id','especialidad_id','fecha'] as $campo){$seleccion[$campo]=is_string($_GET[$campo]??null)?mb_substr($_GET[$campo],0,100):'';}
        if(!array_key_exists('fecha',$_GET)){$seleccion['fecha']=$servicio->ahora()->format('Y-m-d');}
        if($propio!==null && !array_key_exists('medico_id',$_GET)){$seleccion['medico_id']=(string)$propio;}
        $errores=[];$resultado=null;$especialidad=null;$opciones=[];$especialidades=[];
        $accion=$_GET['accion']??'';
        $consultar=$accion==='consultar' || ($accion==='' && (array_key_exists('medico_id',$_GET)||array_key_exists('fecha',$_GET)));
        try{
            if(!is_string($accion) || !in_array($accion,['','consultar','filtrar'],true)){throw new CatalogoValidacionException(['consulta'=>'La solicitud no es válida.']);}
            $medico=$this->id($_GET['medico_id']??($propio===null?'':(string)$propio),'medico_id');
            // Limitar el alcance antes de cargar opciones o ejecutar consultas de terceros.
            if($this->actor['rol']==='medico' && (($medico!==null && $medico!==$propio) || ($sinVinculo && $consultar))){
                http_response_code(403);require dirname(__DIR__) . '/Views/errors/403.php';return;
            }
            $especialidad=$this->id($_GET['especialidad_id']??'','especialidad_id');
            $servicio->validarEspecialidad($especialidad);
            if($this->actor['rol']==='medico' && $propio!==null && $especialidad!==null && !$repo->medico($propio,$especialidad)){
                throw new CatalogoValidacionException(['especialidad_id'=>'Selecciona una especialidad activa de tu registro médico.']);
            }
            if($accion==='filtrar'){$seleccion['medico_id']=$propio===null?'':(string)$propio;}
            if($consultar && !$sinVinculo){
                $parametros=$servicio->validarParametros(['medico_id'=>$_GET['medico_id']??($propio??''),'especialidad_id'=>$_GET['especialidad_id']??'','fecha'=>$_GET['fecha']??'']);
                $resultado=$servicio->consultar($parametros['medico_id'],$parametros['fecha'],$parametros['especialidad_id']);
            }
        }catch(CatalogoValidacionException $e){http_response_code(422);$errores=$e->errores;}
        if(!$sinVinculo){
            $especialidades=$repo->especialidades($this->actor['rol']==='medico'?$propio:null);
            // Si el filtro es inválido, no ampliar accidentalmente el selector de médicos.
            if(!isset($errores['especialidad_id'])){$opciones=$repo->medicos($especialidad,$this->actor['rol']==='medico'?$propio:null);}
        }
        $hoy=$servicio->ahora()->format('Y-m-d');
        $this->pagina('disponibilidad/index','Disponibilidad médica',compact('seleccion','errores','resultado','opciones','especialidades','sinVinculo','hoy'));
    }
}
