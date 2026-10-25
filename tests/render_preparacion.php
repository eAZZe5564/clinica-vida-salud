<?php
declare(strict_types=1);
require dirname(__DIR__) . '/scripts/RenderDestino.php';
$total = 0;
$ok = static function (bool $valor, string $nombre) use (&$total): void {
    if (!$valor) { throw new RuntimeException($nombre); }
    $total++; echo "OK: $nombre\n";
};
$config = ['host'=>'dpg-fixture.oregon-postgres.render.com','puerto'=>5432,'base'=>'clinica_vida_salud_render',
    'usuario'=>'clinica_fixture','password'=>'SoloFixture!91','ca'=>__FILE__,
    'host_confirmado'=>'dpg-fixture.oregon-postgres.render.com','base_confirmada'=>'clinica_vida_salud_render'];
$ok(RenderDestino::validar($config) === $config, 'destino externo dedicado confirmado');
foreach ([['host','127.0.0.1'],['host','dpg-fixture'],['host','dpg-fixture.oregon-postgres.render.com.ejemplo.invalid'],
    ['base','clinica_vida_salud_dev'],['base','panaderia'],['base_confirmada','otra'],['host_confirmado','otro'],
    ['puerto',5433],['usuario','usuario;sslmode=disable'],['password',''],['password',"abc\0def"],['ca',__DIR__.'/no-existe.crt'],['base',[]]] as $i=>[$clave,$valor]) {
    try { RenderDestino::validar(array_replace($config, [$clave=>$valor])); $ok(false,'se esperaba rechazo'); }
    catch (DomainException $error) { $ok(!str_contains($error->getMessage(), $config['password']), 'destino adverso rechazado sin secreto '.$i); }
}

// PDO simulado: verifica guardas de identidad/TLS antes de cualquier servicio de escritura, sin red.
final class DestinoStatementPrueba extends PDOStatement {
    public function __construct(private array $fila) {}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return $this->fila; }
}
final class DestinoPdoPrueba extends PDO {
    public function __construct(private array $fila) {}
    public function query(string $query, ?int $fetchMode=null, mixed ...$fetchModeArgs): PDOStatement|false { return new DestinoStatementPrueba($this->fila); }
}
$fila = ['base'=>$config['base'],'usuario'=>$config['usuario'],'version'=>170010,'tls'=>true];
RenderDestino::comprobar(new DestinoPdoPrueba($fila), $config); $ok(true,'identidad versión y TLS coinciden');
foreach ([['base','otra'],['usuario','otro'],['version',160010],['version',180000],['tls',false],['tls','f']] as $i=>[$clave,$valor]) {
    try { RenderDestino::comprobar(new DestinoPdoPrueba(array_replace($fila,[$clave=>$valor])), $config); $ok(false,'se esperaba rechazo'); }
    catch (DomainException) { $ok(true,'respuesta de servidor insegura rechazada '.$i); }
}

// El CLI rechaza entradas antes de abrir conexión y no refleja su contenido.
foreach (['json-invalido', json_encode(array_replace($config,['base'=>'panaderia']))] as $i=>$linea) {
    $proceso = proc_open([PHP_BINARY,dirname(__DIR__).'/scripts/render-administrador.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fwrite($pipes[0],$linea."\n"); fclose($pipes[0]);
    $salida=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $ok(proc_close($proceso)===1&&!str_contains($salida,$config['password']),'CLI falla cerrado sin red ni eco '.$i);
}
$proceso=proc_open([PHP_BINARY,dirname(__DIR__).'/scripts/render-administrador.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
fwrite($pipes[0],json_encode($config)."\nfixture@example.invalid\nsinseguridad\n");fclose($pipes[0]);
$salida=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
$ok(proc_close($proceso)===1&&str_contains($salida,'mayúscula')&&!str_contains($salida,'sinseguridad'),'CLI comparte política antes de conectar');
echo "Aprobadas: $total. Sin conexiones de red ni escrituras PostgreSQL.\n";
