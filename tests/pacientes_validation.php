<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';
use App\Services\PacienteService;
use App\Services\PacienteValidacionException;

// El repositorio no se invoca: estas pruebas no conectan ni escriben en PostgreSQL.
$repositorio = (new ReflectionClass(App\Repositories\PacienteRepository::class))->newInstanceWithoutConstructor();
$servicio = new PacienteService($repositorio);
$base = ['nombres' => 'Ana', 'apellidos' => 'Prueba', 'documento_identidad' => ' 001 ab ',
    'fecha_nacimiento' => '2000-02-29', 'sexo' => 'femenino'];
$total = 0;
$ok = static function (bool $valor, string $nombre) use (&$total): void {
    if (!$valor) { throw new RuntimeException($nombre); }
    $total++; echo "OK: $nombre\n";
};
$validado = $servicio->validar($base);
$ok($validado['documento_identidad'] === '001AB', 'CI normalizado conserva ceros');
$ok($validado['correo_contacto'] === null && $validado['direccion'] === null, 'opcionales vacíos se guardan como NULL');
foreach ([
    ['nombres', '   '], ['apellidos', "\u{00A0}"], ['documento_identidad', ' '],
    ['fecha_nacimiento', '2999-01-01'], ['fecha_nacimiento', '2023-02-29'],
    ['fecha_nacimiento', '0000-01-01'], ['correo_contacto', 'invalido'],
    ['sexo', 'opcion-inventada'], ['nombres', ['array']], ['direccion', str_repeat('a', 251)],
    ['telefono', "123\0"],
] as [$campo, $valor]) {
    try { $servicio->validar(array_replace($base, [$campo => $valor])); $ok(false, "rechaza $campo"); }
    catch (PacienteValidacionException $e) { $ok(isset($e->errores[$campo]), "rechaza $campo inválido"); }
}
$ok($servicio->validar(array_replace($base, ['fecha_nacimiento' => PacienteService::hoy()]))['fecha_nacimiento'] === PacienteService::hoy(), 'acepta nacimiento hoy en La Paz');
$ok(App\Models\Paciente::codigo(1234567) === 'PAC-1234567', 'código no se trunca ni depende de contar filas');
echo "Aprobadas: $total. Sin escrituras en base.\n";
