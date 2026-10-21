<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/Config/bootstrap.php';require __DIR__ . '/ConsultaCitasPrueba.php';
use App\Services\CatalogoValidacionException;
$pdo=App\Config\Database::connection();$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}$total++;echo "OK: $n\n";};
$pdo->beginTransaction();
try{
    $f=new ConsultaCitasPrueba($pdo);$admin=$f->consulta('administrador');$doctor=$f->consulta('medico');$base=['medico_id'=>$f->medico];
    $r=$admin->listar($base);$ok($r['total']===24&&count($r['filas'])===20&&$r['paginas']===2,'listado JOIN paginado de citas existentes');
    $ok($r['filas'][0]['id']===$f->legado,'orden cronológico estable');
    $r=$admin->listar($base+['pagina'=>2]);$ok(count($r['filas'])===4&&$r['pagina']===2,'segunda página sin pérdida');
    $ok($admin->listar($base+['pagina'=>PHP_INT_MAX])['pagina']===2,'página desbordada se limita antes del offset');
    $ok($admin->listar(['medico_id'=>PHP_INT_MAX])['total']===0,'resultado vacío');
    $r=$admin->listar($base+['fecha'=>'2010-01-01']);$ok($r['total']===1&&$r['filas'][0]['id']===$f->canceladas[0],'fecha exacta local al cruzar UTC');
    $ok($r['filas'][0]['hora_inicio']==='23:30'&&$r['filas'][0]['hora_fin']==='00:00'&&$r['filas'][0]['fecha']==='01/01/2010','horas locales sin doble conversión');
    $ok($admin->listar($base+['desde'=>'2010-01-01','hasta'=>'2010-01-03'])['total']===3,'rango con ambos días incluidos');
    $ok($admin->listar($base+['hasta'=>'2010-01-01'])['total']===2,'rango abierto hasta el día inclusive');
    $ok($admin->listar($base+['desde'=>'2011-01-01'])['total']===2,'rango abierto desde');
    $ok($admin->listar($base+['fecha'=>'2010-01-02','desde'=>'2010-01-03'])['total']===0,'fecha exacta y rango se intersectan');
    $ok($admin->listar(['especialidad_id'=>$f->especialidad])['total']===23,'especialidad guardada sin inferir legado');
    foreach(['programada'=>1,'cancelada'=>21,'atendida'=>2] as $estado=>$cantidad){$ok($admin->listar($base+['estado'=>$estado])['total']===$cantidad,'estado del esquema '.$estado);}
    $ok($admin->listar($base+['especialidad_id'=>$f->especialidad,'estado'=>'cancelada','desde'=>'2010-01-01','hasta'=>'2010-01-02'])['total']===2,'combinación de filtros');
    $codigo=App\Models\Cita::codigo($f->reserva);$r=$admin->listar(['codigo'=>strtolower($codigo)]);$ok($r['total']===1&&$r['filas'][0]['codigo']===$codigo,'código completo derivado de identidad');
    $r=$admin->detalle($f->reserva);$ok($r['especialidad']!==null&&$r['hora_inicio']==='08:00'&&$r['hora_fin']==='08:30'&&$r['estado']==='programada','detalle integra datos de CU13');
    $ok($r['motivo']==='MOTIVO_PRIVADO_CU14 <script>alert(1)</script>'&&$r['registrada_por']===$f->actores['administrador']['correo'],'campos administrativos solo autorizados');
    $ok($r['registro_local']===(new DateTimeImmutable($r['creada_en']))->setTimezone(App\Models\HorarioAtencion::zona())->format('d/m/Y H:i'),'registro en zona clínica');
    $ok($admin->detalle(PHP_INT_MAX)===null,'ID inexistente');
    $r=$f->consulta('recepcionista')->detalle($f->reserva);$ok(isset($r['motivo'])&&!array_key_exists('registrada_por',$r),'recepción ve motivo administrativo sin auditoría de cuentas');
    $r=$doctor->listar([]);$ok($r['total']===24,'médico restringido en listado sin filtro');
    $ok($doctor->listar(['codigo'=>App\Models\Cita::codigo($f->ajena)])['total']===0,'código ajeno no amplía alcance SQL');
    $ok($doctor->detalle($f->ajena)===null,'IDOR ajeno bloqueado en consulta individual');
    $r=$doctor->detalle($f->reserva);$ok($r!==null&&!array_key_exists('motivo',$r)&&!array_key_exists('registrada_por',$r),'proyección médica excluye motivo y creador');
    $ok(!str_contains(json_encode($r),'documento_identidad')&&!str_contains(json_encode($r),'telefono')&&!str_contains(json_encode($r),'direccion')&&!str_contains(json_encode($r),'hash_contrasena'),'detalle sin información sensible innecesaria');
    $ok($f->consulta('sin_vinculo')->listar([])['total']===0&&$f->consulta('sin_vinculo')->detalle($f->reserva)===null,'cuenta sin vínculo nunca tiene alcance general');
    try{$f->consulta('paciente');$ok(false,'paciente denegado');}catch(App\Services\CitaException $e){$ok($e->estadoHttp===403,'servicio deniega paciente');}
    foreach([
        ['fecha'=>'2026-02-30'],['fecha'=>'2026-2-01'],['fecha'=>"2026-01-01\0"],['fecha'=>'0000-01-01'],['fecha'=>[]],
        ['desde'=>'invalida'],['hasta'=>'2026-13-01'],['desde'=>'2026-02-02','hasta'=>'2026-02-01'],['medico_id'=>'1 OR 1=1'],
        ['especialidad_id'=>-1],['estado'=>'pendiente'],['estado'=>[]],['codigo'=>"CM-1' OR 1=1"],['codigo'=>'CM-000000'],
        ['codigo'=>'CM-9999999999999999999'],['pagina'=>0],['pagina'=>[]],['periodo'=>'todas; DROP TABLE citas'],
    ] as $i=>$entrada){try{$admin->listar($entrada);$ok(false,'filtro inválido');}catch(CatalogoValidacionException){$ok(true,'filtro inválido '.$i);}}
    try{$doctor->listar(['medico_id'=>$f->otro]);$ok(false,'agenda manipulada');}catch(CatalogoValidacionException $e){$ok(isset($e->errores['medico_id']),'médico no cambia agenda por parámetro');}
    $ok($doctor->listar(['periodo'=>'proximas'])['total']===1&&$doctor->listar(['periodo'=>'historicas'])['total']===23,'accesos claros a próximas e históricas');
    $r=$doctor->listar(['periodo'=>'historicas']);$ok($r['filas'][0]['id']===$f->historica,'históricas ordenadas más recientes primero');
    $ok($admin->detalle($f->legado)['especialidad']===null,'especialidad histórica nula conservada');
    $libres=$f->t->disponibilidad->consultar($f->medico,$f->t->lunes->format('Y-m-d'),$f->especialidad);
    $ok(count($libres['turnos'])===7&&!in_array('08:00',array_map(static fn($t)=>(new DateTimeImmutable($t['inicio']))->format('H:i'),$libres['turnos']),true),'CU12 conserva turno ocupado por reserva CU13');
    $q=$pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id');$q->execute(['id'=>$f->medico]);
    $q=$pdo->prepare('UPDATE especialidades SET activo=false WHERE id=:id');$q->execute(['id'=>$f->especialidad]);
    $q=$pdo->prepare('DELETE FROM medicos_especialidades WHERE medico_id=:id');$q->execute(['id'=>$f->medico]);
    $ok($doctor->listar([])['total']===24&&$doctor->detalle($f->reserva)!==null,'historia consultable con catálogos inactivos');
    $op=$doctor->opciones();$ok(count($op['medicos'])===1&&count($op['especialidades'])===1&&!$op['especialidades'][0]['activo'],'opciones propias incluyen asociación histórica retirada');
    $ok($pdo->inTransaction(),'consultas no confirman la transacción externa');
}finally{$pdo->rollBack();}
echo "Aprobadas: $total. Transacción ficticia revertida.\n";
