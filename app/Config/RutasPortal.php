<?php
declare(strict_types=1);
namespace App\Config;

final class RutasPortal
{
    public static function despachar(string $path,string $method): bool
    {
        if(preg_match('#^/pacientes/([1-9][0-9]*)/vincular-cuenta$#D',$path,$m)===1){
            $c=new \App\Controllers\VinculacionesController();$id=filter_var($m[1],FILTER_VALIDATE_INT);
            if($id===false){self::invalido(404);}
            elseif(!in_array($method,['GET','POST'],true)){self::invalido(405,'GET, POST');}
            else{$method==='GET'?$c->mostrar($id):$c->guardar($id);}return true;
        }
        if(preg_match('#^/mis-citas(?:/(historial|nueva|[1-9][0-9]*)(?:/(historial|reprogramar|cancelar))?)?$#D',$path,$m)!==1){return false;}
        $c=new \App\Controllers\PortalPacienteController();$recurso=$m[1]??'';$accion=$m[2]??'';
        $id=$recurso!==''&&ctype_digit($recurso)?filter_var($recurso,FILTER_VALIDATE_INT):null;
        if($id===false||($id===null&&$accion!=='')){self::invalido(404);return true;}
        $accion=$id===null?$recurso:$accion;
        $permitidos=$recurso===''||in_array($accion,['reprogramar','cancelar'],true)?['GET','POST']:['GET'];
        if(!in_array($method,$permitidos,true)){self::invalido(405,implode(', ',$permitidos));}
        elseif($method==='POST'){$c->guardar($id,$accion);}
        else{$c->mostrar($id,$accion);}return true;
    }
    private static function invalido(int $status,string $allow=''): void
    {
        http_response_code($status);if($allow!==''){header('Allow: '.$allow);}
        require dirname(__DIR__).'/Views/errors/'.$status.'.php';
    }
}
