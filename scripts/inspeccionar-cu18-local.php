<?php
declare(strict_types=1);
require dirname(__DIR__).'/tests/Cu15Local.php';
$pdo=cu15Conexion();$pdo->beginTransaction();
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
try{
    $pdo->exec(file_get_contents(__DIR__.'/render/verificar-instalacion.sql'));
    $conflictos=(int)$pdo->query("SELECT count(*) FROM citas a JOIN citas b ON a.id<b.id AND a.medico_id=b.medico_id
        AND a.estado<>'cancelada' AND b.estado<>'cancelada'
        AND a.fecha_hora_inicio<b.fecha_hora_inicio+make_interval(mins=>b.duracion_minutos)
        AND b.fecha_hora_inicio<a.fecha_hora_inicio+make_interval(mins=>a.duracion_minutos)")->fetchColumn();
    echo json_encode(['base'=>'clinica_vida_salud_dev','solo_lectura'=>true,
        'tablas'=>(int)$pdo->query("SELECT count(*) FROM pg_tables WHERE schemaname='public'")->fetchColumn(),
        'claves_foraneas'=>(int)$pdo->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn(),
        'conflictos_intervalos_retenidos'=>$conflictos,
        'eventos_duplicados_cu18'=>(int)$pdo->query("SELECT count(*) FROM (SELECT cita_id,tipo FROM citas_historial WHERE tipo IN ('llegada','atencion','inasistencia') GROUP BY cita_id,tipo HAVING count(*)>1) duplicados")->fetchColumn()],JSON_THROW_ON_ERROR).PHP_EOL;
}finally{if($pdo->inTransaction()){$pdo->rollBack();}}
