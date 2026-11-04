<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Repositories\ReporteCitaRepository;
use DateTimeImmutable;
use DateTimeZone;

/** Datos independientes de HTML, reutilizables en CU22 sin duplicar cálculos. */
final class ReporteCitaService
{
    public function __construct(private readonly ReporteCitaRepository $repo,private readonly ConsultaCitaService $consulta,array $actor)
    {
        if(!in_array($actor['rol']??null,['administrador','recepcionista'],true)){
            throw new CitaException(['permisos'=>'No tienes autorización para consultar reportes.'],403);
        }
    }

    public function consultar(array $entrada): array
    {
        $entrada=array_intersect_key($entrada,array_flip(['desde','hasta','medico_id','especialidad_id','estado']));
        $hoy=new DateTimeImmutable('now',HorarioAtencion::zona());
        foreach(['desde'=>'first day of this month','hasta'=>'last day of this month'] as $campo=>$valor){
            if(!isset($entrada[$campo])||$entrada[$campo]===''){$entrada[$campo]=$hoy->modify($valor)->format('Y-m-d');}
        }
        $f=$this->consulta->validar($entrada);$opciones=$this->repo->opciones();$errores=[];
        foreach(['medico_id'=>'medicos','especialidad_id'=>'especialidades'] as $campo=>$catalogo){
            if($f[$campo]!==''&&!in_array($f[$campo],array_map('strval',array_column($opciones[$catalogo],'id')),true)){
                $errores[$campo]='Selecciona un registro existente, o todos.';
            }
        }
        if($errores){throw new CatalogoValidacionException($errores);}
        $utc=new DateTimeZone('UTC');
        $f['limite_desde']=(new DateTimeImmutable($f['desde'],HorarioAtencion::zona()))->setTimezone($utc)->format('Y-m-d H:i:sP');
        $f['limite_hasta']=(new DateTimeImmutable($f['hasta'],HorarioAtencion::zona()))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:sP');
        $estados=array_fill_keys(array_keys(Cita::ESTADOS),0);
        $reporte=['general'=>['total'=>0]+$estados,'estados'=>$estados,'medicos'=>[],'especialidades'=>[],'dias'=>[]];
        foreach($this->repo->resumir($f) as $fila){
            foreach(['total',...array_keys(Cita::ESTADOS)] as $columna){$fila[$columna]=(int)$fila[$columna];}
            switch($fila['tipo']){
                case 'general':$reporte['general']=array_intersect_key($fila,['total'=>0]+$estados);break;
                case 'estado':$reporte['estados'][$fila['estado']]=$fila['total'];break;
                case 'medico':$fila['etiqueta']=$fila['apellidos'].', '.$fila['nombres'];$reporte['medicos'][]=$fila;break;
                case 'especialidad':$fila['etiqueta']=$fila['nombre']??'Sin registrar';$reporte['especialidades'][]=$fila;break;
                case 'dia':$fila['etiqueta']=$fila['dia'];$reporte['dias'][]=$fila;break;
            }
        }
        return ['f'=>$f,'opciones'=>$opciones,'reporte'=>$reporte];
    }
}
