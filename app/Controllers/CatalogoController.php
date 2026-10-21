<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Services\CatalogoValidacionException;
use PDO;

/** Presentación y permisos comunes del catálogo, reutilizando autenticación existente. */
abstract class CatalogoController
{
    protected PDO $pdo;
    protected AuthMiddleware $middleware;
    protected array $actor;
    public function __construct(bool $soloAdministrador, array $rolesConsulta = ['administrador','recepcionista'])
    {
        $this->pdo = Database::connection();
        $this->middleware = new AuthMiddleware(new AuthService(new UsuarioRepository($this->pdo)));
        $this->actor = $this->middleware->exigirAutenticacion();
        $this->middleware->exigirRoles($this->actor, $soloAdministrador ? ['administrador'] : $rolesConsulta);
    }
    protected function escritura(): void
    {
        $this->administrador(); $this->middleware->exigirCsrf();
    }
    protected function administrador(): void { $this->middleware->exigirRol($this->actor, 'administrador'); }
    protected function pagina(string $vista, string $titulo, array $datos = []): void
    {
        extract($datos, EXTR_SKIP);
        $usuario = $this->actor;
        $esAdmin = $this->actor['rol'] === 'administrador';
        $contenido = dirname(__DIR__) . '/Views/' . $vista . '.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }
    protected function entrada(string $clave, int $maximo = 100): string
    {
        return is_string($_GET[$clave] ?? null) ? mb_substr(trim($_GET[$clave]), 0, $maximo) : '';
    }
    protected function numero(string $clave): ?int
    {
        $valor = $_GET[$clave] ?? '';
        $id = is_string($valor) ? filter_var($valor, FILTER_VALIDATE_INT) : false;
        return $id !== false && $id > 0 ? $id : null;
    }
    protected function estadoEnviado(): bool
    {
        if (!is_string($_POST['activo'] ?? null) || !in_array($_POST['activo'], ['0','1'], true)) {
            throw new CatalogoValidacionException(['activo' => 'Selecciona un estado válido.']);
        }
        return $_POST['activo'] === '1';
    }
    protected function datosEnviados(array $campos): array
    {
        $datos = [];
        foreach ($campos as $campo) { $datos[$campo] = is_string($_POST[$campo] ?? null) ? mb_substr($_POST[$campo], 0, 1000) : ''; }
        return $datos;
    }
}
