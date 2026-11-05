<?php
declare(strict_types=1);
// Aplicación única expresamente autorizada; no forma parte del arranque web/Docker.
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--autorizado'){
    fwrite(STDERR,"Se requiere autorización expresa y --autorizado.\n");exit(2);
}
$pdo=null;
try{
    require dirname(__DIR__).'/tests/Cu15Local.php';
    require __DIR__.'/Cu23Esquema.php';require __DIR__.'/Cu24Esquema.php';
    $m=json_decode(file_get_contents(__DIR__.'/cu24/propuesta.json'),true,512,JSON_THROW_ON_ERROR);
    $sql=file_get_contents(dirname(__DIR__).'/database/migrations/010_antecedentes_laboratorio.sql');
    if(hash('sha256',$sql)!==$m['sha256_sql']){throw new RuntimeException('SQL distinto del autorizado.');}
    $pdo=cu15Conexion();$pdo->beginTransaction();
    $pdo->exec("SET LOCAL lock_timeout='5s'");$pdo->exec("SET LOCAL statement_timeout='20s'");
    $pdo->query("SELECT pg_advisory_xact_lock(hashtext('clinica_migracion_010'))");
    $anteriores=[...CU23_ORIGINALES,...CU23_TABLAS];sort($anteriores);
    $pdo->exec('LOCK TABLE public.'.implode(',public.',$anteriores).' IN SHARE MODE');
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));cu23ValidarInstalacion($pdo);
    $reales=$pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
    $q=$pdo->prepare("SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname IN (?,?,?,?)");$q->execute(CU24_TABLAS);
    if($reales!==$anteriores||(int)$q->fetchColumn()!==0){throw new RuntimeException('Se requiere 001–009 sin restos de 010.');}
    $antes=cu24HuellaAnterior($pdo);
    $pdo->exec($sql);
    cu24ValidarNuevo($pdo);
    $esperadas=[...$anteriores,...CU24_TABLAS];sort($esperadas);
    $reales=$pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
    if($reales!==$esperadas||(int)$pdo->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn()!==30){throw new RuntimeException('Conteo final distinto de 16/30.');}
    foreach(CU24_TABLAS as $t){if((int)$pdo->query('SELECT count(*) FROM public.'.$t)->fetchColumn()!==0){throw new RuntimeException('Tabla nueva no vacía.');}}
    if($antes!==cu24HuellaAnterior($pdo)){throw new RuntimeException('Alteración de las doce tablas anteriores.');}
    $pdo->commit();
    echo "010 aplicada una sola vez en clinica_vida_salud_dev local: 16 tablas y 30 FK. Cuatro tablas vacías; doce tablas anteriores, registros y GiST conservados.\n";
}catch(Throwable $e){
    if($pdo instanceof PDO&&$pdo->inTransaction()){$pdo->rollBack();}
    fwrite(STDERR,'010 no aplicada; detenerse sin reintentar. Rollback completo si había transacción. Clase: '.get_class($e).($e instanceof PDOException?' / SQLSTATE '.preg_replace('/[^A-Z0-9]/','',(string)$e->getCode()):'')."; detalle nativo omitido.\n");exit(1);
}
