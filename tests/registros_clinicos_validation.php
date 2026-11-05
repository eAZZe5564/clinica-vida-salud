<?php
declare(strict_types=1);
require __DIR__.'/RegistroClinicoPrueba.php';
use App\Services\RegistroClinicoService as S;
$total=0;
foreach(['','0001-01-01','1990-05-14','2000-02-29'] as $v){clinicoAssert(S::fecha($v,'fecha','2026-01-01')===($v===''?null:$v),'fecha conocida o desconocida sin inferencia');}
foreach([null,[],false,1,'0000-01-01','2025-02-29','2026-02-30','2026-13-01','2026-00-01','2026-01-00','9999-01-01','26-01-01','2026-1-01',' 2026-01-01','infinity','2026-01-01T00:00:00'] as $v){
    clinicoRechaza(fn()=>S::fecha($v,'fecha','2026-01-01'),422,'fechas inválidas o futuras rechazadas');
}
foreach([''=>null,'0'=>'0','-0.000000'=>'0','12.500000'=>'12.5','-123456789012.123456'=>'-123456789012.123456','0.000001'=>'0.000001'] as $v=>$expected){clinicoAssert(S::decimal((string)$v)===$expected,'decimal conserva precisión sin float');}
foreach([null,[],true,1,1.2,'NaN','INF','-Infinity','1e3','1,5','.5','01','+1','1000000000000','0.1234567','1; DROP TABLE usuarios',' 1','1.'] as $v){clinicoRechaza(fn()=>S::decimal($v),422,'decimal ambiguo o fuera de precisión rechazado');}
echo "Aprobadas: $total. Validación CU24 de fechas y decimales sin escrituras.\n";
