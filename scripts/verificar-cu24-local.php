<?php
declare(strict_types=1);
$pdo=null;
try{
    require dirname(__DIR__).'/tests/Cu15Local.php';require __DIR__.'/Cu23Esquema.php';require __DIR__.'/Cu24Esquema.php';
    $pdo=cu15Conexion();$pdo->beginTransaction();$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));
    cu23ValidarInstalacion($pdo);cu24ValidarNuevo($pdo);
    if((int)$pdo->query("SELECT count(*) FROM pg_tables WHERE schemaname='public'")->fetchColumn()!==16
        ||(int)$pdo->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn()!==30){throw new RuntimeException('Se requiere exactamente 16/30.');}
    echo json_encode(['base'=>'clinica_vida_salud_dev','esquema'=>'001-010','tablas'=>16,'claves_foraneas'=>30,'contrato_completo'=>true,'solo_lectura'=>true],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"Verificación CU24 no satisfactoria; detalle nativo omitido. Sin escrituras.\n");exit(1);}
finally{if($pdo instanceof PDO&&$pdo->inTransaction()){$pdo->rollBack();}}
