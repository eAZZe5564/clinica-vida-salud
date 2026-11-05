<?php
declare(strict_types=1);
$pdo=null;$salida=0;
try{
    require dirname(__DIR__).'/tests/Cu15Local.php';require __DIR__.'/Cu23Esquema.php';
    $pdo=cu15Conexion();$pdo->beginTransaction();$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));
    cu23ValidarInstalacion($pdo);
    echo json_encode(['base'=>'clinica_vida_salud_dev','esquema'=>(int)$pdo->query("SELECT count(*) FROM pg_tables WHERE schemaname='public'")->fetchColumn()===16?'001-010':'001-009','tablas'=>(int)$pdo->query("SELECT count(*) FROM pg_tables WHERE schemaname='public'")->fetchColumn(),
        'claves_foraneas'=>(int)$pdo->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn(),'contrato_clinico_completo'=>true,'solo_lectura'=>true],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"Verificación CU23 no satisfactoria; detalle nativo omitido. Sin escrituras.\n");$salida=1;}
finally{if($pdo instanceof PDO&&$pdo->inTransaction()){$pdo->rollBack();}}
exit($salida);
