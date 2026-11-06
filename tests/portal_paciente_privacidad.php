<?php
declare(strict_types=1);
require __DIR__.'/PortalPacientePrueba.php';require __DIR__.'/ReprogramacionPrueba.php';require __DIR__.'/HttpCatalogo.php';
$pdo=cu15Conexion();$f=null;$clinico=null;$http=null;$total=0;$antes=cu24Huella($pdo);
$dir=__DIR__.'/artifacts/cu25';if(!is_dir($dir)){mkdir($dir,0700,true);}$log=$dir.'/privacidad-http.log';file_put_contents($log,'');
try{
    $f=new PortalPacientePrueba($pdo);$id=$f->cita();$s=$f->portal();$base=cu24Huella($pdo);
    foreach(['reprogramar','cancelar'] as $a){
        $proxy=new Cu15FalloPDO($pdo,'INSERT INTO citas_historial');$p=$f->portal(p:$proxy);$e=$f->mutacion($id,$a==='reprogramar'?'09:00':'08:00',$a);
        portalRechaza(fn()=>$p->cambiar($id,$a,$e),$a==='cancelar'?503:422,'fallo atómico de historial');
        portalAssert($base===cu24Huella($pdo),'rollback total de cita e historial');
    }
    $destino=$f->fixture->usuario('paciente');$proxy=new PortalVinculacionFalloPDO($pdo);
    portalRechaza(fn()=>(new App\Services\VinculacionPacienteService($proxy))->vincular($f->sinCuenta,$destino,'1',$f->actores['administrador']['id']),503,'fallo después del UPDATE de vínculo');
    $q=$pdo->prepare('SELECT usuario_id FROM pacientes WHERE id=:p');$q->execute(['p'=>$f->sinCuenta]);portalAssert($q->fetchColumn()===null,'vínculo no queda parcial');
    // No se aceptan transacciones externas de aislamiento incompatible ni READ ONLY.
    foreach(['REPEATABLE READ READ WRITE','READ COMMITTED READ ONLY'] as $modo){$pdo->beginTransaction();$pdo->exec('SET TRANSACTION ISOLATION LEVEL '.$modo);portalRechaza(fn()=>$s->listar([]),409,'transacción incompatible de lectura');portalRechaza(fn()=>(new App\Services\VinculacionPacienteService($pdo))->vincular($f->sinCuenta,$destino,'1',$f->actores['administrador']['id']),409,'transacción incompatible de vínculo');$pdo->rollBack();}
    $clinico=new PerfilClinicoPrueba($pdo);$c=$clinico;$marca='CU25_CLINICO_FICTICIO_'.bin2hex(random_bytes(8));
    (new App\Services\VinculacionPacienteService($pdo))->vincular($c->paciente,$c->actores['paciente']['id'],'1',$c->actores['administrador']['id']);
    $c->servicio()->guardarPerfil($c->paciente,$c->perfil(['observaciones'=>$marca]));$c->servicio()->guardarAlergia($c->paciente,$c->alergia(['sustancia'=>$marca]));
    foreach(['antecedentes','laboratorios'] as $tipo){cu24Servicio($c)->guardar($c->paciente,$tipo,cu24Entrada($tipo,['observaciones'=>$marca]));}
    $portal=new App\Services\PortalPacienteService($pdo,$c->actores['paciente']['id']);$cid=$c->citas['propia'];
    foreach([$portal->listar([],true),$portal->detalle($cid),$portal->formulario([])] as $d){$json=json_encode($d,JSON_THROW_ON_ERROR);portalAssert(!str_contains($json,$marca),'proyección portal no selecciona información clínica');foreach(['responsable','registrada_por','motivo','documento_identidad','paciente_nombres','password_hash','grupo_sanguineo','resultado_texto'] as $key){portalAssert(!str_contains($json,'"'.$key.'"'),'proyección mínima '.$key);}}
    $http=new HttpCatalogo(registroPrueba:$log);$l=$http->pedir('/login');$r=$http->pedir('/login','POST',$l['cookie'],['_csrf'=>HttpCatalogo::csrf($l['body']),'correo'=>$c->actores['paciente']['correo'],'contrasena'=>'Ficticia91!'.$c->fixture->sufijo]);$cookie=$r['cookie'];
    foreach(['/mis-citas?periodo=todas','/mis-citas/historial','/mis-citas/'.$cid,'/mis-citas/'.$cid.'/historial','/mis-citas/nueva'] as $ruta){$r=$http->pedir($ruta,'GET',$cookie);portalAssert($r['status']===200&&!str_contains($r['body'],$marca),'HTTP sin contenido clínico');}
    foreach(['perfil-clinico','alergias','antecedentes','laboratorios'] as $tipo){foreach(['GET','POST'] as $m){$r=$http->pedir('/pacientes/'.$c->paciente.'/'.$tipo,$m,$cookie);portalAssert($r['status']===403&&!str_contains($r['body'],$marca),'el vínculo no otorga acceso clínico');}}
    $contenido=file_get_contents($log);portalAssert(!str_contains($contenido,$marca)&&!str_contains($contenido,'PDOException'),'sin valores clínicos ni errores PDO en logs técnicos');
    // FK y UNIQUE reales: solo SAVEPOINT, nunca altera restricciones.
    $pdo->beginTransaction();foreach([['p'=>$f->sinCuenta,'u'=>$f->actores['paciente']['id'],'esperado'=>'23505'],['p'=>$f->sinCuenta,'u'=>PHP_INT_MAX,'esperado'=>'23503']] as $d){
        $pdo->exec('SAVEPOINT fk_cu25');$codigo=null;try{$pdo->prepare('UPDATE pacientes SET usuario_id=:u WHERE id=:p')->execute(['p'=>$d['p'],'u'=>$d['u']]);}catch(PDOException $e){$codigo=(string)$e->getCode();}finally{$pdo->exec('ROLLBACK TO SAVEPOINT fk_cu25');$pdo->exec('RELEASE SAVEPOINT fk_cu25');}portalAssert($codigo===$d['esperado'],'integridad de FK y unicidad reales');
    }$pdo->rollBack();
}finally{if($pdo->inTransaction()){$pdo->rollBack();}if($http){$http->cerrar();}cu24Limpiar($clinico);if($f){$f->limpiar();}}
portalAssert($antes===cu24Huella($pdo),'privacidad retira todos los fixtures y conserva datos previos');
echo "Aprobadas: $total. Proyecciones privadas, acceso clínico denegado, rollback e integridad.\n";
