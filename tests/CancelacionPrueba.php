<?php
declare(strict_types=1);
require_once __DIR__.'/ReprogramacionPrueba.php';

/** Reutiliza los datos ficticios de CU13/CU15 y limpia solo sus propios IDs. */
final class CancelacionPrueba
{
    public readonly ReprogramacionPrueba $r;
    public readonly App\Services\CancelacionService $servicio;
    public function __construct(public readonly PDO $pdo)
    {
        if(!$pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='citas_historial' AND column_name='motivo_cancelacion')")->fetchColumn()){
            throw new RuntimeException('CU16 requiere migración 006 previamente autorizada y aplicada.');
        }
        $this->r=new ReprogramacionPrueba($pdo);
        $this->servicio=new App\Services\CancelacionService($pdo,new App\Repositories\CitaRepository($pdo));
    }
    public function datos(int $id,string $motivo=''): array
    {
        return ['accion'=>'confirmar','original'=>$this->servicio->obtener($id)['original'],'motivo_cancelacion'=>$motivo];
    }
    public function eventos(int $id): array
    {
        $q=$this->pdo->prepare('SELECT * FROM citas_historial WHERE cita_id=:id ORDER BY id');$q->execute(['id'=>$id]);return $q->fetchAll();
    }
    public function limpiar(): void{$this->r->limpiar();}
}
