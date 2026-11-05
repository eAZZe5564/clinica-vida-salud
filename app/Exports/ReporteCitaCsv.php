<?php
declare(strict_types=1);
namespace App\Exports;

final class ReporteCitaCsv
{
    /** Texto peligroso queda citado y marcado como texto; los números no se transforman. */
    public static function celda(string|int|float $valor): string|int|float
    {
        if(!is_string($valor)){return $valor;}
        if(!mb_check_encoding($valor,'UTF-8')){throw new \RuntimeException('Texto no válido para exportar.');}
        if(preg_match('/^[\p{Z}\s\p{Cf}]*[=+\-@＝＋－＠\p{Cc}]/u',$valor)===1){return "\t'".$valor;}
        return $valor;
    }
    /** Flujo preparado antes de enviar cabeceras; derrame privado al superar 1 MiB. */
    public function crear(ReporteCitaDocumento $documento): mixed
    {
        $flujo=fopen('php://temp/maxmemory:1048576','w+b');
        if($flujo===false){throw new \RuntimeException('No se pudo preparar la descarga.');}
        try{
            if(fwrite($flujo,"\xEF\xBB\xBF")!==3){throw new \RuntimeException('No se pudo escribir la descarga.');}
            foreach($documento->filasCsv() as $fila){
                if(fputcsv($flujo,array_map(self::celda(...),$fila),';','"','',"\r\n")===false){throw new \RuntimeException('No se pudo escribir la descarga.');}
            }
            if(!rewind($flujo)){throw new \RuntimeException('No se pudo preparar la descarga.');}
            return $flujo;
        }catch(\Throwable $error){fclose($flujo);throw $error;}
    }
}
