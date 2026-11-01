<?php
declare(strict_types=1);
require __DIR__.'/HistorialPacientePrueba.php';
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
$pdo=cu15Conexion();$prueba=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$rechazar=static function(callable $accion,int $estado,string $n)use($ok):void{
    try{$accion();$ok(false,$n);}catch(CitaException $e){$ok($e->estadoHttp===$estado,$n);}catch(CatalogoValidacionException){$ok($estado===422,$n);}
};
try{
    $prueba=new HistorialPacientePrueba($pdo);$b=$prueba->base;$p=$b->paciente;$ids=$b->ids;$prueba->reprogramaciones();
    $antes=$b->huella();$pdo->beginTransaction();$pdo->exec('SET TRANSACTION READ ONLY');
    $spy=new AgendaLecturaPDO($pdo);$s=$prueba->servicio('administrador',$spy);$hist=$s->consultar($p,[]);
    $ok(count($spy->consultas)===6,'seis SELECT agrupados para veinte citas, sin N+1');
    $ok($hist['resultado']['total']===41&&count($hist['resultado']['filas'])===20&&$hist['resultado']['paginas']===3,'historial paginado de cuarenta y una citas');
    $spy->consultas=[];$eventos=$s->eventos($p,$ids['programada'],[]);$ok(count($spy->consultas)===4,'cuatro SELECT acotados para detalle de eventos');
    $ok($eventos['eventos']['total']===23&&count($eventos['eventos']['filas'])===20,'eventos también paginados, sin cargar auditoría completa');
    $segunda=$s->eventos($p,$ids['programada'],['pagina'=>2]);$ok(count($segunda['eventos']['filas'])===3&&$segunda['eventos']['pagina']===2,'segunda página de eventos');
    $todos=[];for($i=1;$i<=3;++$i){$todos=array_merge($todos,$s->consultar($p,['pagina'=>$i])['resultado']['filas']);}
    $ok(count($todos)===41&&count(array_unique(array_column($todos,'id')))===41,'cada cita aparece una vez aunque tenga veintitrés cambios');
    $orden=$todos;usort($orden,static fn($a,$b)=>(new DateTimeImmutable($b['fecha_hora_inicio'])<=>new DateTimeImmutable($a['fecha_hora_inicio']))?:($b['id']<=>$a['id']));
    $ok(array_column($todos,'id')===array_column($orden,'id'),'orden descendente estable por inicio e ID, incluso fechas iguales');
    $programada=array_values(array_filter($todos,fn($c)=>(int)$c['id']===$ids['programada']))[0];
    $ok(str_ends_with($programada['inicio_local'],'11:00')&&$programada['eventos']===23,'cita reprogramada conserva identidad y horario vigente');
    $ok(count($hist['medicos'])===2&&count($hist['especialidades'])===2,'opciones derivadas de las citas del paciente');
    foreach(['administrador','recepcionista','medico'] as $rol){$h=$prueba->consultar([],$rol);$ok($h['resultado']['total']===($rol==='medico'?40:41),'alcance principal '.$rol);}
    $med=$prueba->consultar([],'medico');$ok(count($med['medicos'])===1&&count($med['especialidades'])===1&&!$med['seleccionarMedico'],'médico no obtiene catálogos de otras atenciones');
    $ok(!in_array($ids['ajena'],array_column($med['resultado']['filas'],'id'),true),'excluye cita de otro médico del mismo paciente');
    $rechazar(fn()=>$prueba->servicio('medico')->eventos($p,$ids['ajena'],[]),404,'no consulta eventos de cita ajena del mismo paciente');
    $rechazar(fn()=>$prueba->consultar([],'medico',$prueba->ajeno),404,'paciente exclusivo de otro médico no accesible');
    $rechazar(fn()=>$prueba->consultar([],'sin_vinculo'),404,'médico sin vínculo no accede por ID');
    $rechazar(fn()=>$prueba->servicio('paciente'),403,'paciente vinculado sigue sin portal propio CU14/CU20');
    $rechazar(fn()=>$prueba->consultar([], 'administrador',PHP_INT_MAX),404,'paciente inexistente controlado');
    $ok($prueba->consultar([], 'administrador',$prueba->vacio)['resultado']['total']===0,'paciente sin citas válido para administración');
    $ok($prueba->consultar([], 'administrador',$prueba->unico)['resultado']['total']===1,'paciente con una cita');
    $rechazar(fn()=>$prueba->servicio()->eventos($prueba->unico,$ids['programada'],[]),404,'cita de otro paciente no cruza la ruta');
    foreach(App\Models\Cita::ESTADOS as $estado=>$label){$h=$prueba->consultar(['estado'=>$estado]);$ok($h['resultado']['total']>0&&array_unique(array_column($h['resultado']['filas'],'estado'))===[$estado],'estado histórico '.$estado);}
    $ok($prueba->consultar(['periodo'=>'proximas'])['resultado']['total']===8,'futuras incluyen cancelada y todos los estados actuales');
    $ok($prueba->consultar(['periodo'=>'historicas'])['resultado']['total']===33,'anteriores conservan citas históricas');
    $ok($prueba->consultar(['desde'=>$b->fecha,'hasta'=>$b->fecha])['resultado']['total']===8,'rango inclusive del mismo día');
    $ok($prueba->consultar(['desde'=>'2030-01-01'])['resultado']['total']===0,'filtro válido sin resultados');
    $ok($prueba->consultar(['medico_id'=>$b->medico])['resultado']['total']===40,'filtro médico administrativo');
    $ok($prueba->consultar(['especialidad_id'=>$b->especialidad])['resultado']['total']===39,'filtro especialidad conserva alcance');
    $rechazar(fn()=>$prueba->consultar(['medico_id'=>$b->otro],'medico'),422,'médico no fuerza filtro de otro profesional');
    $ok($prueba->consultar(['especialidad_id'=>$b->t->fixture->especialidades[1]],'medico')['resultado']['total']===0,'especialidad ajena no revela citas');
    foreach([['desde'=>'2026-02-30'],['hasta'=>'0000-01-01'],['desde'=>'2026-11-02','hasta'=>'2026-11-01'],['desde'=>[]],['estado'=>[]],['estado'=>'eliminada'],['pagina'=>0],['pagina'=>[]],['medico_id'=>'1 OR 1=1'],['especialidad_id'=>-1]] as $f){$rechazar(fn()=>$prueba->consultar($f),422,'filtro inválido rechazado');}
    $ok($prueba->consultar(['pagina'=>PHP_INT_MAX])['resultado']['pagina']===3,'página excesiva se limita al total real');
    $tipos=[];
    foreach(['programada','confirmada','presente','atendida','inasistencia','cancelada'] as $clave){
        $e=$s->eventos($p,$ids[$clave],[])['eventos']['filas'];foreach($e as $ev){$tipos[$ev['tipo']]=true;}
    }
    $ok(count($tipos)===6,'se consultan los seis tipos de evento reales CU15–CU18');
    $ok($eventos['eventos']['filas'][0]['inicio_anterior_local']!==$eventos['eventos']['filas'][0]['inicio_nuevo_local'],'horario anterior/nuevo solo procede de evidencia del historial');
    $momentos=array_column(array_merge($eventos['eventos']['filas'],$segunda['eventos']['filas']),'cambiado_en');$ordenMomentos=$momentos;sort($ordenMomentos);
    $ok($momentos===$ordenMomentos,'eventos en orden cronológico a través de páginas');
    $ok(isset($eventos['eventos']['filas'][0]['responsable']),'responsable autorizado para administrador');
    foreach(['recepcionista','medico'] as $rol){$ev=$prueba->servicio($rol)->eventos($p,$ids['atendida'],[]);$ok(!$ev['verResponsable']&&!array_key_exists('responsable',$ev['eventos']['filas'][0]),'responsable no seleccionado para '.$rol);}
    $ev=$s->eventos($p,$prueba->legado,[]);$ok($ev['cita']['especialidad']===null&&$ev['eventos']['total']===0,'legado sin especialidad/eventos no inventa datos');
    $ev=$s->eventos($p,$ids['cancelada'],[]);$ok(!array_key_exists('motivo_cancelacion',$ev['eventos']['filas'][0]),'motivo opcional NULL no se selecciona ni se inventa');
    $spy->consultas=[];$busqueda=$s->buscar(['q'=>'ficticio paciente']);$ok(count($spy->consultas)===2&&in_array($p,array_column($busqueda['resultado']['filas'],'id'),true),'búsqueda multitémino paginada reutiliza plegado CU05');
    $q=$pdo->prepare('SELECT documento_identidad FROM pacientes WHERE id=:id');$q->execute(['id'=>$p]);$ci=$q->fetchColumn();
    $ok($s->buscar(['q'=>$ci])['resultado']['total']===1,'administración busca por CI sin mostrarlo');
    $doctor=$prueba->servicio('medico');$ok($doctor->buscar(['q'=>$ci])['resultado']['total']===0,'médico no busca documentos privados');
    $ok($prueba->servicio('sin_vinculo')->buscar(['q'=>'paciente'])['resultado']['filas']===[],'sin vínculo tampoco enumera pacientes');
    $ok($s->buscar(['q'=>'%_!'])['resultado']['total']===0,'comodines se buscan literalmente');
    foreach([['q'=>[]],['q'=>str_repeat('x',101)],['q'=>"x\0"],['q'=>'paciente','pagina'=>0]] as $f){$rechazar(fn()=>$s->buscar($f),422,'búsqueda inválida controlada');}
    foreach($spy->consultas as $sql){$ok(!str_contains($sql,'hash_contrasena')&&!str_contains($sql,'p.telefono')&&!str_contains($sql,'p.direccion'),'proyección de búsqueda sin datos privados');}
    $ok($pdo->query('SHOW transaction_read_only')->fetchColumn()==='on','todas las consultas completas pasan en READ ONLY');$pdo->rollBack();
    $ok($antes===$b->huella(),'nueve tablas idénticas tras todas las lecturas');
    $borde=$b->insertar('2017-01-01T03:30:00+00:00','cancelada');$next=$b->insertar('2017-01-01T04:00:00+00:00','cancelada');
    $a=$prueba->consultar(['desde'=>'2016-12-31','hasta'=>'2016-12-31']);$ok(array_column($a['resultado']['filas'],'id')===[$borde],'hasta inclusive respeta medianoche local y excluye día siguiente');
    $a=$prueba->consultar(['desde'=>'2017-01-01','hasta'=>'2017-01-01']);$ok(array_column($a['resultado']['filas'],'id')===[$next],'inicio local inclusivo y conversión UTC correcta');
    $nueva=$b->t->servicio->crear($b->t->datos($b->medico,$b->paciente,$b->especialidad,'08:00'),$b->actores['administrador']['id'])['id'];
    $h=$prueba->consultar(['desde'=>$b->fecha]);$ok(in_array($nueva,array_column($h['resultado']['filas'],'id'),true),'reserva CU13 nueva aparece al consultar');
    $agenda=$b->consultar();$ok(in_array($nueva,array_column(AgendaPrueba::citas($agenda),'id'),true),'CU19 y CU20 coinciden en la reserva vigente');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. Historial real, READ ONLY, alcance/paginación/eventos/fechas; fixtures propios retirados.\n";
