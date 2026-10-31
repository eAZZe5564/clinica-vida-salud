<?php
declare(strict_types=1);
require_once __DIR__ . '/Cu15Local.php';
require_once __DIR__ . '/CitasPrueba.php';

final class ReprogramacionPrueba
{
    public readonly CitasPrueba $t;
    public readonly App\Services\ReprogramacionService $servicio;
    public function __construct(public readonly PDO $pdo)
    {
        if(!$pdo->query("SELECT to_regclass('public.citas_historial')")->fetchColumn()){
            throw new RuntimeException('PENDIENTE: migración 005 local requiere autorización.');
        }
        $this->t=new CitasPrueba($pdo);
        $this->servicio=new App\Services\ReprogramacionService($pdo,new App\Repositories\CitaRepository($pdo),$this->t->disponibilidad);
    }
    public function datos(int $id,string $hora,string $fecha=''): array
    {
        return ['original'=>$this->servicio->obtener($id)['original'],'fecha'=>$fecha?:$this->t->lunes->format('Y-m-d'),'hora_inicio'=>$hora];
    }
    public function fila(int $id): array
    {
        $q=$this->pdo->prepare('SELECT * FROM citas WHERE id=:id');$q->execute(['id'=>$id]);return $q->fetch();
    }
    public function limpiar(): void
    {
        foreach($this->t->fixture->medicos as $id){
            $q=$this->pdo->prepare('DELETE FROM citas_historial h USING citas c WHERE h.cita_id=c.id AND c.medico_id=:m');$q->execute(['m'=>$id]);
        }
        $this->t->fixture->limpiar();
    }
}

/** Delega a PostgreSQL real, inyectando un fallo en una sentencia concreta, sin DDL. */
final class Cu15FalloPDO extends PDO
{
    public function __construct(private PDO $real,private string $fallar) {}
    public function inTransaction(): bool{return $this->real->inTransaction();}
    public function beginTransaction(): bool{return $this->real->beginTransaction();}
    public function commit(): bool{return $this->real->commit();}
    public function rollBack(): bool{return $this->real->rollBack();}
    public function exec(string $statement): int|false{return $this->real->exec($statement);}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false
    {return $fetchMode===null?$this->real->query($query):$this->real->query($query,$fetchMode,...$fetchModeArgs);}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if(str_starts_with($query,$this->fallar)){throw new PDOException('Fallo ficticio de persistencia CU15',23514);}
        return $this->real->prepare($query,$options);
    }
}
