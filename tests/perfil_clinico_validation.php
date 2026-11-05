<?php
declare(strict_types=1);
require __DIR__.'/PerfilClinicoPrueba.php';
use App\Services\PerfilClinicoService;
use App\Models\PerfilClinico;
$total=0;
clinicoAssert(PerfilClinicoService::texto('','campo',10)===null,'opcional vacío es NULL');
clinicoAssert(PerfilClinicoService::texto(" \tÁrbol\nÑandú\r\n ",'campo',40)==='Árbol Ñandú','UTF-8 y espacios normalizados sin inferencias');
clinicoAssert(PerfilClinicoService::texto(str_repeat('ñ',120),'campo',120)!==null,'límite Unicode por caracteres');
clinicoAssert(PerfilClinicoService::texto('<script>FICTICIO</script>','campo',40)==='<script>FICTICIO</script>','texto se conserva para escape en presentación');
foreach([null,[],false,1,new stdClass(),"\xC3\x28","a\x00b","a\x7Fb","a\u{200B}b",str_repeat('a',121)] as $v){
    clinicoRechaza(fn()=>PerfilClinicoService::texto($v,'campo',120),422,'tipo, codificación, controles o exceso rechazados');
}
foreach([''," \t\n "] as $v){clinicoRechaza(fn()=>PerfilClinicoService::texto($v,'campo',120,true),422,'obligatorio no admite solo espacios');}
clinicoAssert(count(PerfilClinico::GRUPOS)===8&&!in_array('',PerfilClinico::GRUPOS,true),'desconocido no es un grupo predeterminado');
clinicoAssert(array_keys(PerfilClinico::ESTADOS)===['no_registrado','sin_conocidas','con_registros'],'estados de conocimiento separados');
echo "Aprobadas: $total. Validación CU23 sin datos reales ni escrituras.\n";
