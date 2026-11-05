<?php
declare(strict_types=1);
require __DIR__.'/PerfilClinicoPrueba.php';
$pdo=cu15Conexion();$prueba=null;$total=0;
try{
    $prueba=new PerfilClinicoPrueba($pdo);$p=$prueba->paciente;$u=$prueba->actores['medico']['id'];$s=$prueba->servicio();$id=$s->guardarAlergia($p,$prueba->alergia());
    $base=$prueba->huella();$pdo->beginTransaction();
    $rechaza=static function(string $sql,array $params,string $estado,string $label)use($pdo):void{
        $pdo->exec('SAVEPOINT integridad_clinica');$code=null;
        try{$q=$pdo->prepare($sql);$q->execute($params);}catch(PDOException $e){$code=$e->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT integridad_clinica');$pdo->exec('RELEASE SAVEPOINT integridad_clinica');}
        clinicoAssert($code===$estado,$label);
    };
    $perfil='INSERT INTO perfiles_clinicos(paciente_id,creado_por_usuario_id,actualizado_por_usuario_id) VALUES (:p,:u,:v)';
    $rechaza($perfil,['p'=>-9223372036854775807,'u'=>$u,'v'=>$u],'23503','perfil exige paciente existente');
    $rechaza($perfil,['p'=>$p,'u'=>$u,'v'=>$u],'23505','solo un perfil por paciente');
    $q=$pdo->prepare($perfil);$q->execute(['p'=>$prueba->sinCita,'u'=>$u,'v'=>$u]);
    $q=$pdo->prepare('SELECT grupo_sanguineo,fuente_grupo_sanguineo,estado_alergias FROM perfiles_clinicos WHERE paciente_id=:p');$q->execute(['p'=>$prueba->sinCita]);$fila=$q->fetch();
    clinicoAssert($fila['grupo_sanguineo']===null&&$fila['fuente_grupo_sanguineo']===null&&$fila['estado_alergias']==='no_registrado','SQL no asigna datos clínicos confirmados por defecto');
    foreach(["grupo_sanguineo='Z+'","grupo_sanguineo='O+'","fuente_grupo_sanguineo='CU23_FUENTE_FICTICIA'","estado_alergias='inexistente'","observaciones='   '",'version=0',"actualizado_en=creado_en-interval '1 second'"] as $set){
        $rechaza('UPDATE perfiles_clinicos SET '.$set.' WHERE paciente_id=:p',['p'=>$p],'23514','CHECK de perfil rechaza inconsistencia');
    }
    foreach(["sustancia='   '","sustancia='Agente  ficticio'","estado='borrada'","reaccion=' '","observaciones=' '",'version=0',"actualizado_en=creado_en-interval '1 second'"] as $set){
        $rechaza('UPDATE alergias_paciente SET '.$set.' WHERE id=:id',['id'=>$id],'23514','CHECK de alergia rechaza inconsistencia');
    }
    $rechaza('UPDATE alergias_paciente SET sustancia=NULL WHERE id=:id',['id'=>$id],'23502','sustancia no admite NULL');
    $rechaza('UPDATE alergias_paciente SET reaccion=:r WHERE id=:id',['id'=>$id,'r'=>str_repeat('x',251)],'22001','longitud limitada también en PostgreSQL');
    $rechaza('UPDATE alergias_paciente SET paciente_id=:p WHERE id=:id',['id'=>$id,'p'=>-9223372036854775807],'23503','alergia exige paciente válido');
    $rechaza('UPDATE alergias_paciente SET actualizado_por_usuario_id=:u WHERE id=:id',['id'=>$id,'u'=>-9223372036854775807],'23503','responsable exige usuario válido');
    $insert="INSERT INTO alergias_paciente(paciente_id,sustancia,estado,creado_por_usuario_id,actualizado_por_usuario_id) VALUES (:p,:s,:e,:u,:v)";
    $rechaza($insert,['p'=>$p,'s'=>'cu23_agente_ficticio','e'=>'activa','u'=>$u,'v'=>$u],'23505','índice parcial rechaza duplicación activa entre mayúsculas');
    $q=$pdo->prepare($insert);$q->execute(['p'=>$p,'s'=>'cu23_agente_ficticio','e'=>'inactiva','u'=>$u,'v'=>$u]);clinicoAssert($q->rowCount()===1,'índice permite conservar antecedente inactivo equivalente');
    $hist='INSERT INTO alergias_historial(alergia_id,version,evento,sustancia,estado,motivo_cambio,usuario_id) VALUES (:a,:v,:e,:s,:estado,:m,:u)';
    $h=['a'=>$id,'v'=>2,'e'=>'actualizacion','s'=>'CU23_AGENTE_FICTICIO','estado'=>'activa','m'=>'CU23_MOTIVO_FICTICIO','u'=>$u];
    foreach([['a'=>-9223372036854775807],['u'=>-9223372036854775807]] as $c){$rechaza($hist,array_replace($h,$c),'23503','historial conserva referencias válidas');}
    foreach([['v'=>0],['v'=>1,'e'=>'actualizacion'],['e'=>'eliminacion'],['e'=>'inactivacion','estado'=>'activa'],['e'=>'reactivacion','estado'=>'inactiva'],['m'=>null],['m'=>'   ']] as $c){$rechaza($hist,array_replace($h,$c),'23514','historial exige versión, evento, estado y motivo coherentes');}
    $rechaza($hist,array_replace($h,['v'=>1,'e'=>'registro','m'=>null]),'23505','historial impide dos revisiones de la misma versión');
    $rechaza('DELETE FROM alergias_paciente WHERE id=:id',['id'=>$id],'23503','no se elimina una alergia con revisiones conservadas');
    // Una cadena que intenta cerrar SQL se guarda como texto parametrizado, sin ejecutarse.
    $inyeccion="CU23_FICTICIO'); DELETE FROM usuarios; --";
    $q=$pdo->prepare('UPDATE alergias_paciente SET reaccion=:v WHERE id=:id');$q->execute(['v'=>$inyeccion,'id'=>$id]);
    $q=$pdo->prepare('SELECT reaccion FROM alergias_paciente WHERE id=:id');$q->execute(['id'=>$id]);clinicoAssert($q->fetchColumn()===$inyeccion,'PDO trata texto de inyección como dato');
    $pdo->rollBack();clinicoAssert($base===$prueba->huella(),'todas las pruebas de integridad se revierten sin alterar filas previas');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. FK, CHECK, unicidad histórica y parámetros reales; sin migraciones adicionales.\n";
