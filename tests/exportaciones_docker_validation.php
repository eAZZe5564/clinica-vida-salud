<?php
declare(strict_types=1);
// Comprobación estática: nunca ejecuta Docker ni conecta a PostgreSQL.
$total=0;$ok=static function(bool $v,string $n)use(&$total):void{if(!$v){throw new RuntimeException($n);}++$total;echo "OK: $n\n";};
$root=dirname(__DIR__);$docker=file_get_contents($root.'/Dockerfile');$ignore=file_get_contents($root.'/.dockerignore');
$composer=json_decode(file_get_contents($root.'/composer.json'),true,512,JSON_THROW_ON_ERROR);
$lock=json_decode(file_get_contents($root.'/composer.lock'),true,512,JSON_THROW_ON_ERROR);
$ok($composer['require']['dompdf/dompdf']==='^3.1.6'&&$composer['license']==='proprietary','restricción y licencia autorizadas; instalación gobernada por lock');
$versions=array_column($lock['packages'],'version','name');$ok($versions['dompdf/dompdf']==='v3.1.6'&&count($versions)===5,'lock con cinco paquetes concretos');
foreach($lock['packages'] as $p){$ok(str_starts_with($p['source']['url'],'https://github.com/')&&strlen($p['source']['reference'])===40,'fuente oficial HTTPS y referencia fija '.$p['name']);}
$ok(str_contains($docker,'FROM composer:2.11.1 AS composer_bin'),'Composer oficial con versión fijada');
$ok(str_contains($docker,'FROM php:8.4-apache-bookworm'),'base PHP/Apache existente conservada');
$ok(str_contains($docker,'pdo_pgsql mbstring'),'extensiones PostgreSQL y MBString conservadas');
foreach(['dom','mbstring','iconv'] as $extension){$ok(str_contains($docker,'extension_loaded("'.$extension.'")'),'build exige extensión '.$extension);}
$ok(str_contains($docker,'COPY composer.json composer.lock ./'),'build recibe JSON y lock');
$ok(str_contains($docker,'composer install --no-dev')&&!str_contains($docker,'composer update'),'build instala desde lock sin actualizar');
foreach(['--no-scripts','--no-plugins','--optimize-autoloader','check-platform-reqs'] as $arg){$ok(str_contains($docker,$arg),'instalación comprobada '.$arg);}
$ok(str_contains($ignore,'!composer.json')&&str_contains($ignore,'!composer.lock')&&!str_contains($ignore,'!vendor/'),'contexto incluye manifests y excluye vendor local');
$ok(str_contains($ignore,'**/.env')&&str_contains($ignore,'**/sessions')&&str_contains($ignore,'**/artifacts'),'secretos y artefactos excluidos');
$ok(str_contains($docker,'/var/lib/clinica/sessions')&&str_contains($docker,'ENTRYPOINT ["/usr/local/bin/clinica-start"]')&&str_contains($docker,'CMD ["apache2-foreground"]'),'sesiones, puerto/arranque y Apache conservados');
$ok($composer['config']['allow-plugins']===false&&$composer['config']['secure-http']===true,'sin plugins ni transporte inseguro');
echo "Aprobadas: $total. Solo inspección estática Docker/Composer; no build ni contenedor real.\n";
