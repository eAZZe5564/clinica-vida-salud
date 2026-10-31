<?php
declare(strict_types=1);
require_once __DIR__.'/CancelacionPrueba.php';

/** Fixtures locales compartidos, sin crear datos al construir el objeto. */
final class ConfirmacionPrueba
{
    public readonly CancelacionPrueba $c;
    public readonly App\Services\ConfirmacionService $servicio;
    public function __construct(public readonly PDO $pdo)
    {
        if(!$pdo->query("SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='public.citas'::regclass AND conname='citas_estado_valido' AND position('confirmada' IN pg_get_constraintdef(oid))>0)")->fetchColumn()){
            throw new RuntimeException('CU17 requiere migración 007 previamente autorizada y aplicada.');
        }
        $this->c=new CancelacionPrueba($pdo);
        $this->servicio=new App\Services\ConfirmacionService($pdo,new App\Repositories\CitaRepository($pdo));
    }
    public function datos(int $id): array{return ['accion'=>'confirmar','original'=>$this->servicio->obtener($id)['original']];}
    public function limpiar(): void{$this->c->limpiar();}
}
