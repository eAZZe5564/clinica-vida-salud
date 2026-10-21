<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';
use App\Services\HorarioService;
use App\Services\CatalogoValidacionException;
$servicio=(new ReflectionClass(HorarioService::class))->newInstanceWithoutConstructor();
$total=0;
$ok=static function(bool $valor,string $nombre)use(&$total):void{if(!$valor){throw new RuntimeException($nombre);} $total++;echo "OK: $nombre\n";};
$base=['medico_id'=>'1','dia_semana'=>'1','hora_inicio'=>'08:00','hora_fin'=>'12:00'];
$ok($servicio->validar($base)['dia_semana']===1,'lunes ISO y datos válidos');
foreach([['medico_id','0'],['medico_id',[]],['dia_semana','8'],['dia_semana','0'],['dia_semana','lunes'],['hora_inicio',''],['hora_inicio',[]],['hora_inicio','24:00'],['hora_inicio','08:15'],['hora_inicio','08:00:01'],['hora_fin','07:30'],['hora_fin','08:00'],['hora_fin','00:00'],['hora_fin','12:10']] as [$campo,$valor]){
    try{$servicio->validar(array_replace($base,[$campo=>$valor]));$ok(false,'rechazo');}catch(CatalogoValidacionException $e){$ok(isset($e->errores[$campo]),'rechaza ' . $campo . ' inválido');}
}
$ok($servicio->validar(array_replace($base,['dia_semana'=>'7','hora_inicio'=>'23:00','hora_fin'=>'23:30']))['dia_semana']===7,'domingo y último tramo permitido');
$ok(App\Models\HorarioAtencion::DURACION_MINUTOS===30,'duración coincide con esquema');
echo "Aprobadas: $total. Sin conexión ni escrituras en PostgreSQL.\n";
