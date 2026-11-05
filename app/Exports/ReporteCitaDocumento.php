<?php
declare(strict_types=1);
namespace App\Exports;

use App\Models\Cita;
use App\Models\HorarioAtencion;
use DateTimeImmutable;
use Generator;

/** Presentación de un único resultado CU21; no consulta ni recalcula estadísticas. */
final class ReporteCitaDocumento
{
    public readonly DateTimeImmutable $generado;
    public function __construct(public readonly array $datos,?DateTimeImmutable $ahora=null)
    {
        $this->generado=($ahora??new DateTimeImmutable('now',HorarioAtencion::zona()))->setTimezone(HorarioAtencion::zona());
    }
    public function filtros(): array
    {
        $f=$this->datos['f'];$opciones=$this->datos['opciones'];$medico='Todos';$especialidad='Todas';
        foreach($opciones['medicos'] as $m){if((string)$m['id']===$f['medico_id']){$medico=$m['apellidos'].', '.$m['nombres'];break;}}
        foreach($opciones['especialidades'] as $e){if((string)$e['id']===$f['especialidad_id']){$especialidad=$e['nombre'];break;}}
        return ['Fecha inicial'=>$f['desde'],'Fecha final inclusive'=>$f['hasta'],'Médico'=>$medico,
            'Especialidad'=>$especialidad,'Estado'=>$f['estado']===''?'Todos':Cita::ESTADOS[$f['estado']]];
    }
    public function nombre(string $formato): string
    {
        if(!in_array($formato,['pdf','csv'],true)){throw new \InvalidArgumentException('Formato no admitido.');}
        $f=$this->datos['f'];
        foreach(['desde','hasta'] as $campo){if(preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$f[$campo])!==1){throw new \InvalidArgumentException('Nombre de descarga no válido.');}}
        return 'clinica-vida-salud-reporte-citas-'.$f['desde'].'-a-'.$f['hasta'].'.'.$formato;
    }
    private function conteos(array $fila): array
    {
        return [$fila['total'],$fila['programada'],$fila['confirmada'],$fila['presente'],$fila['atendida'],$fila['inasistencia'],$fila['cancelada']];
    }
    /** Nueve columnas estables; metadatos usan Valor/Total para texto. */
    public function filasCsv(): Generator
    {
        yield ['Sección','Concepto','Valor/Total','Programadas','Confirmadas','Presentes','Atendidas','Inasistencias','Canceladas'];
        $informacion=['Clínica'=>'Clínica Vida Salud','Título'=>'Reporte de citas médicas',
            'Generado en America/La_Paz'=>$this->generado->format('Y-m-d H:i:s P'),
            'Definición'=>'Estado actual por fecha programada vigente; cada cita se cuenta una vez, no sus eventos.',
            'Interpretación'=>'Puede incluir citas futuras. Presente no significa atención finalizada. No son tasas clínicas.'];
        foreach($informacion as $campo=>$valor){yield array_pad(['Información',$campo,$valor],9,'');}
        foreach($this->filtros() as $campo=>$valor){yield array_pad(['Filtro',$campo,$valor],9,'');}
        $r=$this->datos['reporte'];$g=$r['general'];
        yield ['General','Total de citas',...$this->conteos($g)];
        foreach(Cita::ESTADOS as $estado=>$label){yield array_pad(['Estado',$label,$r['estados'][$estado]],9,'');}
        foreach(['medicos'=>'Médico','especialidades'=>'Especialidad','dias'=>'Día'] as $grupo=>$seccion){
            foreach($r[$grupo] as $fila){yield [$seccion,$fila['etiqueta'],...$this->conteos($fila)];}
            if(!$r[$grupo]){yield array_pad([$seccion,'Sin registros',0],9,'');}
        }
        foreach(['atendida'=>'Atendidas','inasistencia'=>'Inasistencias','cancelada'=>'Canceladas',
            'programada'=>'Pendientes programadas','confirmada'=>'Pendientes confirmadas','presente'=>'Presentes sin atención finalizada'] as $estado=>$label){
            yield array_pad(['Situación actual',$label,$g[$estado]],9,'');
        }
        if($g['total']===0){yield array_pad(['Información','Sin registros','No hay citas para el período y los filtros seleccionados.'],9,'');}
    }
}
