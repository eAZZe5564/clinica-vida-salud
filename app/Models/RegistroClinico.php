<?php
declare(strict_types=1);
namespace App\Models;

/** Nombres SQL cerrados por código; nunca se reciben tablas/columnas del cliente. */
final class RegistroClinico
{
    public const CATEGORIAS=['personal'=>'Personal','quirurgico'=>'Quirúrgico','familiar'=>'Familiar','otro'=>'Otro'];
    public const EVENTOS=['registro'=>'Registro inicial','rectificacion'=>'Rectificación','inactivacion'=>'Inactivación','reactivacion'=>'Reactivación'];
    public const TIPOS=[
        'antecedentes'=>[
            'tabla'=>'antecedentes_clinicos','historial'=>'antecedentes_historial','fk'=>'antecedente_id',
            'titulo'=>'Antecedentes clínicos','singular'=>'antecedente',
            'campos'=>['categoria','descripcion','fecha_evento','fecha_aproximada','observaciones','estado'],
        ],
        'laboratorios'=>[
            'tabla'=>'resultados_laboratorio','historial'=>'laboratorios_historial','fk'=>'resultado_id',
            'titulo'=>'Resultados de laboratorio','singular'=>'resultado',
            'campos'=>['estudio','fecha_muestra','fecha_resultado','resultado_texto','valor','unidad','rango_referencia','laboratorio','observaciones','estado'],
        ],
    ];
    public static function tipo(string $tipo): array
    {
        return self::TIPOS[$tipo]??throw new \InvalidArgumentException('Apartado clínico no permitido.');
    }
}
