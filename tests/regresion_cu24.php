<?php
declare(strict_types=1);
require __DIR__.'/Cu15Local.php';cu15Conexion();
$nuevas=['registros_clinicos_validation','esquema_cu24_validation','registros_clinicos_integridad','registros_clinicos_integration','registros_clinicos_concurrency','http_registros_clinicos','registros_clinicos_privacidad','registros_clinicos_browser'];
if(isset($argv[1])&&$argv[1]!=='--solo-cu24'&&!in_array($argv[1],$nuevas,true)){throw new RuntimeException('Suite no permitida.');}
$suites=isset($argv[1])?($argv[1]==='--solo-cu24'?$nuevas:[$argv[1]]):['regresion_cu23',...$nuevas];
$dir=__DIR__.'/artifacts/cu24';if(!is_dir($dir)){mkdir($dir,0700,true);}$resultados=[];$total=0;
$wrapper=<<<'PHP'
set_exception_handler(static function(Throwable $e):void{
    $safe=$e instanceof RuntimeException&&!($e instanceof PDOException)&&strlen($e->getMessage())<180
        &&!preg_match('/postgres|password|contras|token|dsn|:\/\//i',$e->getMessage());
    echo 'Fallo de prueba CU24: '.get_class($e).($safe?' / '.$e->getMessage():' / detalle nativo omitido').PHP_EOL;exit(1);
});
$file=$argv[1];$argv=[$file];$argc=1;require $file;
PHP;
foreach($suites as $suite){
    $p=proc_open([PHP_BINARY,'-r',$wrapper,__DIR__.'/'.$suite.'.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($p)){throw new RuntimeException('No se pudo ejecutar una suite.');}fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
    file_put_contents($dir.'/'.$suite.'.log',$out.$err);preg_match_all('/Aprobadas:\s*(\d+)/u',$out,$m);$n=$m[1]?(int)end($m[1]):0;
    $resultados[$suite]=['salida'=>$code,'comprobaciones'=>$n];file_put_contents($dir.'/resultados.json',json_encode($resultados,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    if($code!==0||$n===0){fwrite(STDERR,"FALLO: $suite; revisar diagnóstico local ignorado. No publicar artefactos.\n");exit(1);}
    $total+=$n;echo "$suite: $n aprobadas.\n";
}
echo "Aprobadas: $total. Ejecución local CU24; complementar lector PDF y PowerShell sin red si se ejecutó la regresión CU22.\n";
