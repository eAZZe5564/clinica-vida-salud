<?php
declare(strict_types=1);
namespace App\Config;
// Montaje exclusivo de CI sobre la imagen intacta; no forma parte del build.
final class Database {
    public static function connection(): \PDO {self::event('connection');return new FixturePDO();}
    public static function event(string $name): void {file_put_contents('/tmp/clinica-fixture.events',$name."\n",FILE_APPEND|LOCK_EX);}
}
final class FixturePDO extends \PDO {
    public function __construct(){}
    public function prepare(string $query,array $options=[]): \PDOStatement|false{return new FixtureStatement($query);}
}
final class FixtureStatement extends \PDOStatement {
    private array|false $row=false;
    public function __construct(private readonly string $sql){}
    public function execute(?array $params=null): bool {
        $sql=preg_replace('/\s+/',' ',trim($this->sql));$this->row=false;
        if(str_starts_with($sql,'SELECT u.id, u.correo')&&str_contains($sql,'FROM usuarios u JOIN roles r')){
            foreach(['administrador','recepcionista','medico','paciente'] as $i=>$role){
                if(($params['correo']??null)===$role.'@example.invalid'||(int)($params['id']??0)===$i+1){$this->row=['id'=>$i+1,'correo'=>$role.'@example.invalid','activo'=>true,'rol_id'=>$i+1,'rol_nombre'=>$role,'hash_contrasena'=>(string)getenv('CLINICA_FIXTURE_HASH')];}
            } Database::event('select-user');return true;
        }
        if($sql==='UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = :id'){Database::event('write-memory-only');return true;}
        if($sql==='SELECT id,usuario_id,activo FROM pacientes WHERE usuario_id=:u'){Database::event('select-patient');return true;}
        throw new \RuntimeException('Consulta fuera del doble ficticio; no se ejecuta SQL.');
    }
    public function fetch(int $mode=\PDO::FETCH_DEFAULT,int $cursorOrientation=\PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed{return $this->row;}
}
