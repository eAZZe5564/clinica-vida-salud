<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Config/bootstrap.php';
$pdo=App\Config\Database::connection();
if($pdo->query('SELECT current_database()')->fetchColumn()!=='clinica_vida_salud_dev'){throw new RuntimeException('Solo desarrollo local autorizado');}
$usuario=(string)$pdo->query('SELECT current_user')->fetchColumn();
$total=0;
// Solo lectura: ni migraciones ni seed. Incluso ante un error todo termina en rollback.
foreach(['verificar-instalacion.sql','comprobar-destino.sql','exigir-base-vacia.sql'] as $archivo){
    $pdo->beginTransaction();
    try{
        $pdo->exec('SET TRANSACTION READ ONLY');$pdo->exec('SET LOCAL search_path=public');
        $sql=file_get_contents(dirname(__DIR__).'/scripts/render/'.$archivo);
        $sql=str_replace([":'base_esperada'",":'usuario_esperado'"],[$pdo->quote('clinica_vida_salud_render'),$pdo->quote($usuario)],$sql);
        $rechazo=$archivo!=='verificar-instalacion.sql';
        try{
            $pdo->exec($sql);
            if($rechazo){throw new RuntimeException('La guarda no rechazó el destino local/no vacío');}
        }catch(PDOException $error){if(!$rechazo||$error->getCode()!=='P0001'){throw $error;}}
        $total++;echo "OK: SQL $archivo (solo lectura)\n";
    }finally{$pdo->rollBack();}
}
echo "Aprobadas: $total. SQL de verificación real; sin migraciones, seed ni escrituras.\n";
