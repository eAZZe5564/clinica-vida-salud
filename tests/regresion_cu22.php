<?php
declare(strict_types=1);
require __DIR__.'/Cu15Local.php';cu15Conexion();
$suites=['regresion_cu21','exportacion_csv_validation','exportacion_pdf_validation','http_exportacion_csv','http_exportacion_pdf','exportaciones_integration','exportaciones_browser','exportaciones_docker_validation'];
if(isset($argv[1])){if(!in_array($argv[1],$suites,true)){throw new RuntimeException('Suite no permitida.');}$suites=[$argv[1]];}
$dir=__DIR__.'/artifacts/cu22';if(!is_dir($dir)){mkdir($dir,0700,true);}$resultados=[];$total=0;
$wrapper=<<<'PHP'
set_exception_handler(static function(Throwable $e):void{
    $safe=$e instanceof RuntimeException && !($e instanceof PDOException) && strlen($e->getMessage())<200 && !preg_match('/postgres|password|contras|token|dsn|:\/\//i',$e->getMessage());
    fwrite(STDERR,'Fallo de prueba: '.get_class($e).($safe?' / '.$e->getMessage():' / diagnostico nativo oculto').PHP_EOL);exit(1);
});
$file=$argv[1];$argv=[$file];$argc=1;require $file;
PHP;
foreach($suites as $suite){
    $p=proc_open([PHP_BINARY,'-r',$wrapper,__DIR__.'/'.$suite.'.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($p)){throw new RuntimeException('No se pudo ejecutar una suite.');}fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
    file_put_contents($dir.'/'.$suite.'.log',$out.$err);preg_match_all('/Aprobadas:\s*(\d+)/u',$out,$m);$n=$m[1]?(int)end($m[1]):0;
    $resultados[$suite]=['salida'=>$code,'comprobaciones'=>$n];file_put_contents($dir.'/resultados.json',json_encode($resultados,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    if($code!==0||$n===0){fwrite(STDERR,"FALLO: $suite. Revisar artefacto local ignorado.\n");exit(1);}
    $total+=$n;echo "$suite: $n aprobadas.\n";
}
echo "Aprobadas: $total. Campaña PHP local CU22; complementar con lector PDF Python y herramientas PowerShell sin red.\n";
