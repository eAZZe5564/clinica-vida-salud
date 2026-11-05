<?php
declare(strict_types=1);

// Auxiliares CLI de migración/inspección; no forman parte de las rutas HTTP.
const CU23_TABLAS = ['perfiles_clinicos', 'alergias_paciente', 'alergias_historial'];
const CU23_ORIGINALES = ['citas', 'citas_historial', 'especialidades', 'horarios_atencion', 'medicos',
    'medicos_especialidades', 'pacientes', 'roles', 'usuarios'];

function cu23Catalogo(PDO $pdo): array
{
    return json_decode($pdo->query(file_get_contents(__DIR__.'/cu23/catalogo.sql'))->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
}

/** Validación previa al COMMIT inicial; no modifica nada ni acepta conteos arbitrarios. */
function cu23ValidarInstalacion(PDO $pdo): void
{
    $tablas = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
    $esperadas = [...CU23_ORIGINALES, ...CU23_TABLAS]; sort($esperadas);
    if ($tablas !== $esperadas || (int)$pdo->query("SELECT count(*) FROM pg_constraint WHERE connamespace='public'::regnamespace AND contype='f'")->fetchColumn() !== 20) {
        throw new RuntimeException('Contrato de tablas o referencias incompatible.');
    }
    $catalogo = cu23Catalogo($pdo);
    $tipos = [
        'perfiles_clinicos' => ['paciente_id'=>'bigint','grupo_sanguineo'=>'character varying(3)',
            'fuente_grupo_sanguineo'=>'character varying(120)','observaciones'=>'character varying(500)',
            'estado_alergias'=>'character varying(20)','version'=>'integer','creado_por_usuario_id'=>'bigint',
            'actualizado_por_usuario_id'=>'bigint','creado_en'=>'timestamp with time zone','actualizado_en'=>'timestamp with time zone'],
        'alergias_paciente' => ['id'=>'bigint','paciente_id'=>'bigint','sustancia'=>'character varying(120)',
            'reaccion'=>'character varying(250)','observaciones'=>'character varying(500)','estado'=>'character varying(10)',
            'version'=>'integer','creado_por_usuario_id'=>'bigint','actualizado_por_usuario_id'=>'bigint',
            'creado_en'=>'timestamp with time zone','actualizado_en'=>'timestamp with time zone'],
        'alergias_historial' => ['id'=>'bigint','alergia_id'=>'bigint','version'=>'integer','evento'=>'character varying(20)',
            'sustancia'=>'character varying(120)','reaccion'=>'character varying(250)','observaciones'=>'character varying(500)',
            'estado'=>'character varying(10)','motivo_cambio'=>'character varying(250)','usuario_id'=>'bigint','cambiado_en'=>'timestamp with time zone'],
    ];
    $opcionales = ['perfiles_clinicos.grupo_sanguineo','perfiles_clinicos.fuente_grupo_sanguineo','perfiles_clinicos.observaciones',
        'alergias_paciente.reaccion','alergias_paciente.observaciones','alergias_historial.reaccion',
        'alergias_historial.observaciones','alergias_historial.motivo_cambio'];
    if (count($catalogo['columnas']) !== 32 || count($catalogo['restricciones']) !== 30 || count($catalogo['indices']) !== 6) {
        throw new RuntimeException('Estructura clínica incompleta.');
    }
    foreach ($tipos as $tabla=>$campos) {
        if (($catalogo['tablas'][$tabla]??null) !== 'r') { throw new RuntimeException('Objeto clínico no es tabla ordinaria.'); }
        foreach ($campos as $campo=>$tipo) {
            $key=$tabla.'.'.$campo; $actual=$catalogo['columnas'][$key]??[];
            if (($actual['tipo']??null)!==$tipo || ($actual['obligatoria']??null)!==!in_array($key,$opcionales,true)
                || ($actual['identidad']??null)!==($campo==='id'?'a':'')) {
                throw new RuntimeException('Columna clínica incompatible.');
            }
        }
    }
    foreach (['grupo_sanguineo','fuente_grupo_sanguineo'] as $campo) {
        if ($catalogo['columnas']['perfiles_clinicos.'.$campo]['defecto']!==null) { throw new RuntimeException('No se admiten valores clínicos inferidos.'); }
    }
    $fk = [
        'perfiles_clinicos.paciente_id'=>['pacientes','id'],
        'perfiles_clinicos.creado_por_usuario_id'=>['usuarios','id'],
        'perfiles_clinicos.actualizado_por_usuario_id'=>['usuarios','id'],
        'alergias_paciente.paciente_id'=>['pacientes','id'],
        'alergias_paciente.creado_por_usuario_id'=>['usuarios','id'],
        'alergias_paciente.actualizado_por_usuario_id'=>['usuarios','id'],
        'alergias_historial.alergia_id'=>['alergias_paciente','id'],
        'alergias_historial.usuario_id'=>['usuarios','id'],
    ];
    foreach ($catalogo['restricciones'] as $c) {
        if (!$c['valida'] || $c['diferible']) { throw new RuntimeException('Restricción clínica no validada.'); }
    }
    foreach ($fk as $origen=>[$tabla,$campo]) {
        [$t,$c]=explode('.',$origen); $def='FOREIGN KEY ('.$c.') REFERENCES '.$tabla.'('.$campo.') ON DELETE RESTRICT';
        $matches=array_filter($catalogo['restricciones'],static fn($v,$k)=>str_starts_with($k,$t.'.')&&$v['tipo']==='f'&&$v['definicion']===$def,ARRAY_FILTER_USE_BOTH);
        if(count($matches)!==1){throw new RuntimeException('Referencia clínica incorrecta.');}
    }
    foreach ($catalogo['indices'] as $indice) {
        if (!$indice['valido'] || !$indice['listo']) { throw new RuntimeException('Índice clínico no disponible.'); }
    }
    $unico=$catalogo['indices']['alergias_paciente_sustancia_activa_uidx']??[];
    if (($unico['unico']??null)!==true || !str_contains($unico['definicion']??'',"WHERE ((estado)::text = 'activa'::text)")) {
        throw new RuntimeException('Unicidad activa incompatible.');
    }
}

/** Huellas privadas en memoria: nunca imprime filas ni información de pacientes. */
function cu23HuellaOriginal(PDO $pdo): array
{
    $datos=[];
    foreach(CU23_ORIGINALES as $tabla){
        $datos[$tabla]=$pdo->query('SELECT count(*) AS filas,md5(COALESCE(string_agg(md5(row_to_json(t)::text),\'\' ORDER BY md5(row_to_json(t)::text)),\'\')) AS huella FROM public.'.$tabla.' t')->fetch();
    }
    $lista="'".implode("','",CU23_ORIGINALES)."'";
    foreach([
        "SELECT c.relname,a.attname,format_type(a.atttypid,a.atttypmod),a.attnotnull,a.attidentity,pg_get_expr(d.adbin,d.adrelid) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_attribute a ON a.attrelid=c.oid LEFT JOIN pg_attrdef d ON d.adrelid=c.oid AND d.adnum=a.attnum WHERE n.nspname='public' AND c.relname IN ($lista) AND a.attnum>0 AND NOT a.attisdropped ORDER BY c.relname,a.attnum",
        "SELECT c.relname,f.conname,pg_get_constraintdef(f.oid),f.convalidated,f.condeferrable FROM pg_constraint f JOIN pg_class c ON c.oid=f.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname IN ($lista) ORDER BY c.relname,f.conname",
        "SELECT c.relname,pg_get_indexdef(i.indexrelid),i.indisvalid,i.indisready FROM pg_index i JOIN pg_class c ON c.oid=i.indrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname IN ($lista) ORDER BY c.relname,pg_get_indexdef(i.indexrelid)",
        "SELECT pg_get_triggerdef(t.oid),t.tgenabled,pg_get_functiondef(t.tgfoid) FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname IN ($lista) AND NOT t.tgisinternal ORDER BY c.relname,t.tgname",
    ] as $i=>$sql){$datos['estructura_'.$i]=$pdo->query($sql)->fetchAll();}
    return $datos;
}
