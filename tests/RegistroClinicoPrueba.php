<?php
declare(strict_types=1);
require_once __DIR__.'/PerfilClinicoPrueba.php';

function cu24Servicio(PerfilClinicoPrueba $f,string $rol='medico',?PDO $pdo=null,?Closure $reloj=null): App\Services\RegistroClinicoService
{
    $p=$pdo??$f->pdo;
    return new App\Services\RegistroClinicoService($p,new App\Repositories\RegistroClinicoRepository($p),$f->actores[$rol]['id'],$reloj);
}
function cu24Entrada(string $tipo,array $c=[]): array
{
    $base=$tipo==='antecedentes'
        ?['categoria'=>'personal','descripcion'=>'CU24_ANTECEDENTE_FICTICIO','fecha_evento'=>'','fecha_aproximada'=>'','observaciones'=>'']
        :['estudio'=>'CU24_ESTUDIO_FICTICIO','fecha_muestra'=>'','fecha_resultado'=>'','resultado_texto'=>'CU24_CUALITATIVO_FICTICIO','valor'=>'','unidad'=>'','rango_referencia'=>'','laboratorio'=>'','observaciones'=>''];
    return array_replace($base+['confirmar'=>'1'],$c);
}
function cu24Cambio(PerfilClinicoPrueba $f,string $tipo,int $id,array $c=[]): array
{
    $r=(new App\Repositories\RegistroClinicoRepository($f->pdo))->registro($f->paciente,$tipo,$id);
    $e=[];foreach(App\Models\RegistroClinico::tipo($tipo)['campos'] as $campo){$v=$r[$campo];$e[$campo]=$v===null?'':(is_bool($v)?($v?'1':'0'):(string)$v);}
    return array_replace($e+['version'=>(string)$r['version'],'motivo_cambio'=>'CU24_MOTIVO_FICTICIO','confirmar'=>'1'],$c);
}
function cu24Huella(PDO $pdo): array
{
    $r=[];
    foreach(['roles','usuarios','pacientes','medicos','especialidades','medicos_especialidades','horarios_atencion','citas','citas_historial',
        'perfiles_clinicos','alergias_paciente','alergias_historial','antecedentes_clinicos','antecedentes_historial','resultados_laboratorio','laboratorios_historial'] as $t){
        $r[$t]=$pdo->query('SELECT count(*) AS filas,md5(COALESCE(string_agg(md5(row_to_json(x)::text),\'\' ORDER BY md5(row_to_json(x)::text)),\'\')) AS huella FROM '.$t.' x')->fetch();
    }return $r;
}
function cu24Limpiar(?PerfilClinicoPrueba $f): void
{
    if(!$f){return;}if($f->pdo->inTransaction()){$f->pdo->rollBack();}
    foreach($f->fixture->pacientes as $p){
        foreach(App\Models\RegistroClinico::TIPOS as $d){
            $q=$f->pdo->prepare('DELETE FROM '.$d['historial'].' WHERE '.$d['fk'].' IN (SELECT id FROM '.$d['tabla'].' WHERE paciente_id=:p)');$q->execute(['p'=>$p]);
            $q=$f->pdo->prepare('DELETE FROM '.$d['tabla'].' WHERE paciente_id=:p');$q->execute(['p'=>$p]);
        }
    }$f->limpiar();
}

/** Fallo de auditoría controlado para verificar rollback sin alterar tablas/triggers. */
final class RegistroClinicoFalloPDO extends PDO
{
    public function __construct(private readonly PDO $real){}
    public function beginTransaction(): bool{return $this->real->beginTransaction();}
    public function inTransaction(): bool{return $this->real->inTransaction();}
    public function commit(): bool{return $this->real->commit();}
    public function rollBack(): bool{return $this->real->rollBack();}
    public function exec(string $s): int|false{return $this->real->exec($s);}
    public function query(string $q,?int $mode=null,mixed ...$args): PDOStatement|false{return $mode===null?$this->real->query($q):$this->real->query($q,$mode,...$args);}
    public function prepare(string $q,array $options=[]): PDOStatement|false
    {
        if(preg_match('/^INSERT INTO (antecedentes_historial|laboratorios_historial)\b/',$q)){throw new RuntimeException('Fallo de auditoría ficticio.');}
        return $this->real->prepare($q,$options);
    }
}
