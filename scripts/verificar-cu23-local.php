<?php
declare(strict_types=1);
$pdo=null;$salida=0;
try{
    require dirname(__DIR__).'/tests/Cu15Local.php';require __DIR__.'/Cu23Esquema.php';
    $pdo=cu15Conexion();$pdo->beginTransaction();$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));
    cu23ValidarInstalacion($pdo);
    echo json_encode(['base'=>'clinica_vida_salud_dev','esquema'=>'001-009','tablas'=>12,
        'claves_foraneas'=>20,'contrato_clinico_completo'=>true,'solo_lectura'=>true],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"Verificación CU23 no satisfactoria; detalle nativo omitido. Sin escrituras.\n");$salida=1;}
finally{if($pdo instanceof PDO&&$pdo->inTransaction()){$pdo->rollBack();}}
exit($salida);
