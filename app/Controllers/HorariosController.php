<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Models\HorarioAtencion;
use App\Repositories\HorarioRepository;
use App\Services\HorarioService;
use App\Services\CatalogoValidacionException;

final class HorariosController extends CatalogoController
{
    private HorarioRepository $repo;
    private HorarioService $servicio;
    private ?int $propio=null;
    public function __construct()
    {
        parent::__construct(false,['administrador','recepcionista','medico']);
        $this->repo=new HorarioRepository($this->pdo);
        $this->servicio=new HorarioService($this->pdo,$this->repo);
        if ($this->actor['rol']==='medico') { $this->propio=$this->repo->vinculado($this->actor['id']); }
    }
    private function exigirPropio(?int $medico): void
    {
        if ($this->actor['rol']==='medico' && ($this->propio===null || $medico!==$this->propio)) {
            http_response_code(403); require dirname(__DIR__) . '/Views/errors/403.php'; exit;
        }
    }
    public function listar(): void
    {
        $medico=$this->numero('medico'); $dia=$this->numero('dia');
        if ($dia!==null && !isset(HorarioAtencion::DIAS[$dia])) { $dia=null; }
        $sinVinculo=$this->actor['rol']==='medico' && $this->propio===null;
        if ($this->actor['rol']==='medico') {
            if ($medico!==null) { $this->exigirPropio($medico); }
            $medico=$this->propio;
        }
        $resultado=$sinVinculo ? ['filas'=>[],'total'=>0,'pagina'=>1,'paginas'=>1] : $this->repo->listar($medico,$dia,$this->numero('pagina')??1);
        $opciones=$sinVinculo ? [] : $this->repo->medicos($this->actor['rol']==='medico' ? $this->propio : null);
        $this->pagina('horarios/index','Horarios médicos',compact('medico','dia','resultado','opciones','sinVinculo'));
    }
    public function detalle(int $id): void
    {
        // La autorización por propietario se comprueba también por URL directa.
        $horario=$this->servicio->obtener($id); $this->exigirPropio((int)$horario['medico_id']);
        $this->pagina('horarios/show','Detalle del horario',compact('horario'));
    }
    public function formulario(?int $id=null, array $errores=[], ?array $datos=null): void
    {
        $this->administrador();
        $original=$id===null ? null : $this->servicio->obtener($id);
        $horario=$datos ?? $original ?? ['medico_id'=>$this->numero('medico')??''];
        if ($original!==null) { $horario['medico_id']=$original['medico_id']; }
        $opciones=$this->repo->medicos();
        $this->pagina('horarios/form',$id===null?'Registrar horario':'Editar horario',compact('horario','id','errores','opciones'));
    }
    public function guardar(?int $id=null): void
    {
        $this->escritura();
        try { $guardado=$this->servicio->guardar($_POST,$id); \redirigir('/horarios/' . $guardado . '?mensaje=horario-guardado'); }
        catch (CatalogoValidacionException $e) {
            http_response_code(422); $this->formulario($id,$e->errores,$this->datosEnviados(['medico_id','dia_semana','hora_inicio','hora_fin']));
        }
    }
    public function estado(int $id, array $errores=[]): void
    {
        $this->administrador(); $horario=$this->servicio->obtener($id);
        $this->pagina('horarios/status','Confirmar estado del horario',compact('horario','errores'));
    }
    public function cambiarEstado(int $id): void
    {
        $this->escritura();
        try { $this->servicio->cambiarEstado($id,$this->estadoEnviado()); \redirigir('/horarios/' . $id . '?mensaje=horario-estado'); }
        catch (CatalogoValidacionException $e) { http_response_code(422); $this->estado($id,$e->errores); }
    }
}
