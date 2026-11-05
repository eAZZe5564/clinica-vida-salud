<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Models\RegistroClinico;
use PDO;

final class RegistroClinicoRepository
{
    public function __construct(private readonly PDO $pdo){}
    private function columnas(array $d): string
    {
        return 'id,paciente_id,'.implode(',',$d['campos']).',version,creado_por_usuario_id,actualizado_por_usuario_id,creado_en,actualizado_en';
    }
    public function listar(int $paciente,string $tipo): array
    {
        $d=RegistroClinico::tipo($tipo);
        $orden=$tipo==='laboratorios'?'COALESCE(fecha_resultado,fecha_muestra) DESC NULLS LAST,creado_en DESC,id DESC':'fecha_evento DESC NULLS LAST,creado_en DESC,id DESC';
        $q=$this->pdo->prepare('SELECT '.$this->columnas($d).' FROM '.$d['tabla'].' WHERE paciente_id=:p ORDER BY '.$orden);
        $q->execute(['p'=>$paciente]);return $q->fetchAll();
    }
    public function registro(int $paciente,string $tipo,int $id): ?array
    {
        $d=RegistroClinico::tipo($tipo);$q=$this->pdo->prepare('SELECT '.$this->columnas($d).' FROM '.$d['tabla'].' WHERE paciente_id=:p AND id=:id');
        $q->execute(['p'=>$paciente,'id'=>$id]);return $q->fetch()?:null;
    }
    public function guardar(int $paciente,string $tipo,array $datos,int $actor,?array $previo): int
    {
        $d=RegistroClinico::tipo($tipo);$campos=$d['campos'];$p=$datos+['p'=>$paciente,'actor'=>$actor];
        if($previo===null){
            $p['editor']=$actor;
            $sql='INSERT INTO '.$d['tabla'].'(paciente_id,'.implode(',',$campos).',creado_por_usuario_id,actualizado_por_usuario_id) VALUES (:p,:'.implode(',:',$campos).',:actor,:editor) RETURNING id';
        }else{
            $p['id']=$previo['id'];$p['version']=$previo['version'];
            $sql='UPDATE '.$d['tabla'].' SET '.implode(',',array_map(static fn($c)=>$c.'=:'.$c,$campos)).',version=version+1,actualizado_por_usuario_id=:actor,actualizado_en=clock_timestamp() WHERE paciente_id=:p AND id=:id AND version=:version RETURNING id';
        }
        $q=$this->pdo->prepare($sql);$q->execute($p);$id=$q->fetchColumn();
        if($id===false){throw new \App\Services\PerfilClinicoException(['version'=>'El registro cambió. Recarga antes de guardar.'],409);}
        return (int)$id;
    }
    public function revisar(int $paciente,string $tipo,int $id,string $evento,?string $motivo,int $actor): void
    {
        $d=RegistroClinico::tipo($tipo);$campos=implode(',',$d['campos']);
        $q=$this->pdo->prepare('INSERT INTO '.$d['historial'].'('.$d['fk'].',version,evento,'.$campos.',motivo_cambio,usuario_id) SELECT id,version,:evento,'.$campos.',:motivo,:actor FROM '.$d['tabla'].' WHERE paciente_id=:p AND id=:id');
        $q->execute(['evento'=>$evento,'motivo'=>$motivo,'actor'=>$actor,'p'=>$paciente,'id'=>$id]);
        if($q->rowCount()!==1){throw new \RuntimeException('No se pudo registrar una revisión.');}
    }
    public function revisiones(int $paciente,string $tipo,int $id): array
    {
        $d=RegistroClinico::tipo($tipo);$campos=implode(',',array_map(static fn($c)=>'h.'.$c,$d['campos']));
        $q=$this->pdo->prepare('SELECT h.version,h.evento,'.$campos.',h.motivo_cambio,h.usuario_id,h.cambiado_en FROM '.$d['historial'].' h JOIN '.$d['tabla'].' r ON r.id=h.'.$d['fk'].' WHERE r.paciente_id=:p AND r.id=:id ORDER BY h.version DESC');
        $q->execute(['p'=>$paciente,'id'=>$id]);return $q->fetchAll();
    }
}
