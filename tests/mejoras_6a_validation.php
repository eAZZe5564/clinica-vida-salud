<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Config/bootstrap.php';
use App\Models\BusquedaPaciente;
use App\Services\PoliticaContrasena;
use App\Services\ContrasenaValidacionException;
$total=0;$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}$total++;echo "OK: $n\n";};
foreach([
    ['Ab1!xyz','longitud'],['abcdef1!','mayuscula'],['ABCDEF1!','minuscula'],['Abcdefg!','numero'],
    ['Abcdef12','simbolo'],['Abcdef12 ','simbolo'],["Abcdef12\u{2003}",'simbolo'],
    ['Ab1!'.str_repeat('x',69),'maximo'],['Ab1!'.str_repeat('é',35),'maximo'],["Ab1!abc\0",'control'],
    ["Ab1!abc\n",'control'],["Ab1!abc\xff",'formato'],[[], 'formato'],[null,'formato'],['','longitud'],
] as $i=>[$valor,$campo]){
    try{PoliticaContrasena::validar($valor);$ok(false,'se esperaba rechazo');}
    catch(ContrasenaValidacionException $e){$ok(isset($e->errores[$campo]),'requisito de contraseña '.$i);if(is_string($valor)&&strlen($valor)>2){$ok(!str_contains($e->getMessage(),$valor),'error no reproduce contraseña '.$i);}}
}
foreach(['Ab1!abcd','Ab1!'.str_repeat('x',68),'Ab1!'.str_repeat('é',34),'Árbol١!xx','Ab1😷xxxx'] as $i=>$valor){PoliticaContrasena::validar($valor);$ok(true,'contraseña válida ASCII/Unicode/límite '.$i);}
$ok(BusquedaPaciente::normalizar("  María\t  Pérez \n 1234567 ")==='María Pérez 1234567','normaliza separaciones');
$ok(BusquedaPaciente::terminos('PÉREZ María MARÍA')===['perez','maria'],'términos sin tildes, minúsculas y duplicados');
$ok(BusquedaPaciente::terminos("Mari\u{0301}a Pe\u{0301}rez")===['maria','perez'],'acentos combinados');
$ok(BusquedaPaciente::terminos('Muñoz')===['muñoz'],'ñ conserva su identidad');
$ok(mb_strlen(BusquedaPaciente::normalizar(str_repeat('á',150)))===100,'límite por caracteres Unicode');
foreach([[],null,42,"invalido\xff","hola\0"] as $i=>$entrada){$ok(BusquedaPaciente::normalizar($entrada)==='','entrada de búsqueda no válida '.$i);}
$ok(BusquedaPaciente::terminos('   ')===[],'búsqueda vacía');
echo "Aprobadas: $total. Validaciones sin base de datos.\n";
