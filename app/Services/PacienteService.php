<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\PacienteRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;

/** Validación central de CU04–CU06; el CI sigue siendo obligatorio según el esquema. */
final class PacienteService
{
    public const SEXOS = ['femenino' => 'Femenino', 'masculino' => 'Masculino', 'no_especificado' => 'No especificado'];

    public function __construct(private readonly PacienteRepository $pacientes) {}

    public static function hoy(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('America/La_Paz')))->format('Y-m-d');
    }

    public static function normalizarDocumento(string $valor): string
    {
        // Preserva ceros y signos; únicamente elimina espacios y unifica mayúsculas.
        return mb_strtoupper(preg_replace('/\s+/u', '', $valor) ?? '', 'UTF-8');
    }

    public function validar(array $entrada): array
    {
        $datos = [];
        $errores = [];
        $limites = ['nombres' => 100, 'apellidos' => 100, 'documento_identidad' => 40,
            'telefono' => 30, 'correo_contacto' => 254, 'direccion' => 250, 'sexo' => 20, 'fecha_nacimiento' => 10];
        foreach ($limites as $campo => $limite) {
            $valor = $entrada[$campo] ?? '';
            if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8')) {
                $errores[$campo] = 'El valor enviado no es válido.';
                $valor = '';
            }
            $valor = preg_replace('/^\s+|\s+$/u', '', $valor) ?? '';
            if ($campo === 'documento_identidad') { $valor = self::normalizarDocumento($valor); }
            if (mb_strlen($valor, 'UTF-8') > $limite || preg_match('/[\x00-\x1F\x7F]/u', $valor)) {
                $errores[$campo] = "Usa un texto válido de hasta $limite caracteres.";
            }
            $datos[$campo] = $valor === '' ? null : $valor;
        }
        foreach (['nombres', 'apellidos', 'documento_identidad', 'fecha_nacimiento', 'sexo'] as $campo) {
            if ($datos[$campo] === null) { $errores[$campo] = 'Este campo es obligatorio.'; }
        }
        if ($datos['sexo'] !== null && !isset(self::SEXOS[$datos['sexo']])) {
            $errores['sexo'] = 'Selecciona una opción disponible.';
        }
        if ($datos['correo_contacto'] !== null && filter_var($datos['correo_contacto'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['correo_contacto'] = 'Ingresa un correo electrónico válido.';
        }
        $fecha = $datos['fecha_nacimiento'];
        if ($fecha !== null) {
            $dia = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $fecha)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) : false;
            if (!$dia || $dia->format('Y-m-d') !== $fecha || $fecha < '0001-01-01' || $fecha > self::hoy()) {
                $errores['fecha_nacimiento'] = 'Ingresa una fecha válida que no esté en el futuro.';
            }
        }
        if ($errores) { throw new PacienteValidacionException($errores); }
        return $datos;
    }

    public function guardar(array $entrada, ?int $id = null): int
    {
        if ($id !== null) { $this->obtener($id); }
        $datos = $this->validar($entrada);
        if ($this->pacientes->documentoExiste($datos['documento_identidad'], $id)) {
            throw new PacienteValidacionException(['documento_identidad' => 'Ya existe un paciente con ese CI o documento.']);
        }
        try {
            return $this->pacientes->guardar($datos, $id);
        } catch (PDOException $error) {
            // La restricción de PostgreSQL cubre la carrera entre comprobar y guardar.
            if ($error->getCode() === '23505') {
                throw new PacienteValidacionException(['documento_identidad' => 'Ya existe un paciente con ese CI o documento.']);
            }
            throw $error;
        }
    }

    public function obtener(int $id): array
    {
        return $this->pacientes->buscar($id) ?? throw new \OutOfBoundsException('Paciente inexistente.');
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        // Conserva id, usuario_id y todas las citas; no se realiza DELETE.
        if (!$this->pacientes->cambiarEstado($id, $activo)) { throw new \OutOfBoundsException('Paciente inexistente.'); }
    }
}
