<?php
declare(strict_types=1);
// Cliente CLI dentro del contenedor: solo identidades ficticias y loopback.
$n=0;
function ok(bool $value,string $label): void { global $n; if(!$value){throw new RuntimeException($label);} ++$n; }
function req(string $path,string $method='GET',array $headers=[],array $form=[]): array {
    if(in_array($method,['POST','PUT','PATCH','DELETE'],true)&&$form===[]){$form=['ci_probe'=>'ficticio'];$headers[]='Content-Type: application/x-www-form-urlencoded';}
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$form===[]?'':http_build_query($form),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>5]]);
    $body=@file_get_contents('http://127.0.0.1:10000'.$path,false,$context);
    $raw=$http_response_header??[]; preg_match('/\s(\d{3})\s/',$raw[0]??'',$m); $h=[];
    foreach(array_slice($raw,1) as $line){if(str_contains($line,':')){[$key,$v]=explode(':',$line,2);$h[strtolower($key)][]=trim($v);}}
    return ['status'=>(int)($m[1]??0),'headers'=>$h,'body'=>$body===false?'':$body];
}
function token(array $r): string {ok(preg_match('/name="_csrf" value="([a-f0-9]{64})"/',$r['body'],$m)===1,'CSRF ausente');return $m[1];}
function cookie(array $r,string $old=''): string {foreach($r['headers']['set-cookie']??[] as $h){if(str_starts_with($h,'PHPSESSID=')){return explode(';',$h,2)[0];}} return $old;}
$phase=$argv[1]??'closed';
$roles=['administrador','recepcionista','medico','paciente'];
for($i=0;$i<40;++$i){if(req('/health')['status']===200){break;}usleep(250000);}
ok(req('/health')['status']===200,'Apache no responde /health');
$before=glob('/var/lib/clinica/sessions/*');
$eventsBefore=@file_get_contents('/tmp/clinica-fixture.events');
$r=req('/health');ok($r['body']==='{"status":"ok"}','Salud no es generica');
ok(!isset($r['headers']['set-cookie']),'Salud abre sesion');
ok(req('/health','HEAD')['body']==='','HEAD salud tiene contenido');
ok(glob('/var/lib/clinica/sessions/*')===$before,'Salud crea archivos de sesion');
ok(@file_get_contents('/tmp/clinica-fixture.events')===$eventsBefore,'Salud consulta el doble PDO');
if($phase==='closed'){
    $sessions=['anonimo'=>['cookie'=>'','csrf'=>'']];
    foreach($roles as $i=>$role){
        session_start(); $_SESSION=['usuario_id'=>$i+1,'rol'=>$role,'correo'=>$role.'@example.invalid','autenticado_en'=>time(),'actividad_en'=>time(),'csrf_token'=>bin2hex(random_bytes(32))];
        $sessions[$role]=['cookie'=>'PHPSESSID='.session_id(),'csrf'=>$_SESSION['csrf_token']];session_write_close();session_id('');
    }
    $eventsBefore=@file_get_contents('/tmp/clinica-fixture.events');
    $paths=['/','/login','/dashboard','/admin/users','/admin/users/1/status','/pacientes','/medicos','/especialidades','/horarios','/disponibilidad','/citas','/citas/nueva','/citas/1','/citas/1/reprogramar','/citas/1/cancelar','/citas/1/confirmar','/citas/1/llegada','/citas/1/atencion','/citas/1/inasistencia','/agenda','/agenda/semanal','/historial-citas','/pacientes/1/historial-citas','/reportes/citas','/reportes/citas/exportar/pdf','/reportes/citas/exportar/csv','/pacientes/1/perfil-clinico','/pacientes/1/alergias','/pacientes/1/antecedentes','/pacientes/1/laboratorios','/pacientes/1/vincular-cuenta','/mis-citas','/mis-citas/nueva','/mis-citas/1/reprogramar','/mis-citas/1/cancelar','/logout','/index.php','/index.php/pacientes','/probe.php','/%68ealth','/health/'];
    foreach($sessions as $role=>$s){foreach($paths as $path){foreach(['GET','POST','PUT','PATCH','DELETE','HEAD','OPTIONS'] as $method){
        $headers=['Content-Type: application/x-www-form-urlencoded','X-Requested-With: XMLHttpRequest'];if($s['cookie']!==''){$headers[]='Cookie: '.$s['cookie'];}
        $r=req($path,$method,$headers,['_csrf'=>$s['csrf'],'paciente_id'=>'1']);
        ok($r['status']===503,'Cierre '.$role.' '.$method.' '.$path.' status='.$r['status']);
        ok(($r['headers']['retry-after'][0]??'')==='120','Falta Retry-After');
        ok(str_contains($r['headers']['cache-control'][0]??'','no-store'),'Falta no-store');
        ok(!isset($r['headers']['set-cookie']),'El cierre abre sesion');
        ok(!str_contains($r['body'],'@example.invalid')&&!str_contains($r['body'],'PHP_CANARY'),'Contenido interno en cierre');
        if($method==='HEAD'){ok($r['body']==='','HEAD revela cuerpo');}
    }}}
    foreach(['/.env','/app/Config/Database.php','/vendor/autoload.php','/database/migrations/001_esquema_ciclo1.sql','/tests/docker/http.php'] as $path){
        $r=req($path);ok(in_array($r['status'],[403,404,503],true),'Recurso privado accesible');
        ok(!str_contains($r['body'],'<?php'),'Fuente PHP expuesta');
    }
    foreach([['X-Clinica-Maintenance-Key: '.str_repeat('0',64)],['X-Original-URL: /health'],['X-Forwarded-Uri: /health']] as $h){ok(req('/login','GET',$h)['status']===503,'Cabecera abre acceso');}
    ok(req('/login?key=valor-ficticio')['status']===503,'Parametro abre acceso');
    ok(req('/health','POST')['status']===503,'POST health evade cierre');
    ok(req('/assets/css/app.css')['status']===200,'CSS publico inaccesible');
    ok(@file_get_contents('/tmp/clinica-fixture.events')===$eventsBefore,'El cierre alcanzo PDO ficticio');
} elseif($phase==='overlap') {
    ok(req('/login')['status']===503,'Reinicio/coexistencia abre login');
    ok(req('/citas','POST')['status']===503,'Reinicio/coexistencia abre escrituras');
} elseif(in_array($phase,['normal','private'],true)) {
    $base=$phase==='private'?['X-Clinica-Maintenance-Key: '.getenv('CLINICA_CI_KEY')]:[];
    if($phase==='private'){
        ok(req('/login')['status']===503,'El acceso privado abre el acceso publico');
        ok(req('/login','GET',['X-Clinica-Maintenance-Key: '.str_repeat('0',64)])['status']===503,'Clave falsa admitida');
    }
    ok(req('/dashboard','GET',$base)['status']===303,'Anonimo no exige login');
    $denied=req('/login','POST',$base);
    ok($denied['status']===419,'CSRF anonimo status='.$denied['status'].' pagina_csrf='.(str_contains($denied['body'],'Formulario vencido')?'si':'no'));
    foreach($roles as $role){
        $login=req('/login','GET',$base);ok($login['status']===200,'Login normal falla');
        $cookies=$login['headers']['set-cookie']??[];ok(str_contains(strtolower(implode(' ',$cookies)),'secure'),'Se perdio cookie Secure');
        $c=cookie($login);
        $auth=req('/login','POST',array_merge($base,['Cookie: '.$c,'Content-Type: application/x-www-form-urlencoded']),['_csrf'=>token($login),'correo'=>$role.'@example.invalid','contrasena'=>(string)getenv('CLINICA_FIXTURE_PASSWORD')]);
        ok($auth['status']===303&&($auth['headers']['location'][0]??'')==='/dashboard','Login ficticio '.$role);
        $new=cookie($auth,$c);ok($new!==$c,'No se roto la sesion');$c=$new;
        $headers=array_merge($base,['Cookie: '.$c]);
        $dashboard=req('/dashboard','GET',$headers);
        ok($dashboard['status']===200&&str_contains($dashboard['body'],$role.'@example.invalid'),'Dashboard '.$role);
        ok(req('/logout','POST',$headers)['status']===419,'CSRF autenticado omitido');
        if($role!=='administrador'){ok(req('/admin/users','GET',$headers)['status']===403,'Rol ampliado');}
        $out=req('/logout','POST',array_merge($headers,['Content-Type: application/x-www-form-urlencoded']),['_csrf'=>token($dashboard)]);
        ok($out['status']===303,'Logout falla');
        ok(req('/dashboard','GET',$headers)['status']===303,'Sesion no invalidada');
    }
    if($phase==='normal'){ok(req('/probe.php')['body']==='PHP_CANARY','PHP directo normal falla');ok(req('/health','POST')['status']===405,'Health admite escrituras');}
} else {throw new RuntimeException('Fase desconocida');}
echo json_encode(['phase'=>$phase,'assertions'=>$n,'http'=>'Apache real','postgresql'=>false],JSON_THROW_ON_ERROR)."\n";
