<?php
declare(strict_types=1);
require_once __DIR__.'/RegistroClinicoPrueba.php';
require_once __DIR__.'/CitasPrueba.php';

function portalAssert(bool $valor,string $mensaje): void
{
    global $total;
    if(!$valor){throw new RuntimeException('CU25: '.$mensaje);}$total++;
}
function portalRechaza(Closure $accion,int $status,string $mensaje): void
{
    try{$accion();}catch(App\Services\CitaException|App\Services\CatalogoValidacionException $e){
        portalAssert(($e instanceof App\Services\CitaException?$e->estadoHttp:422)===$status,$mensaje.' (estado)');return;
    }throw new RuntimeException('CU25: se aceptó '.$mensaje);
}

/** Solo identidades inventadas, retenidas por ID para retirar los fixtures. */
final class PortalPacientePrueba
{
    public CitasPrueba $base;
    public HorariosFixture $fixture;
    public array $actores=[];
    public int $paciente;
    public int $ajeno;
    public int $sinCuenta;
    public int $medico;
    public int $especialidad;
    public function __construct(public readonly PDO $pdo)
    {
        $this->base=new CitasPrueba($pdo);$this->fixture=$this->base->fixture;
        try{
            foreach(['administrador','recepcionista','medico','paciente','otro','sin_vinculo'] as $rol){
                $id=$this->fixture->usuario(in_array($rol,['otro','sin_vinculo'],true)?'paciente':$rol);
                $q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);
                $this->actores[$rol]=['id'=>$id,'correo'=>$q->fetchColumn()];
            }
            $this->paciente=$this->fixture->paciente();$this->ajeno=$this->fixture->paciente();$this->sinCuenta=$this->fixture->paciente();
            $this->medico=$this->fixture->medico($this->actores['medico']['id']);$this->especialidad=$this->fixture->especialidades[0];$this->base->horario($this->medico);
            $s=new App\Services\VinculacionPacienteService($pdo);
            $s->vincular($this->paciente,$this->actores['paciente']['id'],'1',$this->actores['administrador']['id']);
            $s->vincular($this->ajeno,$this->actores['otro']['id'],'1',$this->actores['administrador']['id']);
        }catch(Throwable $e){$this->limpiar();throw $e;}
    }
    public function portal(string $rol='paciente',?PDO $p=null): App\Services\PortalPacienteService
    {return new App\Services\PortalPacienteService($p??$this->pdo,$this->actores[$rol]['id']);}
    public function entrada(string $hora='08:00'): array
    {
        return ['medico_id'=>$this->medico,'especialidad_id'=>$this->especialidad,'fecha'=>$this->base->lunes->format('Y-m-d'),'hora_inicio'=>$hora,'confirmar'=>'1'];
    }
    public function cita(string $hora='08:00',string $rol='paciente'): int
    {return $this->portal($rol)->reservar($this->entrada($hora))['id'];}
    public function legado(string $fecha): int
    {
        $q=$this->pdo->prepare("INSERT INTO citas(medico_id,paciente_id,fecha_hora_inicio,estado) VALUES (:m,:p,:f,'atendida') RETURNING id");
        $q->execute(['m'=>$this->medico,'p'=>$this->paciente,'f'=>$fecha]);return (int)$q->fetchColumn();
    }
    public function original(int $id,string $accion='reprogramar',string $rol='paciente'): string
    {return $this->portal($rol)->formulario([],$id,$accion)['original'];}
    public function mutacion(int $id,string $hora='09:00',string $accion='reprogramar',string $rol='paciente'): array
    {return ['original'=>$this->original($id,$accion,$rol),'fecha'=>$this->base->lunes->format('Y-m-d'),'hora_inicio'=>$hora,'confirmar'=>'1'];}
    public function limpiar(): void
    {
        if($this->pdo->inTransaction()){$this->pdo->rollBack();}
        foreach($this->fixture->medicos as $m){$q=$this->pdo->prepare('DELETE FROM citas_historial WHERE cita_id IN (SELECT id FROM citas WHERE medico_id=:m)');$q->execute(['m'=>$m]);}
        $this->fixture->limpiar();
    }
}

/** Falla después de actualizar el vínculo; el servicio debe revertir ese UPDATE. */
final class PortalVinculacionFalloPDO extends PDO
{
    public function __construct(private readonly PDO $real){}
    public function beginTransaction(): bool{return $this->real->beginTransaction();}
    public function inTransaction(): bool{return $this->real->inTransaction();}
    public function commit(): bool{return $this->real->commit();}
    public function rollBack(): bool{return $this->real->rollBack();}
    public function exec(string $q): int|false{return $this->real->exec($q);}
    public function prepare(string $q,array $o=[]): PDOStatement|false{return $this->real->prepare($q,$o);}
    public function query(string $q,?int $mode=null,mixed ...$args): PDOStatement|false
    {
        if($q==='SELECT clock_timestamp()'){throw new RuntimeException('Fallo ficticio posterior al vínculo.');}
        return $mode===null?$this->real->query($q):$this->real->query($q,$mode,...$args);
    }
}
