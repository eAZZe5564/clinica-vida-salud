<?php
declare(strict_types=1);
function demand(bool $ok,string $message): void {if(!$ok){throw new RuntimeException($message);}}
demand(PHP_MAJOR_VERSION===8&&PHP_MINOR_VERSION===4,'PHP no es 8.4');
foreach(['dom','mbstring','iconv','PDO','pdo_pgsql','session','json','openssl'] as $ext){demand(extension_loaded($ext),'Extension ausente: '.$ext);}
$base='/var/www/clinica';
foreach(['.env','.git','tests','scripts','database','app/Config/DatabaseDiagnostic.php'] as $file){demand(!file_exists($base.'/'.$file),'Archivo privado o ajeno al runtime en imagen');}
$apache=file_get_contents('/etc/apache2/sites-enabled/000-default.conf');
demand(str_contains($apache,'php_admin_value auto_prepend_file /var/www/clinica/app/Config/mantenimiento-http.php'),'Apache no impone el guard');
demand(str_contains($apache,'DocumentRoot /var/www/clinica/public'),'Raiz publica incorrecta');
require $base.'/app/Config/mantenimiento-http.php';
if(getenv('CI_VERSION')==='cu26'){
    require $base.'/vendor/autoload.php';
    $expected=['dompdf/dompdf'=>'v3.1.6','dompdf/php-font-lib'=>'1.0.2','dompdf/php-svg-lib'=>'1.0.2','masterminds/html5'=>'2.12.0','sabberworm/php-css-parser'=>'v9.5.0'];
    $lock=json_decode(file_get_contents($base.'/composer.lock'),true,512,JSON_THROW_ON_ERROR);
    $actual=array_column($lock['packages'],'version','name');ksort($actual);ksort($expected);
    demand($actual===$expected,'Lock inesperado');
    foreach($expected as $name=>$version){demand(Composer\InstalledVersions::getPrettyVersion($name)===$version,'Vendor no coincide con lock');}
} else {
    foreach(['vendor','composer.json','composer.lock','app/Config/RutasPortal.php','app/Controllers/PortalPacienteController.php','app/Exports'] as $file){demand(!file_exists($base.'/'.$file),'Se incorporo Ciclo 2 al puente');}
}
echo json_encode(['php'=>PHP_VERSION,'modules'=>'ok','runtime'=>'ok','cli_maintenance'=>'unblocked','version'=>getenv('CI_VERSION')],JSON_THROW_ON_ERROR)."\n";
