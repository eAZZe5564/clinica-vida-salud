<?php
declare(strict_types=1);
require __DIR__ . '/Cu15Local.php';cu15Conexion();
// Suites históricas con datos propios/rollback; no ejecutar auth_users_integration (exige usuarios vacíos).
$suites=['pacientes_validation','pacientes_integration','http_pacientes_integration','http_auth_integration',
    'medicos_validation','medicos_integration','http_medicos_integration','horarios_validation','horarios_integration',
    'http_horarios_integration','horarios_concurrency','disponibilidad_integration','http_disponibilidad_integration',
    'citas_integration','http_citas_integration','citas_concurrency','http_citas_formulario','citas_browser',
    'consulta_citas_integration','http_consulta_citas','consulta_citas_browser','auditoria_ciclo1_http',
    'mejoras_6a_validation','mejoras_6a_integration','http_mejoras_6a','mejoras_6a_browser'];
$directory=__DIR__.'/artifacts/cu15';if(!is_dir($directory)){mkdir($directory,0700,true);}
$results=[];$total=0;
if(isset($argv[1])){
    $index=array_search($argv[1],$suites,true);
    if($index===false){throw new RuntimeException('Suite de reanudación no válida.');}
    $previous=json_decode(file_get_contents($directory.'/regresion.json'),true,512,JSON_THROW_ON_ERROR);
    foreach(array_slice($suites,0,$index) as $name){
        if(($previous[$name]['salida']??1)!==0){throw new RuntimeException('No se puede omitir una suite sin resultado aprobado.');}
        $results[$name]=$previous[$name];$total+=$previous[$name]['comprobaciones'];
    }
    $suites=array_slice($suites,$index);
}
$wrapper=<<<'PHP'
set_exception_handler(static function(Throwable $e):void{
    $safe=get_class($e)==='RuntimeException' && strlen($e->getMessage())<200 && !preg_match('/postgres|password|contras|token|dsn|:\/\//i',$e->getMessage());
    fwrite(STDERR,'Fallo de prueba: '.get_class($e).($safe?' / '.$e->getMessage():' / diagnostico nativo oculto').PHP_EOL);exit(1);
});
require $argv[1];
PHP;
foreach($suites as $suite){
    $process=proc_open([PHP_BINARY,'-r',$wrapper,__DIR__.'/'.$suite.'.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if(!is_resource($process)){throw new RuntimeException('No se pudo iniciar una suite.');}
    fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    file_put_contents($directory.'/'.$suite.'.log',$stdout.$stderr);
    preg_match('/Aprobadas:\s*(\d+)/u',$stdout,$match);
    $count=isset($match[1])?(int)$match[1]:0;
    $results[$suite]=['salida'=>$exit,'comprobaciones'=>$count];
    file_put_contents($directory.'/regresion.json',json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    if($exit!==0||$count===0){fwrite(STDERR,"FALLO: $suite. Revisar artefacto local ignorado; no publicar logs.\n");exit(1);}
    $total+=$count;echo "$suite: $count aprobadas.\n";
}
echo "Aprobadas: $total. Regresión completa CU01–CU14, incluidas mejoras 6A.\n";
