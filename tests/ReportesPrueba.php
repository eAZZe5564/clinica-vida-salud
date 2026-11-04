<?php
declare(strict_types=1);
require_once __DIR__.'/HistorialPacientePrueba.php';

/** Datos propios y auxiliares existentes; guardas de destino Cu15Local. */
final class ReportesPrueba
{
    public readonly HistorialPacientePrueba $historial;
    public readonly AgendaPrueba $base;
    public int $sinCitas;
    public function __construct(public readonly PDO $pdo)
    {
        $this->historial=new HistorialPacientePrueba($pdo);$this->historial->reprogramaciones();$this->base=$this->historial->base;
        $f=$this->base->t->fixture;$this->sinCitas=$f->medico();
        $pdo->prepare('INSERT INTO medicos_especialidades(medico_id,especialidad_id) VALUES (:m,:e) ON CONFLICT DO NOTHING')
            ->execute(['m'=>$this->base->medico,'e'=>$f->especialidades[1]]);
        $this->base->insertar('2021-01-01T03:30:00+00:00');
        $this->base->insertar('2021-01-01T04:00:00+00:00');
    }
    public function servicio(string $rol='administrador',?PDO $p=null): App\Services\ReporteCitaService
    {
        $p??=$this->pdo;$actor=$this->base->actores[$rol];
        return new App\Services\ReporteCitaService(new App\Repositories\ReporteCitaRepository($p),
            new App\Services\ConsultaCitaService(new App\Repositories\CitaRepository($p),new App\Repositories\HorarioRepository($p),$actor),$actor);
    }
    public function filtros(array $f=[]): array
    {return array_replace(['desde'=>$this->base->fecha,'hasta'=>$this->base->fecha,'medico_id'=>(string)$this->base->medico],$f);}
    public function limpiar(): void{$this->historial->limpiar();}
}
