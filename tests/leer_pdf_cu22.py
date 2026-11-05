"""Lector PDF real ya disponible: valida los archivos ficticios, sin instalar paquetes."""
from pathlib import Path
from pypdf import PdfReader
import json
import re

base = Path(__file__).resolve().parent / "artifacts" / "cu22"
total = 0


def comprobar(valor, nombre):
    global total
    if not valor:
        raise AssertionError(nombre)
    total += 1
    print("OK:", nombre)


reader = PdfReader(base / "multipagina.pdf", strict=True)
comprobar(not reader.is_encrypted and len(reader.pages) > 2, "PDF multipágina abierto por lector real")
textos = [pagina.extract_text() for pagina in reader.pages]
texto = "\n".join(textos)
for termino in ["Clínica Vida Salud", "Reporte de citas médicas", "ÁÉÍÓÚ", "áéíóú", "ñ Ñ", "America/La_Paz", "2026-01-01", "2026-05-10", "130"]:
    comprobar(termino in texto, "contenido Unicode y cifras: " + termino)
for numero, pagina in enumerate(reader.pages, 1):
    comprobar(abs(float(pagina.mediabox.width) - 841.89) < 1 and abs(float(pagina.mediabox.height) - 595.28) < 1, "tamaño A4 horizontal")
    comprobar(f"Página {numero} de {len(reader.pages)}" in textos[numero - 1], "numeración de página")
comprobar(all("Fecha" in t and "Total" in t for t in textos[2:]), "encabezados diarios repetidos en páginas siguientes")
vacio = PdfReader(base / "vacio.pdf", strict=True)
comprobar("No hay citas" in "\n".join(p.extract_text() for p in vacio.pages), "PDF vacío legible con mensaje")
esperados = base / "esperados.json"
if esperados.exists():
    for nombre, esperado in json.loads(esperados.read_text(encoding="utf-8")).items():
        pdf = PdfReader(base / nombre, strict=True)
        texto = "\n".join(p.extract_text() for p in pdf.pages)
        comprobar(esperado["desde"] in texto and esperado["hasta"] in texto, "filtros de CU21 presentes en PDF HTTP")
        bloque = texto.split("Resumen general y situación de asistencia", 1)[1].split("Pendientes de llegada", 1)[0]
        conteos = [int(n) for n in re.findall(r"\b\d+\b", bloque)]
        comprobar(conteos == esperado["conteos"], "siete cifras del PDF coinciden exactamente con el servicio CU21")
        comprobar(all(dato not in texto for dato in ["CI-PRIVADO-", "DIRECCION_PRIVADA_CU19", "MOTIVO_CLINICO_PRIVADO_CU19", "hash_contrasena"]), "PDF leído sin datos privados")
print(f"Aprobadas: {total}. Lectura real de PDF con pypdf preinstalado; sin Docker ni producción.")
