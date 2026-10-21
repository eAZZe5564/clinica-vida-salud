<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\MedicoRepository;
use App\Repositories\EspecialidadRepository;
use PDO;
use PDOException;

final class MedicoService
{
    public function __construct(private readonly PDO $pdo, private readonly MedicoRepository $repo, private readonly EspecialidadRepository $especialidades) {}
    public function validar(array $entrada): array
    {
        $errores = []; $datos = [];
        foreach (['nombres' => 100, 'apellidos' => 100, 'numero_colegiatura' => 60, 'telefono' => 30, 'correo_contacto' => 254] as $campo => $maximo) {
            $datos[$campo] = CatalogoValidacion::texto($entrada[$campo] ?? '', $campo, $maximo,
                in_array($campo, ['nombres','apellidos','numero_colegiatura'], true), $errores);
        }
        foreach (['nombres','apellidos'] as $campo) {
            if ($datos[$campo] !== null && !preg_match('/\p{L}/u', $datos[$campo])) { $errores[$campo] = 'Incluye al menos una letra.'; }
        }
        if ($datos['numero_colegiatura'] !== null) {
            $datos['numero_colegiatura'] = CatalogoValidacion::registro($datos['numero_colegiatura']);
            if (!preg_match('/^[\p{L}\p{N}.\/-]+$/u', $datos['numero_colegiatura']) || mb_strlen($datos['numero_colegiatura']) > 60) {
                $errores['numero_colegiatura'] = 'Usa letras, números, punto, guion o barra; máximo 60 caracteres.';
            }
        }
        if ($datos['telefono'] !== null && (!preg_match('/^\+?[0-9 ()-]+$/D', $datos['telefono']) || strlen(preg_replace('/\D/', '', $datos['telefono'])) < 7 || strlen(preg_replace('/\D/', '', $datos['telefono'])) > 15)) {
            $errores['telefono'] = 'Usa entre 7 y 15 dígitos; se permiten + inicial, espacios, paréntesis y guiones.';
        }
        if ($datos['correo_contacto'] !== null && filter_var($datos['correo_contacto'], FILTER_VALIDATE_EMAIL) === false) { $errores['correo_contacto'] = 'Ingresa un correo válido.'; }
        if ($errores) { throw new CatalogoValidacionException($errores); }
        return $datos;
    }
    public function obtener(int $id): array { return $this->repo->obtener($id) ?? throw new \OutOfBoundsException(); }

    private function validarAsignaciones(array $ids, ?int $id, bool $activo): void
    {
        $actuales = $id === null ? [] : array_map('intval', array_column($this->repo->especialidades($id), 'id'));
        $activas = 0;
        foreach ($ids as $especialidad) {
            $fila = $this->especialidades->obtener($especialidad);
            if (!$fila || (!$fila['activo'] && !in_array($especialidad, $actuales, true))) {
                throw new CatalogoValidacionException(['especialidades' => 'Solo puedes asignar especialidades activas.']);
            }
            if ($fila['activo']) { $activas++; }
        }
        if ($activo && $activas === 0) { throw new CatalogoValidacionException(['especialidades' => 'Un médico activo necesita al menos una especialidad activa.']); }
    }

    public function guardar(array $entrada, ?int $id = null): int
    {
        $datos = $this->validar($entrada);
        $ids = CatalogoValidacion::ids($entrada['especialidades'] ?? []);
        $cuenta = $entrada['usuario_id'] ?? '';
        $usuario = $cuenta === '' ? null : ((is_string($cuenta) || is_int($cuenta)) ? filter_var($cuenta, FILTER_VALIDATE_INT) : false);
        if ($usuario !== null && ($usuario === false || $usuario <= 0)) { throw new CatalogoValidacionException(['usuario_id' => 'Selecciona una cuenta válida.']); }
        $datos['usuario_id'] = $usuario;
        try {
            return CatalogoTransaccion::ejecutar($this->pdo, function () use ($datos, $ids, $id): int {
                $activo = $id === null ? true : $this->obtener($id)['activo'];
                // Mismo bloqueo de cambios de usuarios para comprobar rol y vínculo de forma segura.
                $this->pdo->query("SELECT pg_advisory_xact_lock(hashtext('clinica_vida_salud_admin_users'))");
                if ($datos['usuario_id'] !== null && !$this->repo->cuentaValida($datos['usuario_id'], $id)) {
                    throw new CatalogoValidacionException(['usuario_id' => 'La cuenta debe estar activa, tener rol Médico y no estar vinculada a otro médico.']);
                }
                $this->validarAsignaciones($ids, $id, $activo);
                if ($this->repo->registroExiste($datos['numero_colegiatura'], $id)) {
                    throw new CatalogoValidacionException(['numero_colegiatura' => 'Ya existe un médico con ese registro profesional.']);
                }
                $guardado = $this->repo->guardar($datos, $id);
                $this->repo->asignar($guardado, $ids);
                return $guardado;
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') { throw new CatalogoValidacionException(['numero_colegiatura' => 'El registro o la cuenta seleccionada ya están en uso.', 'usuario_id' => 'Comprueba que la cuenta esté disponible.']); }
            throw $e;
        }
    }
    public function asignar(int $id, mixed $entrada): void
    {
        $ids = CatalogoValidacion::ids($entrada);
        CatalogoTransaccion::ejecutar($this->pdo, function () use ($id, $ids): void {
            $medico = $this->obtener($id);
            $this->validarAsignaciones($ids, $id, $medico['activo']);
            $this->repo->asignar($id, $ids);
        });
    }
    public function cambiarEstado(int $id, bool $activo): void
    {
        CatalogoTransaccion::ejecutar($this->pdo, function () use ($id, $activo): void {
            $this->obtener($id);
            if ($activo) { $this->validarAsignaciones(array_map('intval', array_column($this->repo->especialidades($id), 'id')), $id, true); }
            // Nunca altera citas ni vínculos de especialidad o usuario al desactivar.
            $this->repo->cambiarEstado($id, $activo);
        });
    }
}
