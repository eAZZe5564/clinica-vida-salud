<?php
declare(strict_types=1);

// Autorizado únicamente para la base local; nunca forma parte del arranque Docker.
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--autorizado'){
    fwrite(STDERR,"Se requiere autorización explícita y el argumento --autorizado.\n");exit(2);
}
$pdo=null;$salida=0;
try{
    require dirname(__DIR__).'/tests/Cu15Local.php';
    require __DIR__.'/Cu23Esquema.php';
    $pdo=cu15Conexion(); // Guarda antes y después de conectar: loopback/base exacta/PostgreSQL 17.
    $pdo->beginTransaction();
    $pdo->exec("SET LOCAL lock_timeout='5s'");$pdo->exec("SET LOCAL statement_timeout='20s'");
    $pdo->query("SELECT pg_advisory_xact_lock(hashtext('clinica_migracion_009'))");
    $pdo->exec('LOCK TABLE public.'.implode(',public.',CU23_ORIGINALES).' IN SHARE MODE');
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));
    $tablas=$pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
    if($tablas!==CU23_ORIGINALES||(int)$pdo->query("SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname IN ('perfiles_clinicos','alergias_paciente','alergias_historial')")->fetchColumn()!==0){
        throw new RuntimeException('009 ya existe o el esquema previo es incompatible.');
    }
    $asistencia=$pdo->query("SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid='public.citas'::regclass AND conname='citas_estado_valido'")->fetchColumn();
    if(!str_contains((string)$asistencia,'presente')){throw new RuntimeException('Se requiere 001–008 completo.');}
    $antes=cu23HuellaOriginal($pdo);
    $pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/009_perfil_clinico_alergias.sql'));
    cu23ValidarInstalacion($pdo);
    foreach(CU23_TABLAS as $t){if((int)$pdo->query('SELECT count(*) FROM public.'.$t)->fetchColumn()!==0){throw new RuntimeException('Se esperaban tablas clínicas vacías.');}}
    if($antes!==cu23HuellaOriginal($pdo)){throw new RuntimeException('Se detectó alteración de registros o estructura previos.');}
    $pdo->commit();
    echo "009 aplicada una sola vez en clinica_vida_salud_dev local. Doce tablas, veinte FK; nueve tablas previas y sus registros íntegros.\n";
}catch(Throwable $e){
    if($pdo instanceof PDO&&$pdo->inTransaction()){$pdo->rollBack();}
    fwrite(STDERR,"009 no aplicada. Rollback completo; detenerse sin repetir. Diagnóstico: ".get_class($e).($e instanceof PDOException?' / SQLSTATE '.preg_replace('/[^A-Z0-9]/','',(string)$e->getCode()):'').". Detalle nativo omitido.\n");
    $salida=1;
}
exit($salida);
