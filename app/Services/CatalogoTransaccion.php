<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

/** Serializa cambios del catálogo y asociaciones para evitar carreras entre reglas. */
final class CatalogoTransaccion
{
    public static function ejecutar(PDO $pdo, callable $operacion): mixed
    {
        $propia = !$pdo->inTransaction();
        if ($propia) { $pdo->beginTransaction(); }
        $pdo->exec('SAVEPOINT catalogo_medico');
        try {
            $pdo->query("SELECT pg_advisory_xact_lock(hashtext('clinica_medicos_especialidades'))");
            $resultado = $operacion();
            $pdo->exec('RELEASE SAVEPOINT catalogo_medico');
            if ($propia) { $pdo->commit(); }
            return $resultado;
        } catch (\Throwable $error) {
            $pdo->exec('ROLLBACK TO SAVEPOINT catalogo_medico');
            $pdo->exec('RELEASE SAVEPOINT catalogo_medico');
            if ($propia) { $pdo->rollBack(); }
            throw $error;
        }
    }
}
