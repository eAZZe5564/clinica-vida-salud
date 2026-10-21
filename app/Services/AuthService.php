<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use App\Repositories\UsuarioRepository;

/** Verifica credenciales y devuelve únicamente la identidad activa del usuario. */
final class AuthService
{
    // Hash fijo para que un correo inexistente también ejecute una verificación costosa.
    private const HASH_COMPARACION = '$2y$12$x4hWtrS2xm0QtTKGYpNFp.i63hmjMmo2ZWA9p3CpN3apXewWYJwSu';

    public function __construct(private readonly UsuarioRepository $usuarios)
    {
    }

    public function autenticar(string $correo, string $contrasena): ?Usuario
    {
        // Bcrypt solo procesa 72 bytes; se rechaza el exceso para evitar truncamientos.
        if (strlen($contrasena) > 72) {
            password_verify($contrasena, self::HASH_COMPARACION);
            return null;
        }

        $fila = $this->usuarios->buscarPorCorreo(mb_strtolower(trim($correo), 'UTF-8'));
        $hash = $fila['hash_contrasena'] ?? self::HASH_COMPARACION;
        $contrasenaValida = password_verify($contrasena, $hash);

        if (!$fila || !$fila['activo'] || !$contrasenaValida) {
            return null;
        }

        if (password_needs_rehash($fila['hash_contrasena'], PASSWORD_DEFAULT)) {
            $this->usuarios->actualizarHash(
                (int) $fila['id'],
                password_hash($contrasena, PASSWORD_DEFAULT)
            );
        }

        $this->usuarios->registrarAcceso((int) $fila['id']);
        return Usuario::desdeFila($fila);
    }

    public function usuarioActivo(int $id): ?Usuario
    {
        $fila = $this->usuarios->buscarActivoPorId($id);
        return $fila ? Usuario::desdeFila($fila) : null;
    }
}
