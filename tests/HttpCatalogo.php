<?php
declare(strict_types=1);
/** Servidor y cliente locales para pruebas reales HTTP, sin navegador. */
final class HttpCatalogo
{
    private mixed $proceso;
    private string $base;
    public function __construct(?array $entorno=null,string $rutaComprobacion='/login',int $estadoComprobacion=200)
    {
        $puerto = random_int(30000,45000); $this->base = 'http://127.0.0.1:' . $puerto;
        $this->proceso = proc_open([PHP_BINARY,'-S','127.0.0.1:' . $puerto,'-t',dirname(__DIR__) . '/public'],
            [0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,dirname(__DIR__),$entorno);
        if (!is_resource($this->proceso)) { throw new RuntimeException('No se pudo iniciar PHP'); }
        fclose($pipes[0]);
        for ($i=0;$i<30;$i++) { usleep(100000); if ($this->pedir($rutaComprobacion)['status']===$estadoComprobacion) { return; } }
        $this->cerrar(); throw new RuntimeException('Servidor HTTP no disponible');
    }
    public function pedir(string $ruta, string $metodo='GET', string $cookie='', array $datos=[]): array
    {
        $opciones=['method'=>$metodo,'header'=>'Accept: text/html' . ($cookie ? "\r\nCookie: PHPSESSID=$cookie" : ''),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>5];
        if ($metodo==='POST') { $opciones['header'].="\r\nContent-Type: application/x-www-form-urlencoded"; $opciones['content']=http_build_query($datos); }
        $body=@file_get_contents($this->base . $ruta,false,stream_context_create(['http'=>$opciones]));
        $headers=$http_response_header ?? []; preg_match('/\s(\d{3})\s/',$headers[0] ?? '',$m); $location='';
        foreach ($headers as $h) { if (preg_match('/^Set-Cookie: PHPSESSID=([^;]*)/i',$h,$c)) { $cookie=$c[1]; } if (stripos($h,'Location: ')===0) { $location=substr($h,10); } }
        return ['status'=>(int)($m[1]??0),'body'=>$body?:'','cookie'=>$cookie,'location'=>$location,'headers'=>$headers];
    }
    public static function csrf(string $body): string
    {
        if (!preg_match('/name="_csrf"\s+value="([^"]+)"/',$body,$m)) { throw new RuntimeException('Falta token CSRF'); }
        return $m[1];
    }
    public function cerrar(): void { if (is_resource($this->proceso)) { proc_terminate($this->proceso); proc_close($this->proceso); } }
}
