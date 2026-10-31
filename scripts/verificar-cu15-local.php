<?php
declare(strict_types=1);
// Este comando nunca instala objetos, no lee datos personales y solo admite desarrollo local.
require dirname(__DIR__) . '/tests/Cu15Local.php';
$pdo=cu15Conexion();
$salida=0;
try {
    $pdo->beginTransaction();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));
    $tablas=(int)$pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'")->fetchColumn();
    $referencias=(int)$pdo->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn();
    $cancelaciones=(bool)$pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='citas_historial' AND column_name='motivo_cancelacion')")->fetchColumn();
    $confirmaciones=(bool)$pdo->query("SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='public.citas'::regclass AND conname='citas_estado_valido' AND position('confirmada' IN pg_get_constraintdef(oid))>0)")->fetchColumn();
    echo json_encode(['base'=>'clinica_vida_salud_dev','esquema'=>$confirmaciones?'001-007':($cancelaciones?'001-006':($tablas===9?'001-005':'001-004')),
        'tablas'=>$tablas,'claves_foraneas'=>$referencias,'historial'=>$tablas===9,'verificado'=>true,'solo_lectura'=>true],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Esquema local incompatible o comprobacion no disponible. No se realizaron escrituras.\n");
    $salida=1;
} finally {
    if($pdo->inTransaction()){$pdo->rollBack();}
}
exit($salida);
