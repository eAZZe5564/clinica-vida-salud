<?php
declare(strict_types=1);
const CU24_TABLAS = ['antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial'];

function cu24Catalogo(PDO $pdo): array
{
    return json_decode($pdo->query(file_get_contents(__DIR__.'/cu24/catalogo.sql'))->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
}

/** Valida el contrato declarado antes de confirmar la aplicación inicial. */
function cu24ValidarNuevo(PDO $pdo): void
{
    $m=json_decode(file_get_contents(__DIR__.'/cu24/propuesta.json'),true,512,JSON_THROW_ON_ERROR);
    $sql=dirname(__DIR__).'/database/migrations/010_antecedentes_laboratorio.sql';
    if(hash_file('sha256',$sql)!==$m['sha256_sql']){throw new RuntimeException('SQL 010 distinto del revisado.');}
    $c=cu24Catalogo($pdo);
    if(is_file(__DIR__.'/cu24/contrato.json')){
        $contrato=json_decode(file_get_contents(__DIR__.'/cu24/contrato.json'),true,512,JSON_THROW_ON_ERROR);
        if($c!=$contrato){throw new RuntimeException('Contrato 010 alterado.');}
    }
    if(count($c['tablas'])!==4||count($c['columnas'])!==60||count($c['restricciones'])!==52||count($c['indices'])!==8){
        throw new RuntimeException('Contrato 010 incompleto.');
    }
    foreach($m['tablas'] as $t=>$def){
        if(($c['tablas'][$t]??null)!=='r'){throw new RuntimeException('Tabla 010 incompatible.');}
        foreach($def['columnas'] as $col=>$esperado){
            $actual=$c['columnas'][$t.'.'.$col]??[];
            foreach($esperado as $k=>$v){if(($actual[$k]??null)!==$v){throw new RuntimeException('Columna 010 incompatible.');}}
        }
        foreach($def['fk'] as $col=>$destino){
            $r=$c['restricciones'][$t.'.'.$t.'_'.$col.'_fkey']??[];
            if(($r['tipo']??null)!=='f'||($r['definicion']??null)!=='FOREIGN KEY ('.$col.') REFERENCES '.$destino.'(id) ON DELETE RESTRICT'){
                throw new RuntimeException('Referencia 010 incompatible.');
            }
        }
        foreach($def['checks'] as $nombre){if(($c['restricciones'][$t.'.'.$nombre]['tipo']??null)!=='c'){throw new RuntimeException('CHECK 010 ausente.');}}
        if(($c['restricciones'][$t.'.'.$t.'_pkey']['definicion']??null)!=='PRIMARY KEY (id)'){throw new RuntimeException('PK 010 incompatible.');}
        foreach($def['unique'] as [$nombre,$cols]){
            if(($c['restricciones'][$t.'.'.$nombre]['definicion']??null)!=='UNIQUE ('.$cols.')'){throw new RuntimeException('Versión 010 sin unicidad.');}
        }
    }
    foreach($c['restricciones'] as $r){if(!$r['valida']||$r['diferible']){throw new RuntimeException('Restricción 010 no validada.');}}
    foreach($c['indices'] as $i){if(!$i['valido']||!$i['listo']){throw new RuntimeException('Índice 010 no disponible.');}}
    foreach(['antecedentes_clinicos_paciente_fecha_idx','resultados_laboratorio_paciente_fecha_idx'] as $nombre){
        if(!isset($c['indices'][$nombre])||$c['indices'][$nombre]['unico']){throw new RuntimeException('Índice de consulta 010 incompatible.');}
    }
}

/** Conservación de las doce tablas: huellas privadas en memoria, nunca filas en logs. */
function cu24HuellaAnterior(PDO $pdo): array
{
    $tablas=[...CU23_ORIGINALES,...CU23_TABLAS];sort($tablas);
    $r=cu23HuellaOriginal($pdo);$r['catalogo_cu23']=cu23Catalogo($pdo);
    foreach(CU23_TABLAS as $t){
        $r[$t]=$pdo->query('SELECT count(*) AS filas,md5(COALESCE(string_agg(md5(row_to_json(x)::text),\'\' ORDER BY md5(row_to_json(x)::text)),\'\')) AS huella FROM public.'.$t.' x')->fetch();
    }
    return $r;
}
