<?php
declare(strict_types=1);
require __DIR__.'/ReportesPrueba.php';
use App\Services\CitaException;
use App\Services\CatalogoValidacionException;
$pdo=cu15Conexion();$prueba=null;$total=0;
$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$rechazar=static function(callable $a,int $estado)use($ok):void{try{$a();$ok(false,'debía rechazar');}catch(CitaException $e){$ok($e->estadoHttp===$estado,'rol rechazado');}catch(CatalogoValidacionException){$ok($estado===422,'filtro inválido rechazado');}};
$consistente=static function(array $r)use($ok):void{
    $g=$r['general'];$ok(array_sum($r['estados'])===$g['total'],'estados excluyentes suman total');
    foreach(['medicos','especialidades','dias'] as $grupo){
        foreach(['total',...array_keys(App\Models\Cita::ESTADOS)] as $c){$ok(array_sum(array_column($r[$grupo],$c))===$g[$c],'subtotal '.$grupo.' / '.$c);}
    }
};
try{
    $prueba=new ReportesPrueba($pdo);$b=$prueba->base;$antes=$b->huella();
    $pdo->beginTransaction();$pdo->exec('SET TRANSACTION READ ONLY');$spy=new AgendaLecturaPDO($pdo);$s=$prueba->servicio('administrador',$spy);
    $r=$s->consultar($prueba->filtros());$g=$r['reporte']['general'];
    $ok(count($spy->consultas)===3,'dos catálogos y una agregación SQL, sin N+1');
    $sql=$spy->consultas[2];$ok(str_contains($sql,'GROUPING SETS')&&str_contains($sql,'FILTER (WHERE')&&str_contains($sql,':desde')&&str_contains($sql,':hasta'),'agregaciones parametrizadas con límites');
    foreach(['citas_historial','medicos_especialidades','pacientes','motivo','hash_contrasena'] as $privado){$ok(!str_contains($sql,$privado),'sin join o datos innecesarios '.$privado);}
    $esperado=['total'=>8,'programada'=>3,'confirmada'=>1,'presente'=>1,'atendida'=>1,'inasistencia'=>1,'cancelada'=>1];
    $ok($g==$esperado,'conteo exacto de cada estado actual, incluida cancelada y reemplazo');$consistente($r['reporte']);
    $ok(count($r['reporte']['medicos'])===1&&$r['reporte']['medicos'][0]['total']===8,'médico multiespecialidad no duplica citas');
    $ok($r['reporte']['dias'][0]['dia']===$b->fecha,'agrupación por día local');
    $ok($r['f']['limite_desde']===$b->fecha.' 04:00:00+00:00','límite UTC inclusivo de La Paz');
    foreach(array_keys(App\Models\Cita::ESTADOS) as $estado){$g=$s->consultar($prueba->filtros(['estado'=>$estado]))['reporte']['general'];$ok($g['total']===$esperado[$estado]&&$g[$estado]===$g['total'],'filtro estado '.$estado);}
    $recepcion=$prueba->servicio('recepcionista')->consultar($prueba->filtros());$ok($recepcion['reporte']===$r['reporte'],'recepción y administración mismos agregados');
    foreach(['medico','paciente','sin_vinculo'] as $rol){$rechazar(fn()=>$prueba->servicio($rol),403);}
    $especialidad=$s->consultar($prueba->filtros(['especialidad_id'=>$b->especialidad,'estado'=>'atendida']))['reporte'];
    $ok($especialidad['general']['total']===1&&count($especialidad['especialidades'])===1,'filtros combinados médico especialidad estado');
    $todo=$s->consultar($prueba->filtros(['desde'=>'2000-01-01','hasta'=>'2099-12-31']))['reporte'];
    $ok($todo['general']['total']===43,'historial de citas sin multiplicar veintitrés reprogramaciones');
    $sinEspecialidad=array_values(array_filter($todo['especialidades'],fn($e)=>$e['especialidad_id']===null));
    $ok(count($sinEspecialidad)===1&&$sinEspecialidad[0]['total']===1&&$sinEspecialidad[0]['etiqueta']==='Sin registrar','legado NULL contabilizado sin inferir especialidad');$consistente($todo);
    $fin=$s->consultar($prueba->filtros(['desde'=>'2020-12-31','hasta'=>'2020-12-31']))['reporte'];
    $inicio=$s->consultar($prueba->filtros(['desde'=>'2021-01-01','hasta'=>'2021-01-01']))['reporte'];
    $cruce=$s->consultar($prueba->filtros(['desde'=>'2020-12-31','hasta'=>'2021-01-01']))['reporte'];
    $ok($fin['general']['total']===1&&$inicio['general']['total']===1&&$cruce['general']['total']===2,'límites exactos y cruce mes/año');
    $ok(array_column($cruce['dias'],'dia')===['2020-12-31','2021-01-01'],'distribución diaria respeta La Paz, no fecha UTC');
    $vacio=$s->consultar($prueba->filtros(['medico_id'=>$prueba->sinCitas]))['reporte'];$ok($vacio['general']['total']===0&&$vacio['dias']===[],'médico sin citas produce ceros, no inventa filas');$consistente($vacio);
    $mes=$s->consultar([])['f'];$hoy=new DateTimeImmutable('now',App\Models\HorarioAtencion::zona());
    $ok($mes['desde']===$hoy->format('Y-m-01')&&$mes['hasta']===$hoy->format('Y-m-t'),'mes predeterminado local');
    foreach([['desde'=>'2026-02-30'],['hasta'=>'0000-01-01'],['desde'=>'2027-02-01','hasta'=>'2027-01-31'],['desde'=>[]],['desde'=>"2026-11-01\0"],['estado'=>'todas'],['estado'=>[]],['medico_id'=>'1 OR 1=1'],['medico_id'=>0],['especialidad_id'=>[]],['medico_id'=>PHP_INT_MAX],['especialidad_id'=>PHP_INT_MAX]] as $f){$rechazar(fn()=>$s->consultar($prueba->filtros($f)),422);}
    foreach(['2028-02-29','0001-01-01','9999-12-31'] as $fecha){$ok($s->consultar($prueba->filtros(['desde'=>$fecha,'hasta'=>$fecha]))['reporte']['general']['total']===0,'fecha válida extrema/bisiesta '.$fecha);}
    $global=$s->consultar($prueba->filtros(['medico_id'=>'']))['reporte'];
    $q=$pdo->prepare('SELECT count(*) FROM citas WHERE fecha_hora_inicio>=CAST(:d AS timestamptz) AND fecha_hora_inicio<CAST(:h AS timestamptz)');$q->execute(['d'=>$r['f']['limite_desde'],'h'=>$r['f']['limite_hasta']]);
    $ok($global['general']['total']===(int)$q->fetchColumn(),'global coincide con COUNT directo, sin contar historial');$consistente($global);
    $consulta=new App\Services\ConsultaCitaService(new App\Repositories\CitaRepository($pdo),new App\Repositories\HorarioRepository($pdo),$b->actores['administrador']);
    $cu14=$consulta->listar($prueba->filtros());$ok($cu14['total']===8,'coherencia CU14 con mismo rango y médico');
    $agenda=$b->consultar('diaria',['estado'=>'todas']);$ok(count(AgendaPrueba::citas($agenda))===8,'coherencia CU19 usando todos los estados');
    $cu20=$prueba->historial->consultar($prueba->filtros());$otroPaciente=$prueba->historial->consultar($prueba->filtros(),'administrador',$prueba->historial->unico);
    $ok($cu20['resultado']['total']+$otroPaciente['resultado']['total']===8,'coherencia CU20 sumando los dos pacientes del médico fixture');
    $ok($pdo->query('SHOW transaction_read_only')->fetchColumn()==='on','todas las lecturas CU21 en READ ONLY');
    $pdo->rollBack();$ok($antes===$b->huella(),'las nueve tablas intactas tras reportes');
    $pdo->prepare('UPDATE medicos SET activo=false WHERE id=:id')->execute(['id'=>$b->medico]);
    $ok($prueba->servicio()->consultar($prueba->filtros())['reporte']['general']['total']===8,'médico inactivo conserva reporte histórico');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}if($prueba){$prueba->limpiar();}}
echo "Aprobadas: $total. Agregados reales, READ ONLY, filtros, invariantes y coherencia CU14/CU19/CU20.\n";
