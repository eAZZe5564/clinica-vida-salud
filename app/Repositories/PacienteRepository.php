<?php
declare(strict_types=1);
namespace App\Repositories;

use PDO;
use App\Models\BusquedaPaciente;

/** Todas las consultas del módulo; nunca elimina pacientes ni modifica sus citas. */
final class PacienteRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function buscar(int $id): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM pacientes WHERE id = :id');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }

    public function listar(string $busqueda, int $pagina, bool $soloActivos = false): array
    {
        $condiciones=[];$params=[];
        foreach(BusquedaPaciente::terminos($busqueda) as $indice=>$termino){
            $campos=[];
            // Cada término puede coincidir en un campo distinto, pero todos deben pertenecer a la misma fila.
            foreach(['nombres','apellidos','documento_identidad'] as $campo){
                $columna=$campo==='documento_identidad'?"regexp_replace(documento_identidad, '[[:space:]]+', '', 'g')":$campo;
                $parametro=$campo.'_'.$indice;
                $campos[]="translate(lower($columna), 'áéíóúü' || chr(769) || chr(776), 'aeiouu') LIKE :$parametro ESCAPE '!'";
                // ESCAPE explícito: %, _ y ! se buscan como texto, nunca como comodines del usuario.
                $params[$parametro]='%'.strtr($termino,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            }
            $condiciones[]='('.implode(' OR ',$campos).')';
        }
        $where=$condiciones?' WHERE '.implode(' AND ',$condiciones):'';
        if ($soloActivos) { $where .= $where === '' ? ' WHERE activo' : ' AND activo'; }
        $q = $this->pdo->prepare('SELECT count(*) FROM pacientes' . $where);
        $q->execute($params);
        $total = (int) $q->fetchColumn();
        $paginas = max(1, (int) ceil($total / 20));
        $pagina = max(1, min($pagina, $paginas));
        $q = $this->pdo->prepare('SELECT id, nombres, apellidos, documento_identidad, telefono, activo
            FROM pacientes' . $where . ' ORDER BY apellidos, nombres, id LIMIT 20 OFFSET :offset');
        foreach ($params as $key => $value) { $q->bindValue($key, $value); }
        $q->bindValue('offset', ($pagina - 1) * 20, PDO::PARAM_INT);
        $q->execute();
        return ['filas' => $q->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    public function documentoExiste(string $documento, ?int $excepto): bool
    {
        $q = $this->pdo->prepare("SELECT 1 FROM pacientes
            WHERE upper(regexp_replace(documento_identidad, '[[:space:]]+', '', 'g')) = :documento
            AND id <> :excepto LIMIT 1");
        $q->execute(['documento' => $documento, 'excepto' => $excepto ?? 0]);
        return (bool) $q->fetchColumn();
    }

    public function guardar(array $datos, ?int $id): int
    {
        // usuario_id, id y estado no se toman de formularios de edición.
        $campos = ['nombres', 'apellidos', 'documento_identidad', 'fecha_nacimiento', 'sexo', 'telefono', 'correo_contacto', 'direccion'];
        $params = array_intersect_key($datos, array_flip($campos));
        if ($id === null) {
            $sql = 'INSERT INTO pacientes (' . implode(', ', $campos) . ') VALUES (:' . implode(', :', $campos) . ') RETURNING id';
        } else {
            $asignaciones = array_map(static fn($c) => "$c = :$c", $campos);
            $sql = 'UPDATE pacientes SET ' . implode(', ', $asignaciones) . ', actualizado_en = CURRENT_TIMESTAMP WHERE id = :id RETURNING id';
            $params['id'] = $id;
        }
        // El savepoint permite recuperar una colisión UNIQUE sin abortar una transacción de pruebas.
        $propia = !$this->pdo->inTransaction();
        if ($propia) { $this->pdo->beginTransaction(); }
        $this->pdo->exec('SAVEPOINT guardar_paciente');
        try {
            $q = $this->pdo->prepare($sql);
            $q->execute($params);
            $resultado = $q->fetchColumn();
            if ($resultado === false) { throw new \OutOfBoundsException('Paciente inexistente.'); }
            $this->pdo->exec('RELEASE SAVEPOINT guardar_paciente');
            if ($propia) { $this->pdo->commit(); }
            return (int) $resultado;
        } catch (\Throwable $error) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT guardar_paciente');
            $this->pdo->exec('RELEASE SAVEPOINT guardar_paciente');
            if ($propia) { $this->pdo->rollBack(); }
            throw $error;
        }
    }

    public function cambiarEstado(int $id, bool $activo): bool
    {
        $q = $this->pdo->prepare('UPDATE pacientes SET activo = :activo, actualizado_en = CURRENT_TIMESTAMP WHERE id = :id');
        $q->bindValue('activo', $activo, PDO::PARAM_BOOL);
        $q->bindValue('id', $id, PDO::PARAM_INT);
        $q->execute();
        return $q->rowCount() > 0;
    }

    public function citas(int $id, int $pagina): array
    {
        $q = $this->pdo->prepare('SELECT count(*) FROM citas WHERE paciente_id = :id');
        $q->execute(['id' => $id]);
        $total = (int) $q->fetchColumn();
        $paginas = max(1, (int) ceil($total / 20));
        $pagina = max(1, min($pagina, $paginas));
        $q = $this->pdo->prepare('SELECT c.id, c.fecha_hora_inicio, c.estado, m.nombres, m.apellidos
            FROM citas c JOIN medicos m ON m.id = c.medico_id
            WHERE c.paciente_id = :id ORDER BY c.fecha_hora_inicio DESC, c.id DESC LIMIT 20 OFFSET :offset');
        $q->bindValue('id', $id, PDO::PARAM_INT);
        $q->bindValue('offset', ($pagina - 1) * 20, PDO::PARAM_INT);
        $q->execute();
        return ['filas' => $q->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }
}
