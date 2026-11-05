<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\PerfilClinico;
use App\Models\HorarioAtencion;
use App\Repositories\PerfilClinicoRepository;
use Closure;
use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

/** CU23: permiso diario revalidado bajo bloqueos, sin acceso clínico administrativo. */
final class PerfilClinicoService
{
    public function __construct(private readonly PDO $pdo,private readonly PerfilClinicoRepository $repo,
        private readonly int $actorId,private readonly ?Closure $reloj=null) {}
    private function operar(int $paciente,bool $escritura,Closure $operacion): mixed
    {
        return (new AccesoClinicoService($this->pdo,$this->repo,$this->actorId,$this->reloj))->operar($paciente,$escritura,$operacion);
    }
    public static function texto(mixed $valor,string $campo,int $maximo,bool $obligatorio=false): ?string
    {
        if(!is_string($valor)||!mb_check_encoding($valor,'UTF-8')||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\p{Cf}]/u',$valor)||mb_strlen($valor)>$maximo){
            throw new PerfilClinicoException([$campo=>'Introduce un texto válido de hasta '.$maximo.' caracteres.']);
        }
        $v=preg_replace('/\s+/u',' ',trim($valor))??'';$v=trim($v);
        if($obligatorio&&$v===''){throw new PerfilClinicoException([$campo=>'Este campo es obligatorio.']);}
        return $v===''?null:$v;
    }
    private function confirmado(array $e): void
    {
        if(($e['confirmar']??null)!=='1'){throw new PerfilClinicoException(['confirmar'=>'Confirma expresamente la información antes de guardar.']);}
    }
    private function version(array $e,int $actual): void
    {
        $v=$e['version']??null;
        if((!is_string($v)&&!is_int($v))||filter_var($v,FILTER_VALIDATE_INT)===false||(int)$v!==$actual){
            throw new PerfilClinicoException(['version'=>'El registro cambió desde que abriste el formulario. Recarga antes de guardar.'],409);
        }
    }
    public function consultar(int $paciente,?int $alergia=null): array
    {
        return $this->operar($paciente,false,function(array $p)use($paciente,$alergia):array{
            $actual=$alergia===null?null:$this->repo->alergia($paciente,$alergia);
            if($alergia!==null&&$actual===null){throw new PerfilClinicoException(['alergia'=>'Recurso no disponible.'],404);}
            return ['paciente'=>$p,'perfil'=>$this->repo->perfil($paciente),'alergias'=>$this->repo->alergias($paciente),
                'alergia'=>$actual,'revisiones'=>$alergia===null?[]:$this->repo->revisiones($paciente,$alergia)];
        });
    }
    public function guardarPerfil(int $paciente,array $entrada): void
    {
        $this->operar($paciente,true,function()use($paciente,$entrada):void{
            $this->confirmado($entrada);$previo=$this->repo->perfil($paciente);$this->version($entrada,(int)($previo['version']??0));
            $datos=[];foreach(['grupo_sanguineo'=>3,'fuente_grupo_sanguineo'=>120,'observaciones'=>500,'estado_alergias'=>20] as $campo=>$max){
                $datos[$campo]=self::texto($entrada[$campo]??'',$campo,$max,$campo==='estado_alergias');
            }
            if($datos['grupo_sanguineo']!==null&&!in_array($datos['grupo_sanguineo'],PerfilClinico::GRUPOS,true)){
                throw new PerfilClinicoException(['grupo_sanguineo'=>'Selecciona un grupo válido o no registrado.']);
            }
            if(($datos['grupo_sanguineo']===null)!==($datos['fuente_grupo_sanguineo']===null)){
                throw new PerfilClinicoException(['fuente_grupo_sanguineo'=>'Un grupo registrado requiere su referencia documental; si se desconoce, deja ambos vacíos.']);
            }
            if(!array_key_exists($datos['estado_alergias'],PerfilClinico::ESTADOS)){
                throw new PerfilClinicoException(['estado_alergias'=>'Selecciona un estado disponible.']);
            }
            $alergias=$this->repo->alergias($paciente);$activas=array_filter($alergias,static fn($a)=>$a['estado']==='activa');
            if(($activas&&$datos['estado_alergias']!=='con_registros')||(!$alergias&&$datos['estado_alergias']==='con_registros')){
                throw new PerfilClinicoException(['estado_alergias'=>'El estado debe concordar con las alergias registradas. No se puede declarar ausencia con entradas activas.']);
            }
            $this->repo->guardarPerfil($paciente,$datos,$this->actorId,$previo===null);
        });
    }
    public function guardarAlergia(int $paciente,array $entrada,?int $id=null): int
    {
        return $this->operar($paciente,true,function()use($paciente,$entrada,$id):int{
            $this->confirmado($entrada);$previo=$id===null?null:$this->repo->alergia($paciente,$id);
            if($id!==null&&$previo===null){throw new PerfilClinicoException(['alergia'=>'Recurso no disponible.'],404);}
            if($previo){$this->version($entrada,(int)$previo['version']);}
            $datos=[];foreach(['sustancia'=>120,'reaccion'=>250,'observaciones'=>500] as $campo=>$max){$datos[$campo]=self::texto($entrada[$campo]??'',$campo,$max,$campo==='sustancia');}
            $datos['estado']=$id===null?'activa':self::texto($entrada['estado']??'','estado',10,true);
            if(!in_array($datos['estado'],['activa','inactiva'],true)){throw new PerfilClinicoException(['estado'=>'Selecciona un estado disponible.']);}
            $motivo=$id===null?null:self::texto($entrada['motivo_cambio']??'','motivo_cambio',250,true);
            $evento=$id===null?'registro':($previo['estado']===$datos['estado']?'actualizacion':($datos['estado']==='activa'?'reactivacion':'inactivacion'));
            if($previo&&array_intersect_key($previo,$datos)===$datos){throw new PerfilClinicoException(['cambios'=>'No hay cambios que registrar.'],409);}
            $guardado=$this->repo->guardarAlergia($paciente,$datos,$this->actorId,$id);
            if($datos['estado']==='activa'){$this->repo->marcarAntecedentes($paciente,$this->actorId);}
            $this->repo->registrarRevision($guardado,$evento,$motivo,$this->actorId);
            return $guardado;
        });
    }
}
