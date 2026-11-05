<?php
declare(strict_types=1);
namespace App\Exports;

final class ReporteCitaPdf
{
    public const MAX_FILAS=500;
    public function crear(ReporteCitaDocumento $documento): string
    {
        $r=$documento->datos['reporte'];$cantidad=count($r['medicos'])+count($r['especialidades'])+count($r['dias']);
        if($cantidad>self::MAX_FILAS){throw new ReportePdfLimiteException('El reporte es demasiado extenso para PDF. Reduce los filtros o utiliza CSV.');}
        $autoload=dirname(__DIR__,2).'/vendor/autoload.php';
        if(!is_file($autoload)){throw new \RuntimeException('No está disponible el generador PDF.');}
        require_once $autoload;
        $temporal=rtrim(sys_get_temp_dir(),'/\\').DIRECTORY_SEPARATOR.'clinica-cu22-'.bin2hex(random_bytes(12));
        if(!mkdir($temporal,0700)){throw new \RuntimeException('No se pudo preparar la descarga.');}
        $nivel=ob_get_level();
        try{
            ob_start();
            $options=new \Dompdf\Options();
            $options->setIsRemoteEnabled(false);$options->setIsPhpEnabled(false);$options->setIsJavascriptEnabled(false);
            $options->setAllowedProtocols([]);$options->setDefaultFont('DejaVu Sans');
            $options->setTempDir($temporal);$options->setFontCache($temporal);$options->setIsFontSubsettingEnabled(true);
            $options->setChroot([dirname(__DIR__,2).'/vendor/dompdf/dompdf/lib/fonts']);
            $pdf=new \Dompdf\Dompdf($options);$pdf->setPaper('A4','landscape');
            $pdf->loadHtml($this->html($documento),'UTF-8');$pdf->render();
            $canvas=$pdf->getCanvas();$font=$pdf->getFontMetrics()->getFont('DejaVu Sans','normal');
            $canvas->page_text($canvas->get_width()-125,$canvas->get_height()-22,'Página {PAGE_NUM} de {PAGE_COUNT}',$font,8);
            $salida=$pdf->output();
            if(!str_starts_with($salida,'%PDF-')||!str_contains(substr($salida,-1024),'%%EOF')){throw new \RuntimeException('No se pudo generar un PDF válido.');}
            return $salida;
        }finally{
            while(ob_get_level()>$nivel){ob_end_clean();}
            // Solo archivos directos del directorio aleatorio recién creado por esta solicitud.
            foreach(new \FilesystemIterator($temporal,\FilesystemIterator::SKIP_DOTS) as $archivo){
                if($archivo->isFile()||$archivo->isLink()){@unlink($archivo->getPathname());}
            }
            @rmdir($temporal);
        }
    }
    public function html(ReporteCitaDocumento $documento): string
    {
        $e=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $datos=$documento->datos;$generado=$documento->generado->format('d/m/Y H:i:s P');
        ob_start();
        try{require dirname(__DIR__).'/Views/reportes/pdf.php';return ob_get_contents();}
        finally{ob_end_clean();}
    }
}
