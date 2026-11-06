<?php
declare(strict_types=1);
$pdo=null;
try{
    require dirname(__DIR__).'/tests/Cu15Local.php';require __DIR__.'/Cu23Esquema.php';require __DIR__.'/Cu24Esquema.php';
    $pdo=cu15Conexion();$pdo->beginTransaction();$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));cu23ValidarInstalacion($pdo);cu24ValidarNuevo($pdo);
    $tablas=(int)$pdo->query("SELECT count(*) FROM pg_tables WHERE schemaname='public'")->fetchColumn();
    $fk=(int)$pdo->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn();
    $col=$pdo->query("SELECT data_type,is_nullable FROM information_schema.columns WHERE table_schema='public' AND table_name='pacientes' AND column_name='usuario_id'")->fetch();
    $constraints=$pdo->query("SELECT c.contype,c.confrelid::regclass::text AS destino,c.confdeltype,array(SELECT a.attname::text FROM unnest(c.conkey) WITH ORDINALITY k(num,ord) JOIN pg_attribute a ON a.attrelid=c.conrelid AND a.attnum=k.num ORDER BY ord)::text AS columnas,array(SELECT a.attname::text FROM unnest(c.confkey) WITH ORDINALITY k(num,ord) JOIN pg_attribute a ON a.attrelid=c.confrelid AND a.attnum=k.num ORDER BY ord)::text AS referencias FROM pg_constraint c WHERE c.conrelid='public.pacientes'::regclass")->fetchAll();
    $unico=false;$relacion=false;
    foreach($constraints as $c){if($c['columnas']==='{usuario_id}'){$unico=$unico||$c['contype']==='u';$relacion=$relacion||($c['contype']==='f'&&$c['destino']==='usuarios'&&$c['confdeltype']==='r'&&$c['referencias']==='{id}');}}
    if($tablas!==16||$fk!==30||!$col||$col['data_type']!=='bigint'||$col['is_nullable']!=='YES'||!$unico||!$relacion){throw new RuntimeException('Contrato CU25 incompatible.');}
    echo json_encode(['base'=>'clinica_vida_salud_dev','esquema'=>'001-010','tablas'=>$tablas,'claves_foraneas'=>$fk,'vinculo_nullable_bigint'=>true,'vinculo_fk_restrict'=>true,'vinculo_unico'=>true,'solo_lectura'=>true,'migracion_011'=>false],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"Verificación CU25 no satisfactoria; detalle nativo omitido. Sin escrituras.\n");exit(1);}
finally{if($pdo instanceof PDO&&$pdo->inTransaction()){$pdo->rollBack();}}
