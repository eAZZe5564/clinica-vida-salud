<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\HorarioAtencion;
use App\Repositories\DisponibilidadRepository;
use Closure;
use DateTimeImmutable;
use PDO;

/** CU12: calcula intervalos informativos, sin guardar ni modificar citas. */
final class DisponibilidadService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly DisponibilidadRepository $repo,
        private readonly HorarioService $horarios,
        private readonly ?Closure $reloj=null
    ) {}
    public function ahora(): DateTimeImmutable
    {
        return ($this->reloj===null ? new DateTimeImmutable('now',HorarioAtencion::zona()) : ($this->reloj)())->setTimezone(HorarioAtencion::zona());
    }
    public function validarParametros(array $entrada): array
    {
        $errores=[];$datos=[];
        foreach(['medico_id','especialidad_id'] as $campo){
            $valor=$entrada[$campo]??'';
            if($campo==='especialidad_id' && $valor===''){$datos[$campo]=null;continue;}
            $id=is_int($valor)||is_string($valor) ? filter_var($valor,FILTER_VALIDATE_INT) : false;
            if($id===false || $id<=0){$errores[$campo]='Selecciona una opción válida.';}
            $datos[$campo]=$id;
        }
        $fecha=$entrada['fecha']??null;
        $dia=is_string($fecha) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$fecha)
            ? DateTimeImmutable::createFromFormat('!Y-m-d',$fecha,HorarioAtencion::zona()) : false;
        if(!$dia || $dia->format('Y-m-d')!==$fecha || $fecha<'0001-01-01'){$errores['fecha']='Ingresa una fecha válida.';}
        elseif($fecha<$this->ahora()->format('Y-m-d')){$errores['fecha']='No se puede consultar disponibilidad en fechas pasadas.';}
        if($errores){throw new CatalogoValidacionException($errores);}
        $datos['fecha']=$fecha;return $datos;
    }
    public function validarEspecialidad(?int $id): void
    {
        if($id===null){return;}
        $e=$this->repo->especialidad($id);
        if(!$e || !$e['activo']){throw new CatalogoValidacionException(['especialidad_id'=>'La especialidad no existe o está inactiva.']);}
    }
    public function consultar(int $medico,string $fecha,?int $especialidad=null): array
    {
        $entrada=['medico_id'=>$medico,'fecha'=>$fecha,'especialidad_id'=>$especialidad??''];
        $this->validarParametros($entrada);$ahora=$this->ahora();
        // Una consulta autónoma utiliza una instantánea coherente y de solo lectura.
        // CU13 podrá usar la misma conexión dentro de su transacción ya bloqueada.
        $propia=!$this->pdo->inTransaction();
        if($propia){$this->pdo->beginTransaction();$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');}
        try{
            $this->validarEspecialidad($especialidad);
            $m=$this->repo->medico($medico,$especialidad);
            if(!$m || !$m['activo']){throw new CatalogoValidacionException(['medico_id'=>'Selecciona un médico activo que pertenezca a la especialidad indicada.']);}
            $tramos=$this->horarios->tramosParaFecha($medico,$fecha);
            $desde=new DateTimeImmutable($fecha . ' 00:00:00',HorarioAtencion::zona());$hasta=$desde->modify('+1 day');
            $ocupadas=[];
            foreach($this->repo->ocupaciones($medico,$desde->format('c'),$hasta->format('c')) as $fila){
                $inicio=(new DateTimeImmutable($fila['fecha_hora_inicio']))->setTimezone(HorarioAtencion::zona());
                $ocupadas[]=[$inicio,$inicio->modify('+' . (int)$fila['duracion_minutos'] . ' minutes')];
            }
            $turnos=[];$duracion=HorarioAtencion::DURACION_MINUTOS;
            foreach($tramos as $tramo){
                // No ofrecer datos heredados que incumplan la grilla válida de CU11.
                if(!preg_match('/^(?:[01][0-9]|2[0-3]):(?:00|30):00(?:\.0+)?$/D',$tramo['hora_inicio'])
                    || !preg_match('/^(?:[01][0-9]|2[0-3]):(?:00|30):00(?:\.0+)?$/D',$tramo['hora_fin'])){continue;}
                $inicio=new DateTimeImmutable($fecha . ' ' . $tramo['hora_inicio'],HorarioAtencion::zona());
                $cierre=new DateTimeImmutable($fecha . ' ' . $tramo['hora_fin'],HorarioAtencion::zona());
                for($cursor=$inicio;($fin=$cursor->modify("+$duracion minutes"))<=$cierre;$cursor=$fin){
                    if($cursor<=$ahora){continue;}
                    $libre=true;
                    foreach($ocupadas as [$ocupadaInicio,$ocupadaFin]){
                        // Intervalos semiabiertos: se permite iniciar al terminar otra cita.
                        if($cursor<$ocupadaFin && $fin>$ocupadaInicio){$libre=false;break;}
                    }
                    if($libre){$turnos[$cursor->getTimestamp()]=['inicio'=>$cursor->format('c'),'fin'=>$fin->format('c')];}
                }
            }
            ksort($turnos,SORT_NUMERIC);
            $resultado=['medico'=>$m,'fecha'=>$fecha,'zona'=>HorarioAtencion::zona()->getName(),'duracion_minutos'=>$duracion,
                'turnos'=>array_values($turnos),'sin_atencion'=>$tramos===[]];
            if($propia){$this->pdo->commit();}return $resultado;
        }catch(\Throwable $e){if($propia && $this->pdo->inTransaction()){$this->pdo->rollBack();}throw $e;}
    }
}
