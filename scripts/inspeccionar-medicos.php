<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/Config/bootstrap.php';
use App\Config\Env;
if (Env::get('DB_HOST') !== '127.0.0.1' || Env::get('DB_NAME') !== 'clinica_vida_salud_dev') { throw new RuntimeException('Se requiere la base local.'); }
$pdo = App\Config\Database::connection();
foreach (['medicos', 'especialidades', 'medicos_especialidades', 'usuarios', 'roles'] as $tabla) {
    $q = $pdo->prepare("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_schema='public' AND table_name=:tabla ORDER BY ordinal_position");
    $q->execute(['tabla' => $tabla]);
    echo $tabla, ': ', json_encode($q->fetchAll(), JSON_UNESCAPED_UNICODE), PHP_EOL;
    $q = $pdo->prepare('SELECT conname, pg_get_constraintdef(oid) AS definicion FROM pg_constraint WHERE conrelid = CAST(:tabla AS regclass)');
    $q->execute(['tabla' => $tabla]); echo json_encode($q->fetchAll(), JSON_UNESCAPED_UNICODE), PHP_EOL;
}
$consultas = [
    'especialidades_duplicadas' => "SELECT count(*) FROM (SELECT lower(btrim(regexp_replace(nombre, '[[:space:]]+', ' ', 'g'))) FROM especialidades GROUP BY 1 HAVING count(*) > 1) t",
    'registros_duplicados' => "SELECT count(*) FROM (SELECT upper(regexp_replace(numero_colegiatura, '[[:space:]]+', '', 'g')) FROM medicos GROUP BY 1 HAVING count(*) > 1) t",
    'medicos_invalidos' => "SELECT count(*) FROM medicos WHERE btrim(nombres)='' OR btrim(apellidos)='' OR regexp_replace(numero_colegiatura, '[[:space:]]+', '', 'g')=''",
    'especialidades_invalidas' => "SELECT count(*) FROM especialidades WHERE btrim(regexp_replace(nombre, '[[:space:]]+', ' ', 'g'))=''",
    'usuarios' => 'SELECT count(*) FROM usuarios', 'pacientes' => 'SELECT count(*) FROM pacientes',
    'medicos' => 'SELECT count(*) FROM medicos', 'especialidades' => 'SELECT count(*) FROM especialidades',
];
foreach ($consultas as $nombre => $sql) { echo $nombre, ': ', $pdo->query($sql)->fetchColumn(), PHP_EOL; }
