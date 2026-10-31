<?php
declare(strict_types=1);
require __DIR__.'/Cu15Local.php';
$pdo=cu15Conexion();$total=0;
$ok=static function(bool $value,string $label)use(&$total):void{
    if(!$value){throw new RuntimeException($label);}++$total;echo "OK: $label\n";
};
$sql=file_get_contents(dirname(__DIR__).'/scripts/render/verificar-instalacion.sql');
$pattern='~-- CU15_METADATA_INICIO:[^\r\n]*\R(.*?)-- CU15_METADATA_FIN~s';
if(preg_match($pattern,$sql,$match)!==1){throw new RuntimeException('Falta el bloque de lectura del verificador.');}
$lectura=preg_replace('/\bINTO esquema;\s*$/',';',$match[1]);
$pdo->beginTransaction();$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
try{
    $ok($pdo->query('SHOW transaction_read_only')->fetchColumn()==='on','todas las pruebas exigen transacción READ ONLY');
    $pdo->exec($sql);$ok(true,'verificador completo sobre esquema local real');
    $real=json_decode($pdo->query($lectura)->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
    $ok(count($real['tablas'])===9&&count($real['referencias'])===12&&$real['historial_tipo']==='r','base local real posterior a 005: nueve tablas y doce FK');
    // Catálogo simulado C1: quitar SOLO los metadatos de 005 de una copia en memoria.
    // No se ejecutan CREATE/ALTER/DROP ni se elimina una sola fila/tabla real.
    $c1=$real;$c1['tablas']=array_values(array_diff($c1['tablas'],['citas_historial']));
    $c1['historial_tipo']=null;$c1['indices_historial']=(object)[];
    foreach(array_keys($c1['columnas']) as $key){if(str_starts_with($key,'citas_historial.')){unset($c1['columnas'][$key]);}}
    foreach(array_keys($c1['restricciones']) as $key){if(str_starts_with($key,'citas_historial.')){unset($c1['restricciones'][$key]);}}
    $c1['referencias']=array_values(array_filter($c1['referencias'],static fn($f)=>!str_starts_with($f['origen'],'citas_historial.')));
    $verificar=static function(array $catalogo,bool $aceptar,string $label)use($pdo,$sql,$pattern,$ok):void{
        $asignacion='esquema := '.$pdo->quote(json_encode($catalogo,JSON_THROW_ON_ERROR)).'::jsonb;';
        $aislado=preg_replace_callback($pattern,static fn()=> $asignacion,$sql,-1,$reemplazos);
        if($reemplazos!==1){throw new RuntimeException('La prueba no aisló un único bloque de metadatos.');}
        $pdo->exec('SAVEPOINT esquema_fixture');$aceptado=false;$estado=null;
        try{$pdo->exec($aislado);$aceptado=true;}
        catch(PDOException $e){$estado=$e->getCode();}
        finally{$pdo->exec('ROLLBACK TO SAVEPOINT esquema_fixture');$pdo->exec('RELEASE SAVEPOINT esquema_fixture');}
        $ok($aceptar?$aceptado:(!$aceptado&&$estado==='P0001'),$label);
    };
    $verificar($c1,true,'Ciclo 1 aislado: acepta ocho tablas/diez FK sin historial');
    $verificar($real,true,'Ciclo 2 aislado: acepta nueve tablas/doce FK con historial completo');
    $casos=[];
    $x=$c1;$x['referencias']=$real['referencias'];$casos['rechaza ocho tablas/doce FK']=$x;
    $x=$real;$x['referencias']=$c1['referencias'];$casos['rechaza nueve tablas/diez FK']=$x;
    $x=$real;$x['tablas'][]='tabla_no_prevista';$casos['rechaza tabla adicional']=$x;
    $x=$real;$x['tablas']=array_values(array_diff($x['tablas'],['usuarios']));$casos['rechaza tabla original ausente']=$x;
    $x=$real;$x['tablas'][array_search('pacientes',$x['tablas'],true)]='otra_tabla';$casos['rechaza sustitución de tabla aunque coincida cantidad']=$x;
    $x=$real;unset($x['columnas']['citas.especialidad_id']);$casos['rechaza columna de 004 ausente']=$x;
    $x=$real;$x['columnas']['usuarios.id']['tipo']='integer';$casos['rechaza tipo de columna original alterado']=$x;
    $x=$real;$x['columnas']['citas.extra']=['tipo'=>'text'];$casos['rechaza columna no prevista']=$x;
    $x=$real;$x['columnas']['usuarios.hash_contrasena']['obligatoria']=false;$casos['rechaza nulabilidad debilitada de tabla original']=$x;
    $x=$real;$x['historial_tipo']='v';$casos['rechaza vista en lugar de historial']=$x;
    $x=$real;$x['historial_tipo']=null;$casos['rechaza tabla de historial incoherente con versión']=$x;
    $x=$real;unset($x['columnas']['citas_historial.inicio_nuevo']);$casos['rechaza historial parcial']=$x;
    $x=$real;$x['columnas']['citas_historial.inicio_nuevo']['tipo']='timestamp without time zone';$casos['rechaza inicio sin zona horaria']=$x;
    $x=$real;$x['columnas']['citas_historial.usuario_id']['obligatoria']=false;$casos['rechaza actor nullable']=$x;
    $x=$real;$x['columnas']['citas_historial.id']['identidad']='d';$casos['rechaza identidad distinta de GENERATED ALWAYS']=$x;
    $x=$real;$x['columnas']['citas_historial.cambiado_en']['defecto']=null;$casos['rechaza fecha de auditoría sin valor predeterminado']=$x;
    $fk=array_search('citas_historial.usuario_id',array_column($real['referencias'],'origen'),true);
    foreach(['destino'=>'public.pacientes.id','borrado'=>'c','actualizacion'=>'c','valida'=>false,'diferible'=>true] as $key=>$value){
        $x=$real;$x['referencias'][$fk][$key]=$value;$casos['rechaza FK de historial inválida: '.$key]=$x;
    }
    $x=$real;$x['referencias'][$fk]['destino']='otro.usuarios.id';$casos['rechaza referencia a otro esquema']=$x;
    $x=$real;$x['referencias'][$fk]=$x['referencias'][0];$casos['rechaza FK duplicada y referencia faltante con conteo correcto']=$x;
    $x=$real;unset($x['indices_historial']['citas_historial_cita_fecha_idx']);$casos['rechaza índice de consulta ausente']=$x;
    foreach(['valido'=>false,'listo'=>false,'metodo'=>'hash','unico'=>true,'predicado'=>'cita_id > 0',
        'columnas'=>['cambiado_en','cita_id','id'],'atributos'=>4,'claves'=>2,
        'definicion'=>'CREATE INDEX citas_historial_cita_fecha_idx ON public.citas_historial USING btree (cita_id, cambiado_en DESC, id)'] as $key=>$value){
        $x=$real;$x['indices_historial']['citas_historial_cita_fecha_idx'][$key]=$value;$casos['rechaza índice alterado: '.$key]=$x;
    }
    $x=$real;$x['restricciones']['citas_historial.citas_historial_pkey']['definicion']='PRIMARY KEY (cita_id)';$casos['rechaza PK incorrecta']=$x;
    $x=$real;$x['restricciones']['citas_historial.citas_historial_tipo_valido']['valida']=false;$casos['rechaza CHECK no validado']=$x;
    $x=$real;$x['restricciones']['citas_historial.citas_historial_reprogramacion_valida']['definicion']='CHECK (true)';$casos['rechaza CHECK de evento debilitado']=$x;
    $x=$real;$x['restricciones']['citas.citas_medico_sin_superposicion']['definicion']='EXCLUDE USING gist (medico_id WITH =)';$casos['rechaza GiST con definición distinta']=$x;
    $x=$real;$x['btree_gist']=false;$casos['rechaza extensión ausente']=$x;
    $x=$real;$x['trigger_citas']=false;$casos['rechaza trigger desactivado']=$x;
    $x=$real;$x['roles']=3;$casos['rechaza catálogo de roles incompleto']=$x;
    $x=$real;$x['especialidades']=2;$casos['rechaza catálogo de especialidades incompleto']=$x;
    $x=$c1;$x['indices_historial']=$real['indices_historial'];$casos['rechaza restos incoherentes de instalación parcial']=$x;
    $x=$c1;$x['columnas']['medicos.correo_contacto']['tipo']='text';$casos['Ciclo 1: rechaza tipo de migración 003 alterado']=$x;
    $x=$c1;$x['referencias'][0]['destino']='public.pacientes.id';$casos['Ciclo 1: rechaza referencia incorrecta con diez FK']=$x;
    $x=$c1;$x['tablas'][0]='tabla_sustituta';$casos['Ciclo 1: rechaza sustitución manteniendo ocho tablas']=$x;
    $x=$c1;$x['trigger_citas']=false;$casos['Ciclo 1: rechaza trigger desactivado']=$x;
    foreach($casos as $label=>$catalogo){$verificar($catalogo,false,$label);}
    $pdo->exec($sql);$ok(true,'esquema real sigue íntegro después de fixtures aislados');
    $ok($pdo->query('SHOW transaction_read_only')->fetchColumn()==='on','no se rebajó el modo READ ONLY durante las pruebas');
}finally{if($pdo->inTransaction()){$pdo->rollBack();}}
echo "Aprobadas: $total. SQL real en READ ONLY; Ciclo 1 simulado en memoria; sin DDL ni escrituras de datos.\n";
