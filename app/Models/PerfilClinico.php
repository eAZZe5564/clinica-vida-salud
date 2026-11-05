<?php
declare(strict_types=1);
namespace App\Models;

final class PerfilClinico
{
    public const GRUPOS=['A+','A-','B+','B-','AB+','AB-','O+','O-'];
    public const ESTADOS=['no_registrado'=>'Información sobre alergias no registrada',
        'sin_conocidas'=>'Sin alergias conocidas: declaración expresa',
        'con_registros'=>'Existen antecedentes de alergias registrados'];
    public const EVENTOS=['registro'=>'Registro inicial','actualizacion'=>'Rectificación',
        'inactivacion'=>'Inactivación','reactivacion'=>'Reactivación'];
}
