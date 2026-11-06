<?php
declare(strict_types=1);
// Evita código PHP multilínea en argumentos de procesos Windows.
set_exception_handler(static function(Throwable $e):void{
    $safe=$e instanceof RuntimeException&&!($e instanceof PDOException)&&strlen($e->getMessage())<180&&!preg_match('/postgres|password|contras|token|dsn|:\/\//i',$e->getMessage());
    echo 'Fallo CU25: '.get_class($e).($safe?' / '.$e->getMessage():' / detalle nativo omitido').PHP_EOL;exit(1);
});
$suite=$argv[1]??'';
if(!in_array($suite,['regresion_cu24','portal_paciente_integration','portal_paciente_concurrency','http_portal_paciente','portal_paciente_privacidad','portal_paciente_browser'],true)){throw new RuntimeException('Suite no permitida.');}
$argv=[__DIR__.'/'.$suite.'.php'];$argc=1;require $argv[0];
