<?php
declare(strict_types=1);
require __DIR__.'/PerfilClinicoPrueba.php';
use App\Repositories\PerfilClinicoRepository;
$pdo=cu15Conexion();$prueba=null;$total=0;
try{
    $prueba=new PerfilClinicoPrueba($pdo);$p=$prueba->paciente;$s=$prueba->servicio();$repo=new PerfilClinicoRepository($pdo);
    $antes=$prueba->huella();$d=$s->consultar($p);
    clinicoAssert($d['perfil']===null&&$d['alergias']===[],'paciente sin perfil ni alergias permanece desconocido');
    clinicoAssert($antes===$prueba->huella(),'GET de servicio no modifica las doce tablas');
    $s->guardarPerfil($p,$prueba->perfil());$d=$s->consultar($p);
    clinicoAssert($d['perfil']['grupo_sanguineo']===null&&$d['perfil']['fuente_grupo_sanguineo']===null,'grupo desconocido y fuente NULL');
    clinicoAssert($d['perfil']['estado_alergias']==='no_registrado','vacío no confirma ausencia de alergias');
    clinicoAssert((int)$d['perfil']['creado_por_usuario_id']===$prueba->actores['medico']['id'],'responsable obtenido del actor revalidado');
    $base=$prueba->huella();
    foreach([
        ['version'=>'0'],['version'=>[]],['confirmar'=>'0'],['grupo_sanguineo'=>'O+'],
        ['fuente_grupo_sanguineo'=>'CU23_FUENTE_FICTICIA'],['grupo_sanguineo'=>'Z+'],['estado_alergias'=>'con_registros'],
        ['estado_alergias'=>'inventado'],['observaciones'=>str_repeat('x',501)],['observaciones'=>[]]
    ] as $c){$estado=array_key_exists('version',$c)?409:422;clinicoRechaza(fn()=>$s->guardarPerfil($p,$prueba->perfil(array_replace(['version'=>'1'],$c))),$estado,'perfil inválido o edición obsoleta rechazados');}
    clinicoAssert($base===$prueba->huella(),'errores de perfil sin cambios parciales');
    $s->guardarPerfil($p,$prueba->perfil(['version'=>'1','grupo_sanguineo'=>'AB-','fuente_grupo_sanguineo'=>'CU23_FUENTE_FICTICIA','observaciones'=>'CU23_RESUMEN_FICTICIO']));
    $d=$s->consultar($p);clinicoAssert($d['perfil']['grupo_sanguineo']==='AB-'&&(int)$d['perfil']['version']===2,'grupo documentado y actualización versionada');
    $id=$s->guardarAlergia($p,$prueba->alergia(['usuario_id'=>'1','creado_por_usuario_id'=>'1']));
    $d=$s->consultar($p,$id);
    clinicoAssert(count($d['alergias'])===1&&count($d['revisiones'])===1,'alta de alergia con revisión inicial');
    clinicoAssert($d['perfil']['estado_alergias']==='con_registros','alta actualiza el estado de información del perfil');
    clinicoAssert($d['revisiones'][0]['evento']==='registro'&&(int)$d['revisiones'][0]['usuario_id']===$prueba->actores['medico']['id'],'historial con autor real y tipo correcto');
    $base=$prueba->huella();
    clinicoRechaza(fn()=>$s->guardarAlergia($p,$prueba->alergia(['sustancia'=>' cu23_agente_ficticio '])),409,'duplicado activo normalizado rechazado');
    foreach([['sustancia'=>''],['sustancia'=>[]],['sustancia'=>str_repeat('x',121)],['reaccion'=>str_repeat('x',251)],['observaciones'=>str_repeat('x',501)],['confirmar'=>'0']] as $c){
        clinicoRechaza(fn()=>$s->guardarAlergia($p,$prueba->alergia($c)),422,'campos de alergia inválidos rechazados');
    }
    clinicoRechaza(fn()=>$s->guardarPerfil($p,$prueba->perfil(['version'=>(string)$d['perfil']['version'],'estado_alergias'=>'sin_conocidas'])),422,'alergia activa impide declarar ausencia');
    clinicoAssert($base===$prueba->huella(),'rechazos conservan perfil, alergia e historial');
    clinicoRechaza(fn()=>$s->guardarAlergia($p,$prueba->cambio($id,['reaccion'=>'CU23_OTRA_FICTICIA','motivo_cambio'=>'']),$id),422,'rectificación exige motivo');
    $s->guardarAlergia($p,$prueba->cambio($id,['reaccion'=>'CU23_REACCION_RECTIFICADA']),$id);
    $d=$s->consultar($p,$id);clinicoAssert(count($d['revisiones'])===2&&$d['revisiones'][1]['reaccion']==='CU23_REACCION_FICTICIA','rectificación conserva la imagen anterior');
    clinicoRechaza(fn()=>$s->guardarAlergia($p,$prueba->cambio($id,['version'=>'1','reaccion'=>'CU23_OTRA_FICTICIA']),$id),409,'edición obsoleta no sobrescribe cambios');
    $s->guardarAlergia($p,$prueba->cambio($id,['estado'=>'inactiva']),$id);
    $d=$s->consultar($p,$id);clinicoAssert($d['alergia']['estado']==='inactiva'&&count($d['revisiones'])===3,'inactivación conserva registro y revisiones');
    clinicoAssert($d['perfil']['estado_alergias']==='con_registros','inactivar última no declara ausencia automáticamente');
    $otro=$s->guardarAlergia($p,$prueba->alergia());clinicoAssert($otro!==$id,'misma sustancia permite un registro histórico inactivo y otro activo');
    $base=$prueba->huella();clinicoRechaza(fn()=>$s->guardarAlergia($p,$prueba->cambio($id,['estado'=>'activa']),$id),409,'reactivación duplicada revierte');
    clinicoAssert($base===$prueba->huella(),'conflicto de reactivación mantiene todas las huellas');
    $s->guardarAlergia($p,$prueba->cambio($otro,['estado'=>'inactiva']),$otro);
    $s->guardarAlergia($p,$prueba->cambio($id,['estado'=>'activa']),$id);
    clinicoAssert($s->consultar($p,$id)['revisiones'][0]['evento']==='reactivacion','reactivación explícita conserva auditoría');
    $segundo=$s->guardarAlergia($p,$prueba->alergia(['sustancia'=>'CU23_OTRO_AGENTE_FICTICIO']));
    clinicoAssert(count($s->consultar($p)['alergias'])===3,'paciente admite varias alergias y estados');
    foreach([$id,$segundo] as $a){$s->guardarAlergia($p,$prueba->cambio($a,['estado'=>'inactiva']),$a);}
    $v=$s->consultar($p)['perfil']['version'];$s->guardarPerfil($p,$prueba->perfil(['version'=>(string)$v,'estado_alergias'=>'sin_conocidas']));
    clinicoAssert($s->consultar($p)['perfil']['estado_alergias']==='sin_conocidas','sin conocidas requiere declaración expresa sin activas');
    foreach(['administrador','recepcionista','paciente'] as $rol){$r=$prueba->servicio($rol);
        clinicoRechaza(fn()=>$r->consultar($p),403,'rol no clínico rechazado en consulta');
        clinicoRechaza(fn()=>$r->guardarPerfil($p,$prueba->perfil()),403,'rol no clínico rechazado en perfil');
        clinicoRechaza(fn()=>$r->guardarAlergia($p,$prueba->alergia()),403,'rol no clínico rechazado en alergias');
        clinicoRechaza(fn()=>$r->consultar($p,$id),403,'rol no clínico rechazado en revisiones');
    }
    foreach(['sin_vinculo','otro_medico'] as $rol){clinicoRechaza(fn()=>$prueba->servicio($rol)->consultar($p),404,'médico sin relación no puede consultar');}
    foreach([$prueba->ajeno,$prueba->sinCita,PHP_INT_MAX] as $pAjeno){clinicoRechaza(fn()=>$s->consultar($pAjeno),404,'paciente ajeno o inexistente indistinguible');}
    clinicoRechaza(fn()=>$s->consultar($prueba->ajeno,$id),404,'manipulación conjunta paciente/alergia rechazada');
    clinicoRechaza(fn()=>$s->consultar($p,PHP_INT_MAX),404,'registro ajeno o inexistente no expuesto');
    $base=$prueba->huella();$fallo=$prueba->servicio(pdo:new PerfilClinicoFalloPDO($pdo));
    clinicoRechaza(fn()=>$fallo->guardarAlergia($p,$prueba->alergia(['sustancia'=>'CU23_FALLO_FICTICIO'])),503,'fallo al auditar revierte alta y estado del perfil');
    clinicoRechaza(fn()=>$fallo->guardarAlergia($p,$prueba->cambio($id,['reaccion'=>'CU23_FALLO_FICTICIO']),$id),503,'fallo al auditar revierte rectificación');
    clinicoAssert($base===$prueba->huella(),'atomicidad en las doce tablas tras errores de escritura');
    // Cambiar el día entre guardar y revalidar debe revertir toda la operación.
    $n=0;$hoy=new DateTimeImmutable('now',App\Models\HorarioAtencion::zona());
    $vence=$prueba->servicio(reloj:static function()use(&$n,$hoy){return ++$n<=2?$hoy:$hoy->modify('+1 day');});
    clinicoRechaza(fn()=>$vence->guardarAlergia($p,$prueba->alergia(['sustancia'=>'CU23_VENCIDA_FICTICIA'])),404,'ventana vencida durante operación causa rollback');
    clinicoAssert($base===$prueba->huella(),'vencimiento no deja alta ni revisión parcial');
    // Estados/días negativos sobre pacientes propios sin otras citas que concedan acceso.
    foreach(['cancelada','inasistencia'] as $i=>$estado){$np=$prueba->fixture->paciente();$prueba->cita($np,$prueba->medico,$hoy->setTime(1,$i*30)->format('c'),$estado);clinicoRechaza(fn()=>$s->consultar($np),404,'cita no asistencial no concede acceso');}
    foreach([-1,1] as $offset){$np=$prueba->fixture->paciente();$prueba->cita($np,$prueba->medico,$hoy->modify(($offset>0?'+':'').$offset.' day')->setTime(2,0)->format('c'));clinicoRechaza(fn()=>$s->consultar($np),404,'cita de otro día no concede acceso indefinido');}
    $manana=$hoy->modify('+1 day')->setTime(12,0);$conReloj=$prueba->servicio(reloj:static fn()=>$manana);
    foreach(['programada','confirmada','presente','atendida'] as $i=>$estado){
        $np=$prueba->fixture->paciente();$prueba->cita($np,$prueba->medico,$manana->setTime(8+intdiv($i,2),($i%2)*30)->format('c'),$estado);
        clinicoAssert($conReloj->consultar($np)['perfil']===null,'estado asistencial pertinente permite consultar en su día');
        $conReloj->guardarPerfil($np,$prueba->perfil());clinicoAssert($conReloj->consultar($np)['perfil']!==null,'estado asistencial pertinente permite registrar perfil');
    }
    $sql=$pdo->prepare('UPDATE pacientes SET activo=false WHERE id=:id');$sql->execute(['id'=>$p]);clinicoRechaza(fn()=>$s->consultar($p),404,'paciente inactivo revoca acceso');$pdo->prepare('UPDATE pacientes SET activo=true WHERE id=:id')->execute(['id'=>$p]);
    $pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id')->execute(['id'=>$prueba->medico]);clinicoRechaza(fn()=>$s->consultar($p),404,'médico inactivo revoca acceso');$pdo->prepare('UPDATE medicos SET activo=true WHERE id=:id')->execute(['id'=>$prueba->medico]);
    $u=$prueba->actores['medico']['id'];$pdo->prepare('UPDATE usuarios SET activo=false WHERE id=:id')->execute(['id'=>$u]);clinicoRechaza(fn()=>$s->consultar($p),403,'cuenta desactivada se revalida por operación');$pdo->prepare('UPDATE usuarios SET activo=true WHERE id=:id')->execute(['id'=>$u]);
    $pdo->prepare("UPDATE usuarios SET rol_id=(SELECT id FROM roles WHERE nombre='administrador') WHERE id=:id")->execute(['id'=>$u]);clinicoRechaza(fn()=>$s->consultar($p),403,'rol cambiado no conserva permiso clínico');$pdo->prepare("UPDATE usuarios SET rol_id=(SELECT id FROM roles WHERE nombre='medico') WHERE id=:id")->execute(['id'=>$u]);
    clinicoAssert((int)$pdo->query('SELECT count(*) FROM citas_historial WHERE cita_id='.(int)$prueba->citas['propia'])->fetchColumn()===0,'sin reutilizar historial administrativo');
    $meta=cu15Conexion()->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn();clinicoAssert((int)$meta===20,'integración conserva las veinte referencias');
}finally{if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. Integración CU23, privacidad, roles, auditoría y rollback; fixtures retirados.\n";
