<?php
declare(strict_types=1);
namespace App\Config;
use App\Controllers\HorariosController;
final class RutasHorarios
{
    public static function despachar(string $path,string $metodo): bool
    {
        if (!preg_match('#^/horarios(?:/(nuevo|[1-9][0-9]*)(?:/(editar|estado))?)?$#',$path,$p)) { return false; }
        $recurso=$p[1]??''; $accion=$p[2]??'';
        if ($recurso==='nuevo' && $accion!=='') { http_response_code(404); require dirname(__DIR__) . '/Views/errors/404.php'; return true; }
        $permitidos=$recurso==='' || $accion!=='' ? ['GET','POST'] : ['GET'];
        if (!in_array($metodo,$permitidos,true)) {
            http_response_code(405); header('Allow: ' . implode(', ',$permitidos)); require dirname(__DIR__) . '/Views/errors/405.php'; return true;
        }
        $c=new HorariosController();
        try {
            if ($recurso==='') { $metodo==='GET' ? $c->listar() : $c->guardar(); }
            elseif ($recurso==='nuevo') { $c->formulario(); }
            else {
                $id=filter_var($recurso,FILTER_VALIDATE_INT); if ($id===false) { throw new \OutOfBoundsException(); }
                if ($accion==='editar') { $metodo==='GET' ? $c->formulario($id) : $c->guardar($id); }
                elseif ($accion==='estado') { $metodo==='GET' ? $c->estado($id) : $c->cambiarEstado($id); }
                else { $c->detalle($id); }
            }
        } catch (\OutOfBoundsException) { http_response_code(404); require dirname(__DIR__) . '/Views/errors/404.php'; }
        return true;
    }
}
