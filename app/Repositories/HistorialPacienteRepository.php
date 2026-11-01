<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Models\BusquedaPaciente;
use PDO;

/** CU20: proyección administrativa, siempre acotada al paciente y al alcance del médico. */
final class HistorialPacienteRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function paciente(int $id,?int $propio): ?array
    {
        $sql='SELECT p.id,p.nombres,p.apellidos FROM pacientes p WHERE p.id=:paciente';$params=['paciente'=>$id];
        if($propio!==null){$sql.=' AND EXISTS (SELECT 1 FROM citas c WHERE c.paciente_id=p.id AND c.medico_id=:propio)';$params['propio']=$propio;}
        $q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetch()?:null;
    }

    public function buscar(string $busqueda,int $pagina,?int $propio): array
    {
        if($busqueda===''||$propio===0){return ['filas'=>[],'total'=>0,'pagina'=>1,'paginas'=>1];}
        $where=[];$params=[];
        if($propio!==null){$where[]='EXISTS (SELECT 1 FROM citas c WHERE c.paciente_id=p.id AND c.medico_id=:propio)';$params['propio']=$propio;}
        foreach(BusquedaPaciente::terminos($busqueda) as $i=>$termino){
            $campos=[];
            // El CI solo se utiliza para buscar por los roles que ya lo consultan en CU05.
            foreach($propio===null?['nombres','apellidos','documento_identidad']:['nombres','apellidos'] as $campo){
                $columna=$campo==='documento_identidad'?"regexp_replace(p.documento_identidad, '[[:space:]]+', '', 'g')":'p.'.$campo;
                $key=$campo.'_'.$i;$campos[]="translate(lower($columna), 'áéíóúü' || chr(769) || chr(776), 'aeiouu') LIKE :$key ESCAPE '!'";
                $params[$key]='%'.strtr($termino,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            }
            $where[]='('.implode(' OR ',$campos).')';
        }
        $condicion=' WHERE '.implode(' AND ',$where);
        $q=$this->pdo->prepare('SELECT count(*) FROM pacientes p'.$condicion);$q->execute($params);$total=(int)$q->fetchColumn();
        $paginas=max(1,(int)ceil($total/20));$pagina=min(max(1,$pagina),$paginas);
        $q=$this->pdo->prepare('SELECT p.id,p.nombres,p.apellidos FROM pacientes p'.$condicion.' ORDER BY p.apellidos,p.nombres,p.id LIMIT 20 OFFSET :offset');
        $params['offset']=($pagina-1)*20;$this->ejecutar($q,$params);
        return ['filas'=>$q->fetchAll(),'total'=>$total,'pagina'=>$pagina,'paginas'=>$paginas];
    }

    private function alcance(int $paciente,?int $propio): array
    {
        $where='c.paciente_id=:paciente';$params=['paciente'=>$paciente];
        if($propio!==null){$where.=' AND c.medico_id=:propio';$params['propio']=$propio;}
        return [$where,$params];
    }
    private function ejecutar(\PDOStatement $q,array $params): void
    {
        foreach($params as $key=>$value){$q->bindValue($key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);}$q->execute();
    }
    private function camposCita(): string
    {
        return 'c.id,c.fecha_hora_inicio,c.duracion_minutos,c.estado,m.nombres AS medico_nombres,m.apellidos AS medico_apellidos,e.nombre AS especialidad';
    }
    private function joins(): string
    {
        return ' FROM citas c JOIN medicos m ON m.id=c.medico_id LEFT JOIN especialidades e ON e.id=c.especialidad_id';
    }

    public function opciones(int $paciente,?int $propio): array
    {
        [$where,$params]=$this->alcance($paciente,$propio);
        $q=$this->pdo->prepare('SELECT DISTINCT m.id,m.nombres,m.apellidos FROM citas c JOIN medicos m ON m.id=c.medico_id WHERE '.$where.' ORDER BY m.apellidos,m.nombres,m.id');
        $q->execute($params);$medicos=$q->fetchAll();
        $q=$this->pdo->prepare('SELECT DISTINCT e.id,e.nombre FROM citas c JOIN especialidades e ON e.id=c.especialidad_id WHERE '.$where.' ORDER BY e.nombre,e.id');
        $q->execute($params);return ['medicos'=>$medicos,'especialidades'=>$q->fetchAll()];
    }

    public function listar(int $paciente,?int $propio,array $f): array
    {
        [$where,$params]=$this->alcance($paciente,$propio);
        foreach(['estado','medico_id','especialidad_id'] as $campo){if($f[$campo]!==''){$where.=' AND c.'.$campo.'=:'.$campo;$params[$campo]=$campo==='estado'?$f[$campo]:(int)$f[$campo];}}
        foreach(['limite_desde'=>'>=','limite_hasta'=>'<'] as $campo=>$operador){if(isset($f[$campo])){$where.=' AND c.fecha_hora_inicio'.$operador.'CAST(:'.$campo.' AS timestamptz)';$params[$campo]=$f[$campo];}}
        if($f['periodo']!=='todas'){$where.=' AND c.fecha_hora_inicio'.($f['periodo']==='proximas'?'>=':'<').'CAST(:ahora AS timestamptz)';$params['ahora']=$f['ahora'];}
        $q=$this->pdo->prepare('SELECT count(*) FROM citas c WHERE '.$where);$q->execute($params);$total=(int)$q->fetchColumn();
        $paginas=max(1,(int)ceil($total/20));$pagina=min($f['pagina']===''?1:(int)$f['pagina'],$paginas);
        $q=$this->pdo->prepare('SELECT '.$this->camposCita().$this->joins().' WHERE '.$where.' ORDER BY c.fecha_hora_inicio DESC,c.id DESC LIMIT 20 OFFSET :offset');
        $params['offset']=($pagina-1)*20;$this->ejecutar($q,$params);$filas=$q->fetchAll();
        $eventos=$this->conteos($paciente,$propio,array_column($filas,'id'));
        foreach($filas as &$fila){$fila['eventos']=$eventos[(int)$fila['id']]??0;}unset($fila);
        return compact('filas','total','pagina','paginas');
    }

    private function conteos(int $paciente,?int $propio,array $ids): array
    {
        if(!$ids){return [];}
        [$where,$params]=$this->alcance($paciente,$propio);$marcas=[];
        foreach($ids as $i=>$id){$key='cita_'.$i;$marcas[]=':'.$key;$params[$key]=(int)$id;}
        $q=$this->pdo->prepare('SELECT h.cita_id,count(*) AS total FROM citas_historial h JOIN citas c ON c.id=h.cita_id WHERE '.$where.' AND c.id IN ('.implode(',',$marcas).') GROUP BY h.cita_id');
        $this->ejecutar($q,$params);$result=[];foreach($q->fetchAll() as $fila){$result[(int)$fila['cita_id']]=(int)$fila['total'];}return $result;
    }

    public function cita(int $paciente,int $cita,?int $propio): ?array
    {
        [$where,$params]=$this->alcance($paciente,$propio);$params['cita']=$cita;
        $q=$this->pdo->prepare('SELECT '.$this->camposCita().$this->joins().' WHERE '.$where.' AND c.id=:cita');
        $q->execute($params);return $q->fetch()?:null;
    }

    public function eventos(int $paciente,int $cita,?int $propio,int $pagina,bool $verResponsable): array
    {
        [$where,$params]=$this->alcance($paciente,$propio);$where.=' AND c.id=:cita';$params['cita']=$cita;
        $q=$this->pdo->prepare('SELECT count(*) FROM citas_historial h JOIN citas c ON c.id=h.cita_id WHERE '.$where);$q->execute($params);$total=(int)$q->fetchColumn();
        $paginas=max(1,(int)ceil($total/20));$pagina=min(max(1,$pagina),$paginas);
        $sql='SELECT h.tipo,h.estado_anterior,h.estado_nuevo,h.inicio_anterior,h.inicio_nuevo,h.cambiado_en'.($verResponsable?',u.correo AS responsable':'').
            ' FROM citas_historial h JOIN citas c ON c.id=h.cita_id'.($verResponsable?' JOIN usuarios u ON u.id=h.usuario_id':'').
            ' WHERE '.$where.' ORDER BY h.cambiado_en,h.id LIMIT 20 OFFSET :offset';
        $q=$this->pdo->prepare($sql);$params['offset']=($pagina-1)*20;$this->ejecutar($q,$params);
        return ['filas'=>$q->fetchAll(),'total'=>$total,'pagina'=>$pagina,'paginas'=>$paginas];
    }
}
