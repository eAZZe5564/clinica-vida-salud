<?php
declare(strict_types=1);
require_once __DIR__.'/Cu15Local.php';
require_once __DIR__.'/HorariosFixture.php';

use App\Repositories\PerfilClinicoRepository;
use App\Services\PerfilClinicoService;
use App\Services\PerfilClinicoException;

/** Solo cuentas/pacientes inventados, rastreados por ID; no imprime valores clínicos. */
final class PerfilClinicoPrueba
{
    public HorariosFixture $fixture;
    public array $actores=[];
    public int $medico;
    public int $otro;
    public int $paciente;
    public int $ajeno;
    public int $sinCita;
    public array $citas=[];
    public function __construct(public readonly PDO $pdo)
    {
        $this->fixture=new HorariosFixture($pdo);
        try{
            foreach(['administrador','recepcionista','medico','paciente','otro_medico','sin_vinculo'] as $rol){
                $id=$this->fixture->usuario(in_array($rol,['otro_medico','sin_vinculo'],true)?'medico':$rol);
                $q=$pdo->prepare('SELECT correo FROM usuarios WHERE id=:id');$q->execute(['id'=>$id]);
                $this->actores[$rol]=['id'=>$id,'correo'=>$q->fetchColumn()];
            }
            $this->medico=$this->fixture->medico($this->actores['medico']['id']);$this->otro=$this->fixture->medico($this->actores['otro_medico']['id']);
            $this->paciente=$this->fixture->paciente();$this->ajeno=$this->fixture->paciente();$this->sinCita=$this->fixture->paciente();
            $hoy=(new DateTimeImmutable('today',App\Models\HorarioAtencion::zona()))->format('Y-m-d');
            $this->citas['propia']=$this->cita($this->paciente,$this->medico,$hoy.'T00:00:00-04:00');
            $this->citas['ajena']=$this->cita($this->ajeno,$this->otro,$hoy.'T00:30:00-04:00');
        }catch(Throwable $e){$this->limpiar();throw $e;}
    }
    public function cita(int $paciente,int $medico,string $fecha,string $estado='atendida'): int
    {
        // Legado ficticio: no crea eventos falsos en citas_historial ni modifica reglas anteriores.
        $q=$this->pdo->prepare('INSERT INTO citas(paciente_id,medico_id,fecha_hora_inicio,estado) VALUES (:p,:m,:f,:e) RETURNING id');
        $q->execute(['p'=>$paciente,'m'=>$medico,'f'=>$fecha,'e'=>$estado]);return (int)$q->fetchColumn();
    }
    public function servicio(string $rol='medico',?Closure $reloj=null,?PDO $pdo=null): PerfilClinicoService
    {
        $p=$pdo??$this->pdo;return new PerfilClinicoService($p,new PerfilClinicoRepository($p),$this->actores[$rol]['id'],$reloj);
    }
    public function perfil(array $c=[]): array
    {
        return array_replace(['grupo_sanguineo'=>'','fuente_grupo_sanguineo'=>'','observaciones'=>'',
            'estado_alergias'=>'no_registrado','version'=>'0','confirmar'=>'1'],$c);
    }
    public function alergia(array $c=[]): array
    {
        return array_replace(['sustancia'=>'CU23_AGENTE_FICTICIO','reaccion'=>'CU23_REACCION_FICTICIA',
            'observaciones'=>'CU23_OBSERVACION_FICTICIA','confirmar'=>'1'],$c);
    }
    public function cambio(int $id,array $c=[]): array
    {
        $a=(new PerfilClinicoRepository($this->pdo))->alergia($this->paciente,$id);
        return array_replace(array_intersect_key($a,array_flip(['sustancia','reaccion','observaciones','estado','version'])),
            ['motivo_cambio'=>'CU23_RECTIFICACION_FICTICIA','confirmar'=>'1'],$c);
    }
    public function huella(): array
    {
        $r=[];
        foreach(['roles','usuarios','pacientes','medicos','especialidades','medicos_especialidades','horarios_atencion','citas','citas_historial',
            'perfiles_clinicos','alergias_paciente','alergias_historial'] as $t){
            $r[$t]=$this->pdo->query('SELECT count(*) AS n,md5(COALESCE(string_agg(md5(row_to_json(x)::text),\'\' ORDER BY md5(row_to_json(x)::text)),\'\')) AS h FROM '.$t.' x')->fetch();
        }return $r;
    }
    public function limpiar(): void
    {
        if($this->pdo->inTransaction()){$this->pdo->rollBack();}
        foreach($this->fixture->pacientes as $p){
            $q=$this->pdo->prepare('DELETE FROM alergias_historial WHERE alergia_id IN (SELECT id FROM alergias_paciente WHERE paciente_id=:p)');$q->execute(['p'=>$p]);
            foreach(['alergias_paciente','perfiles_clinicos'] as $t){$q=$this->pdo->prepare('DELETE FROM '.$t.' WHERE paciente_id=:p');$q->execute(['p'=>$p]);}
        }
        foreach($this->fixture->medicos as $m){$q=$this->pdo->prepare('DELETE FROM citas_historial WHERE cita_id IN(SELECT id FROM citas WHERE medico_id=:m)');$q->execute(['m'=>$m]);}
        $this->fixture->limpiar();
    }
}

/** Falla únicamente en la escritura de revisión para probar atomicidad sin DDL. */
final class PerfilClinicoFalloPDO extends PDO
{
    public function __construct(private readonly PDO $real){}
    public function beginTransaction(): bool{return $this->real->beginTransaction();}
    public function inTransaction(): bool{return $this->real->inTransaction();}
    public function commit(): bool{return $this->real->commit();}
    public function rollBack(): bool{return $this->real->rollBack();}
    public function exec(string $statement): int|false{return $this->real->exec($statement);}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false
    {return $fetchMode===null?$this->real->query($query):$this->real->query($query,$fetchMode,...$fetchModeArgs);}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if(str_starts_with($query,'INSERT INTO alergias_historial')){throw new RuntimeException('Fallo de prueba controlado.');}
        return $this->real->prepare($query,$options);
    }
}

function clinicoAssert(bool $v,string $mensaje): void
{
    global $total;if(!$v){throw new RuntimeException($mensaje);}++$total;echo 'OK: '.$mensaje.PHP_EOL;
}
function clinicoRechaza(Closure $operacion,int $estado,string $mensaje): void
{
    try{$operacion();}catch(PerfilClinicoException $e){clinicoAssert($e->estadoHttp===$estado,$mensaje);return;}
    throw new RuntimeException('No se rechazó una operación de prueba.');
}
