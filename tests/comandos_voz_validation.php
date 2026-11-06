<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Config/bootstrap.php';
use App\Services\CatalogoVoz;
$total=0;$ok=static function(bool $v,string $m)use(&$total):void{if(!$v){throw new RuntimeException($m);}$total++;};
$catalogos=[];
foreach(['administrador','recepcionista','medico','paciente','sin_vinculo'] as $rol){
    $c=CatalogoVoz::paraRol($rol==='sin_vinculo'?'paciente':$rol,$rol==='paciente');$catalogos[$rol]=$c;
    $ok(count(array_unique(array_column($c,'id')))===count($c),'IDs únicos por catálogo');
    foreach($c as $comando){
        $ok($comando['ruta']===null||str_starts_with($comando['ruta'],'/'),'solo rutas internas cerradas');
        $ok(!preg_match('#clinico|alergia|antecedente|laboratorio|cancelar|reprogramar|confirmar|vincular|logout|exportar#',$comando['ruta']??''),'sin rutas clínicas, escrituras, logout ni descargas');
    }
}
$ok(array_column($catalogos['administrador'],'id')===array_column($catalogos['recepcionista'],'id'),'roles administrativos conservan el mismo alcance de consultas');
$ok(array_column($catalogos['medico'],'id')===['inicio','ayuda','comandos','agenda','hoy'],'médico solo agenda propia y orientación');
$ok(array_column($catalogos['sin_vinculo'],'id')===['inicio','ayuda','comandos'],'cuenta sin vínculo no recibe comandos de citas');
$ok(CatalogoVoz::paraRol('invitado')===[],'rol desconocido sin catálogo');
$js=file_get_contents(dirname(__DIR__).'/public/assets/js/comandos-voz.mjs');
$ok(!preg_match('/MediaRecorder|speechSynthesis|localStorage|sessionStorage|indexedDB|sendBeacon|fetch\s*\(|XMLHttpRequest|innerHTML/',$js),'sin audio almacenado, síntesis, persistencia, transmisión de texto o HTML dinámico');
$p=proc_open(['node',__DIR__.'/comandos_voz_validation.mjs'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file','NUL','a']],$pipes,dirname(__DIR__));
if(!is_resource($p)){throw new RuntimeException('No se pudo iniciar validación JavaScript.');}
fwrite($pipes[0],json_encode($catalogos,JSON_THROW_ON_ERROR));fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($p);$r=json_decode($out,true,512,JSON_THROW_ON_ERROR);
if($code!==0||isset($r['error'])){throw new RuntimeException('Resolución de comandos no satisfactoria.');}$total+=(int)$r['assertions'];
echo "Aprobadas: $total. Catálogo y resolución pura; sin PostgreSQL ni micrófono.\n";
