<?php
declare(strict_types=1);
require __DIR__.'/Cu15Local.php';cu15Conexion();
$suites=['regresion_cu15',
    'reprogramacion_integration','reprogramacion_concurrency','http_reprogramacion','reprogramacion_browser',
    'cancelacion_integration','http_cancelacion','cancelacion_concurrency','cancelacion_browser',
    'confirmacion_integration','http_confirmacion','confirmacion_concurrency','confirmacion_browser',
    'asistencia_integration','http_asistencia','asistencia_concurrency','asistencia_browser',
    'agenda_integration','http_agenda','agenda_browser',
    'historial_paciente_integration','http_historial_paciente','historial_paciente_browser',
    'esquema_cu15_validation','render_sql_local','render_preparacion',
    'reportes_integration','http_reportes','reportes_browser'];
if(isset($argv[1])){if(!in_array($argv[1],$suites,true)){throw new RuntimeException('Suite no permitida.');}$suites=[$argv[1]];}
$directorio=__DIR__.'/artifacts/cu21';if(!is_dir($directorio)){mkdir($directorio,0700,true);}
$wrapper=<<<'PHP'
set_exception_handler(static function(Throwable $e):void{
    $safe=$e instanceof RuntimeException && !($e instanceof PDOException) && strlen($e->getMessage())<200 && !preg_match('/postgres|password|contras|token|dsn|:\/\//i',$e->getMessage());
    fwrite(STDERR,'Fallo de prueba: '.get_class($e).($safe?' / '.$e->getMessage():' / diagnostico nativo oculto').PHP_EOL);exit(1);
});
$suiteFile=$argv[1];$argv=[$suiteFile];$argc=1;require $suiteFile;
PHP;
$total=0;$resultados=[];
foreach($suites as $suite){
    $p=proc_open([PHP_BINARY,'-r',$wrapper,__DIR__.'/'.$suite.'.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($p)){throw new RuntimeException('No se pudo ejecutar una suite.');}fclose($pipes[0]);
    $salida=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$codigo=proc_close($p);
    file_put_contents($directorio.'/'.$suite.'.log',$salida.$error);
    preg_match_all('/Aprobadas:\s*(\d+)/u',$salida,$matches);$n=$matches[1]?(int)end($matches[1]):0;
    $resultados[$suite]=['salida'=>$codigo,'comprobaciones'=>$n];
    file_put_contents($directorio.'/resultados.json',json_encode($resultados,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    if($codigo!==0||$n===0){fwrite(STDERR,"FALLO: $suite. Revisar artefacto local ignorado.\n");exit(1);}
    $total+=$n;echo "$suite: $n aprobadas.\n";
}
echo "Aprobadas: $total. Campaña local CU21; resultados de esta ejecución en artefactos ignorados.\n";
