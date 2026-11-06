<?php
declare(strict_types=1);
require __DIR__.'/Cu15Local.php';cu15Conexion();
$nuevas=['portal_paciente_integration','portal_paciente_concurrency','http_portal_paciente','portal_paciente_privacidad','portal_paciente_browser'];
if(isset($argv[1])&&$argv[1]!=='--solo-cu25'&&!in_array($argv[1],$nuevas,true)){throw new RuntimeException('Suite no permitida.');}
$suites=isset($argv[1])?($argv[1]==='--solo-cu25'?$nuevas:[$argv[1]]):['regresion_cu24',...$nuevas];
$dir=__DIR__.'/artifacts/cu25';if(!is_dir($dir)){mkdir($dir,0700,true);}$total=0;$resultados=[];
foreach($suites as $suite){
    $p=proc_open([PHP_BINARY,__DIR__.'/Cu25Ejecutar.php',$suite],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$dir.'/'.$suite.'-stderr.log','w']],$pipes,dirname(__DIR__));
    if(!is_resource($p)){throw new RuntimeException('No se pudo iniciar suite.');}fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($p);
    file_put_contents($dir.'/'.$suite.'.log',$out);preg_match_all('/Aprobadas:\s*(\d+)/u',$out,$m);$n=$m[1]?(int)end($m[1]):0;
    $resultados[$suite]=['salida'=>$code,'comprobaciones'=>$n];file_put_contents($dir.'/resultados.json',json_encode($resultados,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    if($code!==0||$n===0){fwrite(STDERR,"FALLO: $suite. Consultar diagnóstico local ignorado.\n");exit(1);}$total+=$n;echo "$suite: $n aprobadas.\n";
}
echo "Aprobadas: $total. Ejecución PHP local CU25; otras herramientas se informan por separado.\n";
