<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/Config/bootstrap.php';
use App\Config\Env;
if (Env::get('DB_HOST') !== '127.0.0.1' || Env::get('DB_NAME') !== 'clinica_vida_salud_dev') { throw new RuntimeException('Se requiere la base local.'); }
$pdo = App\Config\Database::connection();
echo 'APP_TIMEZONE: ', Env::get('APP_TIMEZONE', 'UTC'), PHP_EOL;
foreach (['horarios_atencion','medicos','medicos_especialidades','citas'] as $tabla) {
    $q=$pdo->prepare("SELECT column_name,data_type,is_nullable FROM information_schema.columns WHERE table_schema='public' AND table_name=:tabla ORDER BY ordinal_position");
    $q->execute(['tabla'=>$tabla]); echo $tabla, ': ', json_encode($q->fetchAll()), PHP_EOL;
    $q=$pdo->prepare('SELECT conname,pg_get_constraintdef(oid) AS definicion FROM pg_constraint WHERE conrelid=CAST(:tabla AS regclass)');
    $q->execute(['tabla'=>$tabla]); echo json_encode($q->fetchAll()), PHP_EOL;
}
foreach ([
    'horarios'=>'SELECT count(*) FROM horarios_atencion',
    'solapamientos'=> 'SELECT count(*) FROM horarios_atencion a JOIN horarios_atencion b ON a.id<b.id AND a.medico_id=b.medico_id AND a.dia_semana=b.dia_semana WHERE a.activo AND b.activo AND a.hora_inicio<b.hora_fin AND b.hora_inicio<a.hora_fin',
    'fuera_grilla'=>"SELECT count(*) FROM horarios_atencion WHERE mod(extract(epoch FROM hora_inicio)::numeric,1800)<>0 OR mod(extract(epoch FROM hora_fin)::numeric,1800)<>0",
    'citas_futuras'=>"SELECT count(*) FROM citas WHERE estado IN ('programada','confirmada') AND fecha_hora_inicio>now()",
    'usuarios'=>'SELECT count(*) FROM usuarios','pacientes'=>'SELECT count(*) FROM pacientes',
    'medicos'=>'SELECT count(*) FROM medicos','especialidades'=>'SELECT count(*) FROM especialidades',
] as $nombre=>$sql) { echo $nombre, ': ', $pdo->query($sql)->fetchColumn(), PHP_EOL; }
