<?php
declare(strict_types=1);
require_once __DIR__ . '/HorariosFixture.php';
/** Utilidades de pruebas de CU13; no agrega configuración de producción. */
final class CitasPrueba
{
    public readonly HorariosFixture $fixture;
    public readonly App\Services\HorarioService $horarios;
    public readonly App\Services\DisponibilidadService $disponibilidad;
    public readonly App\Services\CitaService $servicio;
    public readonly DateTimeImmutable $lunes;
    public function __construct(public readonly PDO $pdo)
    {
        if(!$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='citas' AND column_name='especialidad_id'")->fetchColumn()){
            fwrite(STDERR,"PENDIENTE: requiere migración 004 autorizada y aplicada.\n");exit(2);
        }
        $this->fixture=new HorariosFixture($pdo);
        $this->horarios=new App\Services\HorarioService($pdo,new App\Repositories\HorarioRepository($pdo));
        $this->disponibilidad=new App\Services\DisponibilidadService($pdo,new App\Repositories\DisponibilidadRepository($pdo),$this->horarios);
        $this->servicio=new App\Services\CitaService($pdo,new App\Repositories\CitaRepository($pdo),$this->disponibilidad);
        $this->lunes=(new DateTimeImmutable('next monday',App\Models\HorarioAtencion::zona()))->modify('+14 days')->setTime(0,0);
    }
    public function horario(int $medico): int{return $this->horarios->guardar(['medico_id'=>$medico,'dia_semana'=>1,'hora_inicio'=>'08:00','hora_fin'=>'12:00']);}
    public function datos(int $medico,int $paciente,int $especialidad,string $hora='08:00'): array
    {
        return ['medico_id'=>$medico,'paciente_id'=>$paciente,'especialidad_id'=>$especialidad,'fecha'=>$this->lunes->format('Y-m-d'),'hora_inicio'=>$hora];
    }
}
