<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RolRepository;
use App\Repositories\UsuarioRepository;
use DomainException;
use PDO;
use PDOException;

/** Aplica validaciones y reglas de integridad de las cuentas y roles fijos. */
final class UsuarioService
{
    private const ROLES_PERMITIDOS = ['administrador', 'recepcionista', 'medico', 'paciente'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly UsuarioRepository $usuarios,
        private readonly RolRepository $roles
    ) {
    }

    public function roles(): array
    {
        return $this->roles->listar();
    }

    public function listar(string $busqueda = ''): array
    {
        return $this->usuarios->listar(trim($busqueda));
    }

    public function obtener(int $id): array
    {
        $usuario = $this->usuarios->buscarPorId($id);
        if (!$usuario) {
            throw new DomainException('No se encontró la cuenta solicitada.');
        }

        return $usuario;
    }

    public function crear(string $correo, string $contrasena, int $rolId): int
    {
        $correo = $this->validarCorreo($correo);
        $hash = $this->validarYHashContrasena($contrasena);
        $rol = $this->validarRol($rolId);

        try {
            return $this->usuarios->crear($correo, $hash, (int) $rol['id']);
        } catch (PDOException $error) {
            if ($error->getCode() === '23505') {
                throw new DomainException('Ya existe una cuenta con ese correo.');
            }
            throw $error;
        }
    }

    public function actualizar(
        int $id,
        string $correo,
        int $rolId,
        ?string $contrasena
    ): void {
        $correo = $this->validarCorreo($correo);
        $rol = $this->validarRol($rolId);
        $hash = ($contrasena ?? '') === ''
            ? null
            : $this->validarYHashContrasena((string) $contrasena);

        $this->enTransaccion(function () use ($id, $correo, $rolId, $rol, $hash): void {
            $this->usuarios->bloquearCambiosAdministrativos();
            $actual = $this->usuarios->buscarPorId($id, true);
            if (!$actual) {
                throw new DomainException('No se encontró la cuenta solicitada.');
            }

            $cambiaAdminActivo = $actual['rol_nombre'] === 'administrador'
                && $actual['activo']
                && ($rol['nombre'] !== 'administrador');
            if ($cambiaAdminActivo && $this->usuarios->contarAdministradoresActivos() <= 1) {
                throw new DomainException('No se puede quitar el último administrador activo.');
            }

            try {
                $this->usuarios->actualizar($id, $correo, $rolId, $hash);
            } catch (PDOException $error) {
                if ($error->getCode() === '23505') {
                    throw new DomainException('Ya existe una cuenta con ese correo.');
                }
                throw $error;
            }
        });
    }

    public function cambiarEstado(int $id, int $actorId, bool $activo): void
    {
        if ($id === $actorId && !$activo) {
            throw new DomainException('No puedes desactivar tu propia cuenta.');
        }

        $this->enTransaccion(function () use ($id, $activo): void {
            $this->usuarios->bloquearCambiosAdministrativos();
            $usuario = $this->usuarios->buscarPorId($id, true);
            if (!$usuario) {
                throw new DomainException('No se encontró la cuenta solicitada.');
            }

            if (
                !$activo
                && $usuario['activo']
                && $usuario['rol_nombre'] === 'administrador'
                && $this->usuarios->contarAdministradoresActivos() <= 1
            ) {
                throw new DomainException('No se puede desactivar el último administrador activo.');
            }

            $this->usuarios->cambiarEstado($id, $activo);
        });
    }

    public function crearAdministradorInicial(string $correo, string $contrasena): int
    {
        $correo = $this->validarCorreo($correo);
        $hash = $this->validarYHashContrasena($contrasena);
        $rol = $this->roles->buscarPorNombre('administrador');
        if (!$rol) {
            throw new DomainException('No existe el rol administrador en la base de datos.');
        }

        return $this->enTransaccion(function () use ($correo, $hash, $rol): int {
            $this->usuarios->bloquearCambiosAdministrativos();
            // El alta inicial solo se permite en una base sin cuentas; no hay instalador web.
            if ($this->usuarios->contarUsuarios() !== 0) {
                throw new DomainException(
                    'La inicialización solo se permite cuando todavía no hay cuentas.'
                );
            }

            return $this->usuarios->crear($correo, $hash, (int) $rol['id']);
        });
    }

    private function validarCorreo(string $correo): string
    {
        $correo = mb_strtolower(trim($correo), 'UTF-8');
        if (strlen($correo) > 254 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Ingresa un correo electrónico válido.');
        }

        return $correo;
    }

    private function validarRol(int $rolId): array
    {
        if ($rolId <= 0) {
            throw new DomainException('Selecciona un rol válido.');
        }

        $rol = $this->roles->buscarPorId($rolId);
        if (!$rol || !in_array($rol['nombre'], self::ROLES_PERMITIDOS, true)) {
            throw new DomainException('Selecciona uno de los roles disponibles.');
        }

        return $rol;
    }

    private function validarYHashContrasena(string $contrasena): string
    {
        PoliticaContrasena::validar($contrasena);
        return password_hash($contrasena, PASSWORD_DEFAULT);
    }

    private function enTransaccion(callable $operacion): mixed
    {
        $iniciadaAqui = !$this->pdo->inTransaction();
        if ($iniciadaAqui) {
            $this->pdo->beginTransaction();
        }

        try {
            $resultado = $operacion();
            if ($iniciadaAqui) {
                $this->pdo->commit();
            }
            return $resultado;
        } catch (\Throwable $error) {
            if ($iniciadaAqui && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
