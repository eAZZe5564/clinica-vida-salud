<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';
use App\Services\MedicoService;
use App\Services\EspecialidadService;
use App\Services\CatalogoValidacion;
use App\Services\CatalogoValidacionException;
// Solo métodos validar, sin usar dependencias ni conectar a PostgreSQL.
$medicos = (new ReflectionClass(MedicoService::class))->newInstanceWithoutConstructor();
$especialidades = (new ReflectionClass(EspecialidadService::class))->newInstanceWithoutConstructor();
$total = 0;
$ok = static function (bool $valor, string $nombre) use (&$total): void {
    if (!$valor) { throw new RuntimeException($nombre); } $total++; echo "OK: $nombre\n";
};
$base = ['nombres' => 'María José', 'apellidos' => 'Prueba', 'numero_colegiatura' => ' 00 ab-12 ', 'telefono' => '+591 7000-0000', 'correo_contacto' => 'prueba@example.invalid'];
$ok($medicos->validar($base)['numero_colegiatura'] === '00AB-12', 'registro normalizado conserva ceros');
$ok($medicos->validar(array_replace($base, ['telefono' => '', 'correo_contacto' => '']))['telefono'] === null, 'contacto opcional vacío NULL');
foreach ([['nombres',' '],['apellidos','123'],['numero_colegiatura',' '],['numero_colegiatura','<script>'],['nombres',[]],['telefono','123'],['telefono','1234567abc'],['telefono',str_repeat('1',16)],['correo_contacto','invalido'],['apellidos',str_repeat('a',101)],['nombres',"Ana\0"]] as [$campo,$valor]) {
    try { $medicos->validar(array_replace($base, [$campo => $valor])); $ok(false, "rechaza $campo"); }
    catch (CatalogoValidacionException $e) { $ok(isset($e->errores[$campo]), "validación médico: $campo"); }
}
$normal = $especialidades->validar(['nombre' => '  Medicina   Interna  ']);
$ok($normal['nombre'] === 'Medicina Interna' && $normal['descripcion'] === null, 'especialidad normaliza espacios y opcional');
foreach ([['nombre',' '],['nombre',[]],['nombre',str_repeat('a',101)],['descripcion',str_repeat('a',251)]] as [$campo,$valor]) {
    try { $especialidades->validar(array_replace(['nombre' => 'Prueba'], [$campo => $valor])); $ok(false,'invalidez'); }
    catch (CatalogoValidacionException $e) { $ok(isset($e->errores[$campo]), "validación especialidad: $campo"); }
}
foreach ([['1','1'], ['-1'], ['1x'], [['1']], '1'] as $entrada) {
    try { CatalogoValidacion::ids($entrada); $ok(false,'relaciones inválidas'); }
    catch (CatalogoValidacionException $e) { $ok(isset($e->errores['especialidades']), 'rechaza lista o ID inválido/duplicado'); }
}
$ok(CatalogoValidacion::ids(['3','1']) === [1,3], 'IDs ordenados y enteros');
$ok(App\Models\Medico::codigo(1234567) === 'MED-1234567', 'código no se trunca');
echo "Aprobadas: $total. Sin conexión ni escrituras en PostgreSQL.\n";
