<?php
declare(strict_types=1);
require __DIR__.'/RegistroClinicoPrueba.php';
$pdo=cu15Conexion();$f=null;$total=0;
try{
    $f=new PerfilClinicoPrueba($pdo);$p=$f->paciente;$s=cu24Servicio($f);
    foreach(['antecedentes','laboratorios'] as $tipo){
        $base=cu24Huella($pdo);$d=$s->consultar($p,$tipo);clinicoAssert($d['registros']===[]&&$base===cu24Huella($pdo),'consulta vacía no afirma ausencia ni crea registros');
        $id=$s->guardar($p,$tipo,cu24Entrada($tipo));$d=$s->consultar($p,$tipo,$id);
        clinicoAssert(count($d['registros'])===1&&count($d['revisiones'])===1&&$d['revisiones'][0]['evento']==='registro','alta e instantánea inicial atómicas');
        clinicoAssert((int)$d['registro']['creado_por_usuario_id']===$f->actores['medico']['id'],'responsable del actor autenticado');
        if($tipo==='antecedentes'){clinicoAssert($d['registro']['fecha_evento']===null&&$d['registro']['fecha_aproximada']===null,'fecha desconocida permanece NULL');}
        else{clinicoAssert($d['registro']['valor']===null&&$d['registro']['unidad']===null&&$d['registro']['fecha_resultado']===null,'resultado cualitativo y desconocidos sin números inventados');}
        $segundo=$s->guardar($p,$tipo,cu24Entrada($tipo));clinicoAssert($segundo!==$id&&count($s->consultar($p,$tipo)['registros'])===2,'admite varios episodios o estudios iguales');
        $campo=$tipo==='antecedentes'?'descripcion':'resultado_texto';$original=$d['registro'][$campo];
        $s->guardar($p,$tipo,cu24Cambio($f,$tipo,$id,[$campo=>'CU24_RECTIFICACION_FICTICIA','usuario_id'=>'1']),$id);
        $d=$s->consultar($p,$tipo,$id);clinicoAssert((int)$d['registro']['version']===2&&count($d['revisiones'])===2,'rectificación genera una versión adicional');
        clinicoAssert($d['revisiones'][1][$campo]===$original&&$d['revisiones'][0][$campo]==='CU24_RECTIFICACION_FICTICIA','conserva instantánea anterior y actual');
        clinicoAssert((int)$d['revisiones'][0]['usuario_id']===$f->actores['medico']['id']&&$d['revisiones'][0]['motivo_cambio']==='CU24_MOTIVO_FICTICIO','auditoría con autor y motivo del cambio');
        $base=cu24Huella($pdo);
        foreach([['version'=>'1'],['version'=>[]],['confirmar'=>'0'],['motivo_cambio'=>''],[$campo=>[]],[$campo=>str_repeat('x',$tipo==='antecedentes'?1001:2001)],['estado'=>'borrado']] as $c){
            clinicoRechaza(fn()=>$s->guardar($p,$tipo,cu24Cambio($f,$tipo,$id,$c),$id),array_key_exists('version',$c)?409:422,'edición inválida o versión obsoleta rechazada');
        }
        clinicoRechaza(fn()=>$s->guardar($p,$tipo,cu24Cambio($f,$tipo,$id),$id),409,'no crea revisión sin cambios');
        clinicoAssert($base===cu24Huella($pdo),'rechazos no dejan escrituras parciales en ninguna tabla');
        foreach(['inactivo','activo'] as $estado){$s->guardar($p,$tipo,cu24Cambio($f,$tipo,$id,['estado'=>$estado]),$id);$d=$s->consultar($p,$tipo,$id);clinicoAssert($d['registro']['estado']===$estado&&count($d['revisiones'])===(int)$d['registro']['version'],'cambio de estado conserva todas las revisiones');}
        foreach(['administrador','recepcionista','paciente'] as $rol){$no=cu24Servicio($f,$rol);
            clinicoRechaza(fn()=>$no->consultar($p,$tipo),403,'rol administrativo o paciente sin lectura clínica');
            clinicoRechaza(fn()=>$no->guardar($p,$tipo,cu24Entrada($tipo)),403,'rol no clínico sin escritura');
            clinicoRechaza(fn()=>$no->consultar($p,$tipo,$id),403,'rol no clínico sin acceso a revisiones');
        }
        foreach(['sin_vinculo','otro_medico'] as $rol){clinicoRechaza(fn()=>cu24Servicio($f,$rol)->consultar($p,$tipo,$id),404,'médico sin vínculo o relación no consulta registros');}
        foreach([$f->ajeno,$f->sinCita,PHP_INT_MAX] as $ajeno){clinicoRechaza(fn()=>$s->consultar($ajeno,$tipo,$id),404,'identificador de paciente manipulado rechazado');}
        clinicoRechaza(fn()=>$s->guardar($f->ajeno,$tipo,cu24Entrada($tipo),$id),404,'escritura fuera del paciente autorizable rechazada');
        clinicoRechaza(fn()=>$s->consultar($p,$tipo,PHP_INT_MAX),404,'registro inexistente no revela información');
        $base=cu24Huella($pdo);$fallo=cu24Servicio($f,pdo:new RegistroClinicoFalloPDO($pdo));
        clinicoRechaza(fn()=>$fallo->guardar($p,$tipo,cu24Entrada($tipo)),503,'fallo de auditoría revierte registro nuevo');
        clinicoRechaza(fn()=>$fallo->guardar($p,$tipo,cu24Cambio($f,$tipo,$id,[$campo=>'CU24_ERROR_FICTICIO']),$id),503,'fallo de auditoría revierte corrección');
        clinicoAssert($base===cu24Huella($pdo),'rollback completo conserva versiones previas');
    }
    $ante=$s->guardar($p,'antecedentes',cu24Entrada('antecedentes',['categoria'=>'familiar','fecha_evento'=>'2001-02-03','fecha_aproximada'=>'1']));
    clinicoAssert($s->consultar($p,'antecedentes',$ante)['registro']['fecha_aproximada']===true,'fecha aproximada declarada se conserva');
    foreach([['fecha_evento'=>'2001-02-03','fecha_aproximada'=>''],['fecha_aproximada'=>'1'],['categoria'=>'alergias'],['fecha_evento'=>'2999-01-01','fecha_aproximada'=>'0']] as $c){clinicoRechaza(fn()=>$s->guardar($p,'antecedentes',cu24Entrada('antecedentes',$c)),422,'fecha y categoría de antecedente coherentes');}
    $num=$s->guardar($p,'laboratorios',cu24Entrada('laboratorios',['valor'=>'-1.250000','unidad'=>'CU24_UNIDAD_FICTICIA','fecha_muestra'=>'2000-01-01','fecha_resultado'=>'2000-01-02','rango_referencia'=>'CU24_RANGO_FICTICIO','laboratorio'=>'CU24_ORIGEN_FICTICIO']));
    $d=$s->consultar($p,'laboratorios',$num);clinicoAssert($d['registro']['valor']==='-1.250000'&&$d['registro']['unidad']==='CU24_UNIDAD_FICTICIA','valor numérico exacto y unidad sin interpretación');
    foreach([['unidad'=>'CU24_UNIDAD_FICTICIA'],['fecha_muestra'=>'2000-02-01','fecha_resultado'=>'2000-01-01'],['fecha_resultado'=>'2001-02-29'],['valor'=>'1.0000001'],['resultado_texto'=>'']] as $c){clinicoRechaza(fn()=>$s->guardar($p,'laboratorios',cu24Entrada('laboratorios',$c)),422,'fechas, unidad o resultado incompatibles rechazados');}
    $orden=$s->consultar($p,'laboratorios')['registros'];clinicoAssert((int)$orden[0]['id']===$num,'listado usa fechas médicas conocidas antes de registros sin fecha');
    // Política temporal y revocación compartidas con CU23: sin ampliación histórica.
    $hoy=new DateTimeImmutable('today',App\Models\HorarioAtencion::zona());
    foreach(['cancelada','inasistencia'] as $i=>$estado){$np=$f->fixture->paciente();$f->cita($np,$f->medico,$hoy->setTime(2,$i*30)->format('c'),$estado);clinicoRechaza(fn()=>$s->consultar($np,'antecedentes'),404,'estado no asistencial no concede permiso CU24');}
    foreach([-1,1] as $offset){$np=$f->fixture->paciente();$f->cita($np,$f->medico,$hoy->modify(($offset>0?'+':'').$offset.' day')->setTime(3,0)->format('c'));clinicoRechaza(fn()=>$s->consultar($np,'laboratorios'),404,'cita de otro día no concede permiso CU24');}
    $base=cu24Huella($pdo);$contador=0;$vence=cu24Servicio($f,reloj:static function()use(&$contador,$hoy){return ++$contador<=3?$hoy:$hoy->modify('+1 day');});
    clinicoRechaza(fn()=>$vence->guardar($p,'antecedentes',cu24Entrada('antecedentes')),404,'permiso vencido antes de confirmar revierte CU24');clinicoAssert($base===cu24Huella($pdo),'vencimiento no deja registro ni instantánea parcial');
    $pdo->beginTransaction();$q=$pdo->prepare('UPDATE antecedentes_clinicos SET observaciones=:v WHERE id=:id');$q->execute(['v'=>'CU24_EXTERNO_FICTICIO','id'=>$ante]);
    clinicoRechaza(fn()=>cu24Servicio($f,pdo:new RegistroClinicoFalloPDO($pdo))->guardar($p,'antecedentes',cu24Entrada('antecedentes')),503,'fallo anidado revierte solo su savepoint');
    $q=$pdo->prepare('SELECT observaciones FROM antecedentes_clinicos WHERE id=:id');$q->execute(['id'=>$ante]);clinicoAssert($pdo->inTransaction()&&$q->fetchColumn()==='CU24_EXTERNO_FICTICIO','transacción externa conserva su trabajo');$pdo->rollBack();
    clinicoAssert($base===cu24Huella($pdo),'rollback externo restaura las dieciséis tablas');
}finally{cu24Limpiar($f);}
echo "Aprobadas: $total. Integración CU24, versiones, permisos y rollback con fixtures retirados.\n";
