<?php
declare(strict_types=1);
set_exception_handler(static function(Throwable $e):void{
    $safe=$e instanceof RuntimeException&&!($e instanceof PDOException)&&strlen($e->getMessage())<200&&!preg_match('/postgres|password|contras|token|dsn|:\/\//i',$e->getMessage());
    echo 'Fallo CU26: '.get_class($e).($safe?' / '.$e->getMessage():' / detalle nativo omitido').PHP_EOL;exit(1);
});
$suite=$argv[1]??'';
if(!in_array($suite,['regresion_cu25','comandos_voz_validation','http_comandos_voz','comandos_voz_browser'],true)){throw new RuntimeException('Suite no permitida.');}
$argv=[__DIR__.'/'.$suite.'.php'];$argc=1;require $argv[0];
