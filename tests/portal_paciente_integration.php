<?php
declare(strict_types=1);
require __DIR__.'/PortalPacientePrueba.php';
$pdo=cu15Conexion();$f=null;$total=0;$antes=cu24Huella($pdo);
try{
    $f=new PortalPacientePrueba($pdo);$s=$f->portal();$v=new App\Services\VinculacionPacienteService($pdo);$admin=$f->actores['administrador']['id'];
    foreach(['paciente','recepcionista','medico'] as $rol){portalRechaza(fn()=>$v->vincular($f->sinCuenta,$f->actores['sin_vinculo']['id'],'1',$f->actores[$rol]['id']),403,'vinculación exclusiva Administrador');portalRechaza(fn()=>$f->portal($rol==='paciente'?'sin_vinculo':$rol)->listar([]),403,'portal exclusivo Paciente vinculado');}
    portalRechaza(fn()=>$f->portal('administrador')->listar([]),403,'administrador no suplanta');
    foreach([null,'0',1,[],true] as $confirmar){portalRechaza(fn()=>$v->vincular($f->sinCuenta,$f->actores['sin_vinculo']['id'],$confirmar,$admin),422,'confirmación explícita identidad');}
    foreach([[],0,-1,'1 OR 1=1','9223372036854775808'] as $id){portalRechaza(fn()=>$v->vincular($f->sinCuenta,$id,'1',$admin),422,'identificador de cuenta inválido');}
    portalRechaza(fn()=>$v->vincular($f->sinCuenta,$admin,'1',$admin),409,'rol destino incorrecto');
    portalRechaza(fn()=>$v->vincular($f->paciente,$f->actores['sin_vinculo']['id'],'1',$admin),409,'no sustitución');
    portalRechaza(fn()=>$v->vincular($f->sinCuenta,$f->actores['paciente']['id'],'1',$admin),409,'cuenta única');
    $pdo->prepare('UPDATE usuarios SET activo=false WHERE id=:u')->execute(['u'=>$f->actores['sin_vinculo']['id']]);
    portalRechaza(fn()=>$v->vincular($f->sinCuenta,$f->actores['sin_vinculo']['id'],'1',$admin),409,'destino inactivo');
    $pdo->prepare('UPDATE usuarios SET activo=true WHERE id=:u')->execute(['u'=>$f->actores['sin_vinculo']['id']]);
    $pdo->prepare('UPDATE pacientes SET activo=false WHERE id=:p')->execute(['p'=>$f->sinCuenta]);
    portalRechaza(fn()=>$v->vincular($f->sinCuenta,$f->actores['sin_vinculo']['id'],'1',$admin),409,'paciente inactivo');
    $pdo->prepare('UPDATE pacientes SET activo=true WHERE id=:p')->execute(['p'=>$f->sinCuenta]);
    $recibo=$v->vincular($f->sinCuenta,$f->actores['sin_vinculo']['id'],'1',$admin);
    portalAssert($recibo['administrador_id']===$admin&&isset($recibo['realizada_en']),'recibo transitorio de actor y momento');
    portalAssert($f->portal('sin_vinculo')->listar([])['resultado']['total']===0,'vinculación inicial habilita solo sus propias citas');
    foreach(['paciente_id','usuario_id','actor_id','rol','documento_identidad','ci','nombres','apellidos','correo','telefono'] as $campo){
        portalRechaza(fn()=>$s->listar([$campo=>'CU25_AJENO']),422,'identidad GET rechazada');
        portalRechaza(fn()=>$s->reservar([$campo=>'CU25_AJENO']+$f->entrada()),422,'identidad POST rechazada');
    }
    foreach([['fecha'=>'2000-01-01'],['fecha'=>'2026-02-30'],['fecha'=>[]],['hora_inicio'=>'08:15'],['confirmar'=>'0'],['medico_id'=>'1;SELECT 1'],['especialidad_id'=>[]]] as $c){portalRechaza(fn()=>$s->reservar(array_replace($f->entrada(),$c)),422,'reserva inválida');}
    $id=$f->cita();$ajena=$f->cita('08:30','otro');$r=$s->listar([]);
    portalAssert($r['resultado']['total']===1&&(int)$r['resultado']['filas'][0]['id']===$id,'solo próxima propia');
    portalAssert($s->detalle($id)['cita']['codigo']===App\Models\Cita::codigo($id),'código conservado');
    portalRechaza(fn()=>$s->reservar($f->entrada()),409,'turno propio ocupado');
    portalRechaza(fn()=>$f->portal('otro')->reservar($f->entrada()),409,'turno ocupado por otro paciente');
    foreach(['detalle','formulario'] as $a){portalRechaza(fn()=>$a==='detalle'?$s->detalle($ajena):$s->formulario([],$ajena,'reprogramar'),404,'recurso ajeno');}
    foreach(['reprogramar','cancelar'] as $a){portalRechaza(fn()=>$s->cambiar($ajena,$a,['confirmar'=>'1']),404,'mutación ajena');}
    // Los servicios compartidos también rechazan accesos directos al paciente ajeno.
    portalRechaza(fn()=>$f->base->servicio->crear($f->base->datos($f->medico,$f->ajeno,$f->especialidad,'10:30'),$f->actores['paciente']['id']),403,'reserva directa ajena');
    $repro=new App\Services\ReprogramacionService($pdo,new App\Repositories\CitaRepository($pdo),$f->base->disponibilidad);
    $cancel=new App\Services\CancelacionService($pdo,new App\Repositories\CitaRepository($pdo));
    portalRechaza(fn()=>$repro->reprogramar($ajena,[],$f->actores['paciente']['id']),404,'reprogramación directa ajena');
    portalRechaza(fn()=>$cancel->cancelar($ajena,['accion'=>'confirmar'],$f->actores['paciente']['id']),404,'cancelación directa ajena');
    $datos=$f->mutacion($id);$s->cambiar($id,'reprogramar',$datos);
    $d=$s->detalle($id);portalAssert(count($d['eventos']['filas'])===1&&$d['eventos']['filas'][0]['tipo']==='reprogramacion','reprogramación conserva historial');
    portalRechaza(fn()=>$s->cambiar($id,'reprogramar',$datos),409,'versión obsoleta');
    $c=$f->mutacion($id,accion:'cancelar')+['motivo_cancelacion'=>'CU25_MOTIVO_PRIVADO_FICTICIO'];$s->cambiar($id,'cancelar',$c);$d=$s->detalle($id);
    portalAssert($d['cita']['estado']==='cancelada'&&count($d['eventos']['filas'])===2,'cancelación y ambos eventos');
    portalAssert(!str_contains(json_encode($d,JSON_THROW_ON_ERROR),'CU25_MOTIVO_PRIVADO_FICTICIO')&&!str_contains(json_encode($d),'responsable'),'historial sin motivo interno ni responsable');
    portalAssert($s->listar(['periodo'=>'todas','estado'=>'cancelada'])['resultado']['total']===1,'filtro canceladas');
    $f->cita('09:00','otro');portalAssert(true,'cancelación libera intervalo para otra cuenta');
    portalRechaza(fn()=>$s->formulario([],$id,'reprogramar'),409,'cancelada no reprogramable');
    portalRechaza(fn()=>$s->cambiar($id,'cancelar',$c),409,'cancelada no cancelable otra vez');
    // Fixtures históricos y de estados: no cambian estados a través del portal.
    foreach(['confirmada','presente','atendida','inasistencia'] as $i=>$estado){
        $hora=sprintf('%02d:%02d',10+intdiv($i,2),($i%2)*30);$cid=$f->cita($hora);
        $pdo->prepare('UPDATE citas SET estado=:s WHERE id=:id')->execute(['s'=>$estado,'id'=>$cid]);
        foreach(['cancelar','reprogramar'] as $a){portalRechaza(fn()=>$s->formulario([],$cid,$a),409,'estado '.$estado.' no habilita '.$a);portalRechaza(fn()=>$s->cambiar($cid,$a,['confirmar'=>'1']),409,'servidor conserva estado '.$estado);}
    }
    $cid=$f->legado('2001-01-01T08:00:00-04:00');
    portalAssert($s->listar(['periodo'=>'historicas'])['resultado']['total']===1,'anteriores separadas');
    for($i=0;$i<23;$i++){$f->legado((new DateTimeImmutable('2002-01-01T08:00:00-04:00'))->modify('+'.$i.' days')->format('c'));}
    $pagina=$s->listar(['periodo'=>'historicas','pagina'=>'2']);portalAssert($pagina['resultado']['total']===24&&count($pagina['resultado']['filas'])===4,'historial paginado');
    portalAssert($s->listar(['periodo'=>'historicas','pagina'=>'999'])['resultado']['pagina']===2,'página extrema limitada');
    foreach([['desde'=>'2026-02-30'],['hasta'=>[]],['periodo'=>'<script>'],['pagina'=>'1 OR 1=1'],['estado'=>[]],['desde'=>'2026-11-10','hasta'=>'2026-11-01']] as $c){portalRechaza(fn()=>$s->listar($c),422,'filtro inválido');}
    $pdo->prepare('UPDATE pacientes SET activo=false WHERE id=:p')->execute(['p'=>$f->paciente]);portalRechaza(fn()=>$s->listar([]),403,'paciente desactivado sin citas');
    $pdo->prepare('UPDATE pacientes SET activo=true WHERE id=:p')->execute(['p'=>$f->paciente]);
    $pdo->prepare('UPDATE usuarios SET activo=false WHERE id=:u')->execute(['u'=>$f->actores['paciente']['id']]);portalRechaza(fn()=>$s->detalle($id),403,'cuenta desactivada sin recurso');
    $pdo->prepare('UPDATE usuarios SET activo=true WHERE id=:u')->execute(['u'=>$f->actores['paciente']['id']]);
}finally{if($f){$f->limpiar();}}
portalAssert($antes===cu24Huella($pdo),'conservación exacta de las dieciséis tablas previas');
echo "Aprobadas: $total. Vinculación, alcance propio, reglas compartidas, estados e historial.\n";
