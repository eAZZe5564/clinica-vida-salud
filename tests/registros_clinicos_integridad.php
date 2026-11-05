<?php
declare(strict_types=1);
require __DIR__.'/RegistroClinicoPrueba.php';
$pdo=cu15Conexion();$f=null;$total=0;
try{
    $f=new PerfilClinicoPrueba($pdo);$s=cu24Servicio($f);$p=$f->paciente;$u=$f->actores['medico']['id'];
    $ids=[];foreach(['antecedentes','laboratorios'] as $tipo){$ids[$tipo]=$s->guardar($p,$tipo,cu24Entrada($tipo));}
    $base=cu24Huella($pdo);$pdo->beginTransaction();
    $rechaza=static function(string $sql,array $args,string $estado,string $mensaje)use($pdo):void{
        $pdo->exec('SAVEPOINT integridad_cu24');$code=null;
        try{$q=$pdo->prepare($sql);$q->execute($args);}catch(PDOException $e){$code=$e->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT integridad_cu24');$pdo->exec('RELEASE SAVEPOINT integridad_cu24');}
        clinicoAssert($code===$estado,$mensaje);
    };
    foreach($ids as $tipo=>$id){$d=App\Models\RegistroClinico::tipo($tipo);$t=$d['tabla'];$h=$d['historial'];
        foreach(['paciente_id','creado_por_usuario_id','actualizado_por_usuario_id'] as $col){$rechaza('UPDATE '.$t.' SET '.$col.'=-9223372036854775807 WHERE id=:id',['id'=>$id],'23503','FK de paciente y responsables protege registro');}
        foreach(["estado='borrado'",'version=0',"observaciones=' '","actualizado_en=creado_en-interval '1 second'"] as $set){$rechaza('UPDATE '.$t.' SET '.$set.' WHERE id=:id',['id'=>$id],'23514','CHECK de registro evita inconsistencias');}
        $campo=$tipo==='antecedentes'?'descripcion':'resultado_texto';
        $rechaza('UPDATE '.$t.' SET '.$campo.'=NULL WHERE id=:id',['id'=>$id],'23502','texto obligatorio no admite NULL');
        $rechaza('UPDATE '.$t.' SET '.$campo."='  ' WHERE id=:id",['id'=>$id],'23514','texto obligatorio no admite blanco');
        $rechaza('UPDATE '.$t.' SET '.$campo.'=:valor WHERE id=:id',['id'=>$id,'valor'=>str_repeat('x',$tipo==='antecedentes'?1001:2001)],'22001','longitud protegida por PostgreSQL');
        $rechaza('DELETE FROM '.$t.' WHERE id=:id',['id'=>$id],'23503','historial impide eliminar registro padre');
        foreach([$d['fk'],'usuario_id'] as $col){$rechaza('UPDATE '.$h.' SET '.$col.'=-9223372036854775807 WHERE '.$d['fk'].'=:id',['id'=>$id],'23503','FK de revisión exige registro y responsable existentes');}
        foreach(["evento='borrado'",'version=0',"evento='rectificacion'", "motivo_cambio='CU24_FICTICIO'"] as $set){$rechaza('UPDATE '.$h.' SET '.$set.' WHERE '.$d['fk'].'=:id',['id'=>$id],'23514','evento inicial y motivo de auditoría coherentes');}
        $cols=implode(',',$d['campos']);
        $rechaza('INSERT INTO '.$h.'('.$d['fk'].',version,evento,'.$cols.',motivo_cambio,usuario_id) SELECT '.$d['fk'].',version,evento,'.$cols.',motivo_cambio,usuario_id FROM '.$h.' WHERE '.$d['fk'].'=:id',['id'=>$id],'23505','no duplica una versión histórica');
        $inyeccion="CU24_FICTICIO'); DELETE FROM usuarios; --";
        $q=$pdo->prepare('UPDATE '.$t.' SET '.$campo.'=:v WHERE id=:id');$q->execute(['v'=>$inyeccion,'id'=>$id]);
        $q=$pdo->prepare('SELECT '.$campo.' FROM '.$t.' WHERE id=:id');$q->execute(['id'=>$id]);clinicoAssert($q->fetchColumn()===$inyeccion,'PDO conserva intento de SQL como texto');
    }
    foreach(["categoria='alergias'","fecha_evento='2000-01-01'", "fecha_aproximada=true", "fecha_evento='infinity'::date,fecha_aproximada=false"] as $set){$rechaza('UPDATE antecedentes_clinicos SET '.$set.' WHERE id=:id',['id'=>$ids['antecedentes']],'23514','categoría y precisión de fecha validadas en SQL');}
    foreach(["estudio=' '","valor='NaN'::numeric", "unidad='CU24_UNIDAD_FICTICIA'", "fecha_muestra='2001-02-01',fecha_resultado='2001-01-01'", "fecha_resultado='infinity'::date", "rango_referencia=' '","laboratorio=' '"] as $set){$rechaza('UPDATE resultados_laboratorio SET '.$set.' WHERE id=:id',['id'=>$ids['laboratorios']],'23514','fechas, texto, unidades y NaN protegidos en SQL');}
    $pdo->rollBack();clinicoAssert($base===cu24Huella($pdo),'pruebas SQL aisladas restauran dieciséis tablas');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}cu24Limpiar($f);}
echo "Aprobadas: $total. Integridad referencial, CHECK, versiones y parámetros CU24, sin migraciones.\n";
