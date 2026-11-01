<?php
declare(strict_types=1);
require __DIR__.'/AgendaPrueba.php';
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
use App\Services\AgendaService;
$pdo=cu15Conexion();$prueba=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$rechazar=static function(callable $accion,int $estado,string $n)use($ok):void{
    try{$accion();$ok(false,$n);}catch(CitaException $e){$ok($e->estadoHttp===$estado,$n);}catch(CatalogoValidacionException){$ok($estado===422,$n);}
};
try{
    $prueba=new AgendaPrueba($pdo);$fecha=$prueba->fecha;$m=$prueba->medico;$ids=$prueba->ids;
    $borde=$prueba->insertar('2027-01-01T03:30:00+00:00'); // 31/12, 23:30 en La Paz.
    $siguiente=$prueba->insertar('2027-01-01T04:00:00+00:00');
    $domingo=$prueba->insertar('2027-01-03T23:30:00-04:00');
    $lunes=$prueba->insertar('2027-01-04T00:00:00-04:00');
    $arrastre=$prueba->insertar('2027-02-01T23:45:00-04:00');
    $antes=$prueba->huella();$pdo->beginTransaction();$pdo->exec('SET TRANSACTION READ ONLY');
    $spy=new AgendaLecturaPDO($pdo);$service=$prueba->agenda('administrador',$spy);
    $base=['fecha'=>$fecha,'medico_id'=>$m];$dia=$service->consultar('diaria',$base);
    $ok(count($spy->consultas)===4,'consulta diaria con cuatro SELECT agrupados, sin N+1');
    $spy->consultas=[];$semana=$service->consultar('semanal',$base);
    $ok(count($spy->consultas)===4,'semana completa con cuatro SELECT agrupados');
    $sql=$spy->consultas[3];$ok(str_contains($sql,'c.medico_id=:medico')&&str_contains($sql,'<CAST(:hasta')&&str_contains($sql,'>=CAST(:desde')&&!str_contains($sql,'DATE('),'PDO parametrizado con médico y límites sobre columna indexada');
    foreach(['motivo','documento_identidad','direccion','telefono','hash_contrasena'] as $privado){$ok(!str_contains($sql,$privado),'no selecciona dato sensible '.$privado);}
    $ok($dia['desde']->format('Y-m-d')===$fecha&&count($dia['dias'])===1,'agenda diaria exacta');
    $ok(count($semana['dias'])===7&&$semana['desde']->format('N')==='1'&&$semana['hasta']->format('N')==='1','lunes a domingo con extremo superior exclusivo');
    $ok(array_column($semana['dias'],'titulo')===array_values(App\Models\HorarioAtencion::DIAS),'siete días en orden');
    $filas=AgendaPrueba::citas($dia);$ok(count($filas)===6&&!in_array('cancelada',array_column($filas,'estado'),true),'operativa oculta canceladas, incluye reemplazo');
    $ok(array_column($filas,'estado')===['programada','confirmada','presente','atendida','inasistencia','programada'],'orden cronológico con estados CU13–CU18');
    foreach(array_keys(App\Models\Cita::ESTADOS) as $estado){$a=$prueba->consultar('diaria',['estado'=>$estado]);$c=AgendaPrueba::citas($a);$ok(count($c)>0&&array_unique(array_column($c,'estado'))===[$estado],'filtro exacto '.$estado);}
    $todas=$prueba->consultar('diaria',['estado'=>'todas']);$ok(count(AgendaPrueba::citas($todas))===7,'filtro todos conserva cancelación histórica');
    $ok(count($dia['dias'][0]['franjas'])===2&&$dia['dias'][0]['franjas'][1]['inicio']->format('H:i')==='14:00','dos tramos reales CU11');
    $eventos=$dia['dias'][0]['eventos'];$ok(count(array_filter($eventos,fn($e)=>$e['tipo']==='vacio'))===4,'cuatro intervalos de treinta minutos sin cita registrada');
    $ok(count(array_filter($eventos,fn($e)=>$e['tipo']==='fuera'))===3,'fuera de atención antes, entre y después de tramos');
    $filtrada=$prueba->consultar('diaria',['estado'=>'cancelada']);$ok(count(array_filter($filtrada['dias'][0]['eventos'],fn($e)=>$e['tipo']==='oculto'))===6,'no presenta citas omitidas por filtro como huecos vacíos');
    $vacia=$prueba->consultar('diaria',['fecha'=>$prueba->t->lunes->modify('+1 day')->format('Y-m-d')]);$ok(AgendaPrueba::citas($vacia)===[]&&count($vacia['dias'][0]['franjas'])===1,'sin citas con atención real del martes');
    $sinHoras=$prueba->consultar('diaria',['fecha'=>$prueba->t->lunes->modify('+2 days')->format('Y-m-d')]);$ok($sinHoras['dias'][0]['franjas']===[]&&count($sinHoras['dias'][0]['eventos'])===1,'día sin atención ni citas controlado');
    $vaciaSemana=$prueba->consultar('semanal',['fecha'=>'2031-06-01']);$ok(count($vaciaSemana['dias'])===7&&AgendaPrueba::citas($vaciaSemana)===[],'semana sin citas válida');
    foreach(['administrador','recepcionista','medico'] as $rol){$ok(count(AgendaPrueba::citas($prueba->consultar('diaria',[],$rol)))===6,'alcance permitido '.$rol);}
    $propia=$prueba->agenda('medico')->consultar('semanal',['fecha'=>$fecha]);$ok($propia['medicoId']===$m&&count($propia['medicos'])===1,'médico deriva ID del vínculo sin filtro');
    $rechazar(fn()=>$prueba->consultar('diaria',['medico_id'=>$prueba->otro],'medico'),403,'médico no fuerza otra agenda');
    $rechazar(fn()=>$prueba->consultar('diaria',['medico_id'=>PHP_INT_MAX],'medico'),403,'no revela existencia de identificador ajeno');
    $rechazar(fn()=>$prueba->agenda('paciente'),403,'paciente sin módulo');
    $noVinculo=$prueba->agenda('sin_vinculo')->consultar('semanal',['fecha'=>$fecha]);$ok($noVinculo['sinVinculo']&&$noVinculo['medicos']===[]&&AgendaPrueba::citas($noVinculo)===[],'médico sin vínculo no obtiene otras agendas');
    $sinSeleccion=$prueba->agenda()->consultar('diaria',['fecha'=>$fecha]);$ok($sinSeleccion['medico']===null&&AgendaPrueba::citas($sinSeleccion)===[],'administrador debe seleccionar un profesional');
    $rechazar(fn()=>$prueba->consultar('diaria',['medico_id'=>PHP_INT_MAX]),404,'médico inexistente controlado');
    foreach(['2026-02-30','0000-01-01','2026-13-01','2026-1-1','2026-01-01 OR 1=1','<script>','2026-11-01'."\0",['2026-11-01']] as $valor){$rechazar(fn()=>$prueba->consultar('diaria',['fecha'=>$valor]),422,'fecha inválida o malformada');}
    foreach([0,-1,'1 OR 1=1',[],str_repeat('9',50)] as $valor){$rechazar(fn()=>$prueba->consultar('diaria',['medico_id'=>$valor]),422,'médico inválido o inyección');}
    foreach(['borrada',[],str_repeat('x',50)] as $valor){$rechazar(fn()=>$prueba->consultar('diaria',['estado'=>$valor]),422,'estado inválido');}
    foreach(['2028-02-29','0001-01-01','9999-12-31'] as $valor){$ok($service->validar(['fecha'=>$valor])['fecha']===$valor,'fecha real válida '.$valor);}
    $cruce=$prueba->consultar('semanal',['fecha'=>'2027-01-01']);$ok($cruce['desde']->format('Y-m-d')==='2026-12-28'&&$cruce['hasta']->format('Y-m-d')==='2027-01-04','semana cruza mes y año correctamente');
    $cruceIds=array_column(AgendaPrueba::citas($cruce),'id');$ok(in_array($borde,$cruceIds)&&in_array($siguiente,$cruceIds)&&in_array($domingo,$cruceIds)&&!in_array($lunes,$cruceIds),'domingo incluido y lunes siguiente excluido');
    $finAnio=$prueba->consultar('diaria',['fecha'=>'2026-12-31']);$ok(array_column(AgendaPrueba::citas($finAnio),'id')===[$borde],'medianoche UTC se asigna al día local correcto');
    $inicioAnio=$prueba->consultar('diaria',['fecha'=>'2027-01-01']);$ok(array_column(AgendaPrueba::citas($inicioAnio),'id')===[$siguiente],'00:00 local incluido en día nuevo');
    $ok(AgendaPrueba::citas($finAnio)[0]['inicio']->format('Y-m-d H:i P')==='2026-12-31 23:30 -04:00','cita conserva instante y zona');
    $ok($finAnio['dias'][0]['eventos'][1]['cubierta']===false,'cita histórica fuera de horario no desaparece');
    $arr=$prueba->consultar('diaria',['fecha'=>'2027-02-02']);$ok(AgendaPrueba::citas($arr)===[],'arrastre del día anterior no expone otra fecha');
    $nav=$prueba->consultar('diaria',['fecha'=>'2026-12-31','estado'=>'todas']);parse_str(parse_url($nav['navegacion']['siguiente'],PHP_URL_QUERY),$n);
    $ok($n===['fecha'=>'2027-01-01','medico_id'=>(string)$m,'estado'=>'todas'],'día siguiente mantiene médico/filtro al cruzar año');
    parse_str(parse_url($cruce['navegacion']['siguiente'],PHP_URL_QUERY),$n);$ok($n['fecha']==='2027-01-04','semana siguiente avanza siete días');
    parse_str(parse_url($cruce['navegacion']['anterior'],PHP_URL_QUERY),$n);$ok($n['fecha']==='2026-12-21','semana anterior retrocede siete días');
    $ok($pdo->query('SHOW transaction_read_only')->fetchColumn()==='on','todas las consultas completas pasan en READ ONLY');
    $pdo->rollBack();$ok($antes===$prueba->huella(),'lecturas no alteran ninguna de las nueve tablas');
    $prueba->t->horarios->guardar(['medico_id'=>$m,'dia_semana'=>2,'hora_inicio'=>'00:00','hora_fin'=>'00:30']);
    $arr=$prueba->consultar('diaria',['fecha'=>'2027-02-02']);
    $ok(count(array_filter($arr['dias'][0]['eventos'],fn($e)=>$e['tipo']==='oculto'&&$e['inicio']->format('H:i')==='00:00'))===1&&AgendaPrueba::citas($arr)===[],'arrastre ocupa fracción de medianoche sin exponer cita del día anterior');
    $mes=$prueba->consultar('diaria',['fecha'=>'2027-04-30']);parse_str(parse_url($mes['navegacion']['siguiente'],PHP_URL_QUERY),$n);
    $ok($n['fecha']==='2027-05-01','día siguiente cruza mes de treinta días');
    $bis=$prueba->consultar('semanal',['fecha'=>'2028-02-29']);
    $ok($bis['desde']->format('Y-m-d')==='2028-02-28'&&$bis['hasta']->format('Y-m-d')==='2028-03-06','semana bisiesta cruza febrero y marzo');
    foreach(['0001-01-01','9999-12-31'] as $extremo){
        $ext=$prueba->consultar('semanal',['fecha'=>$extremo]);$ok(count($ext['dias'])===7&&AgendaPrueba::citas($ext)===[],'consulta real controlada de extremo de fecha '.$extremo);
    }
    // Integración tras escrituras legítimas de los casos existentes, solo sobre fixtures.
    $a=$prueba->actores['administrador']['id'];$f=$prueba->t->fixture;
    $nueva=$prueba->t->servicio->crear($prueba->t->datos($m,$prueba->paciente,$prueba->especialidad,'11:30'),$a)['id'];
    $ok(in_array($nueva,array_column(AgendaPrueba::citas($prueba->consultar()),'id'),true),'reserva nueva CU13 aparece al consultar');
    $nuevaFecha=$prueba->t->lunes->modify('+7 days')->format('Y-m-d');$r=$prueba->asistencia->previa->c->r;
    $r->servicio->reprogramar($nueva,$r->datos($nueva,'08:00',$nuevaFecha),$a);
    $ok(!in_array($nueva,array_column(AgendaPrueba::citas($prueba->consultar()),'id'),true)&&in_array($nueva,array_column(AgendaPrueba::citas($prueba->consultar('diaria',['fecha'=>$nuevaFecha])),'id'),true),'CU15 mueve la cita entre agendas sin duplicarla');
    $prueba->asistencia->previa->c->servicio->cancelar($nueva,$prueba->asistencia->previa->c->datos($nueva),$a);
    $ok(AgendaPrueba::citas($prueba->consultar('diaria',['fecha'=>$nuevaFecha]))===[]&&count(AgendaPrueba::citas($prueba->consultar('diaria',['fecha'=>$nuevaFecha,'estado'=>'cancelada'])))===1,'CU16 oculta cancelada operativa y permite historia');
    $q=$pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id');$q->execute(['id'=>$m]);
    $inactivo=$prueba->consultar();$ok(!$inactivo['medico']['activo']&&$inactivo['dias'][0]['franjas']===[]&&count(AgendaPrueba::citas($inactivo))===6,'profesional inactivo conserva citas sin atención activa');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. Agenda real, READ ONLY, filtros/fechas y transiciones existentes; fixtures propios retirados.\n";
