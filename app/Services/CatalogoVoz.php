<?php
declare(strict_types=1);
namespace App\Services;

/** Orientación estática: abrir una ruta nunca sustituye su autorización en servidor. */
final class CatalogoVoz
{
    public static function paraRol(string $rol,bool $pacienteVinculado=false): array
    {
        if(!in_array($rol,['administrador','recepcionista','medico','paciente'],true)){return [];}
        $comandos=[
            ['id'=>'inicio','ejemplo'=>'Ir al inicio','frases'=>['ir al inicio','abrir inicio'],'ruta'=>'/dashboard'],
            ['id'=>'ayuda','ejemplo'=>'Abrir ayuda','frases'=>['abrir ayuda','mostrar ayuda'],'ruta'=>null],
            ['id'=>'comandos','ejemplo'=>'Mostrar comandos disponibles','frases'=>['mostrar comandos disponibles','ver comandos'],'ruta'=>null],
        ];
        if($rol==='paciente'&&$pacienteVinculado){
            return [...$comandos,
                ['id'=>'mis_citas','ejemplo'=>'Abrir mis citas','frases'=>['abrir mis citas','ver mis citas'],'ruta'=>'/mis-citas'],
                ['id'=>'proximas','ejemplo'=>'Ver mis próximas citas','frases'=>['ver mis proximas citas','consultar mis proximas citas'],'ruta'=>'/mis-citas?periodo=proximas'],
                ['id'=>'historial','ejemplo'=>'Consultar mi historial','frases'=>['consultar mi historial','ver mi historial'],'ruta'=>'/mis-citas/historial'],
                // Solo GET al formulario: no reserva ni envía datos en nombre del usuario.
                ['id'=>'reservar','ejemplo'=>'Reservar una cita','frases'=>['reservar una cita','reservar cita'],'ruta'=>'/mis-citas/nueva'],
            ];
        }
        if($rol==='medico'){
            return [...$comandos,
                ['id'=>'agenda','ejemplo'=>'Abrir mi agenda','frases'=>['abrir mi agenda','abrir agenda'],'ruta'=>'/agenda'],
                ['id'=>'hoy','ejemplo'=>'Ver la agenda de hoy','frases'=>['ver la agenda de hoy','ver mi agenda de hoy'],'ruta'=>'/agenda/diaria'],
            ];
        }
        if(in_array($rol,['administrador','recepcionista'],true)){
            return [...$comandos,
                ['id'=>'agenda','ejemplo'=>'Abrir agenda','frases'=>['abrir agenda','ver agenda'],'ruta'=>'/agenda'],
                ['id'=>'citas','ejemplo'=>'Ver citas','frases'=>['ver citas','abrir citas'],'ruta'=>'/citas'],
                ['id'=>'reportes','ejemplo'=>'Abrir reportes','frases'=>['abrir reportes','ver reportes'],'ruta'=>'/reportes/citas'],
                ['id'=>'pacientes','ejemplo'=>'Buscar pacientes','frases'=>['buscar pacientes','abrir pacientes'],'ruta'=>'/pacientes'],
            ];
        }
        return $comandos;
    }
}
