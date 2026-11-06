<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use PDOException;

final class VinculacionPacienteService
{
    public function __construct(private readonly PDO $pdo){}
    private function administrador(int $id): void
    {
        $q=$this->pdo->prepare("SELECT u.id FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=:id AND u.activo AND r.nombre='administrador'");$q->execute(['id'=>$id]);
        if(!$q->fetchColumn()){throw new CitaException(['permisos'=>'Solo un administrador puede realizar esta operación.'],403);}
    }
    public function contexto(int $paciente,int $actor): array
    {
        $this->administrador($actor);
        $q=$this->pdo->prepare('SELECT id,nombres,apellidos,activo,usuario_id FROM pacientes WHERE id=:id');$q->execute(['id'=>$paciente]);$p=$q->fetch();
        if(!$p){throw new CitaException(['paciente'=>'Recurso no disponible.'],404);}
        $cuentas=$this->pdo->query("SELECT u.id,u.correo FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.activo AND r.nombre='paciente' AND NOT EXISTS(SELECT 1 FROM pacientes p WHERE p.usuario_id=u.id) ORDER BY u.correo,u.id LIMIT 100")->fetchAll();
        return ['paciente'=>$p,'cuentas'=>$cuentas];
    }
    public function vincular(int $paciente,mixed $usuario,mixed $confirmacion,int $actor): array
    {
        $this->administrador($actor);
        $u=is_int($usuario)||is_string($usuario)?filter_var($usuario,FILTER_VALIDATE_INT):false;
        if($paciente<=0||$u===false||$u<=0||$confirmacion!=='1'){throw new CitaException(['verificacion'=>'Selecciona una cuenta y confirma expresamente que verificaste su identidad.']);}
        $propia=!$this->pdo->inTransaction();$savepoint=false;
        try{
            if($propia){$this->pdo->beginTransaction();$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED READ WRITE');}
            if($this->pdo->query('SHOW transaction_isolation')->fetchColumn()!=='read committed'||$this->pdo->query('SHOW transaction_read_only')->fetchColumn()==='on'){throw new CitaException(['transaccion'=>'Transacción incompatible.'],409);}
            $this->pdo->exec('SAVEPOINT vincular_paciente');$savepoint=true;
            $q=$this->pdo->prepare('SELECT id,activo,usuario_id FROM pacientes WHERE id=:id FOR UPDATE');$q->execute(['id'=>$paciente]);$p=$q->fetch();
            $q=$this->pdo->prepare('SELECT u.id,u.activo,r.nombre AS rol FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id IN (:actor,:destino) ORDER BY u.id FOR UPDATE OF u');$q->execute(['actor'=>$actor,'destino'=>$u]);$cuentas=[];foreach($q->fetchAll() as $f){$cuentas[(int)$f['id']]=$f;}
            if(!isset($cuentas[$actor])||!$cuentas[$actor]['activo']||$cuentas[$actor]['rol']!=='administrador'){throw new CitaException(['permisos'=>'No tienes autorización para esta operación.'],403);}
            if(!$p||!$p['activo']||!isset($cuentas[$u])||!$cuentas[$u]['activo']||$cuentas[$u]['rol']!=='paciente'){throw new CitaException(['vinculo'=>'El paciente o la cuenta no están disponibles.'],409);}
            $q=$this->pdo->prepare('SELECT id FROM pacientes WHERE usuario_id=:id');$q->execute(['id'=>$u]);
            if($p['usuario_id']!==null||$q->fetchColumn()){throw new CitaException(['vinculo'=>'El paciente o la cuenta ya tienen una vinculación. No se permiten sustituciones.'],409);}
            $q=$this->pdo->prepare('UPDATE pacientes SET usuario_id=:u,actualizado_en=clock_timestamp() WHERE id=:p AND usuario_id IS NULL');$q->execute(['u'=>$u,'p'=>$paciente]);
            if($q->rowCount()!==1){throw new CitaException(['vinculo'=>'La vinculación cambió; vuelve a consultar.'],409);}
            $momento=$this->pdo->query('SELECT clock_timestamp()')->fetchColumn();
            $this->pdo->exec('RELEASE SAVEPOINT vincular_paciente');$savepoint=false;if($propia){$this->pdo->commit();}
            // Recibo de esta ejecución, no bitácora persistente ni prueba documental automática.
            return ['administrador_id'=>$actor,'realizada_en'=>$momento];
        }catch(\Throwable $e){
            if($this->pdo->inTransaction()){if($propia){$this->pdo->rollBack();}elseif($savepoint){$this->pdo->exec('ROLLBACK TO SAVEPOINT vincular_paciente');$this->pdo->exec('RELEASE SAVEPOINT vincular_paciente');}}
            if($e instanceof CitaException){throw $e;}
            if($e instanceof PDOException&&in_array($e->getCode(),['23505','40P01','40001','55P03'],true)){throw new CitaException(['vinculo'=>'La vinculación coincidió con otro cambio. Recarga y revisa.'],409);}
            throw new CitaException(['vinculo'=>'No se pudo completar la vinculación; no se guardaron cambios parciales.'],503);
        }
    }
}
