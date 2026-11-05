<?php
declare(strict_types=1);
require __DIR__.'/PerfilClinicoPrueba.php';require dirname(__DIR__).'/scripts/Cu23Esquema.php';
$pdo=cu15Conexion();$total=0;$sql=file_get_contents(dirname(__DIR__).'/scripts/render/verificar-instalacion.sql');
$pattern='~-- CU23_METADATA_INICIO\R.*?-- CU23_METADATA_FIN~s';
$pdo->beginTransaction();$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
try{
    $pdo->exec($sql);cu23ValidarInstalacion($pdo);$real=cu23Catalogo($pdo);
    clinicoAssert((int)$pdo->query("SELECT count(*) FROM pg_tables WHERE schemaname='public'")->fetchColumn()===16,'dieciséis tablas reales exactas después de 010');
    clinicoAssert((int)$pdo->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn()===30,'treinta FK reales exactas después de 010');
    clinicoAssert(count($real['columnas'])===32&&count($real['restricciones'])===30&&count($real['indices'])===6,'contrato clínico completo');
    $verificar=static function(array $c,bool $esperado,string $label)use($pdo,$sql,$pattern):void{
        $asignacion='clinico := '.$pdo->quote(json_encode($c,JSON_THROW_ON_ERROR)).'::jsonb;';
        $prueba=preg_replace_callback($pattern,static fn()=>$asignacion,$sql,-1,$n);
        if($n!==1){throw new RuntimeException('No se aisló un único contrato clínico.');}
        $pdo->exec('SAVEPOINT contrato_clinico');$acepta=false;$estado=null;
        try{$pdo->exec($prueba);$acepta=true;}catch(PDOException $e){$estado=$e->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT contrato_clinico');$pdo->exec('RELEASE SAVEPOINT contrato_clinico');}
        clinicoAssert($esperado?$acepta:(!$acepta&&$estado==='P0001'),$label);
    };
    $verificar($real,true,'acepta el contrato aprobado completo');
    $vacio=['tablas'=>(object)[],'columnas'=>(object)[],'restricciones'=>(object)[],'indices'=>(object)[]];
    $verificar($vacio,true,'contrato clínico ausente permite validar el esquema administrativo previo aislado');
    foreach(array_keys($real['tablas']) as $t){$x=$real;unset($x['tablas'][$t]);$verificar($x,false,'rechaza tabla clínica ausente');$x=$real;$x['tablas'][$t]='v';$verificar($x,false,'rechaza vista en lugar de tabla');}
    foreach(array_keys($real['columnas']) as $key){$x=$real;unset($x['columnas'][$key]);$verificar($x,false,'rechaza columna clínica faltante');}
    foreach(['tipo'=>'text','obligatoria'=>false,'identidad'=>'d','defecto'=>'1'] as $k=>$v){$x=$real;$x['columnas']['alergias_paciente.id'][$k]=$v;$verificar($x,false,'rechaza tipo, nulabilidad, identidad o defecto alterados');}
    $x=$real;$x['columnas']['perfiles_clinicos.grupo_sanguineo']['defecto']="'O+'::character varying";$verificar($x,false,'rechaza grupo sanguíneo predeterminado');
    foreach($real['restricciones'] as $key=>$c){$x=$real;unset($x['restricciones'][$key]);$verificar($x,false,'rechaza restricción clínica ausente');
        $x=$real;$x['restricciones'][$key]['valida']=false;$verificar($x,false,'rechaza restricción sin validar');
        $x=$real;$x['restricciones'][$key]['definicion']=$c['tipo']==='c'?'CHECK (true)':'definicion alterada';$verificar($x,false,'rechaza CHECK, PK, unicidad o FK debilitados');}
    foreach($real['indices'] as $key=>$i){foreach(['unico'=>!$i['unico'],'valido'=>false,'listo'=>false,'definicion'=>'CREATE INDEX incorrecto'] as $k=>$v){$x=$real;$x['indices'][$key][$k]=$v;$verificar($x,false,'rechaza índice alterado o incompleto');}}
    $x=$vacio;$x['columnas']=$real['columnas'];$verificar($x,false,'no ignora restos de instalación parcial');
    $pdo->exec($sql);clinicoAssert($pdo->query('SHOW transaction_read_only')->fetchColumn()==='on','verificaciones e inyecciones aisladas siguen READ ONLY');
}finally{$pdo->rollBack();}
echo "Aprobadas: $total. Contrato CU23 real y mutaciones de metadatos aisladas; sin DDL ni datos privados.\n";
